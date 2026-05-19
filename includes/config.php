<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use App\Core\Config;
use App\Core\Security;

// Start session and CSRF protection base
Security::initSession();

// Global shorthand for config (kept for transition, prefer DI)
$GLOBALS['APP_CONFIG'] = Config::get();
