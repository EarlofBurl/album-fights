<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';
require_once 'includes/api_manager.php';

$albums = loadCsv(FILE_ELO);

// ==========================================
// CALCULATIONS FOR STATISTICS
// ==========================================
$totalAlbums = count($albums);
$totalDuels = array_reduce($albums, function($carry, $item) { return $carry + $item['Duels']; }, 0) / 2;

// Top 10 & Flop 10
$sortedByElo = $albums;
usort($sortedByElo, function($a, $b) { return $b['Elo'] <=> $a['Elo']; });
$top10 = array_slice($sortedByElo, 0, 10);
$flop10 = array_slice(array_reverse($sortedByElo), 0, 10);

// Most Battle-Tested
$sortedByDuels = $albums;
usort($sortedByDuels, function($a, $b) { return $b['Duels'] <=> $a['Duels']; });
$veterans = array_slice($sortedByDuels, 0, 5);

// Hidden Gems
$hiddenGems = array_filter($albums, function($a) { return $a['Elo'] > 1210 && $a['Playcount'] > 0; });
usort($hiddenGems, function($a, $b) { return $a['Playcount'] <=> $b['Playcount']; });
$hiddenGems = array_slice($hiddenGems, 0, 5);

// Disappointments
$disappointments = array_filter($albums, function($a) { return $a['Playcount'] > 50 && $a['Elo'] < 1200; });
usort($disappointments, function($a, $b) { return $a['Elo'] <=> $b['Elo']; });
$disappointments = array_slice($disappointments, 0, 5);

// Top Artists (By Power Score)
$artistStats = [];
foreach ($albums as $a) {
    if (!isset($artistStats[$a['Artist']])) $artistStats[$a['Artist']] = ['elo_above_baseline' => 0, 'count' => 0];
    $artistStats[$a['Artist']]['elo_above_baseline'] += ($a['Elo'] - 1200);
    $artistStats[$a['Artist']]['count']++;
}
$topArtists = [];
foreach ($artistStats as $name => $stat) {
    if ($stat['count'] >= 2 && $stat['elo_above_baseline'] > 0) {
        $topArtists[] = ['Artist' => $name, 'PowerScore' => $stat['elo_above_baseline'], 'Count' => $stat['count']];
    }
}
usort($topArtists, function($a, $b) { return $b['PowerScore'] <=> $a['PowerScore']; });
$topArtists = array_slice($topArtists, 0, 5);


// --- NEW: GENRES & DECADES ---
$genreStats = [];
$decadeStats = [];

foreach ($albums as $a) {
    $cacheBaseName = getAlbumCacheBaseName($a['Artist'], $a['Album']);
    $jsonFile = DIR_CACHE . $cacheBaseName . '.json';

    // Backward compatibility for old cache naming scheme.
    if (!file_exists($jsonFile)) {
        $legacyName = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower("album_" . $a['Artist'] . "_" . $a['Album']));
        $legacyFile = DIR_CACHE . $legacyName . '.json';
        if (file_exists($legacyFile)) {
            $jsonFile = $legacyFile;
        }
    }

    // We only evaluate albums already in the cache (to avoid extreme loading times from APIs)
    if (file_exists($jsonFile)) {
        $info = json_decode(file_get_contents($jsonFile), true);
        
        // Aggregate genres
        if (!empty($info['genres'])) {
            foreach ($info['genres'] as $genre) {
                $g = ucwords(strtolower($genre));
                if (!isset($genreStats[$g])) $genreStats[$g] = ['elo_sum' => 0, 'count' => 0];
                $genreStats[$g]['elo_sum'] += $a['Elo'];
                $genreStats[$g]['count']++;
            }
        }
        
        // Aggregate decades
        if (!empty($info['year'])) {
            $year = (int)$info['year'];
            if ($year > 1900 && $year <= date('Y')) { // Plausibility check
                $decade = floor($year / 10) * 10 . "s";
                if (!isset($decadeStats[$decade])) $decadeStats[$decade] = ['elo_sum' => 0, 'count' => 0];
                $decadeStats[$decade]['elo_sum'] += $a['Elo'];
                $decadeStats[$decade]['count']++;
            }
        }
    }
}

// Top & Flop Genres (Min. 3 albums for relevance)
$filteredGenres = [];
foreach ($genreStats as $g => $stat) {
    if ($stat['count'] >= 3) {
        $filteredGenres[] = ['Name' => $g, 'AvgElo' => $stat['elo_sum'] / $stat['count'], 'Count' => $stat['count']];
    }
}
usort($filteredGenres, function($a, $b) { return $b['AvgElo'] <=> $a['AvgElo']; });
$bestGenres = array_slice($filteredGenres, 0, 5);
$worstGenres = array_slice(array_reverse($filteredGenres), 0, 5);

// Top & Flop Decades (Min. 2 albums for relevance)
$filteredDecades = [];
foreach ($decadeStats as $d => $stat) {
    if ($stat['count'] >= 2) {
        $filteredDecades[] = ['Name' => $d, 'AvgElo' => $stat['elo_sum'] / $stat['count'], 'Count' => $stat['count']];
    }
}
usort($filteredDecades, function($a, $b) { return $b['AvgElo'] <=> $a['AvgElo']; });
$bestDecades = array_slice($filteredDecades, 0, 5);
$worstDecades = array_slice(array_reverse($filteredDecades), 0, 5);


