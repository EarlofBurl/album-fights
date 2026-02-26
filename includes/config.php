<?php
session_start();

// --- SMART PATH RESOLUTION (Web/Docker vs Desktop) ---

function normalizeAppDir($path) {
    $path = str_replace('\\', '/', (string)$path);
    return rtrim($path, '/') . '/';
}

// 1. Standard für Web / Docker / Dev VM
$dataDir = normalizeAppDir(__DIR__ . '/../data');
$cacheDir = normalizeAppDir(__DIR__ . '/../cache');

// 2. Desktop-Override über Electron-Umgebungsvariablen
if (getenv('ALBUMFIGHTS_DESKTOP') === '1') {
    $desktopDataDir = getenv('ALBUMFIGHTS_DATA_DIR') ?: '';
    $desktopCacheDir = getenv('ALBUMFIGHTS_CACHE_DIR') ?: '';

    if ($desktopDataDir !== '') {
        $dataDir = normalizeAppDir($desktopDataDir);
    }

    if ($desktopCacheDir !== '') {
        $cacheDir = normalizeAppDir($desktopCacheDir);
    }
} elseif (getenv('FLATPAK_ID')) {
    // Fallback für ältere Linux-Desktop-Builds
    $xdgDataHome = getenv('XDG_DATA_HOME') ?: (getenv('HOME') ? rtrim(getenv('HOME'), '/') . '/.local/share' : '');
    $xdgCacheHome = getenv('XDG_CACHE_HOME') ?: (getenv('HOME') ? rtrim(getenv('HOME'), '/') . '/.cache' : '');

    if ($xdgDataHome !== '') {
        $dataDir = normalizeAppDir($xdgDataHome . '/AlbumFightsData');
    }

    if ($xdgCacheHome !== '') {
        $cacheDir = normalizeAppDir($xdgCacheHome . '/AlbumFightsCache');
    }
} elseif (getenv('APPDATA')) {
    // Fallback für ältere Windows-Desktop-Builds
    $dataDir = normalizeAppDir(getenv('APPDATA') . '/AlbumFights/data');

    $localAppData = getenv('LOCALAPPDATA') ?: getenv('APPDATA');
    $cacheDir = normalizeAppDir($localAppData . '/AlbumFights/cache');
} elseif (PHP_OS_FAMILY === 'Darwin' && getenv('HOME') && getenv('ELECTRON_RUN_AS_NODE')) {
    // Fallback für ältere macOS-Desktop-Builds
    $dataDir = normalizeAppDir(getenv('HOME') . '/Library/Application Support/AlbumFights/data');
    $cacheDir = normalizeAppDir(getenv('HOME') . '/Library/Caches/AlbumFights');
}

// Konstanten setzen
define('DIR_DATA', $dataDir);
define('DIR_CACHE', $cacheDir);

define('FILE_ELO', DIR_DATA . 'elo_state.csv');
define('FILE_QUEUE', DIR_DATA . 'listening_queue.csv');
define('FILE_SETTINGS', DIR_DATA . 'settings.json');

// Ordner automatisch anlegen, falls sie noch nicht existieren
if (!is_dir(DIR_CACHE)) mkdir(DIR_CACHE, 0777, true);
if (!is_dir(DIR_DATA)) mkdir(DIR_DATA, 0777, true);

define('APP_ENV', getenv('APP_ENV') ?: 'prod');
define('DEV_PERF_LOG_ENABLED', APP_ENV === 'dev' && getenv('DEV_PERF_LOG') === '1');
define('DEV_PERF_LOG_FILE', DIR_DATA . 'dev_perf.log');

function devPerfLog($event, $context = []) {
    if (!DEV_PERF_LOG_ENABLED) {
        return;
    }

    $payload = [
        'ts' => date('c'),
        'event' => $event,
        'context' => $context
    ];

    @file_put_contents(DEV_PERF_LOG_FILE, json_encode($payload, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
}

// Default settings with current models
$defaultSettings = [
    'lastfm_api_key' => getenv('LASTFM_API_KEY') ?: '',
    'listenbrainz_api_key' => getenv('LISTENBRAINZ_API_KEY') ?: '',
    'listenbrainz_username' => getenv('LISTENBRAINZ_USERNAME') ?: '',
    'subsonic_base_url' => getenv('SUBSONIC_BASE_URL') ?: '',
    'subsonic_username' => getenv('SUBSONIC_USERNAME') ?: '',
    'subsonic_password' => getenv('SUBSONIC_PASSWORD') ?: '',
    'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
    'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
    'ai_provider' => 'gemini',
    'gemini_model' => 'gemini-3-flash-preview',
    'openai_model' => 'gpt-4o-mini',
    'nerd_comments_enabled' => true,
    'import_min_plays' => 8,
    'tag_blacklist' => [],
    'duel_category_weights' => [
        'top_25_vs' => 20,
        'top_50_vs' => 20,
        'top_100_vs' => 20,
        'playcount_gt_20' => 15,
        'duel_counter_zero' => 15,
        'random' => 10
    ]
];

// Load settings or create new
if (file_exists(FILE_SETTINGS)) {
    $userSettings = json_decode(file_get_contents(FILE_SETTINGS), true) ?: [];
    $APP_SETTINGS = array_merge($defaultSettings, $userSettings);
} else {
    $APP_SETTINGS = $defaultSettings;
    file_put_contents(FILE_SETTINGS, json_encode($APP_SETTINGS, JSON_PRETTY_PRINT));
}

// For backwards compatibility in the rest of the code
define('LASTFM_API_KEY', $APP_SETTINGS['lastfm_api_key']);
define('GEMINI_API_KEY', $APP_SETTINGS['gemini_api_key']);
define('OPENAI_API_KEY', $APP_SETTINGS['openai_api_key']);
define('LISTENBRAINZ_API_KEY', $APP_SETTINGS['listenbrainz_api_key']);

if (!isset($_SESSION['duel_count'])) $_SESSION['duel_count'] = 0;
if (!isset($_SESSION['history'])) $_SESSION['history'] = [];
if (!isset($_SESSION['recent_picks'])) $_SESSION['recent_picks'] = [];
?>