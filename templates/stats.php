<?php
/**
 * @var array<string, mixed> $stats
 */
use App\Utils\CsvHelper;

require __DIR__ . '/partials/header.php';
?>

<div style="width: 100%; max-width: 1200px; margin: 0 auto;">
    <h2 style="text-align: center; margin-bottom: 10px;">📊 The Nerd Analytics</h2>
    <p style="text-align: center; color: var(--text-muted); margin-bottom: 40px;">
        Total Albums: <strong style="color: #fff;"><?= (int)$stats['totalAlbums'] ?></strong> | Total Duels Fought: <strong style="color: #fff;"><?= (int)$stats['totalDuels'] ?></strong>
    </p>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px;">

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: var(--accent); margin-top: 0;">🏆 The Olympus (Top 10)</h3>
            <ol style="padding-left: 20px; color: var(--text-muted);">
                <?php foreach ($stats['top10'] as $a): ?>
                    <li style="margin-bottom: 8px;">
                        <strong style="color: #fff;"><?= htmlspecialchars((string)$a['Artist']) ?></strong> - <?= htmlspecialchars((string)$a['Album']) ?>
                        <span style="float: right; color: var(--accent);"><?= round((float)$a['Elo']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: var(--danger); margin-top: 0;">📉 The Abyss (Flop 10)</h3>
            <ol style="padding-left: 20px; color: var(--text-muted);">
                <?php foreach ($stats['flop10'] as $a): ?>
                    <li style="margin-bottom: 8px;">
                        <strong style="color: #fff;"><?= htmlspecialchars((string)$a['Artist']) ?></strong> - <?= htmlspecialchars((string)$a['Album']) ?>
                        <span style="float: right; color: var(--danger);"><?= round((float)$a['Elo']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #ff9800; margin-top: 0;">🎸 Top Genres</h3>
            <p style="font-size: 0.8rem; color: #666;">Highest average Elo (min. 3 albums).</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($stats['bestGenres'] as $g): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;">🏷️ <?= htmlspecialchars((string)$g['Name']) ?></strong>
                        <span style="float: right; color: #ff9800;">Ø <?= round((float)$g['AvgElo']) ?></span><br>
                        <small><?= (int)$g['Count'] ?> Albums</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #cf6679; margin-top: 0;">🗑️ Worst Genres</h3>
            <p style="font-size: 0.8rem; color: #666;">Lowest average Elo (min. 3 albums).</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($stats['worstGenres'] as $g): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;">🏷️ <?= htmlspecialchars((string)$g['Name']) ?></strong>
                        <span style="float: right; color: #cf6679;">Ø <?= round((float)$g['AvgElo']) ?></span><br>
                        <small><?= (int)$g['Count'] ?> Albums</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #8bc34a; margin-top: 0;">📅 Best Decades</h3>
            <p style="font-size: 0.8rem; color: #666;">Highest average Elo (min. 2 albums).</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($stats['bestDecades'] as $d): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars((string)$d['Name']) ?></strong>
                        <span style="float: right; color: #8bc34a;">Ø <?= round((float)$d['AvgElo']) ?></span><br>
                        <small><?= (int)$d['Count'] ?> Albums</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #cf6679; margin-top: 0;">👴 Worst Decades</h3>
            <p style="font-size: 0.8rem; color: #666;">Lowest average Elo (min. 2 albums).</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($stats['worstDecades'] as $d): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars((string)$d['Name']) ?></strong>
                        <span style="float: right; color: #cf6679;">Ø <?= round((float)$d['AvgElo']) ?></span><br>
                        <small><?= (int)$d['Count'] ?> Albums</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #03A9F4; margin-top: 0;">👑 Top Artists</h3>
            <p style="font-size: 0.8rem; color: #666;">Highest Power Score (Elo above baseline).</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($stats['topArtists'] as $a): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars((string)$a['Artist']) ?></strong>
                        <span style="float: right; color: var(--accent);">+<?= round((float)$a['PowerScore']) ?> Power</span><br>
                        <small><?= (int)$a['Count'] ?> Albums in DB</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #4CAF50; margin-top: 0;">💎 Hidden Gems</h3>
            <p style="font-size: 0.8rem; color: #666;">High Elo, but low Last.fm playcount.</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($stats['hiddenGems'] as $a): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars((string)$a['Artist']) ?></strong> - <?= htmlspecialchars((string)$a['Album']) ?><br>
                        <small>Elo: <?= round((float)$a['Elo']) ?> | Plays: <?= (int)$a['Playcount'] ?> | W/L: <?= (int)($a['Wins'] ?? 0) ?>/<?= (int)($a['Losses'] ?? 0) ?> (<?= htmlspecialchars(CsvHelper::winLossRatio((int)($a['Wins'] ?? 0), (int)($a['Losses'] ?? 0))) ?>)</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: var(--warning); margin-top: 0;">🤡 Overplayed & Overrated</h3>
            <p style="font-size: 0.8rem; color: #666;">High playcount, but lost most duels.</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($stats['disappointments'] as $a): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars((string)$a['Artist']) ?></strong> - <?= htmlspecialchars((string)$a['Album']) ?><br>
                        <small>Elo: <?= round((float)$a['Elo']) ?> | Plays: <?= (int)$a['Playcount'] ?> | W/L: <?= (int)($a['Wins'] ?? 0) ?>/<?= (int)($a['Losses'] ?? 0) ?> (<?= htmlspecialchars(CsvHelper::winLossRatio((int)($a['Wins'] ?? 0), (int)($a['Losses'] ?? 0))) ?>)</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div style="background: var(--card-bg); padding: 20px; border-radius: 12px; border: 1px solid var(--border);">
            <h3 style="color: #9C27B0; margin-top: 0;">⚔️ Battle-Hardened Veterans</h3>
            <p style="font-size: 0.8rem; color: #666;">Albums that faced the most duels.</p>
            <ul style="padding-left: 0; list-style: none; color: var(--text-muted);">
                <?php foreach ($stats['veterans'] as $a): ?>
                    <li style="margin-bottom: 8px; border-bottom: 1px solid #333; padding-bottom: 5px;">
                        <strong style="color: #fff;"><?= htmlspecialchars((string)$a['Artist']) ?></strong> - <?= htmlspecialchars((string)$a['Album']) ?><br>
                        <small><?= (int)$a['Duels'] ?> Duels fought | W/L: <?= (int)($a['Wins'] ?? 0) ?>/<?= (int)($a['Losses'] ?? 0) ?> (<?= htmlspecialchars(CsvHelper::winLossRatio((int)($a['Wins'] ?? 0), (int)($a['Losses'] ?? 0))) ?>)</small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
