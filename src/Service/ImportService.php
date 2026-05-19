<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Repository\AlbumRepository;
use App\Repository\SettingsRepository;
use App\Utils\HttpClient;
use App\Utils\SubsonicClient;

class ImportService
{
    private HttpClient $http;
    private SubsonicClient $subsonic;

    public function __construct(
        private Config $config,
        private AlbumRepository $albumRepo,
        private SettingsRepository $settings
    ) {
        $this->http     = new HttpClient();
        $this->subsonic = new SubsonicClient($config);
    }

    /**
     * @return array{error: ?array{source: string, message: string}, albums: list<array{artist: string, album: string, playcount: int}>, updates: int}
     */
    public function syncPlaycounts(string $source, string $username): array
    {
        $error = null;

        if ($source === 'listenbrainz') {
            $albums = $this->fetchListenbrainzTopAlbums($username, 1000, $error);
        } elseif ($source === 'subsonic') {
            $albums = $this->subsonic->fetchAlbumList('frequent', 1000);
        } else {
            $albums = $this->fetchLastfmTopAlbums($username, 1000, $error);
        }

        if (empty($albums)) {
            return ['error' => $error, 'albums' => [], 'updates' => 0];
        }

        $liveCounts = [];
        foreach ($albums as $alb) {
            $key = strtolower(trim($alb['artist']) . '_' . trim($alb['album']));
            $liveCounts[$key] = (int)$alb['playcount'];
        }

        $updates = 0;
        $eloData = $this->albumRepo->loadElo();
        $queueData = $this->albumRepo->loadQueue();

        foreach ($eloData as &$row) {
            $key = strtolower(trim((string)$row['Artist']) . '_' . trim((string)$row['Album']));
            if (isset($liveCounts[$key]) && $liveCounts[$key] > (int)$row['Playcount']) {
                $row['Playcount'] = $liveCounts[$key];
                $updates++;
            }
        }
        $this->albumRepo->saveElo($eloData);

        foreach ($queueData as &$row) {
            $key = strtolower(trim((string)$row['Artist']) . '_' . trim((string)$row['Album']));
            if (isset($liveCounts[$key]) && $liveCounts[$key] > (int)$row['Playcount']) {
                $row['Playcount'] = $liveCounts[$key];
                $updates++;
            }
        }
        $this->albumRepo->saveQueue($queueData);

        return ['error' => null, 'albums' => $albums, 'updates' => $updates];
    }

    /**
     * @return array{error: ?array{source: string, message: string}, candidates: list<array{artist: string, album: string, playcount: int}>}
     */
    public function fetchCandidates(string $source, string $mode, string $username, int $topLimit): array
    {
        $error = null;
        $existing = $this->albumRepo->buildExistingLookup();
        $minPlays = $this->settings->getImportMinPlays();

        if ($mode === 'top') {
            $allowedLimits = [100, 200, 500, 1000];
            if (!in_array($topLimit, $allowedLimits, true)) {
                $topLimit = 100;
            }

            if ($source === 'listenbrainz') {
                $albums = $this->fetchListenbrainzTopAlbums($username, $topLimit, $error);
            } elseif ($source === 'subsonic') {
                $albums = $this->subsonic->fetchAlbumList('frequent', $topLimit);
            } else {
                $albums = $this->fetchLastfmTopAlbums($username, $topLimit, $error);
            }

            return [
                'error'      => $error,
                'candidates' => $this->buildCandidatesFromAlbumRows($albums, $existing, $minPlays, true, $minPlays),
            ];
        }

        // Recent / Liked modes
        if ($source === 'listenbrainz') {
            $counts = $this->fetchListenbrainzRecentCounts($username, 400, $error);
            $candidates = $this->buildCandidatesFromCounts($counts, $existing, $minPlays);
        } elseif ($source === 'subsonic' && $mode === 'liked') {
            $albums = $this->subsonic->fetchStarredAlbums(1000);
            $candidates = $this->buildCandidatesFromAlbumRows($albums, $existing, $minPlays, false, 1);
        } elseif ($source === 'subsonic') {
            $albums = $this->subsonic->fetchAlbumList('recent', 400);
            $candidates = $this->buildCandidatesFromAlbumRows($albums, $existing, $minPlays, false, max(1, $minPlays));
            foreach ($candidates as &$c) {
                if ((int)$c['playcount'] < $minPlays) {
                    $c['playcount'] = $minPlays;
                }
            }
            unset($c);
        } else {
            $counts = $this->fetchLastfmRecentCounts($username, 400, $error);
            $candidates = $this->buildCandidatesFromCounts($counts, $existing, $minPlays);
        }

        return ['error' => $error, 'candidates' => $candidates];
    }

