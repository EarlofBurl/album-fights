<?php
require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

$testFile = DIR_CACHE . '__cache_test.txt';
$bytes = @file_put_contents($testFile, "cache test " . date('c') . PHP_EOL);

echo "DIR_CACHE: " . DIR_CACHE . PHP_EOL;
echo "exists: " . (is_dir(DIR_CACHE) ? 'yes' : 'no') . PHP_EOL;
echo "writable: " . (is_writable(DIR_CACHE) ? 'yes' : 'no') . PHP_EOL;
echo "write bytes: " . var_export($bytes, true) . PHP_EOL;
echo "test file exists: " . (file_exists($testFile) ? 'yes' : 'no') . PHP_EOL;