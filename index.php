<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';
require_once 'includes/api_manager.php';

$albums = loadCsv(FILE_ELO);
$review_text = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $idxA = isset($_POST['idxA']) ? (int)$_POST['idxA'] : null;
    $idxB = isset($_POST['idxB']) ? (int)$_POST['idxB'] : null;

    if ($action === 'vote') {
        if (isset($albums[$idxA], $albums[$idxB])) {
            unset($_SESSION['keep_artist'], $_SESSION['keep_album']);
            $scoreA = (float)$_POST['scoreA'];
            $albumA = $albums[$idxA];
            $albumB = $albums[$idxB];

            // Track recent picks for the 25-duel comment
            if ($scoreA == 1) {
                $_SESSION['recent_picks'][] = $albumA['Artist'] . " - " . $albumA['Album'];
            } elseif ($scoreA == 0) {
                $_SESSION['recent_picks'][] = $albumB['Artist'] . " - " . $albumB['Album'];
            } else {
                $_SESSION['recent_picks'][] = "[Draw] " . $albumA['Artist'] . " AND " . $albumB['Artist'];
            }
            if (count($_SESSION['recent_picks']) > 25) array_shift($_SESSION['recent_picks']);

            $expectedA = 1 / (1 + pow(10, (($albumB['Elo'] - $albumA['Elo']) / 400)));
            $newRatingA = $albumA['Elo'] + 32 * ($scoreA - $expectedA);
            $expectedB = 1 / (1 + pow(10, (($albumA['Elo'] - $albumB['Elo']) / 400)));
            $newRatingB = $albumB['Elo'] + 32 * ((1 - $scoreA) - $expectedB);
            
            $albums[$idxA]['Elo'] = $newRatingA;
            $albums[$idxB]['Elo'] = $newRatingB;
            $albums[$idxA]['Duels']++;
            $albums[$idxB]['Duels']++;
            
            saveCsv(FILE_ELO, $albums);
            
            $_SESSION['duel_count']++;
            
            // AI Nerd Comment Trigger (Every 25 Duels) based on recent picks
            if ($_SESSION['duel_count'] > 0 && $_SESSION['duel_count'] % 25 === 0) {
                $picksText = implode(", ", $_SESSION['recent_picks']);
                $review_text = triggerNerdComment($picksText);
                $_SESSION['recent_picks'] = []; // Reset after review
            }
        }
    } elseif ($action === 'queue' || $action === 'delete') {
        $targetIdx = (int)$_POST['targetIdx'];
        if (isset($albums[$targetIdx])) {
            if ($action === 'queue') {
                moveToQueue($albums[$targetIdx]['Artist'], $albums[$targetIdx]['Album'], $albums[$targetIdx]['Elo'], $albums[$targetIdx]['Duels'], $albums[$targetIdx]['Playcount']);
            }
            array_splice($albums, $targetIdx, 1);
            saveCsv(FILE_ELO, $albums);
        }
        
        if (isset($_POST['survivorArtist'], $_POST['survivorAlbum'])) {
            $_SESSION['keep_artist'] = $_POST['survivorArtist'];
            $_SESSION['keep_album'] = $_POST['survivorAlbum'];
        }
    }
    
    // Clear the current session duel to force a new draw
    unset($_SESSION['current_duel']);

    // Redirect to prevent form resubmission unless we have a review text to show
    if (empty($review_text)) {
        header("Location: index.php");
        exit;
    }
}

// Select albums for duel
$idxA = null;
$idxB = null;
$total = count($albums);
$rankByIndex = [];

if ($total > 0) {
    $rankedIndexes = array_keys($albums);
    usort($rankedIndexes, function($a, $b) use ($albums) {
        return $albums[$b]['Elo'] <=> $albums[$a]['Elo'];
    });

    foreach ($rankedIndexes as $position => $originalIndex) {
        $rankByIndex[$originalIndex] = $position + 1;
    }
}