    /**
     * @param list<array{artist: string, album: string, playcount: int}> $selectedCandidates
     */
    public function importToDb(array $selectedCandidates): int
    {
        if (empty($selectedCandidates)) {
            return 0;
        }

        $eloData = $this->albumRepo->loadElo();
        $existing = [];
        foreach ($eloData as $row) {
            $existing[strtolower(trim((string)$row['Artist']) . '|||' . trim((string)$row['Album']))] = true;
        }

        $added = 0;
        foreach ($selectedCandidates as $item) {
            $key = strtolower(trim($item['artist']) . '|||' . trim($item['album']));
            if (isset($existing[$key])) {
                continue;
            }
            $eloData[] = [
                'Artist'    => $item['artist'],
                'Album'     => $item['album'],
                'Elo'       => 1200,
                'Duels'     => 0,
                'Playcount' => (int)$item['playcount'],
                'Wins'      => 0,
                'Losses'    => 0,
            ];
            $existing[$key] = true;
            $added++;
        }

        $this->albumRepo->saveElo($eloData);
        return $added;
    }

    /**
     * @param list<array{artist: string, album: string, playcount: int}> $selectedCandidates
     */
    public function importToQueue(array $selectedCandidates): int
    {
        if (empty($selectedCandidates)) {
            return 0;
        }

        $queueData = $this->albumRepo->loadQueue();
        $existing = [];
        foreach ($queueData as $row) {
            $existing[strtolower(trim((string)$row['Artist']) . '|||' . trim((string)$row['Album']))] = true;
        }

        $added = 0;
        foreach ($selectedCandidates as $item) {
            $key = strtolower(trim($item['artist']) . '|||' . trim($item['album']));
            if (isset($existing[$key])) {
                continue;
            }
            $this->albumRepo->moveToQueue(
                $item['artist'],
                $item['album'],
                1200,
                0,
                (int)$item['playcount'],
                0,
                0
            );
            $existing[$key] = true;
            $added++;
        }

        return $added;
    }

    /**
     * @return list<array{artist: string, album: string, playcount: int}>
     */
    public function parseCsvFile(string $filePath, int $minPlays): array
    {
        $candidates = [];
        if (($handle = fopen($filePath, 'r')) === false) {
            return $candidates;
        }

        fgetcsv($handle, 1000, ',', '"', ''); // skip header
        while (($data = fgetcsv($handle, 1000, ',', '"', '')) !== false) {
            $artist = trim((string)($data[0] ?? ''));
            $album  = trim((string)($data[1] ?? ''));
            $playcount = isset($data[2]) ? (int)$data[2] : 1;

            if ($artist !== '' && $album !== '' && $playcount >= $minPlays) {
                $candidates[] = ['artist' => $artist, 'album' => $album, 'playcount' => $playcount];
            }
        }
        fclose($handle);

        usort($candidates, fn(array $a, array $b): int => $b['playcount'] <=> $a['playcount']);
        return $candidates;
    }

    /**
     * @param list<array{artist: string, album: string, playcount: int}> $candidates
     * @return string
     */
    public function encodeCandidatesState(array $candidates, string $source = ''): string
    {
        return base64_encode(json_encode([
            'source' => $source,
            'items'  => array_values($candidates),
        ]));
    }

    /**
     * @return array{source: string, items: list<array{artist: string, album: string, playcount: int}>}
     */
    public function decodeCandidatesState(string $encoded): array
    {
        if (empty($encoded)) {
            return ['source' => '', 'items' => []];
        }
        $decoded = json_decode(base64_decode($encoded), true);
        if (!is_array($decoded)) {
            return ['source' => '', 'items' => []];
        }
        return [
            'source' => (string)($decoded['source'] ?? ''),
            'items'  => is_array($decoded['items'] ?? null) ? $decoded['items'] : [],
        ];
    }

    public static function getCandidateKey(array $candidate): string
    {
        return strtolower(trim($candidate['artist']) . '|||' . trim($candidate['album']));
    }

    /**
     * @param list<array{artist: string, album: string, playcount: int}> $albums
     * @param array<string, true> $existingAlbums
     * @return list<array{artist: string, album: string, playcount: int}>
     */
    private function buildCandidatesFromAlbumRows(array $albums, array $existingAlbums, int $minPlays, bool $enforceMinPlays, int $defaultPlaycount): array
    {
        $candidates = [];
        foreach ($albums as $album) {
            $artist = trim((string)($album['artist'] ?? ''));
            $name   = trim((string)($album['album'] ?? ''));
            $playcount = max(1, (int)($album['playcount'] ?? $defaultPlaycount));

            if ($artist === '' || $name === '') {
                continue;
            }

            $dbKey = strtolower($artist . '_' . $name);
            if (isset($existingAlbums[$dbKey])) {
                continue;
            }
            if ($enforceMinPlays && $playcount < $minPlays) {
                continue;
            }

            $candidates[] = ['artist' => $artist, 'album' => $name, 'playcount' => $playcount];
        }

        usort($candidates, fn(array $a, array $b): int => $b['playcount'] <=> $a['playcount']);
        return $candidates;
    }

