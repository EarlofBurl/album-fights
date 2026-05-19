<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Repository\SettingsRepository;
use App\Utils\HttpClient;
use App\Utils\SubsonicClient;

class MetadataService
{
    private HttpClient $http;
    private SubsonicClient $subsonic;

    public function __construct(
        private Config $config,
        private SettingsRepository $settings
    ) {
        $this->http     = new HttpClient();
        $this->subsonic = new SubsonicClient($config);
    }

    public function getPreferredMusicService(): string
    {
        if (!empty($this->settings->getLastFmApiKey())) {
            return 'lastfm';
        }
        if (!empty($this->settings->getListenbrainzApiKey()) || !empty($this->settings->getListenbrainzUsername())) {
            return 'listenbrainz';
        }
        return 'lastfm';
    }

    public function getArtistExternalUrl(string $artist): string
    {
        if ($this->getPreferredMusicService() === 'listenbrainz') {
            return 'https://listenbrainz.org/search/?query=' . rawurlencode($artist) . '&type=artist';
        }
        return 'https://www.last.fm/music/' . rawurlencode($artist);
    }

    public function getAlbumExternalUrl(string $artist, string $album): string
    {
        if ($this->getPreferredMusicService() === 'listenbrainz') {
            return 'https://listenbrainz.org/search/?query=' . rawurlencode(trim($artist . ' ' . $album)) . '&type=release';
        }
        return 'https://www.last.fm/music/' . rawurlencode($artist) . '/' . rawurlencode($album);
    }

