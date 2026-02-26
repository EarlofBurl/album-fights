<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';

$lastfm_user = $_SESSION['lastfm_user'] ?? '';
$listenbrainz_user = $_SESSION['listenbrainz_user'] ?? ($APP_SETTINGS['listenbrainz_username'] ?? '');
$subsonic_user = $_SESSION['subsonic_user'] ?? ($APP_SETTINGS['subsonic_username'] ?? '');
$candidates = [];
$message = '';
$candidateSource = '';
global $APP_SETTINGS;

$min_plays = $APP_SETTINGS['import_min_plays'];
$bundledBestAlbumsCsv = __DIR__ . '/1000_best_albums.csv';

if (empty($_SESSION['listenbrainz_user']) && !empty($APP_SETTINGS['listenbrainz_username'])) {
    $_SESSION['listenbrainz_user'] = $APP_SETTINGS['listenbrainz_username'];
}

if (empty($_SESSION['subsonic_user']) && !empty($APP_SETTINGS['subsonic_username'])) {
    $_SESSION['subsonic_user'] = $APP_SETTINGS['subsonic_username'];
}

// Smarte Weiche für den Desktop
function getSslOptionsForDesktop() {
    if (
        getenv('ALBUMFIGHTS_DESKTOP') === '1' ||
        getenv('FLATPAK_ID') ||
        getenv('ELECTRON_RUN_AS_NODE')
    ) {
        return [
            'verify_peer' => false,
            'verify_peer_name' => false
        ];
    }

    return [];
}

function encodeCandidatesState($candidates, $source = '') {
    return base64_encode(json_encode([
        'source' => $source,
        'items' => array_values($candidates)
    ]));
}

function decodeCandidatesState($encoded) {
    if (empty($encoded)) {
        return ['source' => '', 'items' => []];
    }

    $decoded = json_decode(base64_decode($encoded), true);
    if (!is_array($decoded)) {
        return ['source' => '', 'items' => []];
    }

    return [
        'source' => $decoded['source'] ?? '',
        'items' => is_array($decoded['items'] ?? null) ? $decoded['items'] : []
    ];
}

function getCandidateKey($candidate) {
    return strtolower(trim($candidate['artist']) . '|||' . trim($candidate['album']));
}

function fetchJson($url, $headers = []) {
    $opts = ['http' => ['method' => 'GET', 'timeout' => 20]];

    $userAgent = "User-Agent: AlbumDuelApp/1.0\r\n";

    if (!empty($headers)) {
        $opts['http']['header'] = $userAgent . implode("\r\n", $headers) . "\r\n";
    } else {
        $opts['http']['header'] = $userAgent;
    }

    $sslOpts = getSslOptionsForDesktop();
    if (!empty($sslOpts)) {
        $opts['ssl'] = $sslOpts;
    }

    $response = @file_get_contents($url, false, stream_context_create($opts));
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}


function getSubsonicConfig() {
    global $APP_SETTINGS;

    $baseUrl = rtrim(trim((string)($APP_SETTINGS['subsonic_base_url'] ?? '')), '/');
    $username = trim((string)($APP_SETTINGS['subsonic_username'] ?? ''));
    $password = trim((string)($APP_SETTINGS['subsonic_password'] ?? ''));

    if ($baseUrl === '' || $username === '' || $password === '') {
        return null;
    }

    return [
        'base_url' => $baseUrl,
        'username' => $username,
        'password' => $password
    ];
}

function normalizeSubsonicArray($value) {
    if (!is_array($value)) {
        return [];
    }

    $isAssoc = array_keys($value) !== range(0, count($value) - 1);
    return $isAssoc ? [$value] : $value;
}

function subsonicApiRequest($method, $params = []) {
    $cfg = getSubsonicConfig();
    if ($cfg === null) {
        return null;
    }

    $salt = bin2hex(random_bytes(6));
    $query = array_merge([
        'u' => $cfg['username'],
        't' => md5($cfg['password'] . $salt),
        's' => $salt,
        'v' => '1.16.1',
        'c' => 'albumfights',
        'f' => 'json'
    ], $params);

    $url = $cfg['base_url'] . '/rest/' . $method . '.view?' . http_build_query($query);
    $data = fetchJson($url);

    if (!is_array($data) || !isset($data['subsonic-response'])) {
        return null;
    }

    $response = $data['subsonic-response'];
    if (($response['status'] ?? 'failed') !== 'ok') {
        return null;
    }

    return $response;
}

