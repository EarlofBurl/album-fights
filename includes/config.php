<?php
session_start();

// --- SMART PATH RESOLUTION (Web/Docker vs Desktop) ---

// 1. Dein funktionierender Standard (Web / Docker / Dev VM)
$dataDir = __DIR__ . '/../data/';
$cacheDir = __DIR__ . '/../cache/';

// 2. Desktop-Overrides (Diese greifen NUR, wenn die App verpackt auf dem Desktop läuft)
if (getenv('FLATPAK_ID')) {
    // Linux Flatpak
    $dataDir = rtrim(getenv('XDG_DATA_HOME'), '/') . '/AlbumFightsData/';
    $cacheDir = rtrim(getenv('XDG_CACHE_HOME'), '/') . '/AlbumFightsCache/';
} elseif (getenv('APPDATA')) {
    // Windows Desktop (.exe)
    $dataDir = str_replace('\\', '/', getenv('APPDATA')) . '/AlbumFights/data/';
    $cacheDir = str_replace('\\', '/', getenv('LOCALAPPDATA')) . '/AlbumFights/cache/';
} elseif (PHP_OS_FAMILY === 'Darwin' && getenv('HOME') && getenv('ELECTRON_RUN_AS_NODE')) {
    // macOS Desktop (.dmg)
    $dataDir = rtrim(getenv('HOME'), '/') . '/Library/Application Support/AlbumFights/data/';
    $cacheDir = rtrim(getenv('HOME'), '/') . '/Library/Caches/AlbumFights/';
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