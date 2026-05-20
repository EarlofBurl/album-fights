<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Config;
use App\Core\Security;
use App\Repository\AlbumRepository;
use App\Repository\SettingsRepository;
use App\Service\DuplicateService;
use App\Service\MetadataService;

$config    = Config::get();
$albumRepo = new AlbumRepository($config);
$settings  = new SettingsRepository($config);
$dupService = new DuplicateService();
$metaService = new MetadataService($config, $settings);

$message = '';
$albums  = $albumRepo->loadElo();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::requirePost();
    $postedAction = Security::getString($_GET, 'action') ?: Security::getString($_POST, 'action');

    if ($postedAction === 'save_blacklist') {
        $raw = Security::getString($_POST, 'tag_blacklist');
        $parts = preg_split('/[\r\n,]+/', $raw);
        $tags = [];
        foreach ($parts as $tag) {
            $normalized = strtolower(trim((string)$tag));
            if ($normalized !== '') {
                $tags[] = $normalized;
            }
        }
        $settings->setTagBlacklist(array_values(array_unique($tags)));
        $settings->save();

        // Only invalidate the computed stats cache so genre stats refresh on next visit.
        // Metadata cache files are kept — blacklist is applied at read time.
        $statsCache = $config->getDataDir() . 'stats_cache.json';
        if (file_exists($statsCache)) {
            unlink($statsCache);
        }

        $message = '✅ Tag blacklist saved. Genre filters will apply immediately on next page load.';
    }

    if ($postedAction === 'delete_duplicate') {
        $deleteIndex = isset($_GET['delete_index']) ? Security::getInt($_GET, 'delete_index', -1) : Security::getInt($_POST, 'delete_index', -1);
        if (isset($albums[$deleteIndex])) {
            array_splice($albums, $deleteIndex, 1);
            $albumRepo->saveElo($albums);
            $message = '✅ Duplicate entry deleted.';
        }
    }

    if ($postedAction === 'merge_duplicates') {
        $indicesRaw = Security::getString($_POST, 'group_indices');
        $indices = array_values(array_unique(array_filter(array_map('intval', explode(',', $indicesRaw)), function (int $idx) use ($albums): bool {
            return isset($albums[$idx]);
        })));
        $primaryIndex = Security::getInt($_POST, 'primary_index', -1);

        if (count($indices) >= 2 && in_array($primaryIndex, $indices, true) && isset($albums[$primaryIndex])) {
            [$albums] = $dupService->mergeGroup($albums, $primaryIndex, $indices);
            $albumRepo->saveElo($albums);
            $message = '✅ Duplicate group merged (plays/duels/wins/losses summed, Elo averaged).';
        }
    }
}

$albums = $albumRepo->loadElo();
$blacklist = $settings->getTagBlacklist();
$blacklistLookup = [];
foreach ($blacklist as $tag) {
    $normalized = strtolower(trim($tag));
    if ($normalized !== '') {
        $blacklistLookup[$normalized] = true;
    }
}

$tagCounts = [];
foreach ((array) glob($config->getCacheDir() . '*.json') as $jsonFile) {
    $info = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($info) || !is_array($info['genres'] ?? null)) {
        continue;
    }
    foreach ($info['genres'] as $rawTag) {
        $normalized = strtolower(trim((string)$rawTag));
        if ($normalized === '') {
            continue;
        }
        $display = ucwords($normalized);
        $tagCounts[$display] = ($tagCounts[$display] ?? 0) + 1;
    }
}
arsort($tagCounts);

$duplicateGroups = $dupService->buildGroups($albums);
$csrfField = Security::csrfField();

require __DIR__ . '/templates/database.php';
