<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';
require_once 'includes/api_manager.php';

$requestStartedAt = microtime(true);
$albumsLoadStartedAt = microtime(true);
$albums = loadCsv(FILE_ELO);
$albumsLoadMs = round((microtime(true) - $albumsLoadStartedAt) * 1000, 2);
$review_text = "";

// Dynamischer K-Faktor (Angepasst an deine aktuelle DB-Verteilung)
function getKFactor($elo, $duels) {
    if ($duels < 10) return 40;     // Volatilität für neue Alben (schnelle Einordnung)
    if ($elo >= 1350) return 16;    // Olymp (Top 10 Bereich bei dir): Sehr stabil, schützt vor harten Abstürzen
    if ($elo >= 1280) return 24;    // Top-Tier (Erweitertes Spitzenfeld): Schwerere Aufstiege
    return 32;                      // Standard fürs Mittelfeld
}

// Visuelle Klassen für die Top-Ränge
function getRankClass($rank) {
    if ($rank === 1) return 'tier-platinum';
    if ($rank === 2) return 'tier-gold';
    if ($rank === 3) return 'tier-bronze';
    if ($rank <= 10) return 'tier-top10';
    if ($rank <= 25) return 'tier-top25';
    return '';
}

function renderTracklist($info) {
    $tracks = $info['tracks'] ?? [];
    if (!is_array($tracks) || empty($tracks)) {
        return;
    }

    echo '<details style="margin: 12px 0; text-align: left;">';
    echo '<summary style="cursor: pointer; color: var(--accent); font-size: 0.85rem; font-weight: bold;">🎵 Show tracklist (' . count($tracks) . ')</summary>';
    echo '<ol style="margin: 8px 0 0 18px; padding-right: 8px; max-height: 140px; overflow-y: auto; color: var(--text-muted); font-size: 0.8rem;">';
    foreach ($tracks as $track) {
        echo '<li style="margin-bottom: 4px;">' . htmlspecialchars((string)$track) . '</li>';
    }
    echo '</ol>';
    echo '</details>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $idxA = isset($_POST['idxA']) ? (int)$_POST['idxA'] : null;
    $idxB = isset($_POST['idxB']) ? (int)$_POST['idxB'] : null;
    $shouldResetCurrentDuel = true;

    if ($action === 'reload_metadata') {
        $targetIdx = (int)($_POST['targetIdx'] ?? -1);
        if (isset($albums[$targetIdx])) {
            getAlbumData($albums[$targetIdx]['Artist'], $albums[$targetIdx]['Album'], true);
        }
        $shouldResetCurrentDuel = false;
    } elseif ($action === 'vote') {
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

            // Individueller K-Faktor wird für beide Seiten berechnet
            $kFactorA = getKFactor($albumA['Elo'], $albumA['Duels']);
            $kFactorB = getKFactor($albumB['Elo'], $albumB['Duels']);

            $expectedA = 1 / (1 + pow(10, (($albumB['Elo'] - $albumA['Elo']) / 400)));
            $newRatingA = $albumA['Elo'] + $kFactorA * ($scoreA - $expectedA);
            
            $expectedB = 1 / (1 + pow(10, (($albumA['Elo'] - $albumB['Elo']) / 400)));
            $newRatingB = $albumB['Elo'] + $kFactorB * ((1 - $scoreA) - $expectedB);
            
            $albums[$idxA]['Elo'] = $newRatingA;
            $albums[$idxB]['Elo'] = $newRatingB;
            $albums[$idxA]['Duels']++;
            $albums[$idxB]['Duels']++;

            if ($scoreA == 1) {
                $albums[$idxA]['Wins'] = (int)($albums[$idxA]['Wins'] ?? 0) + 1;
                $albums[$idxB]['Losses'] = (int)($albums[$idxB]['Losses'] ?? 0) + 1;
            } elseif ($scoreA == 0) {
                $albums[$idxB]['Wins'] = (int)($albums[$idxB]['Wins'] ?? 0) + 1;
                $albums[$idxA]['Losses'] = (int)($albums[$idxA]['Losses'] ?? 0) + 1;
            }
            
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
                moveToQueue($albums[$targetIdx]['Artist'], $albums[$targetIdx]['Album'], $albums[$targetIdx]['Elo'], $albums[$targetIdx]['Duels'], $albums[$targetIdx]['Playcount'], $albums[$targetIdx]['Wins'] ?? 0, $albums[$targetIdx]['Losses'] ?? 0);
            }
            array_splice($albums, $targetIdx, 1);
            saveCsv(FILE_ELO, $albums);
        }
        
        if (isset($_POST['survivorArtist'], $_POST['survivorAlbum'])) {
            $_SESSION['keep_artist'] = $_POST['survivorArtist'];
            $_SESSION['keep_album'] = $_POST['survivorAlbum'];
        }
    }
    
    // Clear the current session duel to force a new draw (except metadata reload)
    if ($shouldResetCurrentDuel) {
        unset($_SESSION['current_duel']);
    }

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
    $matchmakingStartedAt = microtime(true);
    $defaultWeights = [
        'top_25_vs' => 20,
        'top_50_vs' => 20,
        'top_100_vs' => 20,
        'playcount_gt_20' => 15,
        'duel_counter_zero' => 15,
        'random' => 10
    ];
    $categoryWeights = array_merge($defaultWeights, $APP_SETTINGS['duel_category_weights'] ?? []);

    $buildRankedSubset = function (int $start, int $end) use ($albums, $total): array {
        if ($total <= $start + 1) {
            return [];
        }

        $ranked = [];
        foreach ($albums as $k => $album) {
            $album['_OriginalIndex'] = $k;
            $ranked[] = $album;
        }

        usort($ranked, function($a, $b) { return $b['Elo'] <=> $a['Elo']; });
        $actualEnd = min($end, $total - 1);
        if ($actualEnd <= $start) {
            return [];
        }

        return array_slice($ranked, $start, $actualEnd - $start + 1);
    };

    $pickLeastDueledPair = function (array $subset): ?array {
        if (count($subset) < 2) {
            return null;
        }

        usort($subset, function($a, $b) {
            if ($a['Duels'] === $b['Duels']) {
                return $b['Elo'] <=> $a['Elo'];
            }
            return $a['Duels'] <=> $b['Duels'];
        });

        return [$subset[0]['_OriginalIndex'], $subset[1]['_OriginalIndex']];
    };

    $getRandomPair = function () use ($albums): array {
        $a = array_rand($albums);
        do {
            $b = array_rand($albums);
        } while ($a === $b);
        return [$a, $b];
    };

    if (isset($_SESSION['keep_artist'], $_SESSION['keep_album'])) {
        foreach ($albums as $i => $alb) {
            if ($alb['Artist'] === $_SESSION['keep_artist'] && $alb['Album'] === $_SESSION['keep_album']) {
                $idxA = $i;
                break;
            }
        }
        unset($_SESSION['keep_artist'], $_SESSION['keep_album']);
        unset($_SESSION['current_duel']);
    }

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
        $matchmakers = [
            'top_25_vs' => function () use ($buildRankedSubset, $pickLeastDueledPair) {
                return $pickLeastDueledPair($buildRankedSubset(0, 24));
            },
            'top_50_vs' => function () use ($buildRankedSubset, $pickLeastDueledPair) {
                return $pickLeastDueledPair($buildRankedSubset(25, 49));
            },
            'top_100_vs' => function () use ($buildRankedSubset, $pickLeastDueledPair) {
                return $pickLeastDueledPair($buildRankedSubset(50, 99));
            },
            'playcount_gt_20' => function () use ($albums, $pickLeastDueledPair) {
                $subset = [];
                foreach ($albums as $k => $album) {
                    if ((int)$album['Playcount'] > 20) {
                        $album['_OriginalIndex'] = $k;
                        $subset[] = $album;
                    }
                }
                return $pickLeastDueledPair($subset);
            },
            'duel_counter_zero' => function () use ($albums, $pickLeastDueledPair) {
                $subset = [];
                foreach ($albums as $k => $album) {
                    if ((int)$album['Duels'] === 0) {
                        $album['_OriginalIndex'] = $k;
                        $subset[] = $album;
                    }
                }
                return $pickLeastDueledPair($subset);
            },
            'random' => $getRandomPair
        ];

        $weightedPool = [];
        foreach ($matchmakers as $category => $picker) {
            $weight = max(0, (int)($categoryWeights[$category] ?? 0));
            if ($weight <= 0) {
                continue;
            }

            $candidate = $picker();
            if ($candidate !== null) {
                $weightedPool[] = [
                    'category' => $category,
                    'weight' => $weight,
                    'pair' => $candidate
                ];
            }
        }

        if (!empty($weightedPool)) {
            $weightSum = array_sum(array_column($weightedPool, 'weight'));
            $pickValue = random_int(1, max(1, $weightSum));
            $rolling = 0;
            foreach ($weightedPool as $entry) {
                $rolling += $entry['weight'];
                if ($pickValue <= $rolling) {
                    [$idxA, $idxB] = $entry['pair'];
                    break;
                }
            }
        }

        if ($idxA === null || $idxB === null) {
            [$idxA, $idxB] = $getRandomPair();
        }

        $_SESSION['current_duel'] = ['idxA' => $idxA, 'idxB' => $idxB];
    }

    $matchmakingMs = round((microtime(true) - $matchmakingStartedAt) * 1000, 2);

    $albumA = $albums[$idxA];
    $albumB = $albums[$idxB];
    $albumA['OriginalIndex'] = $idxA;
    $albumB['OriginalIndex'] = $idxB;
    $albumA['Rank'] = $rankByIndex[$idxA] ?? null;
    $albumB['Rank'] = $rankByIndex[$idxB] ?? null;

    $infoAStartedAt = microtime(true);
    $infoA = getAlbumData($albumA['Artist'], $albumA['Album']);
    $infoAMs = round((microtime(true) - $infoAStartedAt) * 1000, 2);

    $infoBStartedAt = microtime(true);
    $infoB = getAlbumData($albumB['Artist'], $albumB['Album']);
    $infoBMs = round((microtime(true) - $infoBStartedAt) * 1000, 2);
}

