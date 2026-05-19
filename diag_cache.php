<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Config;

header('Content-Type: text/plain; charset=utf-8');

$cacheDir = Config::get()->getCacheDir();
$testFile = $cacheDir . '__cache_test.txt';
$bytes = @file_put_contents($testFile, 'cache test ' . date('c') . PHP_EOL);

echo 'DIR_CACHE: ' . $cacheDir . PHP_EOL;
echo 'exists: ' . (is_dir($cacheDir) ? 'yes' : 'no') . PHP_EOL;
echo 'writable: ' . (is_writable($cacheDir) ? 'yes' : 'no') . PHP_EOL;
echo 'write bytes: ' . var_export($bytes, true) . PHP_EOL;
echo 'test file exists: ' . (file_exists($testFile) ? 'yes' : 'no') . PHP_EOL;