function fetchSubsonicAlbumList($type, $limit) {
    $results = [];
    $offset = 0;
    $pageSize = 500;

    while (count($results) < $limit) {
        $size = min($pageSize, $limit - count($results));
        $response = subsonicApiRequest('getAlbumList2', [
            'type' => $type,
            'size' => $size,
            'offset' => $offset
        ]);

        if (!is_array($response)) {
            break;
        }

        $albums = normalizeSubsonicArray($response['albumList2']['album'] ?? []);
        if (empty($albums)) {
            break;
        }

        foreach ($albums as $album) {
            $artist = trim((string)($album['artist'] ?? ''));
            $albumName = trim((string)($album['name'] ?? ''));
            if ($artist === '' || $albumName === '') {
                continue;
            }

            $results[] = [
                'artist' => $artist,
                'album' => $albumName,
                'playcount' => max(1, (int)($album['playCount'] ?? 0))
            ];
        }

        if (count($albums) < $size) {
            break;
        }

        $offset += $size;
    }

    return array_slice($results, 0, $limit);
}

function fetchSubsonicStarredAlbums($limit) {
    $response = subsonicApiRequest('getStarred2');
    if (!is_array($response)) {
        return [];
    }

    $albums = normalizeSubsonicArray($response['starred2']['album'] ?? []);
    $results = [];

    foreach ($albums as $album) {
        $artist = trim((string)($album['artist'] ?? ''));
        $albumName = trim((string)($album['name'] ?? ''));
        if ($artist === '' || $albumName === '') {
            continue;
        }

        $results[] = [
            'artist' => $artist,
            'album' => $albumName,
            'playcount' => 1
        ];
    }

    return array_slice($results, 0, $limit);
}

function buildCandidatesFromAlbumRows($albums, $existingAlbums, $minPlays, $enforceMinPlays = true, $defaultPlaycount = 1) {
    $candidates = [];

    foreach ($albums as $album) {
        $artist = trim((string)($album['artist'] ?? ''));
        $albumName = trim((string)($album['album'] ?? ''));
        $playcount = (int)($album['playcount'] ?? $defaultPlaycount);
        $playcount = max(1, $playcount);

        if ($artist === '' || $albumName === '') {
            continue;
        }

        $dbKey = strtolower($artist . '_' . $albumName);
        if (isset($existingAlbums[$dbKey])) {
            continue;
        }

        if ($enforceMinPlays && $playcount < $minPlays) {
            continue;
        }

        $candidates[] = [
            'artist' => $artist,
            'album' => $albumName,
            'playcount' => $playcount
        ];
    }

    usort($candidates, function($a, $b) { return $b['playcount'] <=> $a['playcount']; });
    return $candidates;
}

function buildExistingAlbumsLookup() {
    $existingAlbums = [];
    $eloData = loadCsv(FILE_ELO);
    $queueData = loadCsv(FILE_QUEUE);

    foreach (array_merge($eloData, $queueData) as $row) {
        $key = strtolower(trim($row['Artist']) . '_' . trim($row['Album']));
        $existingAlbums[$key] = true;
    }

    return $existingAlbums;
}

function parseCandidateFromCsvRow($data, $defaultPlaycount = 1) {
    if (!is_array($data) || count($data) < 2) {
        return null;
    }

    $artist = trim((string)($data[0] ?? ''));
    $album = trim((string)($data[1] ?? ''));

    if ($artist === '' || $album === '') {
        return null;
    }

    $playcount = isset($data[2]) ? (int)$data[2] : (int)$defaultPlaycount;

    return [
        'artist' => $artist,
        'album' => $album,
        'playcount' => $playcount
    ];
}

function fetchLastfmTopAlbums($username, $limit) {
    if (empty($username) || empty(LASTFM_API_KEY)) {
        return [];
    }

    $url = "http://ws.audioscrobbler.com/2.0/?method=user.gettopalbums&user=" . urlencode($username) . "&api_key=" . LASTFM_API_KEY . "&format=json&limit=" . (int)$limit;
    $data = fetchJson($url);
    if (!isset($data['topalbums']['album']) || !is_array($data['topalbums']['album'])) {
        return [];
    }

    $results = [];
    foreach ($data['topalbums']['album'] as $alb) {
        $artist = trim($alb['artist']['name'] ?? '');
        $album = trim($alb['name'] ?? '');
        $plays = (int)($alb['playcount'] ?? 0);
        if (!empty($artist) && !empty($album)) {
            $results[] = ['artist' => $artist, 'album' => $album, 'playcount' => $plays];
        }
    }

    return $results;
}

