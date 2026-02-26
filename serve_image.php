<?php
require_once 'includes/config.php';

$file = $_GET['file'] ?? '';

// Sicherheitscheck: Nur erlaubte Dateinamen zulassen (.jpg)
if (empty($file) || !preg_match('/^[a-zA-Z0-9_\-]+\.jpg$/', $file)) {
    http_response_code(400);
    exit('Invalid image request');
}

$filePath = DIR_CACHE . $file;

if (file_exists($filePath)) {
    // Cache-Validierung via Last-Modified und ETag
    $lastModified = filemtime($filePath);
    $eTag = '"' . md5($filePath . $lastModified) . '"';

    header('Content-Type: image/jpeg');
    header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");
    header("Etag: $eTag");
    header('Cache-Control: public, max-age=86400'); // 24 Stunden Cache

    // Prüfen, ob der Client das Bild schon in dieser Version hat (304 Not Modified)
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $eTag) {
        http_response_code(304);
        exit;
    }

    // Falls nicht gecached, Bild ausliefern
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
} else {
    http_response_code(404);
    exit('Image not found');
}
?>