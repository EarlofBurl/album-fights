<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';

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

<div style="width: 100%; max-width: 1400px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 10px; flex-wrap: wrap;">
        <h2 style="margin: 0;">The List</h2>
        <a class="btn-small" href="list.php?<?= htmlspecialchars(http_build_query(['sort' => $sortBy, 'order' => $order, 'export' => 'csv'])) ?>" style="background: var(--accent); color: #000; text-decoration: none; display: inline-block;">⬇️ Export CSV</a>
    </div>

    <p style="margin-top: 0; color: var(--text-muted);">Showing entries <?= $totalAlbums === 0 ? 0 : $offset + 1 ?> - <?= min($offset + $perPage, $totalAlbums) ?> of <?= $totalAlbums ?>.</p>

    <div class="top-list" style="max-width: 100%; margin-top: 0;">
        <table>
            <thead>
                <tr>
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
                        <td colspan="5" style="text-align: center;">No albums found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($visibleAlbums as $album): ?>
                        <tr>
                            <td><?= htmlspecialchars($album['Artist']) ?></td>
                            <td><?= htmlspecialchars($album['Album']) ?></td>
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
