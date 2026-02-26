<?php
require_once __DIR__ . '/includes/config.php';

$file = basename((string)($_GET['file'] ?? ''));

if ($file === '' || !preg_match('/^[a-zA-Z0-9._-]+$/', $file)) {
    http_response_code(404);
    exit('Not found');
}

$path = DIR_CACHE . $file;

if (!is_file($path)) {
    http_response_code(404);
    exit('Not found');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = $finfo ? finfo_file($finfo, $path) : false;
if ($finfo) {
    finfo_close($finfo);
}

if (!$mime) {
    $mime = 'image/jpeg';
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: public, max-age=31536000, immutable');

readfile($path);
exit;