// ==========================================
// RENDER HTML
// ==========================================
require_once 'includes/header.php';
?>

<div style="width: 100%; max-width: 1200px; margin: 0 auto;">
    <h2 style="text-align: center; margin-bottom: 10px;">📊 The Nerd Analytics</h2>
    <p style="text-align: center; color: var(--text-muted); margin-bottom: 40px;">
        Total Albums: <strong style="color: #fff;"><?= $totalAlbums ?></strong> | Total Duels Fought: <strong style="color: #fff;"><?= floor($totalDuels) ?></strong>
    </p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">
        
        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: var(--accent); margin-top: 0;">🏆 The Olympus (Top 10)</h3>
            <ol style="padding-left: 20px; color: var(--text-muted);">
                <?php foreach ($top10 as $a): ?>
                    <li style="margin-bottom: 8px;">
                        <strong style="color: #fff;"><?= htmlspecialchars($a['Artist']) ?></strong> - <?= htmlspecialchars($a['Album']) ?> 
                        <span style="float: right; color: var(--accent);"><?= round($a['Elo']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: var(--danger); margin-top: 0;">📉 The Abyss (Flop 10)</h3>
            <ol style="padding-left: 20px; color: var(--text-muted);">
                <?php foreach ($flop10 as $a): ?>
                    <li style="margin-bottom: 8px;">
                        <strong style="color: #fff;"><?= htmlspecialchars($a['Artist']) ?></strong> - <?= htmlspecialchars($a['Album']) ?> 
                        <span style="float: right; color: var(--danger);"><?= round($a['Elo']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #ff9800; margin-top: 0;">🎸 Top Genres</h3>
            <p style="font-size: 0.8rem; color: #666;">Highest average Elo (min. 3 albums).</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($bestGenres as $g): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;">🏷️ <?= htmlspecialchars($g['Name']) ?></strong> 
                        <span style="float: right; color: #ff9800;">Ø <?= round($g['AvgElo']) ?></span><br>
                        <small><?= $g['Count'] ?> Albums</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #cf6679; margin-top: 0;">🗑️ Worst Genres</h3>
            <p style="font-size: 0.8rem; color: #666;">Lowest average Elo (min. 3 albums).</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($worstGenres as $g): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;">🏷️ <?= htmlspecialchars($g['Name']) ?></strong> 
                        <span style="float: right; color: #cf6679;">Ø <?= round($g['AvgElo']) ?></span><br>
                        <small><?= $g['Count'] ?> Albums</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #8bc34a; margin-top: 0;">📅 Best Decades</h3>
            <p style="font-size: 0.8rem; color: #666;">Highest average Elo (min. 2 albums).</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($bestDecades as $d): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars($d['Name']) ?></strong> 
                        <span style="float: right; color: #8bc34a;">Ø <?= round($d['AvgElo']) ?></span><br>
                        <small><?= $d['Count'] ?> Albums</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #cf6679; margin-top: 0;">👴 Worst Decades</h3>
            <p style="font-size: 0.8rem; color: #666;">Lowest average Elo (min. 2 albums).</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($worstDecades as $d): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars($d['Name']) ?></strong> 
                        <span style="float: right; color: #cf6679;">Ø <?= round($d['AvgElo']) ?></span><br>
                        <small><?= $d['Count'] ?> Albums</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #03A9F4; margin-top: 0;">👑 Top Artists</h3>
            <p style="font-size: 0.8rem; color: #666;">Highest Power Score (Elo above baseline).</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($topArtists as $a): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars($a['Artist']) ?></strong> 
                        <span style="float: right; color: var(--accent);">+<?= round($a['PowerScore']) ?> Power</span><br>
                        <small><?= $a['Count'] ?> Albums in DB</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #4CAF50; margin-top: 0;">💎 Hidden Gems</h3>
            <p style="font-size: 0.8rem; color: #666;">High Elo, but low Last.fm playcount.</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($hiddenGems as $a): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars($a['Artist']) ?></strong> - <?= htmlspecialchars($a['Album']) ?><br>
                        <small>Elo: <?= round($a['Elo']) ?> | Plays: <?= $a['Playcount'] ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: var(--warning); margin-top: 0;">🤡 Overplayed & Overrated</h3>
            <p style="font-size: 0.8rem; color: #666;">High playcount, but lost most duels.</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($disappointments as $a): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars($a['Artist']) ?></strong> - <?= htmlspecialchars($a['Album']) ?><br>
                        <small>Elo: <?= round($a['Elo']) ?> | Plays: <?= $a['Playcount'] ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #9C27B0; margin-top: 0;">⚔️ Battle-Hardened Veterans</h3>
            <p style="font-size: 0.8rem; color: #666;">Albums that faced the most duels.</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($veterans as $a): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars($a['Artist']) ?></strong> - <?= htmlspecialchars($a['Album']) ?><br>
                        <small><?= $a['Duels'] ?> Duels fought</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

    </div>
</div>

<?php require_once 'includes/footer.php'; ?>