<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';
require_once 'includes/api_manager.php';

$albums = loadCsv(FILE_ELO);

$allowedSortFields = ['Artist', 'Album', 'Elo', 'Duels', 'Playcount'];
$sortBy = $_GET['sort'] ?? 'Elo';
$sortBy = in_array($sortBy, $allowedSortFields, true) ? $sortBy : 'Elo';

$order = strtolower($_GET['order'] ?? 'desc');
$order = $order === 'asc' ? 'asc' : 'desc';

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportAlbums = $albums;
    usort($exportAlbums, function ($a, $b) use ($sortBy, $order) {
        $cmp = $a[$sortBy] <=> $b[$sortBy];
        if ($cmp === 0) {
            $cmp = $a['Artist'] <=> $b['Artist'];
            if ($cmp === 0) {
                $cmp = $a['Album'] <=> $b['Album'];
            }
        }
        return $order === 'asc' ? $cmp : -$cmp;
    });

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="the-list.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Artist', 'Album', 'Elo', 'Duels', 'Playcount']);
    foreach ($exportAlbums as $row) {
        fputcsv($output, [
            $row['Artist'],
            $row['Album'],
            round((float)$row['Elo'], 2),
            (int)$row['Duels'],
            (int)$row['Playcount'],
        ]);
    }
    fclose($output);
    exit;
}

usort($albums, function ($a, $b) use ($sortBy, $order) {
    $cmp = $a[$sortBy] <=> $b[$sortBy];
    if ($cmp === 0) {
        $cmp = $a['Artist'] <=> $b['Artist'];
        if ($cmp === 0) {
            $cmp = $a['Album'] <=> $b['Album'];
        }
    }
    return $order === 'asc' ? $cmp : -$cmp;
});

$perPage = 100;
$totalAlbums = count($albums);
$totalPages = max(1, (int)ceil($totalAlbums / $perPage));
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, min($page, $totalPages));
$offset = ($page - 1) * $perPage;
$visibleAlbums = array_slice($albums, $offset, $perPage);

$nextOrder = $order === 'asc' ? 'desc' : 'asc';
$arrow = $order === 'asc' ? '▲' : '▼';
$topAlbums = getTopAlbums($albums, 25);

function getCachedCoverUrl(string $artist, string $album): string {
    $cacheBaseName = getAlbumCacheBaseName($artist, $album);
    $cacheFilePath = DIR_CACHE . $cacheBaseName . '.jpg';

    if (file_exists($cacheFilePath)) {
        // NEU: Jetzt routen wir das Bild sauber durch unseren Proxy
        return 'serve_image.php?file=' . urlencode($cacheBaseName . '.jpg');
    }

    return '';
}

function sortLink(string $field, string $label, string $sortBy, string $order, int $page, string $nextOrder, string $arrow): string {
    $isActive = $sortBy === $field;
    $targetOrder = $isActive ? $nextOrder : 'desc';
    $indicator = $isActive ? " {$arrow}" : '';
    $query = http_build_query([
        'sort' => $field,
        'order' => $targetOrder,
        'page' => $page,
    ]);

    return '<a href="list.php?' . htmlspecialchars($query) . '" style="color: inherit; text-decoration: none;">' .
        htmlspecialchars($label . $indicator) .
        '</a>';
}

require_once 'includes/header.php';
?>