function fetchListenbrainzTopAlbums($username, $limit) {
    if (empty($username)) {
        return [];
    }

    $headers = ['Accept: application/json'];
    if (!empty(LISTENBRAINZ_API_KEY)) {
        $headers[] = 'Authorization: Token ' . LISTENBRAINZ_API_KEY;
    }

    $url = 'https://api.listenbrainz.org/1/stats/user/' . rawurlencode($username) . '/releases?range=all_time&count=' . (int)$limit;
    $data = fetchJson($url, $headers);
    if (!isset($data['payload']['releases']) || !is_array($data['payload']['releases'])) {
        return [];
    }

    $results = [];
    foreach ($data['payload']['releases'] as $release) {
        $artist = trim($release['artist_name'] ?? '');
        $album = trim($release['release_name'] ?? '');
        $plays = (int)($release['listen_count'] ?? 0);
        if (!empty($artist) && !empty($album)) {
            $results[] = ['artist' => $artist, 'album' => $album, 'playcount' => $plays];
        }
    }

    return $results;
}

function fetchLastfmRecentAlbumCounts($username, $targetTracks = 400) {
    if (empty($username) || empty(LASTFM_API_KEY)) {
        return [];
    }

    $albumCounts = [];
    $pages = (int)ceil($targetTracks / 200);

    for ($page = 1; $page <= $pages; $page++) {
        $url = 'http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=' . urlencode($username) . '&api_key=' . LASTFM_API_KEY . '&format=json&limit=200&page=' . $page;
        $data = fetchJson($url);

        if (!isset($data['recenttracks']['track'])) {
            continue;
        }

        $tracks = $data['recenttracks']['track'];
        if (isset($tracks['name'])) {
            $tracks = [$tracks];
        }

        foreach ($tracks as $track) {
            if (($track['@attr']['nowplaying'] ?? '') === 'true') {
                continue;
            }

            $artist = trim($track['artist']['#text'] ?? $track['artist']['name'] ?? '');
            $album = trim($track['album']['#text'] ?? '');

            if (!empty($artist) && !empty($album)) {
                $key = $artist . '|||' . $album;
                if (!isset($albumCounts[$key])) {
                    $albumCounts[$key] = 0;
                }
                $albumCounts[$key]++;
            }
        }
    }

    return $albumCounts;
}

function fetchListenbrainzRecentAlbumCounts($username, $targetTracks = 400) {
    if (empty($username)) {
        return [];
    }

    $albumCounts = [];
    $remaining = $targetTracks;
    $maxTs = null;

    $headers = ['Accept: application/json'];
    if (!empty(LISTENBRAINZ_API_KEY)) {
        $headers[] = 'Authorization: Token ' . LISTENBRAINZ_API_KEY;
    }

    while ($remaining > 0) {
        $count = min(100, $remaining);
        $url = 'https://api.listenbrainz.org/1/user/' . rawurlencode($username) . '/listens?count=' . $count;
        if ($maxTs !== null) {
            $url .= '&max_ts=' . $maxTs;
        }

        $data = fetchJson($url, $headers);
        $listens = $data['payload']['listens'] ?? [];
        if (empty($listens)) {
            break;
        }

        $lastTsInBatch = null;
        foreach ($listens as $listen) {
            $trackMetadata = $listen['track_metadata'] ?? [];
            $artist = trim($trackMetadata['artist_name'] ?? '');
            $album = trim($trackMetadata['release_name'] ?? '');

            if (!empty($artist) && !empty($album)) {
                $key = $artist . '|||' . $album;
                if (!isset($albumCounts[$key])) {
                    $albumCounts[$key] = 0;
                }
                $albumCounts[$key]++;
            }

            $listenTs = $listen['listened_at'] ?? null;
            if (is_numeric($listenTs)) {
                $lastTsInBatch = (int)$listenTs;
            }
        }

        if ($lastTsInBatch === null) {
            break;
        }

        $maxTs = $lastTsInBatch - 1;
        $remaining -= count($listens);

        if (count($listens) < $count) {
            break;
        }
    }

    return $albumCounts;
}

