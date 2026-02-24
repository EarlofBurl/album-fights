<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';

$lastfm_user = $_SESSION['lastfm_user'] ?? '';
$candidates = [];
$message = '';
global $APP_SETTINGS;

$min_plays = $APP_SETTINGS['import_min_plays'];

// ==========================================
// HANDLE ACTIONS
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // --- NEW: SYNC PLAYCOUNTS ---
        if ($action === 'sync_playcounts') {
            $_SESSION['lastfm_user'] = trim($_POST['username']);
            $lastfm_user = $_SESSION['lastfm_user'];
            
            if (!empty($lastfm_user) && !empty(LASTFM_API_KEY)) {
                // Fetch up to 1000 top albums to get the most recent playcounts in one massive batch
                $url = "http://ws.audioscrobbler.com/2.0/?method=user.gettopalbums&user=" . urlencode($lastfm_user) . "&api_key=" . LASTFM_API_KEY . "&format=json&limit=1000";
                $response = @file_get_contents($url);
                
                if ($response) {
                    $data = json_decode($response, true);
                    if (isset($data['topalbums']['album'])) {
                        // Create a fast lookup dictionary from the Last.fm data
                        $livePlaycounts = [];
                        foreach ($data['topalbums']['album'] as $alb) {
                            $key = strtolower(trim($alb['artist']['name']) . '_' . trim($alb['name']));
                            $livePlaycounts[$key] = (int)$alb['playcount'];
                        }
                        
                        $updates = 0;
                        
                        // Update Main DB
                        $eloData = loadCsv(FILE_ELO);
                        foreach ($eloData as &$row) {
                            $key = strtolower(trim($row['Artist']) . '_' . trim($row['Album']));
                            if (isset($livePlaycounts[$key]) && $livePlaycounts[$key] > $row['Playcount']) {
                                $row['Playcount'] = $livePlaycounts[$key];
                                $updates++;
                            }
                        }
                        saveCsv(FILE_ELO, $eloData);
                        
                        // Update Queue
                        $queueData = loadCsv(FILE_QUEUE);
                        foreach ($queueData as &$row) {
                            $key = strtolower(trim($row['Artist']) . '_' . trim($row['Album']));
                            if (isset($livePlaycounts[$key]) && $livePlaycounts[$key] > $row['Playcount']) {
                                $row['Playcount'] = $livePlaycounts[$key];
                                $updates++;
                            }
                        }
                        saveCsv(FILE_QUEUE, $queueData);
                        
                        $message = "🔄 Sync complete! Updated the playcounts for <strong>$updates</strong> albums.";
                    } else {
                        $message = "❌ Could not parse Last.fm data.";
                    }
                } else {
                    $message = "❌ Error connecting to Last.fm API.";
                }
            } else {
                $message = "❌ Please provide a username and ensure your Last.fm API key is set.";
            }
        }
        // --- END SYNC PLAYCOUNTS ---
        
        elseif ($action === 'set_user') {
            $_SESSION['lastfm_user'] = trim($_POST['username']);
            $lastfm_user = $_SESSION['lastfm_user'];
            
            if (!empty($lastfm_user) && !empty(LASTFM_API_KEY)) {
                // 1. Build a lookup array of all albums currently in the database or queue
                $existingAlbums = [];
                $eloData = loadCsv(FILE_ELO);
                $queueData = loadCsv(FILE_QUEUE);
                $allCurrentData = array_merge($eloData, $queueData);
                
                foreach ($allCurrentData as $row) {
                    $key = strtolower(trim($row['Artist']) . '_' . trim($row['Album']));
                    $existingAlbums[$key] = true;
                }

                // 2. Fetch the last 400 scrobbles
                $recentScrobbles = [];
                for ($page = 1; $page <= 2; $page++) {
                    $url = "http://ws.audioscrobbler.com/2.0/?method=user.getrecenttracks&user=" . urlencode($lastfm_user) . "&api_key=" . LASTFM_API_KEY . "&format=json&limit=200&page=" . $page;
                    $response = @file_get_contents($url);
                    
                    if ($response) {
                        $data = json_decode($response, true);
                        if (isset($data['recenttracks']['track'])) {
                            $tracks = $data['recenttracks']['track'];
                            if (isset($tracks['name'])) $tracks = [$tracks];
                            
                            foreach ($tracks as $track) {
                                if (isset($track['@attr']['nowplaying']) && $track['@attr']['nowplaying'] === 'true') continue;
                                
                                $artist = $track['artist']['#text'] ?? $track['artist']['name'] ?? '';
                                $album = $track['album']['#text'] ?? '';
                                
                                if (!empty($artist) && !empty($album)) {
                                    $hash = $artist . '|||' . $album;
                                    if (!isset($recentScrobbles[$hash])) $recentScrobbles[$hash] = 0;
                                    $recentScrobbles[$hash]++;
                                }
                            }
                        }
                    }
                }

                // 3. Filter the aggregated scrobbles
                foreach ($recentScrobbles as $hash => $playcount) {
                    list($artist, $album) = explode('|||', $hash);
                    $dbKey = strtolower(trim($artist) . '_' . trim($album));
                    
                    if (!isset($existingAlbums[$dbKey]) && $playcount >= $min_plays) {
                        $candidates[] = [
                            'artist' => $artist,
                            'album' => $album,
                            'playcount' => $playcount
                        ];
                    }
                }
                
                usort($candidates, function($a, $b) { return $b['playcount'] <=> $a['playcount']; });
                
                if (empty($candidates)) {
                    $message = "ℹ️ Searched the last 400 scrobbles. No new albums found that meet the minimum criteria ($min_plays plays).";
                } else {
                    $message = "✅ Analyzed the last 400 scrobbles and found " . count($candidates) . " new candidates!";
                }
            } else {
                $message = "❌ Please provide a username and ensure your Last.fm API key is set in Settings.";
            }
        } 
        elseif ($action === 'import_db' || $action === 'import_queue') {
            $artist = $_POST['artist'];
            $album = $_POST['album'];
            $playcount = (int)$_POST['playcount'];
            
            if ($action === 'import_db') {
                $eloData = loadCsv(FILE_ELO);
                $eloData[] = ['Artist' => $artist, 'Album' => $album, 'Elo' => 1200, 'Duels' => 0, 'Playcount' => $playcount];
                saveCsv(FILE_ELO, $eloData);
                $message = "✅ '$album' added to the duel ranking!";
            } else {
                moveToQueue($artist, $album, 1200, 0, $playcount);
                $message = "🎧 '$album' moved to the queue!";
            }
        }
        elseif ($action === 'upload_csv') {
            if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['csv_file']['tmp_name'];
                if (($handle = fopen($fileTmpPath, "r")) !== FALSE) {
                    $header = fgetcsv($handle, 1000, ",");
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) >= 2) {
                            $playcount = isset($data[2]) ? (int)$data[2] : 1;
                            if ($playcount >= $min_plays) {
                                $candidates[] = [
                                    'artist' => trim($data[0]),
                                    'album' => trim($data[1]),
                                    'playcount' => $playcount
                                ];
                            }
                        }
                    }
                    fclose($handle);
                    $message = "📄 CSV parsed successfully! Select what to import.";
                }
            } else {
                $message = "❌ Error uploading the CSV file.";
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div style="width: 100%; max-width: 1000px; margin: 0 auto;">
    <h2 style="margin-top: 0;">📥 Import & Maintenance</h2>
    <p style="color: var(--text-muted);">
        Active Rules: Minimum <strong><?= $min_plays ?> Scrobbles</strong> required. 
        (Changeable in <a href="settings.php" style="color: var(--accent);">Settings</a>).
    </p>

    <?php if ($message): ?>
        <div style="background: #4CAF50; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-weight: bold;">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 30px; border-left: 5px solid var(--accent);">
        <h3 style="margin-top: 0; color: var(--accent);">🔄 Sync Live Playcounts</h3>
        <p style="font-size: 0.9rem; color: var(--text-muted);">This will pull your Top 1000 albums from Last.fm and update the playcounts of any matching albums already in your Duel Database and Queue. (Keeps your stats page fresh!)</p>
        <form method="POST" style="display: flex; gap: 15px;">
            <input type="hidden" name="action" value="sync_playcounts">
            <input type="text" name="username" value="<?= htmlspecialchars($lastfm_user) ?>" placeholder="Last.fm Username" style="flex: 1; padding: 10px; background: #111; color: #fff; border: 1px solid #333; border-radius: 5px;">
            <button type="submit" class="btn-small" style="background-color: var(--accent); color: #000; width: 200px; font-weight: bold;">Update Playcounts</button>
        </form>
    </div>

    <div style="display: flex; gap: 20px; margin-bottom: 30px;">
        <div style="flex: 1; background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3>🔴 Fetch from Last.fm</h3>
            <p style="font-size: 0.8rem; color: var(--text-muted);">Scans your last 400 scrobbles for albums that meet the minimum scrobble requirement and aren't already in your database.</p>
            <form method="POST">
                <input type="hidden" name="action" value="set_user">
                <input type="text" name="username" value="<?= htmlspecialchars($lastfm_user) ?>" placeholder="Last.fm Username" style="width: 100%; padding: 10px; margin-bottom: 15px; background: #111; color: #fff; border: 1px solid #333; border-radius: 5px;">
                <button type="submit" class="btn-small" style="background-color: #333; color: #fff; width: 100%;">Analyze Recent Tracks</button>
            </form>
        </div>

        <div style="flex: 1; background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3>📄 Upload CSV List</h3>
            <p style="font-size: 0.8rem; color: var(--text-muted);">Expected Format (without header): <code>Artist, Album, Playcount</code></p>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_csv">
                <input type="file" name="csv_file" accept=".csv" style="width: 100%; padding: 10px; margin-bottom: 15px; color: #fff; border: 1px solid #333; border-radius: 5px; background: #111;">
                <button type="submit" class="btn-small" style="background-color: #4CAF50; color: white; width: 100%;">Upload & Parse CSV</button>
            </form>
        </div>
    </div>

    <?php if (!empty($candidates)): ?>
        <h3 style="margin-top: 30px;">🔍 Found Candidates</h3>
        <table class="top-list" style="width: 100%; border-collapse: collapse; background: var(--card-bg); border-radius: 8px; overflow: hidden;">
            <thead>
                <tr>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border);">Artist</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border);">Album</th>
                    <th style="padding: 12px; text-align: left; border-bottom: 1px solid var(--border);">Recent Scrobbles</th>
                    <th style="padding: 12px; text-align: center; border-bottom: 1px solid var(--border);">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $album): ?>
                    <tr>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border); font-weight: bold; color: var(--accent);"><?= htmlspecialchars($album['artist']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?= htmlspecialchars($album['album']) ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border);"><?= $album['playcount'] ?></td>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border);">
                            <div style="display: flex; gap: 10px; justify-content: center;">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="import_db">
                                    <input type="hidden" name="artist" value="<?= htmlspecialchars($album['artist']) ?>">
                                    <input type="hidden" name="album" value="<?= htmlspecialchars($album['album']) ?>">
                                    <input type="hidden" name="playcount" value="<?= $album['playcount'] ?>">
                                    <button type="submit" class="btn-small" style="background-color: var(--accent); color: #000; width: 100px;">➕ To DB</button>
                                </form>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="import_queue">
                                    <input type="hidden" name="artist" value="<?= htmlspecialchars($album['artist']) ?>">
                                    <input type="hidden" name="album" value="<?= htmlspecialchars($album['album']) ?>">
                                    <input type="hidden" name="playcount" value="<?= $album['playcount'] ?>">
                                    <button type="submit" class="btn-small" style="background-color: #333; color: #fff; width: 100px;">🎧 To Queue</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>