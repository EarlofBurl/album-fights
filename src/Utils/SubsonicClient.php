<?php
declare(strict_types=1);

namespace App\Utils;

use App\Core\Config;

class SubsonicClient
{
    private ?array $creds = null;
    private HttpClient $http;

    public function __construct(private Config $config)
    {
        $this->http = new HttpClient();
        $this->creds = $this->resolveCreds();
    }

    public function isConfigured(): bool
    {
        return $this->creds !== null;
    }

    /**
     * @param array<string, mixed> $params
     * @return ?array<string, mixed>
     */
    public function call(string $method, array $params = []): ?array
    {
        if ($this->creds === null) {
            return null;
        }

        $salt  = bin2hex(random_bytes(6));
        $query = array_merge([
            'u' => $this->creds['username'],
            't' => md5($this->creds['password'] . $salt),
            's' => $salt,
            'v' => '1.16.1',
            'c' => 'albumfights',
            'f' => 'json',
        ], $params);

        $url = $this->creds['base_url'] . '/rest/' . $method . '.view?' . http_build_query($query);
        $result = $this->http->fetchJson($url, ['User-Agent: AlbumDuelApp/1.0 (subsonic)']);

        if (!$result['ok'] || !isset($result['data']['subsonic-response'])) {
            return null;
        }

        $payload = $result['data']['subsonic-response'];
        return ($payload['status'] ?? '') === 'ok' ? $payload : null;
    }

    public function downloadCoverArt(string $coverId): ?string
    {
        $coverId = trim($coverId);
        if ($coverId === '' || $this->creds === null) {
            return null;
        }

        $salt = bin2hex(random_bytes(6));
        $query = [
            'u'    => $this->creds['username'],
            't'    => md5($this->creds['password'] . $salt),
            's'    => $salt,
            'v'    => '1.16.1',
            'c'    => 'albumfights',
            'f'    => 'json',
            'id'   => $coverId,
            'size' => 900,
        ];

        $url = $this->creds['base_url'] . '/rest/getCoverArt.view?' . http_build_query($query);
        return $this->http->fetchBinary($url, ['User-Agent: AlbumDuelApp/1.0 (subsonic)'], 8);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchAlbumList(string $type, int $limit): array
    {
        $results = [];
        $offset  = 0;
        $pageSize = 500;

        while (count($results) < $limit) {
            $size = min($pageSize, $limit - count($results));
            $response = $this->call('getAlbumList2', [
                'type'   => $type,
                'size'   => $size,
                'offset' => $offset,
            ]);

            if (!is_array($response)) {
                break;
            }

            $albums = self::normalizeArray($response['albumList2']['album'] ?? []);
            if (empty($albums)) {
                break;
            }

            foreach ($albums as $album) {
                $artist = trim((string)($album['artist'] ?? ''));
                $name   = trim((string)($album['name'] ?? ''));
                if ($artist === '' || $name === '') {
                    continue;
                }
                $results[] = [
                    'artist'    => $artist,
                    'album'     => $name,
                    'playcount' => max(1, (int)($album['playCount'] ?? 0)),
                ];
            }

            if (count($albums) < $size) {
                break;
            }
            $offset += $size;
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchStarredAlbums(int $limit): array
    {
        $response = $this->call('getStarred2');
        if (!is_array($response)) {
            return [];
        }

        $albums = self::normalizeArray($response['starred2']['album'] ?? []);
        $results = [];

        foreach ($albums as $album) {
            $artist = trim((string)($album['artist'] ?? ''));
            $name   = trim((string)($album['name'] ?? ''));
            if ($artist === '' || $name === '') {
                continue;
            }
            $results[] = [
                'artist'    => $artist,
                'album'     => $name,
                'playcount' => 1,
            ];
        }

        return array_slice($results, 0, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function fetchAlbumData(string $artist, string $album): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $search = $this->call('search3', [
            'query'       => trim($artist . ' ' . $album),
            'albumCount'  => 8,
            'artistCount' => 0,
            'songCount'   => 0,
        ]);

        if (!is_array($search)) {
            return null;
        }

        $albums = self::normalizeArray($search['searchResult3']['album'] ?? []);
        if (empty($albums)) {
            return null;
        }

        $targetArtist = self::normalizeText($artist);
        $targetAlbum  = self::normalizeText($album);

        $best = null;
        foreach ($albums as $entry) {
            $entryArtist = self::normalizeText($entry['artist'] ?? '');
            $entryAlbum  = self::normalizeText($entry['name'] ?? '');

            if ($entryAlbum === $targetAlbum && ($entryArtist === $targetArtist || str_contains($entryArtist, $targetArtist))) {
                $best = $entry;
                break;
            }

            if ($best === null && $entryAlbum === $targetAlbum) {
                $best = $entry;
            }
        }

        if ($best === null) {
            $best = $albums[0];
        }

        $albumId = trim((string)($best['id'] ?? ''));
        $coverId = trim((string)($best['coverArt'] ?? $albumId));
        $genres  = [];
        $tracks  = [];
        $year    = !empty($best['year']) ? (string)$best['year'] : '';

        if (!empty($best['genre'])) {
            $genres[] = ucwords(trim((string)$best['genre']));
        }

        if ($albumId !== '') {
            $albumResponse = $this->call('getAlbum', ['id' => $albumId]);
            if (is_array($albumResponse)) {
                $fullAlbum = $albumResponse['album'] ?? [];
                if (empty($year) && !empty($fullAlbum['year'])) {
                    $year = (string)$fullAlbum['year'];
                }
                if (!empty($fullAlbum['genre'])) {
                    $genres[] = ucwords(trim((string)$fullAlbum['genre']));
                }
                if ($coverId === '' && !empty($fullAlbum['coverArt'])) {
                    $coverId = trim((string)$fullAlbum['coverArt']);
                }

                $songs = self::normalizeArray($fullAlbum['song'] ?? []);
                foreach ($songs as $song) {
                    if (!empty($song['title'])) {
                        $tracks[] = trim((string)$song['title']);
                    }
                }
            }
        }

        return [
            'url'      => $albumId !== '' ? '#subsonic-album-' . $albumId : '',
            'summary'  => '',
            'genres'   => array_values(array_unique(array_filter($genres))),
            'year'     => $year,
            'cover_id' => $coverId,
            'tracks'   => array_values(array_unique(array_filter($tracks))),
        ];
    }

    /**
     * @param array<array-key, mixed> $value
     * @return list<array<string, mixed>>
     */
    public static function normalizeArray(array $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);
        return $isAssoc ? [$value] : $value;
    }

    public static function normalizeText(string $value): string
    {
        return preg_replace('/\s+/', ' ', strtolower(trim($value)));
    }

    private function resolveCreds(): ?array
    {
        $baseUrl  = rtrim(trim((string)$this->config->getSetting('subsonic_base_url', '')), '/');
        $username = trim((string)$this->config->getSetting('subsonic_username', ''));
        $password = trim((string)$this->config->getSetting('subsonic_password', ''));

        if ($baseUrl === '' || $username === '' || $password === '') {
            return null;
        }

        return [
            'base_url' => $baseUrl,
            'username' => $username,
            'password' => $password,
        ];
    }
}
