<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Config;
use App\Repository\AlbumRepository;
use App\Repository\SettingsRepository;
use App\Service\StatsService;

$config    = Config::get();
$albumRepo = new AlbumRepository($config);
$settings  = new SettingsRepository($config);
$stats     = (new StatsService($albumRepo, $settings, $config))->buildStats();

require __DIR__ . '/templates/stats.php';