if ($total >= 2) {
    if (isset($_SESSION['keep_artist'], $_SESSION['keep_album'])) {
        foreach ($albums as $i => $alb) {
            if ($alb['Artist'] === $_SESSION['keep_artist'] && $alb['Album'] === $_SESSION['keep_album']) {
                $idxA = $i;
                break;
            }
        }
        unset($_SESSION['keep_artist'], $_SESSION['keep_album']);
        unset($_SESSION['current_duel']); // Force new pairing with the survivor
    }

    // Load persistent duel if available
    if (isset($_SESSION['current_duel']) && $idxA === null) {
        $savedA = $_SESSION['current_duel']['idxA'];
        $savedB = $_SESSION['current_duel']['idxB'];
        if (isset($albums[$savedA]) && isset($albums[$savedB])) {
            $idxA = $savedA;
            $idxB = $savedB;
        } else {
            unset($_SESSION['current_duel']);
        }
    }

    if ($idxA === null || $idxB === null) {
        
        // --- TIERED MATCHMAKING LOGIC ---
        $cyclePos = $_SESSION['duel_count'] % 25;
        $isTierDuel = false;
        $tStart = 0; $tEnd = 0;
        
        // Define when to trigger special high-up duels
        if ($cyclePos === 5)       { $tStart = 0;  $tEnd = 19; $isTierDuel = true; } // Top 20
        elseif ($cyclePos === 10)  { $tStart = 20; $tEnd = 49; $isTierDuel = true; } // 21 - 50
        elseif ($cyclePos === 15)  { $tStart = 50; $tEnd = 99; $isTierDuel = true; } // 51 - 100
        
        // Only trigger if we are doing a completely fresh draw (no survivor being carried over)
        if ($isTierDuel && $idxA === null && $total > $tStart + 1) {
            $ranked = $albums;
            foreach ($ranked as $k => $v) { $ranked[$k]['_OriginalIndex'] = $k; }
            
            usort($ranked, function($a, $b) { return $b['Elo'] <=> $a['Elo']; });
            
            $actualEnd = min($tEnd, $total - 1);
            $tier = array_slice($ranked, $tStart, $actualEnd - $tStart + 1);
            
            usort($tier, function($a, $b) { return $a['Duels'] <=> $b['Duels']; });
            
            if (count($tier) >= 2) {
                $idxA = $tier[0]['_OriginalIndex'];
                $idxB = $tier[1]['_OriginalIndex'];
            }
        }
        // --- END TIERED MATCHMAKING ---

        // Standard randomizer
        if ($idxA === null) {
            $idxA = array_rand($albums);
        }
        if ($idxB === null) {
            do {
                $idxB = array_rand($albums);
            } while ($idxA === $idxB);
        }
        
        // Save new duel to session for persistency
        $_SESSION['current_duel'] = ['idxA' => $idxA, 'idxB' => $idxB];
    }

    $albumA = $albums[$idxA];
    $albumB = $albums[$idxB];
    $albumA['OriginalIndex'] = $idxA;
    $albumB['OriginalIndex'] = $idxB;
    $albumA['Rank'] = $rankByIndex[$idxA] ?? null;
    $albumB['Rank'] = $rankByIndex[$idxB] ?? null;

    $infoA = getAlbumData($albumA['Artist'], $albumA['Album']);
    $infoB = getAlbumData($albumB['Artist'], $albumB['Album']);
}

require_once 'includes/header.php';
?>

<?php if (!empty($review_text)): ?>
    <div style="width: 100%; max-width: 800px; background: #2c2c2c; padding: 30px; border-radius: 12px; margin-bottom: 30px; border-left: 5px solid var(--accent);">
        <h2 style="margin-top: 0; color: var(--accent);">🤖 The Nerd Speaks! (25 Duels Milestone)</h2>
        <div style="line-height: 1.6; font-size: 1.1rem;">
            <?= nl2br(htmlspecialchars($review_text)) ?>
        </div>
        <form method="GET" action="index.php" style="margin-top: 20px;">
            <button type="submit" class="btn-small" style="background-color: var(--accent); color: #000; font-size: 1.1rem; padding: 10px 20px;">
                Continue Dueling ⚔️
            </button>
        </form>
    </div>
<?php endif; ?>

