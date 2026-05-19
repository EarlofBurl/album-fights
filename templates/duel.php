<?php
/**
 * @var string $csrfField
 * @var string $reviewText
 * @var ?array<string, mixed> $albumA
 * @var ?array<string, mixed> $albumB
 * @var array<string, mixed> $infoA
 * @var array<string, mixed> $infoB
 * @var list<array<string, mixed>> $top20
 * @var int $total
 * @var EloService $eloService
 */

use App\Service\EloService;
use App\Utils\CsvHelper;

function renderTracklist(array $info): void {
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
    echo '</ol></details>';
}

require __DIR__ . '/partials/header.php';
?>

<?php if (!empty($reviewText)): ?>
    <div style="width: 100%; max-width: 800px; background: #2c2c2c; padding: 30px; border-radius: 12px; margin-bottom: 30px; border-left: 5px solid var(--accent);">
        <h2 style="margin-top: 0; color: var(--accent);">🤖 The Nerd Speaks! (25 Duels Milestone)</h2>
        <div style="line-height: 1.6; font-size: 1.1rem;">
            <?= nl2br(htmlspecialchars($reviewText)) ?>
        </div>
        <form method="GET" action="index.php" style="margin-top: 20px;">
            <button type="submit" class="btn-small" style="background-color: var(--accent); color: #000; font-size: 1.1rem; padding: 10px 20px;">
                Continue Dueling ⚔️
            </button>
        </form>
    </div>
<?php endif; ?>

