<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Config;

$file = $_GET['file'] ?? '';

if (empty($file) || !preg_match('/^album_[a-f0-9]{32}\.jpg$/', $file)) {
    http_response_code(400);
    exit('Invalid image request');
}

$filePath = Config::get()->getCacheDir() . $file;

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