function buildCandidatesFromCounts($albumCounts, $existingAlbums, $minPlays) {
    $candidates = [];

    foreach ($albumCounts as $hash => $playcount) {
        [$artist, $album] = explode('|||', $hash, 2);
        $dbKey = strtolower(trim($artist) . '_' . trim($album));

        if (!isset($existingAlbums[$dbKey]) && $playcount >= $minPlays) {
            $candidates[] = [
                'artist' => $artist,
                'album' => $album,
                'playcount' => $playcount
            ];
        }
    }

    usort($candidates, function($a, $b) { return $b['playcount'] <=> $a['playcount']; });
    return $candidates;
}

function importAlbums($selectedCandidates, $destination) {
    if (empty($selectedCandidates)) {
        return 0;
    }

    $addedCount = 0;

    if ($destination === 'db') {
        $eloData = loadCsv(FILE_ELO);
        $existing = [];

        foreach ($eloData as $row) {
            $existing[strtolower(trim($row['Artist']) . '|||' . trim($row['Album']))] = true;
        }

        foreach ($selectedCandidates as $item) {
            $key = getCandidateKey($item);
            if (isset($existing[$key])) {
                continue;
            }

            $eloData[] = [
                'Artist' => $item['artist'],
                'Album' => $item['album'],
                'Elo' => 1200,
                'Duels' => 0,
                'Playcount' => (int)$item['playcount']
            ];
            $existing[$key] = true;
            $addedCount++;
        }

        saveCsv(FILE_ELO, $eloData);
    } else {
        $queueData = loadCsv(FILE_QUEUE);
        $existing = [];

        foreach ($queueData as $row) {
            $existing[strtolower(trim($row['Artist']) . '|||' . trim($row['Album']))] = true;
        }

        foreach ($selectedCandidates as $item) {
            $key = getCandidateKey($item);
            if (isset($existing[$key])) {
                continue;
            }

            moveToQueue($item['artist'], $item['album'], 1200, 0, (int)$item['playcount']);
            $existing[$key] = true;
            $addedCount++;
        }
    }

    return $addedCount;
}

