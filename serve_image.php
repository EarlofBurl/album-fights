<?php
declare(strict_types=1);

$file = $_GET['file'] ?? '';

if (empty($file) || !preg_match('/^album_[a-f0-9]{32}\.jpg$/', $file)) {
    http_response_code(400);
    exit('Invalid image request');
}

// Resolve cache dir the same way Config does, but without bootstrapping the whole app
$cacheDir = __DIR__ . '/cache/';

$electronPath = getenv('APP_USER_DATA_PATH') ?: ($_SERVER['APP_USER_DATA_PATH'] ?? '');
if (!empty($electronPath)) {
    $cacheDir = rtrim(str_replace('\\', '/', $electronPath), '/') . '/AlbumFightsCache/';
} elseif (getenv('APPDATA')) {
    $cacheDir = str_replace('\\', '/', (string)getenv('LOCALAPPDATA')) . '/AlbumFights/cache/';
} elseif (getenv('FLATPAK_ID')) {
    $cacheDir = rtrim((string)getenv('XDG_CACHE_HOME'), '/') . '/AlbumFightsCache/';
}

$filePath = $cacheDir . $file;

if (file_exists($filePath)) {
    $lastModified = filemtime($filePath);
    $eTag = '"' . md5($filePath . $lastModified) . '"';

    header('Content-Type: image/jpeg');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
    header('Etag: ' . $eTag);
    header('Cache-Control: public, max-age=86400');

    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $eTag) {
        http_response_code(304);
        exit;
    }

    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
} else {
    http_response_code(404);
    exit('Image not found');
}