devPerfLog('duel.request', [
    'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
    'album_count' => $total,
    'albums_load_ms' => $albumsLoadMs,
    'matchmaking_ms' => $matchmakingMs ?? null,
    'album_a_data_ms' => $infoAMs ?? null,
    'album_b_data_ms' => $infoBMs ?? null,
    'total_ms' => round((microtime(true) - $requestStartedAt) * 1000, 2)
]);

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
        <div class="duel-card <?= getRankClass($albumA['Rank']) ?>">
            <div class="duel-rank-badge">#<?= $albumA['Rank'] ?></div>
            <form method="POST" style="position: absolute; top: 10px; right: 10px; margin: 0;">
                <input type="hidden" name="action" value="reload_metadata">
                <input type="hidden" name="targetIdx" value="<?= $albumA['OriginalIndex'] ?>">
                <button type="submit" class="btn-reload-metadata" title="Refetch metadata" aria-label="Refetch metadata">↻</button>
            </form>
            <h2 class="artist-name"><a href="<?= htmlspecialchars(getArtistExternalUrl($albumA['Artist'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($albumA['Artist']) ?></a></h2>
            <h3 style="margin-top: 0; margin-bottom: 5px;"><a href="<?= htmlspecialchars(getAlbumExternalUrl($albumA['Artist'], $albumA['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($albumA['Album']) ?></a></h3>
            
            <div style="font-size: 0.8rem; margin-bottom: 10px; font-weight: bold; letter-spacing: 0.5px; color: var(--accent);">
                <?= !empty($infoA['year']) ? htmlspecialchars($infoA['year']) . ' • ' : '' ?>
                <?= !empty($infoA['genres']) ? htmlspecialchars(implode(' • ', array_slice($infoA['genres'], 0, 4))) : 'No genres found' ?>
            </div>
            
            <p style="font-size: 0.9rem; margin-top: 0; color: var(--text-muted);">Elo: <?= round($albumA['Elo']) ?> | Plays: <?= $albumA['Playcount'] ?> | W/L: <?= (int)($albumA['Wins'] ?? 0) ?>/<?= (int)($albumA['Losses'] ?? 0) ?> (<?= htmlspecialchars(calculateWinLossRatio($albumA['Wins'] ?? 0, $albumA['Losses'] ?? 0)) ?>)</p>
            
            <div style="flex-grow: 1; display: flex; align-items: center; justify-content: center; margin: 15px 0;">
                <?php if (!empty($infoA['local_image'])): ?>
                    <a href="<?= htmlspecialchars(getAlbumExternalUrl($albumA['Artist'], $albumA['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block;">
                        <img src="<?= htmlspecialchars($infoA['local_image']) ?>" alt="Cover" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                    </a>
                <?php else: ?>
                    <div style="width: 250px; height: 250px; background: #333; display: flex; align-items: center; justify-content: center; border-radius: 8px;">No Image</div>
                <?php endif; ?>
            </div>

            <p style="font-size: 0.85rem; color: var(--text-muted); text-align: justify; height: 100px; overflow-y: auto; padding-right: 10px;">
                <?= htmlspecialchars($infoA['summary']) ?>
            </p>

            <?php renderTracklist($infoA); ?>

            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="action" value="vote">
                <input type="hidden" name="idxA" value="<?= $albumA['OriginalIndex'] ?>">
                <input type="hidden" name="idxB" value="<?= $albumB['OriginalIndex'] ?>">
                <input type="hidden" name="scoreA" value="1">
                <button type="submit" class="btn-vote">
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

        <div class="duel-card <?= getRankClass($albumB['Rank']) ?>">
            <div class="duel-rank-badge">#<?= $albumB['Rank'] ?></div>
            <form method="POST" style="position: absolute; top: 10px; right: 10px; margin: 0;">
                <input type="hidden" name="action" value="reload_metadata">
                <input type="hidden" name="targetIdx" value="<?= $albumB['OriginalIndex'] ?>">
                <button type="submit" class="btn-reload-metadata" title="Refetch metadata" aria-label="Refetch metadata">↻</button>
            </form>
            <h2 class="artist-name"><a href="<?= htmlspecialchars(getArtistExternalUrl($albumB['Artist'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($albumB['Artist']) ?></a></h2>
            <h3 style="margin-top: 0; margin-bottom: 5px;"><a href="<?= htmlspecialchars(getAlbumExternalUrl($albumB['Artist'], $albumB['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($albumB['Album']) ?></a></h3>
            
            <div style="font-size: 0.8rem; margin-bottom: 10px; font-weight: bold; letter-spacing: 0.5px; color: var(--accent);">
                <?= !empty($infoB['year']) ? htmlspecialchars($infoB['year']) . ' • ' : '' ?>
                <?= !empty($infoB['genres']) ? htmlspecialchars(implode(' • ', array_slice($infoB['genres'], 0, 4))) : 'No genres found' ?>
            </div>

            <p style="font-size: 0.9rem; margin-top: 0; color: var(--text-muted);">Elo: <?= round($albumB['Elo']) ?> | Plays: <?= $albumB['Playcount'] ?> | W/L: <?= (int)($albumB['Wins'] ?? 0) ?>/<?= (int)($albumB['Losses'] ?? 0) ?> (<?= htmlspecialchars(calculateWinLossRatio($albumB['Wins'] ?? 0, $albumB['Losses'] ?? 0)) ?>)</p>
            
            <div style="flex-grow: 1; display: flex; align-items: center; justify-content: center; margin: 15px 0;">
                <?php if (!empty($infoB['local_image'])): ?>
                    <a href="<?= htmlspecialchars(getAlbumExternalUrl($albumB['Artist'], $albumB['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block;">
                        <img src="<?= htmlspecialchars($infoB['local_image']) ?>" alt="Cover" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                    </a>
                <?php else: ?>
                    <div style="width: 250px; height: 250px; background: #333; display: flex; align-items: center; justify-content: center; border-radius: 8px;">No Image</div>
                <?php endif; ?>
            </div>

            <p style="font-size: 0.85rem; color: var(--text-muted); text-align: justify; height: 100px; overflow-y: auto; padding-right: 10px;">
                <?= htmlspecialchars($infoB['summary']) ?>
            </p>

            <?php renderTracklist($infoB); ?>

            <form method="POST" style="margin-top: 15px;">
                <input type="hidden" name="action" value="vote">
                <input type="hidden" name="idxA" value="<?= $albumA['OriginalIndex'] ?>">
                <input type="hidden" name="idxB" value="<?= $albumB['OriginalIndex'] ?>">
                <input type="hidden" name="scoreA" value="0">
                <button type="submit" class="btn-vote">
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
        <thead><tr><th class="rank-col">#</th><th>Artist</th><th>Album</th><th>Elo</th><th>W/L Ratio</th></tr></thead>
        <tbody>
            <?php foreach ($top20 as $index => $album): ?>
                <tr>
                    <td class="rank-col"><?= $index + 1 ?></td>
                    <td style="color: var(--accent); font-weight: bold;"><a href="<?= htmlspecialchars(getArtistExternalUrl($album['Artist'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($album['Artist']) ?></a></td>
                    <td><a href="<?= htmlspecialchars(getAlbumExternalUrl($album['Artist'], $album['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($album['Album']) ?></a></td>
                    <td style="color: var(--text-muted); font-size: 0.9rem;"><?= round($album['Elo']) ?></td>
                    <td style="color: var(--text-muted); font-size: 0.9rem;"><?= (int)($album['Wins'] ?? 0) ?>/<?= (int)($album['Losses'] ?? 0) ?> (<?= htmlspecialchars(calculateWinLossRatio($album['Wins'] ?? 0, $album['Losses'] ?? 0)) ?>)</td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
