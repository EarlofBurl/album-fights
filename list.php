<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Config;
use App\Core\Security;
use App\Repository\AlbumRepository;
use App\Repository\SettingsRepository;
use App\Service\MetadataService;
use App\Utils\CsvHelper;

$config    = Config::get();
$albumRepo = new AlbumRepository($config);
$settings  = new SettingsRepository($config);
$meta      = new MetadataService($config, $settings);

$allowedSortFields = ['Artist', 'Album', 'Elo', 'Duels', 'Playcount', 'Wins', 'Losses', 'Ratio'];
$sortBy = in_array($_GET['sort'] ?? '', $allowedSortFields, true) ? $_GET['sort'] : 'Elo';
$order  = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

$albums = $albumRepo->loadElo();

usort($albums, function (array $a, array $b) use ($sortBy, $order): int {
    if ($sortBy === 'Ratio') {
        $ratioA = (float)($a['Losses'] ?? 0) === 0.0
            ? ((int)($a['Wins'] ?? 0) > 0 ? INF : 0.0)
            : ((int)($a['Wins'] ?? 0) / (int)($a['Losses'] ?? 0));
        $ratioB = (float)($b['Losses'] ?? 0) === 0.0
            ? ((int)($b['Wins'] ?? 0) > 0 ? INF : 0.0)
            : ((int)($b['Wins'] ?? 0) / (int)($b['Losses'] ?? 0));
        $cmp = $ratioA <=> $ratioB;
    } else {
        $cmp = $a[$sortBy] <=> $b[$sortBy];
    }
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
$totalPages  = max(1, (int)ceil($totalAlbums / $perPage));
$page = max(1, min((int)($_GET['page'] ?? 1), $totalPages));
$offset = ($page - 1) * $perPage;
$visibleAlbums = array_slice($albums, $offset, $perPage);

$nextOrder = $order === 'asc' ? 'desc' : 'asc';
$arrow = $order === 'asc' ? '▲' : '▼';
$topAlbums = $albumRepo->getTop(25);

// CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="the-list.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Artist', 'Album', 'Elo', 'Duels', 'Playcount', 'Wins', 'Losses', 'Ratio']);
    foreach ($albums as $row) {
        fputcsv($output, [
            $row['Artist'],
            $row['Album'],
            round((float)$row['Elo'], 2),
            (int)$row['Duels'],
            (int)$row['Playcount'],
            (int)($row['Wins'] ?? 0),
            (int)($row['Losses'] ?? 0),
            CsvHelper::winLossRatio((int)($row['Wins'] ?? 0), (int)($row['Losses'] ?? 0)),
        ]);
    }
    fclose($output);
    exit;
}

function getCachedCoverUrl(string $artist, string $album, MetadataService $meta, Config $config): string {
    $base = $meta->getAlbumCacheBaseName($artist, $album);
    if (file_exists($config->getCacheDir() . $base . '.jpg')) {
        return 'serve_image.php?file=' . urlencode($base . '.jpg');
    }
    return '';
}

function sortLink(string $field, string $label, string $sortBy, string $order, int $page, string $nextOrder, string $arrow, MetadataService $meta): string {
    $isActive = $sortBy === $field;
    $targetOrder = $isActive ? $nextOrder : 'desc';
    $indicator = $isActive ? " {$arrow}" : '';
    $query = http_build_query(['sort' => $field, 'order' => $targetOrder, 'page' => $page]);
    return '<a href="list.php?' . htmlspecialchars($query) . '" style="color: inherit; text-decoration: none;">' . htmlspecialchars($label . $indicator) . '</a>';
}

$csrfField = Security::csrfField();

require __DIR__ . '/templates/list.php';