<?php if (empty($reviewText) && $total >= 2 && $albumA !== null && $albumB !== null): ?>
    <div class="duel-container">
        <div class="duel-card <?= htmlspecialchars($eloService->getRankClass((int)$albumA['Rank'])) ?>">
            <div class="duel-rank-badge">#<?= (int)$albumA['Rank'] ?></div>
            <form method="POST" style="position: absolute; top: 10px; right: 10px; margin: 0;">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="reload_metadata">
                <input type="hidden" name="targetIdx" value="<?= (int)$albumA['OriginalIndex'] ?>">
                <button type="submit" class="btn-reload-metadata" title="Refetch metadata" aria-label="Refetch metadata">↻</button>
            </form>
            <h2 class="artist-name"><a href="<?= htmlspecialchars($metaService->getArtistExternalUrl((string)$albumA['Artist'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars((string)$albumA['Artist']) ?></a></h2>
            <h3 style="margin-top: 0; margin-bottom: 5px;"><a href="<?= htmlspecialchars($metaService->getAlbumExternalUrl((string)$albumA['Artist'], (string)$albumA['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars((string)$albumA['Album']) ?></a></h3>
            <div style="font-size: 0.8rem; margin-bottom: 10px; font-weight: bold; letter-spacing: 0.5px; color: var(--accent);">
                <?= !empty($infoA['year']) ? htmlspecialchars((string)$infoA['year']) . ' • ' : '' ?>
                <?= !empty($infoA['genres']) ? htmlspecialchars(implode(' • ', array_slice($infoA['genres'], 0, 4))) : 'No genres found' ?>
            </div>
            <p style="font-size: 0.9rem; margin-top: 0; color: var(--text-muted);">Elo: <?= round((float)$albumA['Elo']) ?> | Plays: <?= (int)$albumA['Playcount'] ?> | W/L: <?= (int)($albumA['Wins'] ?? 0) ?>/<?= (int)($albumA['Losses'] ?? 0) ?> (<?= htmlspecialchars(CsvHelper::winLossRatio((int)($albumA['Wins'] ?? 0), (int)($albumA['Losses'] ?? 0))) ?>)</p>
            <div style="flex-grow: 1; display: flex; align-items: center; justify-content: center; margin: 15px 0;">
                <?php if (!empty($infoA['local_image'])): ?>
                    <a href="<?= htmlspecialchars($metaService->getAlbumExternalUrl((string)$albumA['Artist'], (string)$albumA['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block;">
                        <img src="<?= htmlspecialchars((string)$infoA['local_image']) ?>" alt="Cover" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                    </a>
                <?php else: ?>
                    <div style="width: 250px; height: 250px; background: #333; display: flex; align-items: center; justify-content: center; border-radius: 8px;">No Image</div>
                <?php endif; ?>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); text-align: justify; height: 100px; overflow-y: auto; padding-right: 10px;">
                <?= htmlspecialchars((string)$infoA['summary']) ?>
            </p>
            <?php renderTracklist($infoA); ?>
            <form method="POST" style="margin-top: 15px;">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="vote">
                <input type="hidden" name="idxA" value="<?= (int)$albumA['OriginalIndex'] ?>">
                <input type="hidden" name="idxB" value="<?= (int)$albumB['OriginalIndex'] ?>">
                <input type="hidden" name="scoreA" value="1">
                <button type="submit" class="btn-vote">🏆 Choose A</button>
            </form>
            <div style="display: flex; gap: 10px;">
                <form method="POST" style="flex:1;">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="queue">
                    <input type="hidden" name="targetIdx" value="<?= (int)$albumA['OriginalIndex'] ?>">
                    <input type="hidden" name="survivorArtist" value="<?= htmlspecialchars((string)$albumB['Artist']) ?>">
                    <input type="hidden" name="survivorAlbum" value="<?= htmlspecialchars((string)$albumB['Album']) ?>">
                    <button type="submit" class="btn-small btn-queue" style="width: 100%;">🎧 To Queue</button>
                </form>
                <form method="POST" style="flex:1;" onsubmit="return confirm('Do you want to delete this album forever?');">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="targetIdx" value="<?= (int)$albumA['OriginalIndex'] ?>">
                    <input type="hidden" name="survivorArtist" value="<?= htmlspecialchars((string)$albumB['Artist']) ?>">
                    <input type="hidden" name="survivorAlbum" value="<?= htmlspecialchars((string)$albumB['Album']) ?>">
                    <button type="submit" class="btn-small btn-delete" style="width: 100%;">🗑️ Delete</button>
                </form>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; justify-content: center; align-items: center; gap: 20px;">
            <div style="background: #111; color: var(--text-muted); padding: 15px; border-radius: 50%; font-weight: bold; border: 2px solid var(--border);">VS</div>
            <form method="POST">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="vote">
                <input type="hidden" name="idxA" value="<?= (int)$albumA['OriginalIndex'] ?>">
                <input type="hidden" name="idxB" value="<?= (int)$albumB['OriginalIndex'] ?>">
                <input type="hidden" name="scoreA" value="0.5">
                <button type="submit" style="background-color: var(--warning); color: #000; border: none; padding: 10px 20px; border-radius: 8px; font-weight: bold; cursor: pointer;">🤝 Draw</button>
            </form>
        </div>

        <div class="duel-card <?= htmlspecialchars($eloService->getRankClass((int)$albumB['Rank'])) ?>">
            <div class="duel-rank-badge">#<?= (int)$albumB['Rank'] ?></div>
            <form method="POST" style="position: absolute; top: 10px; right: 10px; margin: 0;">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="reload_metadata">
                <input type="hidden" name="targetIdx" value="<?= (int)$albumB['OriginalIndex'] ?>">
                <button type="submit" class="btn-reload-metadata" title="Refetch metadata" aria-label="Refetch metadata">↻</button>
            </form>
            <h2 class="artist-name"><a href="<?= htmlspecialchars($metaService->getArtistExternalUrl((string)$albumB['Artist'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars((string)$albumB['Artist']) ?></a></h2>
            <h3 style="margin-top: 0; margin-bottom: 5px;"><a href="<?= htmlspecialchars($metaService->getAlbumExternalUrl((string)$albumB['Artist'], (string)$albumB['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars((string)$albumB['Album']) ?></a></h3>
            <div style="font-size: 0.8rem; margin-bottom: 10px; font-weight: bold; letter-spacing: 0.5px; color: var(--accent);">
                <?= !empty($infoB['year']) ? htmlspecialchars((string)$infoB['year']) . ' • ' : '' ?>
                <?= !empty($infoB['genres']) ? htmlspecialchars(implode(' • ', array_slice($infoB['genres'], 0, 4))) : 'No genres found' ?>
            </div>
            <p style="font-size: 0.9rem; margin-top: 0; color: var(--text-muted);">Elo: <?= round((float)$albumB['Elo']) ?> | Plays: <?= (int)$albumB['Playcount'] ?> | W/L: <?= (int)($albumB['Wins'] ?? 0) ?>/<?= (int)($albumB['Losses'] ?? 0) ?> (<?= htmlspecialchars(CsvHelper::winLossRatio((int)($albumB['Wins'] ?? 0), (int)($albumB['Losses'] ?? 0))) ?>)</p>
            <div style="flex-grow: 1; display: flex; align-items: center; justify-content: center; margin: 15px 0;">
                <?php if (!empty($infoB['local_image'])): ?>
                    <a href="<?= htmlspecialchars($metaService->getAlbumExternalUrl((string)$albumB['Artist'], (string)$albumB['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="display: inline-block;">
                        <img src="<?= htmlspecialchars((string)$infoB['local_image']) ?>" alt="Cover" style="max-width: 100%; max-height: 300px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                    </a>
                <?php else: ?>
                    <div style="width: 250px; height: 250px; background: #333; display: flex; align-items: center; justify-content: center; border-radius: 8px;">No Image</div>
                <?php endif; ?>
            </div>
            <p style="font-size: 0.85rem; color: var(--text-muted); text-align: justify; height: 100px; overflow-y: auto; padding-right: 10px;">
                <?= htmlspecialchars((string)$infoB['summary']) ?>
            </p>
            <?php renderTracklist($infoB); ?>
            <form method="POST" style="margin-top: 15px;">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="vote">
                <input type="hidden" name="idxA" value="<?= (int)$albumA['OriginalIndex'] ?>">
                <input type="hidden" name="idxB" value="<?= (int)$albumB['OriginalIndex'] ?>">
                <input type="hidden" name="scoreA" value="0">
                <button type="submit" class="btn-vote">🏆 Choose B</button>
            </form>
            <div style="display: flex; gap: 10px;">
                <form method="POST" style="flex:1;">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="queue">
                    <input type="hidden" name="targetIdx" value="<?= (int)$albumB['OriginalIndex'] ?>">
                    <input type="hidden" name="survivorArtist" value="<?= htmlspecialchars((string)$albumA['Artist']) ?>">
                    <input type="hidden" name="survivorAlbum" value="<?= htmlspecialchars((string)$albumA['Album']) ?>">
                    <button type="submit" class="btn-small btn-queue" style="width: 100%;">🎧 To Queue</button>
                </form>
                <form method="POST" style="flex:1;" onsubmit="return confirm('Do you want to delete this album forever?');">
                    <?= $csrfField ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="targetIdx" value="<?= (int)$albumB['OriginalIndex'] ?>">
                    <input type="hidden" name="survivorArtist" value="<?= htmlspecialchars((string)$albumA['Artist']) ?>">
                    <input type="hidden" name="survivorAlbum" value="<?= htmlspecialchars((string)$albumA['Album']) ?>">
                    <button type="submit" class="btn-small btn-delete" style="width: 100%;">🗑️ Delete</button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (empty($reviewText)): ?>
    <div class="top-list">
        <h3>🏆 Top 20 Albums</h3>
        <table>
            <thead><tr><th class="rank-col">#</th><th>Artist</th><th>Album</th><th>Elo</th><th>W/L Ratio</th></tr></thead>
            <tbody>
                <?php foreach ($top20 as $index => $album): ?>
                    <tr>
                        <td class="rank-col"><?= $index + 1 ?></td>
                        <td style="color: var(--accent); font-weight: bold;"><a href="<?= htmlspecialchars($metaService->getArtistExternalUrl((string)$album['Artist'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars((string)$album['Artist']) ?></a></td>
                        <td><a href="<?= htmlspecialchars($metaService->getAlbumExternalUrl((string)$album['Artist'], (string)$album['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars((string)$album['Album']) ?></a></td>
                        <td style="color: var(--text-muted); font-size: 0.9rem;"><?= round((float)$album['Elo']) ?></td>
                        <td style="color: var(--text-muted); font-size: 0.9rem;"><?= (int)($album['Wins'] ?? 0) ?>/<?= (int)($album['Losses'] ?? 0) ?> (<?= htmlspecialchars(CsvHelper::winLossRatio((int)($album['Wins'] ?? 0), (int)($album['Losses'] ?? 0))) ?>)</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/partials/footer.php'; ?>