// ==========================================
// HANDLE ACTIONS
// ==========================================
$messageType = 'success'; // Default message style

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        if ($action === 'sync_playcounts') {
            $source = $_POST['source'] ?? 'lastfm';
            $username = trim($_POST['username'] ?? '');

            if ($source === 'listenbrainz') {
                $_SESSION['listenbrainz_user'] = $username;
                $listenbrainz_user = $username;
                $liveAlbums = fetchListenbrainzTopAlbums($username, 1000);
                $sourceLabel = 'ListenBrainz';
            } elseif ($source === 'subsonic') {
                $_SESSION['subsonic_user'] = $username;
                $subsonic_user = $username;
                $liveAlbums = fetchSubsonicAlbumList('frequent', 1000);
                $sourceLabel = 'Navidrome/Subsonic';
            } else {
                $_SESSION['lastfm_user'] = $username;
                $lastfm_user = $username;
                $liveAlbums = fetchLastfmTopAlbums($username, 1000);
                $sourceLabel = 'Last.fm';
            }

            if (!empty($liveAlbums)) {
                $livePlaycounts = [];
                foreach ($liveAlbums as $alb) {
                    $key = strtolower(trim($alb['artist']) . '_' . trim($alb['album']));
                    $livePlaycounts[$key] = (int)$alb['playcount'];
                }

                $updates = 0;
                $eloData = loadCsv(FILE_ELO);
                foreach ($eloData as &$row) {
                    $key = strtolower(trim($row['Artist']) . '_' . trim($row['Album']));
                    if (isset($livePlaycounts[$key]) && $livePlaycounts[$key] > $row['Playcount']) {
                        $row['Playcount'] = $livePlaycounts[$key];
                        $updates++;
                    }
                }
                saveCsv(FILE_ELO, $eloData);

                $queueData = loadCsv(FILE_QUEUE);
                foreach ($queueData as &$row) {
                    $key = strtolower(trim($row['Artist']) . '_' . trim($row['Album']));
                    if (isset($livePlaycounts[$key]) && $livePlaycounts[$key] > $row['Playcount']) {
                        $row['Playcount'] = $livePlaycounts[$key];
                        $updates++;
                    }
                }
                saveCsv(FILE_QUEUE, $queueData);

                $message = "🔄 Sync complete via $sourceLabel! Updated the playcounts for <strong>$updates</strong> albums.";
            } else {
                $message = "❌ No top-album data returned from $sourceLabel. Check username/API settings and try again.";
                $messageType = 'error';
            }
        }
        elseif ($action === 'fetch_candidates') {
            $source = $_POST['source'] ?? 'lastfm';
            $mode = $_POST['fetch_mode'] ?? 'recent';
            $username = trim($_POST['username'] ?? '');
            $topLimit = (int)($_POST['top_limit'] ?? 100);

            $existingAlbums = buildExistingAlbumsLookup();
            $sourceLabel = 'Last.fm';

            if ($source === 'listenbrainz') {
                $sourceLabel = 'ListenBrainz';
                $_SESSION['listenbrainz_user'] = $username;
                $listenbrainz_user = $username;
            } elseif ($source === 'subsonic') {
                $sourceLabel = 'Navidrome/Subsonic';
                $_SESSION['subsonic_user'] = $username;
                $subsonic_user = $username;
            } else {
                $_SESSION['lastfm_user'] = $username;
                $lastfm_user = $username;
            }

            if ($mode === 'top') {
                $allowedLimits = [100, 200, 500, 1000];
                if (!in_array($topLimit, $allowedLimits, true)) {
                    $topLimit = 100;
                }

                if ($source === 'listenbrainz') {
                    $topAlbums = fetchListenbrainzTopAlbums($username, $topLimit);
                } elseif ($source === 'subsonic') {
                    $topAlbums = fetchSubsonicAlbumList('frequent', $topLimit);
                } else {
                    $topAlbums = fetchLastfmTopAlbums($username, $topLimit);
                }

                $candidates = buildCandidatesFromAlbumRows($topAlbums, $existingAlbums, $min_plays, true, $min_plays);
                $candidateSource = $source;

                if (empty($candidates)) {
                    $message = "ℹ️ Checked Top $topLimit from $sourceLabel. No new albums found that meet the minimum criteria ($min_plays plays).";
                    $messageType = 'info';
                } else {
                    $message = "✅ Imported preview from Top $topLimit ($sourceLabel): found " . count($candidates) . ' new candidates!';
                }
            } else {
                if ($source === 'listenbrainz') {
                    $recentScrobbles = fetchListenbrainzRecentAlbumCounts($username, 400);
                    $candidates = buildCandidatesFromCounts($recentScrobbles, $existingAlbums, $min_plays);
                    $modeLabel = 'the last 400 scrobbles/listens';
                } elseif ($source === 'subsonic' && $mode === 'liked') {
                    $likedAlbums = fetchSubsonicStarredAlbums(1000);
                    $candidates = buildCandidatesFromAlbumRows($likedAlbums, $existingAlbums, $min_plays, false, 1);
                    $modeLabel = 'your liked/starred albums';
                } elseif ($source === 'subsonic') {
                    $recentAlbums = fetchSubsonicAlbumList('recent', 400);
                    $candidates = buildCandidatesFromAlbumRows($recentAlbums, $existingAlbums, $min_plays, false, max(1, $min_plays));
                    foreach ($candidates as &$candidate) {
                        if ((int)$candidate['playcount'] < (int)$min_plays) {
                            $candidate['playcount'] = (int)$min_plays;
                        }
                    }
                    unset($candidate);
                    $modeLabel = 'recently played albums';
                } else {
                    $recentScrobbles = fetchLastfmRecentAlbumCounts($username, 400);
                    $candidates = buildCandidatesFromCounts($recentScrobbles, $existingAlbums, $min_plays);
                    $modeLabel = 'the last 400 scrobbles/listens';
                }

                $candidateSource = $source;

                if (empty($candidates)) {
                    $message = "ℹ️ Searched $modeLabel via $sourceLabel. No new albums found.";
                    $messageType = 'info';
                } else {
                    $message = "✅ Analyzed $modeLabel via $sourceLabel and found " . count($candidates) . ' new candidates!';
                }
            }
        }
        elseif (in_array($action, ['import_db', 'import_queue', 'import_selected_db', 'import_selected_queue', 'import_all_db', 'import_all_queue'], true)) {
            $state = decodeCandidatesState($_POST['candidates_state'] ?? '');
            $candidateSource = $state['source'];
            $candidates = $state['items'];

            $selectedKeys = [];
            if ($action === 'import_db' || $action === 'import_queue') {
                $singleKey = $_POST['candidate_key'] ?? '';
                if (!empty($singleKey)) {
                    $selectedKeys[] = $singleKey;
                }
            } elseif ($action === 'import_selected_db' || $action === 'import_selected_queue') {
                $selectedKeys = $_POST['selected_candidates'] ?? [];
            } else {
                foreach ($candidates as $candidate) {
                    $selectedKeys[] = getCandidateKey($candidate);
                }
            }

            $selectedLookup = array_fill_keys($selectedKeys, true);
            $selectedCandidates = [];
            $remainingCandidates = [];

            foreach ($candidates as $candidate) {
                $candidateKey = getCandidateKey($candidate);
                if (isset($selectedLookup[$candidateKey])) {
                    $selectedCandidates[] = $candidate;
                } else {
                    $remainingCandidates[] = $candidate;
                }
            }

            $destination = str_contains($action, '_queue') ? 'queue' : 'db';
            $imported = importAlbums($selectedCandidates, $destination);

            if (empty($selectedCandidates)) {
                $message = 'ℹ️ No albums were selected.';
                $messageType = 'info';
            } elseif ($destination === 'db') {
                $message = "✅ Imported <strong>$imported</strong> album(s) into the duel ranking.";
            } else {
                $message = "🎧 Imported <strong>$imported</strong> album(s) into the queue.";
            }

            $candidates = $remainingCandidates;
        }
        elseif ($action === 'upload_csv') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['csv_file']['tmp_name'];
                if (($handle = fopen($fileTmpPath, 'r')) !== FALSE) {
                    $header = fgetcsv($handle, 1000, ',', '"', '');
                    while (($data = fgetcsv($handle, 1000, ',', '"', '')) !== FALSE) {
                        $candidate = parseCandidateFromCsvRow($data);
                        if ($candidate !== null && $candidate['playcount'] >= $min_plays) {
                            $candidates[] = $candidate;
                        }
                    }
                    fclose($handle);
                    usort($candidates, function($a, $b) { return $b['playcount'] <=> $a['playcount']; });
                    $candidateSource = 'csv';
                    $count = count($candidates);
                    $message = "📄 CSV preview ready: <strong>$count</strong> albums will be imported (sorted by most plays).";
                }
            } else {
                $message = '❌ Error uploading the CSV file.';
                $messageType = 'error';
            }
        }
        elseif ($action === 'load_best_albums') {
            if (!file_exists($bundledBestAlbumsCsv)) {
                $message = '❌ Could not find 1000_best_albums.csv in the project root.';
                $messageType = 'error';
            } elseif (($handle = fopen($bundledBestAlbumsCsv, 'r')) === FALSE) {
                $message = '❌ Could not open 1000_best_albums.csv.';
                $messageType = 'error';
            } else {
                $existingAlbums = buildExistingAlbumsLookup();
                $existingCandidates = [];
                $candidatePlaycount = max(1, (int)$min_plays);
                $added = 0;
                $alreadyInDb = 0;

                while (($row = fgetcsv($handle, 2000, ',', '"', '')) !== FALSE) {
                    $candidate = parseCandidateFromCsvRow($row, $candidatePlaycount);
                    if ($candidate === null) {
                        continue;
                    }

                    $dbKey = strtolower(trim($candidate['artist']) . '_' . trim($candidate['album']));
                    if (isset($existingAlbums[$dbKey])) {
                        $alreadyInDb++;
                        continue;
                    }

                    $candidateKey = getCandidateKey($candidate);
                    if (isset($existingCandidates[$candidateKey])) {
                        continue;
                    }

                    $existingCandidates[$candidateKey] = true;
                    $candidates[] = $candidate;
                    $added++;
                }

                fclose($handle);
                usort($candidates, function($a, $b) {
                    return strcmp(getCandidateKey($a), getCandidateKey($b));
                });

                $candidateSource = 'best1000';
                if ($added === 0) {
                    $message = "ℹ️ Checked bundled 1000_best_albums.csv. No new albums found (already in DB/Queue).";
                    $messageType = 'info';
                } else {
                    $message = "📚 Bundled Top-1000 preview ready: <strong>$added</strong> new albums found. Skipped <strong>$alreadyInDb</strong> already present entries.";
                }
            }
        }
    }
}