<?php if (empty($review_text) && $total >= 2): ?>
    <div class="duel-container">
        <div style="flex: 1; background: var(--card-bg); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid var(--border); display: flex; flex-direction: column;">
            <div class="duel-rank-badge">#<?= $albumA['Rank'] ?></div>
            <h2 style="color: var(--accent); margin-top: 0;"><?= htmlspecialchars($albumA['Artist']) ?></h2>
            <h3 style="margin-top: 0; margin-bottom: 5px;"><?= htmlspecialchars($albumA['Album']) ?></h3>
            
            <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 10px; font-weight: bold; letter-spacing: 0.5px;">
                <?= !empty($infoA['year']) ? htmlspecialchars($infoA['year']) . ' • ' : '' ?>
                <?= !empty($infoA['genres']) ? htmlspecialchars(implode(' • ', array_slice($infoA['genres'], 0, 4))) : 'No genres found' ?>
            </div>
            
            <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0;">Elo: <?= round($albumA['Elo']) ?> | Plays: <?= $albumA['Playcount'] ?></p>
            
            <div style="flex-grow: 1; display: flex; align-items: center; justify-content: center; margin: 15px 0;">
                <?php if (!empty($infoA['local_image'])): ?>
                    <img src="<?= htmlspecialchars($infoA['local_image']) ?>" alt="Cover" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                <?php else: ?>
                    <div style="width: 250px; height: 250px; background: #333; display: flex; align-items: center; justify-content: center; border-radius: 8px;">No Image</div>
                <?php endif; ?>
            </div>

            <p style="font-size: 0.85rem; color: var(--text-muted); text-align: justify; height: 100px; overflow-y: auto; padding-right: 10px;">
                <?= htmlspecialchars($infoA['summary']) ?>
            </p>

            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="action" value="vote">
                <input type="hidden" name="idxA" value="<?= $albumA['OriginalIndex'] ?>">
                <input type="hidden" name="idxB" value="<?= $albumB['OriginalIndex'] ?>">
                <input type="hidden" name="scoreA" value="1">
                <button type="submit" style="width: 100%; padding: 15px; font-size: 1.2rem; background-color: var(--accent); color: #000; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin-bottom: 10px;">
                    🏆 Choose A
                </button>
            </form>

            <div style="display: flex; gap: 10px;">
                <form method="POST" style="flex:1;">
                    <input type="hidden" name="action" value="queue">
                    <input type="hidden" name="targetIdx" value="<?= $albumA['OriginalIndex'] ?>">
                    <input type="hidden" name="survivorArtist" value="<?= htmlspecialchars($albumB['Artist']) ?>">
                    <input type="hidden" name="survivorAlbum" value="<?= htmlspecialchars($albumB['Album']) ?>">
                    <button type="submit" class="btn-small btn-queue" style="width: 100%;">🎧 To Queue</button>
                </form>
                <form method="POST" style="flex:1;" onsubmit="return confirm('Do you want to delete this album forever?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="targetIdx" value="<?= $albumA['OriginalIndex'] ?>">
                    <input type="hidden" name="survivorArtist" value="<?= htmlspecialchars($albumB['Artist']) ?>">
                    <input type="hidden" name="survivorAlbum" value="<?= htmlspecialchars($albumB['Album']) ?>">
                    <button type="submit" class="btn-small btn-delete" style="width: 100%;">🗑️ Delete</button>
                </form>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 20px;">
            <div style="background: #111; color: var(--text-muted); padding: 15px; border-radius: 50%; font-weight: bold; border: 2px solid var(--border);">VS</div>
            <form method="POST">
                <input type="hidden" name="action" value="vote">
                <input type="hidden" name="idxA" value="<?= $albumA['OriginalIndex'] ?>">
                <input type="hidden" name="idxB" value="<?= $albumB['OriginalIndex'] ?>">
                <input type="hidden" name="scoreA" value="0.5">
                <button type="submit" style="background-color: var(--warning); color: #000; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer;">
                    🤝 Draw
                </button>
            </form>
        </div>

        <div style="flex: 1; background: var(--card-bg); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid var(--border); display: flex; flex-direction: column;">
            <div class="duel-rank-badge">#<?= $albumB['Rank'] ?></div>
            <h2 style="color: var(--accent); margin-top: 0;"><?= htmlspecialchars($albumB['Artist']) ?></h2>
            <h3 style="margin-top: 0; margin-bottom: 5px;"><?= htmlspecialchars($albumB['Album']) ?></h3>
            
            <div style="font-size: 0.8rem; color: var(--accent); margin-bottom: 10px; font-weight: bold; letter-spacing: 0.5px;">
                <?= !empty($infoB['year']) ? htmlspecialchars($infoB['year']) . ' • ' : '' ?>
                <?= !empty($infoB['genres']) ? htmlspecialchars(implode(' • ', array_slice($infoB['genres'], 0, 4))) : 'No genres found' ?>
            </div>

            <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0;">Elo: <?= round($albumB['Elo']) ?> | Plays: <?= $albumB['Playcount'] ?></p>
            
            <div style="flex-grow: 1; display: flex; align-items: center; justify-content: center; margin: 15px 0;">
                <?php if (!empty($infoB['local_image'])): ?>
                    <img src="<?= htmlspecialchars($infoB['local_image']) ?>" alt="Cover" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                <?php else: ?>
                    <div style="width: 250px; height: 250px; background: #333; display: flex; align-items: center; justify-content: center; border-radius: 8px;">No Image</div>
                <?php endif; ?>
            </div>

            <p style="font-size: 0.85rem; color: var(--text-muted); text-align: justify; height: 100px; overflow-y: auto; padding-right: 10px;">
                <?= htmlspecialchars($infoB['summary']) ?>
            </p>

            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="action" value="vote">
                <input type="hidden" name="idxA" value="<?= $albumA['OriginalIndex'] ?>">
                <input type="hidden" name="idxB" value="<?= $albumB['OriginalIndex'] ?>">
                <input type="hidden" name="scoreA" value="0">
                <button type="submit" style="width: 100%; padding: 15px; font-size: 1.2rem; background-color: var(--accent); color: #000; border: none; border-radius: 8px; cursor: pointer; font-weight: bold; margin-bottom: 10px;">
                    🏆 Choose B
                </button>
            </form>

            <div style="display: flex; gap: 10px;">
                <form method="POST" style="flex:1;">
                    <input type="hidden" name="action" value="queue">
                    <input type="hidden" name="targetIdx" value="<?= $albumB['OriginalIndex'] ?>">
                    <input type="hidden" name="survivorArtist" value="<?= htmlspecialchars($albumA['Artist']) ?>">
                    <input type="hidden" name="survivorAlbum" value="<?= htmlspecialchars($albumA['Album']) ?>">
                    <button type="submit" class="btn-small btn-queue" style="width: 100%;">🎧 To Queue</button>
                </form>
                <form method="POST" style="flex:1;" onsubmit="return confirm('Do you want to delete this album forever?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="targetIdx" value="<?= $albumB['OriginalIndex'] ?>">
                    <input type="hidden" name="survivorArtist" value="<?= htmlspecialchars($albumA['Artist']) ?>">
                    <input type="hidden" name="survivorAlbum" value="<?= htmlspecialchars($albumA['Album']) ?>">
                    <button type="submit" class="btn-small btn-delete" style="width: 100%;">🗑️ Delete</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php 
if (empty($review_text)): 
    $topAlbums = $albums;
    usort($topAlbums, function($a, $b) { return $b['Elo'] <=> $a['Elo']; });
    $top20 = array_slice($topAlbums, 0, 20);
?>
<div class="top-list">
    <h3>🏆 Top 20 Albums</h3>
    <table>
        <thead><tr><th class="rank-col">#</th><th>Artist</th><th>Album</th><th>Elo</th></tr></thead>
        <tbody>
            <?php foreach ($top20 as $index => $album): ?>
                <tr>
                    <td class="rank-col"><?= $index + 1 ?></td>
                    <td style="color: var(--accent); font-weight: bold;"><?= htmlspecialchars($album['Artist']) ?></td>
                    <td><?= htmlspecialchars($album['Album']) ?></td>
                    <td style="color: var(--text-muted); font-size: 0.9rem;"><?= round($album['Elo']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