    public function getAlbumCacheBaseName(string $artist, string $album): string
    {
        $hash = md5(strtolower($artist . '_' . $album));
        return 'album_' . $hash;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAlbumData(string $artist, string $album, bool $forceRefresh = false): array
    {
        $fnStart  = microtime(true);
        $safeName = $this->getAlbumCacheBaseName($artist, $album);
        $jsonFile = $this->config->getCacheDir() . $safeName . '.json';
        $imgFile  = $this->config->getCacheDir() . $safeName . '.jpg';
        $imgUrl   = 'serve_image.php?file=' . urlencode($safeName . '.jpg');
        $now      = time();
        $cooldown = 60 * 60 * 24 * 7;

        if (!$forceRefresh && file_exists($jsonFile)) {
            $data = json_decode(file_get_contents($jsonFile), true);
            if (is_array($data)) {
                if (file_exists($imgFile)) {
                    $data['local_image'] = $imgUrl;
                }

                $source = $data['metadata_source'] ?? '';
                $shouldRefreshOldItunes = $source === 'itunes' && ($this->subsonic->isConfigured() || !empty($this->settings->getLastFmApiKey()) || !empty($this->settings->getListenbrainzApiKey()) || !empty($this->settings->getListenbrainzUsername()));
                $missingYear   = empty($data['year']);
                $missingGenres = empty($data['genres']) || !is_array($data['genres']);
                $shouldRefreshForLb = ($this->settings->getListenbrainzApiKey() || $this->settings->getListenbrainzUsername()) && $source !== 'listenbrainz' && ($missingYear || $missingGenres);
                $missingTracks = !isset($data['tracks']) || !is_array($data['tracks']) || count($data['tracks']) === 0;
                $shouldRefreshForTracks = $missingTracks && in_array($source, ['subsonic', 'lastfm'], true);

                $lastRefresh = isset($data['refresh_attempted_at']) ? (int)$data['refresh_attempted_at'] : 0;
                $cooldownActive = $lastRefresh > 0 && ($now - $lastRefresh) < $cooldown;
                $refreshNeeded = $shouldRefreshOldItunes || $shouldRefreshForLb || $shouldRefreshForTracks;

                if (isset($data['full_data_fetched']) && $data['full_data_fetched'] === true && (!$refreshNeeded || $cooldownActive)) {
                    $data['genres'] = $this->applyTagBlacklist($data['genres'] ?? []);
                    return $data;
                }
            }
        }

        $result = [
            'summary'           => 'No info available.',
            'local_image'       => '',
            'url'               => '',
            'genres'            => [],
            'year'              => '',
            'tracks'            => [],
            'full_data_fetched' => true,
            'metadata_source'   => '',
            'refresh_attempted_at' => $now,
        ];

        $foundImage = false;

        // 1. Subsonic first
        if ($this->subsonic->isConfigured()) {
            $subData = $this->subsonic->fetchAlbumData($artist, $album);
            if (is_array($subData)) {
                if (!empty($subData['url'])) {
                    $result['url'] = $subData['url'];
                }
                if (empty($result['year']) && !empty($subData['year'])) {
                    $result['year'] = $subData['year'];
                }
                if (!empty($subData['genres'])) {
                    $result['genres'] = array_values(array_unique(array_merge($result['genres'], $subData['genres'])));
                }
                if (!empty($subData['tracks'])) {
                    $result['tracks'] = array_values(array_unique(array_filter(array_merge($result['tracks'], $subData['tracks']))));
                }
                if (!$foundImage && !empty($subData['cover_id'])) {
                    $imgData = $this->subsonic->downloadCoverArt($subData['cover_id']);
                    if ($imgData !== null) {
                        file_put_contents($imgFile, $imgData);
                        $result['local_image'] = $imgUrl;
                        $foundImage = true;
                    }
                }
                if ($foundImage || $this->hasCoreMetadata($result)) {
                    $result['metadata_source'] = 'subsonic';
                }
            }
        }

        // 2. Last.fm
        if (!empty($this->settings->getLastFmApiKey())) {
            $this->fetchLastFm($artist, $album, $result, $foundImage, $imgFile, $imgUrl);
        }

        // 3. ListenBrainz
        $lbConfigured = !empty($this->settings->getListenbrainzApiKey()) || !empty($this->settings->getListenbrainzUsername());
        $needsFallback = !$foundImage || !$this->hasCoreMetadata($result);
        $needsEnrichment = empty($result['year']) || empty($result['genres']);
        if ($lbConfigured && ($needsFallback || $needsEnrichment)) {
            $this->fetchListenbrainz($artist, $album, $result, $foundImage, $imgFile, $imgUrl);
        }

        // 4. iTunes fallback
        $needsFallback = !$foundImage || !$this->hasCoreMetadata($result);
        if ($needsFallback) {
            $this->fetchItunes($artist, $album, $result, $foundImage, $imgFile, $imgUrl);
        }

        if (!$foundImage && file_exists($imgFile)) {
            $result['local_image'] = $imgUrl;
        }

        $result['genres'] = $this->applyTagBlacklist($result['genres'] ?? []);
        $result['tracks'] = array_values(array_unique(array_filter($result['tracks'] ?? [])));
        file_put_contents($jsonFile, json_encode($result));

        return $result;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function fetchLastFm(string $artist, string $album, array &$result, bool &$foundImage, string $imgFile, string $imgUrl): void
    {
        $url = 'http://ws.audioscrobbler.com/2.0/?method=album.getinfo&api_key=' . $this->settings->getLastFmApiKey()
            . '&artist=' . urlencode($artist) . '&album=' . urlencode($album) . '&format=json';
        $decoded = $this->http->fetchJson($url, ['User-Agent: AlbumDuelApp/1.0'], 8);

        if (!$decoded['ok']) {
            return;
        }

        $albumData = $decoded['data']['album'] ?? null;
        if (!is_array($albumData)) {
            return;
        }

        if (!empty($albumData['url'])) {
            $result['url'] = $albumData['url'];
        }
        if (!empty($albumData['wiki']['summary'])) {
            $result['summary'] = trim(strip_tags(explode('<a href', $albumData['wiki']['summary'])[0]));
        }
        if (!empty($albumData['wiki']['published']) && preg_match('/\b(19|20)\d{2}\b/', (string)$albumData['wiki']['published'], $m)) {
            $result['year'] = $m[0];
        }
        if (empty($result['year']) && preg_match('/\b(19|20)\d{2}\b/', (string)$result['summary'], $m)) {
            $result['year'] = $m[0];
        }

        if (!empty($albumData['tags']['tag'])) {
            $tags = $albumData['tags']['tag'];
            if (isset($tags['name'])) {
                $result['genres'][] = ucwords(trim((string)$tags['name']));
            } elseif (is_array($tags)) {
                foreach ($tags as $tag) {
                    if (!empty($tag['name'])) {
                        $result['genres'][] = ucwords(trim((string)$tag['name']));
                    }
                }
            }
            $result['genres'] = array_values(array_unique($result['genres']));
        }

        if (!empty($albumData['tracks']['track'])) {
            $tracks = $albumData['tracks']['track'];
            if (isset($tracks['name'])) {
                $result['tracks'][] = trim((string)$tracks['name']);
            } elseif (is_array($tracks)) {
                foreach ($tracks as $track) {
                    if (!empty($track['name'])) {
                        $result['tracks'][] = trim((string)$track['name']);
                    }
                }
            }
            $result['tracks'] = array_values(array_unique(array_filter($result['tracks'])));
        }

        if (!empty($albumData['image']) && is_array($albumData['image'])) {
            foreach (array_reverse($albumData['image']) as $img) {
                if (empty($img['#text'])) {
                    continue;
                }
                $imgData = $this->http->fetchBinary((string)$img['#text'], ['User-Agent: AlbumDuelApp/1.0'], 8);
                if ($imgData !== null) {
                    file_put_contents($imgFile, $imgData);
                    $result['local_image'] = $imgUrl;
                    $foundImage = true;
                    break;
                }
            }
        }

        if ($foundImage || $this->hasCoreMetadata($result)) {
            $result['metadata_source'] = 'lastfm';
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function fetchListenbrainz(string $artist, string $album, array &$result, bool &$foundImage, string $imgFile, string $imgUrl): void
    {
        $query = sprintf('artist:"%s" AND release:"%s"', $artist, $album);
        $url = 'https://musicbrainz.org/ws/2/release/?query=' . urlencode($query) . '&fmt=json&limit=1';
        $decoded = $this->http->fetchJson($url, ['User-Agent: AlbumDuelApp/1.0 (listenbrainz-fallback)']);

        if (!$decoded['ok'] || !isset($decoded['data']['releases'][0])) {
            return;
        }

        $release = $decoded['data']['releases'][0];
        $genres = [];
        if (!empty($release['tags']) && is_array($release['tags'])) {
            foreach ($release['tags'] as $tag) {
                if (!empty($tag['name'])) {
                    $genres[] = ucwords(trim((string)$tag['name']));
                }
            }
        }

        $year = '';
        if (!empty($release['date']) && preg_match('/\b(19|20)\d{2}\b/', (string)$release['date'], $m)) {
            $year = $m[0];
        }

        $mbid = trim((string)($release['id'] ?? ''));
        $coverUrl = $mbid !== '' ? 'https://coverartarchive.org/release/' . rawurlencode($mbid) . '/front-500' : '';

        if (empty($result['url']) && $mbid !== '') {
            $result['url'] = 'https://musicbrainz.org/release/' . rawurlencode($mbid);
        }
        if ($result['summary'] === 'No info available.' && !empty($listenbrainzData['summary'] ?? '')) {
            $result['summary'] = $listenbrainzData['summary'];
        }
        if (!$foundImage && $coverUrl !== '') {
            $imgData = $this->http->fetchBinary($coverUrl, ['User-Agent: AlbumDuelApp/1.0'], 8);
            if ($imgData !== null) {
                file_put_contents($imgFile, $imgData);
                $result['local_image'] = $imgUrl;
                $foundImage = true;
            }
        }
        if (empty($result['year']) && $year !== '') {
            $result['year'] = $year;
        }
        if (!empty($genres)) {
            $result['genres'] = array_values(array_unique(array_merge($result['genres'], $genres)));
        }
        if ($result['metadata_source'] === '' || (empty($result['year']) || empty($result['genres']))) {
            $result['metadata_source'] = 'listenbrainz';
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function fetchItunes(string $artist, string $album, array &$result, bool &$foundImage, string $imgFile, string $imgUrl): void
    {
        $term = trim($artist . ' ' . $album);
        if ($term === '') {
            return;
        }

        $url = 'https://itunes.apple.com/search?term=' . urlencode($term) . '&entity=album&limit=1';
        $decoded = $this->http->fetchJson($url, ['User-Agent: AlbumDuelApp/1.0']);

        if (!$decoded['ok'] || !isset($decoded['data']['results'][0])) {
            return;
        }

        $r = $decoded['data']['results'][0];
        if (empty($result['url']) && !empty($r['collectionViewUrl'])) {
            $result['url'] = $r['collectionViewUrl'];
        }
        if (!empty($r['primaryGenreName'])) {
            $result['genres'][] = ucwords(trim((string)$r['primaryGenreName']));
            $result['genres'] = array_values(array_unique($result['genres']));
        }
        if (empty($result['year']) && !empty($r['releaseDate']) && preg_match('/\b(19|20)\d{2}\b/', (string)$r['releaseDate'], $m)) {
            $result['year'] = $m[0];
        }
        if (!$foundImage && !empty($r['artworkUrl100'])) {
            $coverUrl = str_replace('100x100bb', '600x600bb', (string)$r['artworkUrl100']);
            $imgData = $this->http->fetchBinary($coverUrl, ['User-Agent: AlbumDuelApp/1.0'], 8);
            if ($imgData !== null) {
                file_put_contents($imgFile, $imgData);
                $result['local_image'] = $imgUrl;
                $foundImage = true;
            }
        }
        if ($result['metadata_source'] === '' && ($foundImage || $this->hasCoreMetadata($result))) {
            $result['metadata_source'] = 'itunes';
        }
    }

    /**
     * @param array<string, mixed> $result
     */
    private function hasCoreMetadata(array $result): bool
    {
        return !empty($result['url']) || !empty($result['year']) || !empty($result['genres']) || ($result['summary'] ?? '') !== 'No info available.';
    }

    /**
     * @param list<string> $genres
     * @return list<string>
     */
    /**
     * @param list<string> $genres
     * @return list<string>
     */
    public function applyTagBlacklist(array $genres): array
    {
        $blacklist = $this->settings->getTagBlacklist();
        $lookup = [];
        foreach ($blacklist as $tag) {
            $normalized = $this->normalizeTag($tag);
            if ($normalized !== '') {
                $lookup[$normalized] = true;
            }
        }

        $filtered = [];
        foreach ($genres as $genre) {
            $normalized = $this->normalizeTag($genre);
            if ($normalized === '' || isset($lookup[$normalized])) {
                continue;
            }
            $filtered[] = ucwords($normalized);
        }

        return array_values(array_unique($filtered));
    }

    private function normalizeTag(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value);
        return $value;
    }
}
