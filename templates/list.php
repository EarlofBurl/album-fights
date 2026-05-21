<?php
use App\Utils\CsvHelper;

/**
 * @var string $csrfField
 * @var list<array<string, mixed>> $visibleAlbums
 * @var list<array<string, mixed>> $topAlbums
 * @var string $sortBy
 * @var string $order
 * @var string $nextOrder
 * @var string $arrow
 * @var int $page
 * @var int $totalPages
 * @var int $totalAlbums
 * @var int $offset
 * @var int $perPage
 * @var MetadataService $meta
 * @var Config $config
 */

require __DIR__ . '/partials/header.php';
?>

<div style="width: 100%; max-width: 1400px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 10px; flex-wrap: wrap;">
        <h2 style="margin: 0;">The List</h2>
        <a class="btn-small" href="list.php?<?= htmlspecialchars(http_build_query(['sort' => $sortBy, 'order' => $order, 'export' => 'csv'])) ?>" style="background: var(--accent); color: #000; text-decoration: none; display: inline-block;">⬇️ Export CSV</a>
    </div>

    <h3 style="margin: 0 0 8px;">Top 25</h3>
    <?php if (empty($topAlbums)): ?>
        <p style="margin: 0 0 22px; color: var(--text-muted);">No albums available yet.</p>
    <?php else: ?>
        <div class="top25-grid">
            <?php foreach ($topAlbums as $index => $album):
                $rank = $index + 1;
                $coverUrl = getCachedCoverUrl((string)$album['Artist'], (string)$album['Album'], $meta, $config);
                $cardClasses = 'top25-card' . ($rank <= 10 ? ' top10' : '') . ($rank <= 3 ? ' top3' : '');
            ?>
                <div class="<?= htmlspecialchars($cardClasses) ?>">
                    <span class="top25-rank">#<?= $rank ?></span>
                    <?php if ($coverUrl !== ''): ?>
                        <a href="<?= htmlspecialchars($meta->getAlbumExternalUrl((string)$album['Artist'], (string)$album['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="display: block;">
                            <img class="top25-cover" src="<?= htmlspecialchars($coverUrl) ?>" alt="Cover: <?= htmlspecialchars((string)$album['Artist'] . ' - ' . $album['Album']) ?>">
                        </a>
                    <?php else: ?>
                        <div class="top25-cover-placeholder">No cover cached</div>
                    <?php endif; ?>
                    <div class="top25-title"><a href="<?= htmlspecialchars($meta->getAlbumExternalUrl((string)$album['Artist'], (string)$album['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars((string)$album['Album']) ?></a></div>
                    <div class="top25-artist"><a href="<?= htmlspecialchars($meta->getArtistExternalUrl((string)$album['Artist'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars((string)$album['Artist']) ?></a></div>
                    <div class="top25-artist">Duels: <?= (int)$album['Duels'] ?> | W/L: <?= (int)($album['Wins'] ?? 0) ?>/<?= (int)($album['Losses'] ?? 0) ?> (<?= htmlspecialchars(CsvHelper::winLossRatio((int)($album['Wins'] ?? 0), (int)($album['Losses'] ?? 0))) ?>)</div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 10px; flex-wrap: wrap; gap: 10px;">
        <h3 style="margin: 0;">Full List</h3>
        <p style="margin: 0; color: var(--text-muted); font-size: 0.9rem;">Showing entries <?= $totalAlbums === 0 ? 0 : $offset + 1 ?> - <?= min($offset + $perPage, $totalAlbums) ?> of <?= $totalAlbums ?>.</p>
    </div>

    <div class="top-list" style="max-width: 100%; margin-top: 0;">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th><?= sortLink('Artist', 'Artist', $sortBy, $order, $page, $nextOrder, $arrow, $meta) ?></th>
                    <th><?= sortLink('Album', 'Album', $sortBy, $order, $page, $nextOrder, $arrow, $meta) ?></th>
                    <th><?= sortLink('Elo', 'Elo', $sortBy, $order, $page, $nextOrder, $arrow, $meta) ?></th>
                    <th><?= sortLink('Duels', 'Duels', $sortBy, $order, $page, $nextOrder, $arrow, $meta) ?></th>
                    <th><?= sortLink('Playcount', 'Playcount', $sortBy, $order, $page, $nextOrder, $arrow, $meta) ?></th>
                    <th><?= sortLink('Ratio', 'W/L Ratio', $sortBy, $order, $page, $nextOrder, $arrow, $meta) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visibleAlbums)): ?>
                    <tr><td colspan="7" style="text-align: center;">No albums found.</td></tr>
                <?php else: ?>
                    <?php foreach ($visibleAlbums as $index => $album): ?>
                        <tr>
                            <td class="rank-col"><?= $offset + $index + 1 ?></td>
                            <td><a href="<?= htmlspecialchars($meta->getArtistExternalUrl((string)$album['Artist'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars((string)$album['Artist']) ?></a></td>
                            <td><a href="<?= htmlspecialchars($meta->getAlbumExternalUrl((string)$album['Artist'], (string)$album['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars((string)$album['Album']) ?></a></td>
                            <td><?= round((float)$album['Elo']) ?></td>
                            <td><?= (int)$album['Duels'] ?></td>
                            <td><?= (int)$album['Playcount'] ?></td>
                            <td><?= (int)($album['Wins'] ?? 0) ?>/<?= (int)($album['Losses'] ?? 0) ?> (<?= htmlspecialchars(CsvHelper::winLossRatio((int)($album['Wins'] ?? 0), (int)($album['Losses'] ?? 0))) ?>)</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div style="display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap;">
        <?php if ($page > 1): ?>
            <a class="btn-small" style="background: #333; color: #fff; text-decoration: none;" href="list.php?<?= htmlspecialchars(http_build_query(['sort' => $sortBy, 'order' => $order, 'page' => $page - 1])) ?>">← Prev</a>
        <?php endif; ?>
        <span style="padding: 8px 12px; border: 1px solid var(--border); border-radius: 4px; color: var(--text-muted);">Page <?= $page ?> / <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a class="btn-small" style="background: #333; color: #fff; text-decoration: none;" href="list.php?<?= htmlspecialchars(http_build_query(['sort' => $sortBy, 'order' => $order, 'page' => $page + 1])) ?>">Next →</a>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