    /**
     * @param array<string, int> $counts
     * @param array<string, true> $existingAlbums
     * @return list<array{artist: string, album: string, playcount: int}>
     */
    private function buildCandidatesFromCounts(array $counts, array $existingAlbums, int $minPlays): array
    {
        $candidates = [];
        foreach ($counts as $hash => $playcount) {
            [$artist, $album] = explode('|||', $hash, 2);
            $dbKey = strtolower(trim($artist) . '_' . trim($album));
            if (!isset($existingAlbums[$dbKey]) && $playcount >= $minPlays) {
                $candidates[] = ['artist' => $artist, 'album' => $album, 'playcount' => $playcount];
            }
        }
        usort($candidates, fn(array $a, array $b): int => $b['playcount'] <=> $a['playcount']);
        return $candidates;
    }

    /**
     * @return list<array{artist: string, album: string, playcount: int}>
     */
    private function fetchLastfmTopAlbums(string $username, int $limit, ?array &$error): array
    {
        $apiKey = $this->settings->getLastFmApiKey();
        if (empty($username)) {
            $error = ['source' => 'Last.fm', 'message' => 'Bitte einen Last.fm-Username eingeben.'];
            return [];
        }
        if (empty($apiKey)) {
            $error = ['source' => 'Last.fm', 'message' => 'Last.fm API-Key fehlt in der Konfiguration.'];
            return [];
        }

        $url = "http://ws.audioscrobbler.com/2.0/?method=user.gettopalbums&user=" . urlencode($username)
            . "&api_key=" . $apiKey . "&format=json&limit=" . $limit;
        $result = $this->http->fetchJson($url);

        if (!$result['ok']) {
            $error = ['source' => 'Last.fm', 'message' => 'Last.fm-API nicht erreichbar: ' . ($result['error'] ?? 'Verbindung fehlgeschlagen.')];
            return [];
        }

        $data = $result['data'];
        if (isset($data['error'])) {
            $error = ['source' => 'Last.fm', 'message' => "Last.fm Fehler {$data['error']}: " . ($data['message'] ?? 'Unbekannter Fehler')];
            return [];
        }

        $results = [];
        foreach ($data['topalbums']['album'] ?? [] as $alb) {
            $artist = trim((string)($alb['artist']['name'] ?? ''));
            $album  = trim((string)($alb['name'] ?? ''));
            $plays  = (int)($alb['playcount'] ?? 0);
            if ($artist !== '' && $album !== '') {
                $results[] = ['artist' => $artist, 'album' => $album, 'playcount' => $plays];
            }
        }
        return $results;
    }

    /**
     * @return array<string, int>
     */
    private function fetchLastfmRecentCounts(string $username, int $targetTracks, ?array &$error): array
    {
        $apiKey = $this->settings->getLastFmApiKey();
        if (empty($username) || empty($apiKey)) {
            $error = ['source' => 'Last.fm', 'message' => 'Last.fm nicht vollständig konfiguriert.'];
            return [];
        }

        $albumCounts = [];
        $pages = (int)ceil($targetTracks / 200);

        for ($page = 1; $page <= $pages; $page++) {
            $url = 'http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=' . urlencode($username)
                . '&api_key=' . $apiKey . '&format=json&limit=200&page=' . $page;
            $result = $this->http->fetchJson($url);

            if (!$result['ok']) {
                $error = ['source' => 'Last.fm', 'message' => 'Last.fm-API nicht erreichbar: ' . ($result['error'] ?? 'Verbindung fehlgeschlagen.')];
                return [];
            }

            $data = $result['data'];
            if (isset($data['error'])) {
                $error = ['source' => 'Last.fm', 'message' => "Last.fm Fehler {$data['error']}: " . ($data['message'] ?? 'Unbekannter Fehler')];
                return [];
            }

            $tracks = $data['recenttracks']['track'] ?? [];
            if (isset($tracks['name'])) {
                $tracks = [$tracks];
            }

            foreach ($tracks as $track) {
                if (($track['@attr']['nowplaying'] ?? '') === 'true') {
                    continue;
                }
                $artist = trim((string)($track['artist']['#text'] ?? $track['artist']['name'] ?? ''));
                $album  = trim((string)($track['album']['#text'] ?? ''));
                if ($artist !== '' && $album !== '') {
                    $key = $artist . '|||' . $album;
                    $albumCounts[$key] = ($albumCounts[$key] ?? 0) + 1;
                }
            }
        }

        return $albumCounts;
    }