$importUsernameValue = $lastfm_user ?: ($listenbrainz_user ?: $subsonic_user);
$candidatesState = encodeCandidatesState($candidates, $candidateSource);

require_once 'includes/header.php';
?>

<style>
    /* Scoped CSS for the Import Page */
    .import-container { max-width: 1100px; margin: 0 auto; padding: 20px 0; }
    
    /* Cards */
    .import-card { background: var(--card-bg, #222); border: 1px solid var(--border, #333); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
    .import-card.sync-card { border-left: 5px solid var(--accent, #b088ff); }
    .import-card h3 { margin-top: 0; margin-bottom: 8px; }
    .import-card p { font-size: 0.9rem; color: var(--text-muted, #999); margin-top: 0; margin-bottom: 20px; }
    
    /* Grid Layouts - Fixed for equal height and bottom-aligned buttons */
    .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px; align-items: stretch; }
    .grid-2 .import-card { display: flex; flex-direction: column; margin-bottom: 0; }
    .grid-2 .import-card form { flex: 1; display: flex; flex-direction: column; }
    .grid-2 .import-card .btn-full { margin-top: auto; }
    
    .flex-row { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
    
    /* Form Controls */
    .form-group { margin-bottom: 15px; }
    .form-control { width: 100%; padding: 12px; background: #111; color: #fff; border: 1px solid #333; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
    .form-control:focus { border-color: var(--accent, #b088ff); outline: none; }
    input[type="file"].form-control { padding: 9px; }
    
    /* Buttons */
    .btn { padding: 12px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: opacity 0.2s; text-align: center; font-size: 0.95rem; }
    .btn:hover { opacity: 0.8; }
    .btn-accent { background: var(--accent, #b088ff); color: #000; }
    .btn-dark { background: #333; color: #fff; }
    .btn-success { background: #2ecc71; color: #000; }
    .btn-full { width: 100%; display: block; }
    
    /* Alerts */
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 24px; font-weight: bold; }
    .alert-success { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.3); }
    .alert-error { background: rgba(231, 76, 60, 0.15); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.3); }
    .alert-info { background: rgba(52, 152, 219, 0.15); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.3); }

    /* Candidates Table */
    .table-toolbar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; margin-top: 10px; }
    .candidates-table { width: 100%; border-collapse: collapse; background: var(--card-bg, #222); border-radius: 8px; overflow: hidden; font-size: 0.95rem; }
    .candidates-table th, .candidates-table td { padding: 14px; text-align: left; border-bottom: 1px solid var(--border, #333); }
    .candidates-table th { background: #1a1a1a; font-weight: bold; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
    .candidates-table th.center, .candidates-table td.center { text-align: center; }
    .candidates-table tr:last-child td { border-bottom: none; }
    .candidates-table td.artist { color: var(--accent, #b088ff); font-weight: bold; }
    .table-actions { display: flex; gap: 10px; justify-content: center; }
    .table-actions form { margin: 0; }
</style>

<div class="import-container">
    <h2 style="margin-top: 0;">📥 Import & Maintenance</h2>
    <p style="color: var(--text-muted); margin-bottom: 24px;">
        Active Rules: Minimum <strong><?= $min_plays ?> Scrobbles</strong> required.
        (Changeable in <a href="settings.php" style="color: var(--accent);">Settings</a>).
    </p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="import-card sync-card">
        <h3 style="color: var(--accent);">🔄 Sync Live Playcounts</h3>
        <p>This pulls your Top 1000 albums from Last.fm, ListenBrainz, or Navidrome/Subsonic and updates matching playcounts in Duel Database + Queue.</p>
        <form method="POST" class="flex-row">
            <input type="hidden" name="action" value="sync_playcounts">
            <select name="source" class="form-control" style="flex: 0 0 170px;">
                <option value="lastfm">Last.fm</option>
                <option value="listenbrainz">ListenBrainz</option>
                <option value="subsonic">Navidrome/Subsonic</option>
            </select>
            <input type="text" name="username" class="form-control" style="flex: 1;" value="<?= htmlspecialchars($importUsernameValue) ?>" placeholder="Username">
            <button type="submit" class="btn btn-accent" style="flex: 0 0 200px;">Update Playcounts</button>
        </form>
    </div>

    <div class="grid-2">
        <div class="import-card">
            <h3>🎯 API Import</h3>
            <p>Choose source and mode: Top/Recent for all APIs, plus Liked for Navidrome/Subsonic.</p>
            <form method="POST">
                <input type="hidden" name="action" value="fetch_candidates">
                <div class="form-group">
                    <select name="source" class="form-control">
                        <option value="lastfm">Last.fm</option>
                        <option value="listenbrainz">ListenBrainz</option>
                        <option value="subsonic">Navidrome/Subsonic</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($importUsernameValue) ?>" placeholder="Username">
                </div>
                <div class="form-group flex-row">
                    <select name="fetch_mode" class="form-control" style="flex: 1;">
                        <option value="recent">Recent albums</option>
                        <option value="top">Most played (Top albums)</option>
                        <option value="liked">Liked / Starred albums (Subsonic only)</option>
                    </select>
                    <select name="top_limit" class="form-control" style="flex: 0 0 120px;">
                        <option value="100">Top 100</option>
                        <option value="200">Top 200</option>
                        <option value="500">Top 500</option>
                        <option value="1000">Top 1000</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-dark btn-full">Fetch & Preview</button>
            </form>
        </div>

        <div class="import-card">
            <h3>📄 Upload CSV List</h3>
            <p>Expected Format: <code>Artist, Album, Playcount</code>. Full preview first.</p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_csv">
                <div class="form-group">
                    <input type="file" name="csv_file" accept=".csv" class="form-control">
                </div>
                <button type="submit" class="btn btn-success btn-full">Upload & Preview CSV</button>
            </form>
        </div>

        <div class="import-card">
            <h3>🏆 Bundled Top 1000</h3>
            <p>One-click preview from <code>1000_best_albums.csv</code>. Only albums not already in DB/Queue are shown.</p>
            <form method="POST">
                <input type="hidden" name="action" value="load_best_albums">
                <button type="submit" class="btn btn-accent btn-full">Load 1000 Best Albums</button>
            </form>
        </div>
    </div>

    <?php if (!empty($candidates)): ?>
        <h3 style="margin-top: 40px; margin-bottom: 5px;">🔍 Found Candidates (<?= count($candidates) ?>)</h3>
        
        <form method="POST" id="bulk-import-form" class="table-toolbar">
            <input type="hidden" name="candidates_state" value="<?= htmlspecialchars($candidatesState) ?>">
            <button type="submit" name="action" value="import_selected_db" class="btn btn-accent">➕ Import Selected to DB</button>
            <button type="submit" name="action" value="import_selected_queue" class="btn btn-dark">🎧 Import Selected to Queue</button>
            <button type="submit" name="action" value="import_all_db" class="btn btn-success">✅ Import ALL to DB</button>
        </form>

        <table class="candidates-table">
            <thead>
                <tr>
                    <th class="center" style="width: 50px;"><input type="checkbox" id="select-all-candidates"></th>
                    <th>Artist</th>
                    <th>Album</th>
                    <th>Plays</th>
                    <th class="center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $album): ?>
                    <?php $candidateKey = getCandidateKey($album); ?>
                    <tr>
                        <td class="center">
                            <input type="checkbox" name="selected_candidates[]" value="<?= htmlspecialchars($candidateKey) ?>" form="bulk-import-form" class="candidate-checkbox">
                        </td>
                        <td class="artist"><?= htmlspecialchars($album['artist']) ?></td>
                        <td><?= htmlspecialchars($album['album']) ?></td>
                        <td><?= (int)$album['playcount'] ?></td>
                        <td>
                            <div class="table-actions">
                                <form method="POST">
                                    <input type="hidden" name="action" value="import_db">
                                    <input type="hidden" name="candidate_key" value="<?= htmlspecialchars($candidateKey) ?>">
                                    <input type="hidden" name="candidates_state" value="<?= htmlspecialchars($candidatesState) ?>">
                                    <button type="submit" class="btn btn-accent" style="padding: 8px 12px; font-size: 0.85rem;">➕ DB</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="action" value="import_queue">
                                    <input type="hidden" name="candidate_key" value="<?= htmlspecialchars($candidateKey) ?>">
                                    <input type="hidden" name="candidates_state" value="<?= htmlspecialchars($candidatesState) ?>">
                                    <button type="submit" class="btn btn-dark" style="padding: 8px 12px; font-size: 0.85rem;">🎧 Queue</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('select-all-candidates');
                const checkboxes = document.querySelectorAll('.candidate-checkbox');

                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        checkboxes.forEach(cb => cb.checked = selectAll.checked);
                    });
                }
            });
        </script>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>