<style>
    .top25-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(132px, 1fr));
        gap: 12px;
        margin: 12px 0 30px;
    }
    .top25-card {
        border: 1px solid var(--border);
        border-radius: 10px;
        padding: 10px;
        min-height: 188px;
        background: #171717;
    }
    .top25-card.top10 {
        background: rgba(187, 134, 252, 0.15);
        border-color: rgba(187, 134, 252, 0.45);
    }
    .top25-card.top3 {
        grid-column: span 2;
        min-height: 290px;
        background: rgba(187, 134, 252, 0.24);
    }
    .top25-rank {
        display: inline-block;
        font-size: 0.78rem;
        border: 1px solid rgba(255, 255, 255, 0.25);
        border-radius: 999px;
        padding: 2px 8px;
        margin-bottom: 8px;
        color: var(--text-muted);
    }
    .top25-cover {
        width: 100%;
        aspect-ratio: 1 / 1;
        object-fit: cover;
        border-radius: 8px;
        background: #252525;
    }
    .top25-cover-placeholder {
        width: 100%;
        aspect-ratio: 1 / 1;
        border-radius: 8px;
        background: linear-gradient(135deg, #2a2a2a, #1c1c1c);
        color: var(--text-muted);
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        font-size: 0.78rem;
        padding: 8px;
        box-sizing: border-box;
    }
    .top25-title {
        margin-top: 8px;
        font-weight: 700;
        font-size: 0.92rem;
        line-height: 1.25;
    }
    .top25-artist {
        color: var(--text-muted);
        font-size: 0.82rem;
        line-height: 1.2;
        margin-top: 3px;
    }
    @media (max-width: 760px) {
        .top25-card.top3 {
            grid-column: span 1;
            min-height: 210px;
        }
    }
</style>

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
            <?php foreach ($topAlbums as $index => $album): ?>
                <?php
                $rank = $index + 1;
                $coverUrl = getCachedCoverUrl($album['Artist'], $album['Album']);
                $cardClasses = 'top25-card';
                if ($rank <= 10) {
                    $cardClasses .= ' top10';
                }
                if ($rank <= 3) {
                    $cardClasses .= ' top3';
                }
                ?>
                <div class="<?= htmlspecialchars($cardClasses) ?>">
                    <span class="top25-rank">#<?= $rank ?></span>
                    <?php if ($coverUrl !== ''): ?>
                        <img class="top25-cover" src="<?= htmlspecialchars($coverUrl) ?>" alt="Cover: <?= htmlspecialchars($album['Artist'] . ' - ' . $album['Album']) ?>">
                    <?php else: ?>
                        <div class="top25-cover-placeholder">No cover cached</div>
                    <?php endif; ?>
                    <div class="top25-title"><a href="<?= htmlspecialchars(getAlbumExternalUrl($album['Artist'], $album['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($album['Album']) ?></a></div>
                    <div class="top25-artist"><a href="<?= htmlspecialchars(getArtistExternalUrl($album['Artist'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($album['Artist']) ?></a></div>
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
                    <th><?= sortLink('Artist', 'Artist', $sortBy, $order, $page, $nextOrder, $arrow) ?></th>
                    <th><?= sortLink('Album', 'Album', $sortBy, $order, $page, $nextOrder, $arrow) ?></th>
                    <th><?= sortLink('Elo', 'Elo', $sortBy, $order, $page, $nextOrder, $arrow) ?></th>
                    <th><?= sortLink('Duels', 'Duels', $sortBy, $order, $page, $nextOrder, $arrow) ?></th>
                    <th><?= sortLink('Playcount', 'Playcount', $sortBy, $order, $page, $nextOrder, $arrow) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($visibleAlbums)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center;">No albums found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($visibleAlbums as $index => $album): ?>
                        <tr>
                            <td class="rank-col"><?= $offset + $index + 1 ?></td>
                            <td><a href="<?= htmlspecialchars(getArtistExternalUrl($album['Artist'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($album['Artist']) ?></a></td>
                            <td><a href="<?= htmlspecialchars(getAlbumExternalUrl($album['Artist'], $album['Album'])) ?>" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none;"><?= htmlspecialchars($album['Album']) ?></a></td>
                            <td><?= round((float)$album['Elo']) ?></td>
                            <td><?= (int)$album['Duels'] ?></td>
                            <td><?= (int)$album['Playcount'] ?></td>
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

<?php require_once 'includes/footer.php'; ?>