    /**
     * @return list<array{artist: string, album: string, playcount: int}>
     */
    private function fetchListenbrainzTopAlbums(string $username, int $limit, ?array &$error): array
    {
        if (empty($username)) {
            $error = ['source' => 'ListenBrainz', 'message' => 'Bitte einen ListenBrainz-Username eingeben.'];
            return [];
        }

        $headers = ['Accept: application/json'];
        if (!empty($this->settings->getListenbrainzApiKey())) {
            $headers[] = 'Authorization: Token ' . $this->settings->getListenbrainzApiKey();
        }

        $url = 'https://api.listenbrainz.org/1/stats/user/' . rawurlencode($username) . '/releases?range=all_time&count=' . $limit;
        $result = $this->http->fetchJson($url, $headers);

        if (!$result['ok']) {
            $error = ['source' => 'ListenBrainz', 'message' => 'ListenBrainz-API nicht erreichbar: ' . ($result['error'] ?? 'Verbindung fehlgeschlagen.')];
            return [];
        }

        $data = $result['data'];
        if (($data['code'] ?? 200) !== 200) {
            $error = ['source' => 'ListenBrainz', 'message' => 'ListenBrainz meldet einen Fehler: ' . ($data['error'] ?? $data['message'] ?? 'Unbekannter Fehler')];
            return [];
        }

        $results = [];
        foreach ($data['payload']['releases'] ?? [] as $release) {
            $artist = trim((string)($release['artist_name'] ?? ''));
            $album  = trim((string)($release['release_name'] ?? ''));
            $plays  = (int)($release['listen_count'] ?? 0);
            if ($artist !== '' && $album !== '') {
                $results[] = ['artist' => $artist, 'album' => $album, 'playcount' => $plays];
            }
        }
        return $results;
    }

    /**
     * @return array<string, int>
     */
    private function fetchListenbrainzRecentCounts(string $username, int $targetTracks, ?array &$error): array
    {
        if (empty($username)) {
            $error = ['source' => 'ListenBrainz', 'message' => 'Bitte einen ListenBrainz-Username eingeben.'];
            return [];
        }

        $headers = ['Accept: application/json'];
        if (!empty($this->settings->getListenbrainzApiKey())) {
            $headers[] = 'Authorization: Token ' . $this->settings->getListenbrainzApiKey();
        }

        $albumCounts = [];
        $remaining = $targetTracks;
        $maxTs = null;

        while ($remaining > 0) {
            $count = min(100, $remaining);
            $url = 'https://api.listenbrainz.org/1/user/' . rawurlencode($username) . '/listens?count=' . $count;
            if ($maxTs !== null) {
                $url .= '&max_ts=' . $maxTs;
            }

            $result = $this->http->fetchJson($url, $headers);
            if (!$result['ok']) {
                $error = ['source' => 'ListenBrainz', 'message' => 'ListenBrainz-API nicht erreichbar: ' . ($result['error'] ?? 'Verbindung fehlgeschlagen.')];
                return [];
            }

            $data = $result['data'];
            if (($data['code'] ?? 200) !== 200) {
                $error = ['source' => 'ListenBrainz', 'message' => 'ListenBrainz meldet einen Fehler: ' . ($data['error'] ?? $data['message'] ?? 'Unbekannter Fehler')];
                return [];
            }

            $listens = $data['payload']['listens'] ?? [];
            if (empty($listens)) {
                break;
            }

            $lastTs = null;
            foreach ($listens as $listen) {
                $meta  = $listen['track_metadata'] ?? [];
                $artist = trim((string)($meta['artist_name'] ?? ''));
                $album  = trim((string)($meta['release_name'] ?? ''));
                if ($artist !== '' && $album !== '') {
                    $key = $artist . '|||' . $album;
                    $albumCounts[$key] = ($albumCounts[$key] ?? 0) + 1;
                }
                $ts = $listen['listened_at'] ?? null;
                if (is_numeric($ts)) {
                    $lastTs = (int)$ts;
                }
            }

            if ($lastTs === null) {
                break;
            }
            $maxTs = $lastTs - 1;
            $remaining -= count($listens);
            if (count($listens) < $count) {
                break;
            }
        }

        return $albumCounts;
    }
}
