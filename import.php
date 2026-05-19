<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Config;
use App\Core\Security;
use App\Repository\AlbumRepository;
use App\Repository\SettingsRepository;
use App\Service\ImportService;

$config    = Config::get();
$albumRepo = new AlbumRepository($config);
$settings  = new SettingsRepository($config);
$import    = new ImportService($config, $albumRepo, $settings);

$message     = '';
$messageType = 'success';
$candidates  = [];
$candidateSource = '';
$lastfmUser = $_SESSION['lastfm_user'] ?? '';
$listenbrainzUser = $_SESSION['listenbrainz_user'] ?? $settings->getListenbrainzUsername();
$subsonicUser = $_SESSION['subsonic_user'] ?? $settings->get('subsonic_username', '');
$minPlays = $settings->getImportMinPlays();
$bundledCsv = __DIR__ . '/1000_best_albums.csv';

if (empty($_SESSION['listenbrainz_user']) && !empty($settings->getListenbrainzUsername())) {
    $_SESSION['listenbrainz_user'] = $settings->getListenbrainzUsername();
}
if (empty($_SESSION['subsonic_user']) && !empty($settings->get('subsonic_username', ''))) {
    $_SESSION['subsonic_user'] = $settings->get('subsonic_username', '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Security::requirePost();
    $action = Security::getString($_POST, 'action');

    if ($action === 'sync_playcounts') {
        $source   = Security::getString($_POST, 'source', 'lastfm');
        $username = Security::getString($_POST, 'username');

        if ($source === 'listenbrainz') {
            $_SESSION['listenbrainz_user'] = $username;
            $listenbrainzUser = $username;
        } elseif ($source === 'subsonic') {
            $_SESSION['subsonic_user'] = $username;
            $subsonicUser = $username;
        } else {
            $_SESSION['lastfm_user'] = $username;
            $lastfmUser = $username;
        }

        $result = $import->syncPlaycounts($source, $username);
        $sourceLabel = match ($source) {
            'listenbrainz' => 'ListenBrainz',
            'subsonic'     => 'Navidrome/Subsonic',
            default        => 'Last.fm',
        };

        if (empty($result['albums'])) {
            $message = "❌ No top-album data returned from {$sourceLabel}. Check username/API settings and try again.";
            $messageType = 'error';
        } else {
            $message = "🔄 Sync complete via {$sourceLabel}! Updated the playcounts for <strong>{$result['updates']}</strong> albums.";
        }
    }

    if ($action === 'fetch_candidates') {
        $source   = Security::getString($_POST, 'source', 'lastfm');
        $mode     = Security::getString($_POST, 'fetch_mode', 'recent');
        $username = Security::getString($_POST, 'username');
        $topLimit = Security::getInt($_POST, 'top_limit', 100);

        if ($source === 'listenbrainz') {
            $_SESSION['listenbrainz_user'] = $username;
            $listenbrainzUser = $username;
        } elseif ($source === 'subsonic') {
            $_SESSION['subsonic_user'] = $username;
            $subsonicUser = $username;
        } else {
            $_SESSION['lastfm_user'] = $username;
            $lastfmUser = $username;
        }

        $result = $import->fetchCandidates($source, $mode, $username, $topLimit);
        $candidates = $result['candidates'];
        $candidateSource = $source;

        $sourceLabel = match ($source) {
            'listenbrainz' => 'ListenBrainz',
            'subsonic'     => 'Navidrome/Subsonic',
            default        => 'Last.fm',
        };

        if (empty($candidates)) {
            if ($result['error'] !== null) {
                $message = "❌ {$result['error']['source']} Fehler: {$result['error']['message']}";
                $messageType = 'error';
            } else {
                $message = "ℹ️ Searched via {$sourceLabel}. No new albums found.";
                $messageType = 'info';
            }
        } else {
            $message = "✅ Found " . count($candidates) . " new candidates via {$sourceLabel}!";
        }
    }

    if (in_array($action, ['import_db', 'import_queue', 'import_selected_db', 'import_selected_queue', 'import_all_db', 'import_all_queue'], true)) {
        $state = $import->decodeCandidatesState(Security::getString($_POST, 'candidates_state'));
        $candidateSource = $state['source'];
        $candidates = $state['items'];

        $selectedKeys = [];
        if ($action === 'import_db' || $action === 'import_queue') {
            $key = Security::getString($_POST, 'candidate_key');
            if ($key !== '') {
                $selectedKeys[] = $key;
            }
        } elseif ($action === 'import_selected_db' || $action === 'import_selected_queue') {
            $selectedKeys = $_POST['selected_candidates'] ?? [];
        } else {
            foreach ($candidates as $c) {
                $selectedKeys[] = ImportService::getCandidateKey($c);
            }
        }

        $selectedLookup = array_fill_keys($selectedKeys, true);
        $selectedCandidates = [];
        $remainingCandidates = [];
        foreach ($candidates as $c) {
            $key = ImportService::getCandidateKey($c);
            if (isset($selectedLookup[$key])) {
                $selectedCandidates[] = $c;
            } else {
                $remainingCandidates[] = $c;
            }
        }

        $isQueue = str_contains($action, '_queue');
        $imported = $isQueue ? $import->importToQueue($selectedCandidates) : $import->importToDb($selectedCandidates);

        if (empty($selectedCandidates)) {
            $message = 'ℹ️ No albums were selected.';
            $messageType = 'info';
        } elseif ($isQueue) {
            $message = "🎧 Imported <strong>{$imported}</strong> album(s) into the queue.";
        } else {
            $message = "✅ Imported <strong>{$imported}</strong> album(s) into the duel ranking.";
        }

        $candidates = $remainingCandidates;
    }

    if ($action === 'upload_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $candidates = $import->parseCsvFile($_FILES['csv_file']['tmp_name'], $minPlays);
            $candidateSource = 'csv';
            $message = "📄 CSV preview ready: <strong>" . count($candidates) . "</strong> albums.";
        } else {
            $message = '❌ Error uploading the CSV file.';
            $messageType = 'error';
        }
    }

    if ($action === 'load_best_albums') {
        if (!file_exists($bundledCsv)) {
            $message = '❌ Could not find 1000_best_albums.csv.';
            $messageType = 'error';
        } elseif (($handle = fopen($bundledCsv, 'r')) === false) {
            $message = '❌ Could not open 1000_best_albums.csv.';
            $messageType = 'error';
        } else {
            $existing = $albumRepo->buildExistingLookup();
            $existingCandidates = [];
            $candidatePlaycount = max(1, $minPlays);
            $added = 0;
            $alreadyInDb = 0;

            while (($row = fgetcsv($handle, 2000, ',', '"', '')) !== false) {
                $artist = trim((string)($row[0] ?? ''));
                $album  = trim((string)($row[1] ?? ''));
                if ($artist === '' || $album === '') {
                    continue;
                }
                $dbKey = strtolower($artist . '_' . $album);
                if (isset($existing[$dbKey])) {
                    $alreadyInDb++;
                    continue;
                }
                $cKey = strtolower($artist . '|||' . $album);
                if (isset($existingCandidates[$cKey])) {
                    continue;
                }
                $existingCandidates[$cKey] = true;
                $candidates[] = ['artist' => $artist, 'album' => $album, 'playcount' => $candidatePlaycount];
                $added++;
            }
            fclose($handle);
            usort($candidates, fn(array $a, array $b): int => strcmp(
                strtolower(trim($a['artist']) . '|||' . trim($a['album'])),
                strtolower(trim($b['artist']) . '|||' . trim($b['album']))
            ));
            $candidateSource = 'best1000';
            if ($added === 0) {
                $message = "ℹ️ Checked bundled 1000_best_albums.csv. No new albums found.";
                $messageType = 'info';
            } else {
                $message = "📚 Bundled Top-1000 preview ready: <strong>{$added}</strong> new albums found. Skipped <strong>{$alreadyInDb}</strong> already present entries.";
            }
        }
    }
}

$importUsernameValue = $lastfmUser ?: ($listenbrainzUser ?: $subsonicUser);
$candidatesState = $import->encodeCandidatesState($candidates, $candidateSource);
$csrfField = Security::csrfField();

require __DIR__ . '/templates/import.php';
