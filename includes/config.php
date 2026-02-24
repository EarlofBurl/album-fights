<?php
session_start();

define('DIR_DATA', __DIR__ . '/../data/');
define('DIR_CACHE', __DIR__ . '/../cache/');
define('FILE_ELO', DIR_DATA . 'elo_state.csv');
define('FILE_QUEUE', DIR_DATA . 'listening_queue.csv');
define('FILE_SETTINGS', DIR_DATA . 'settings.json');

if (!is_dir(DIR_CACHE)) mkdir(DIR_CACHE, 0777, true);
if (!is_dir(DIR_DATA)) mkdir(DIR_DATA, 0777, true);

// Default settings with current models
$defaultSettings = [
    'lastfm_api_key' => getenv('LASTFM_API_KEY') ?: '',
    'listenbrainz_api_key' => getenv('LISTENBRAINZ_API_KEY') ?: '',
    'gemini_api_key' => getenv('GEMINI_API_KEY') ?: '',
    'openai_api_key' => getenv('OPENAI_API_KEY') ?: '',
    'ai_provider' => 'gemini', 
    'gemini_model' => 'gemini-3-flash-preview',
    'openai_model' => 'gpt-4o-mini',
    'nerd_comments_enabled' => true,
    'import_min_plays' => 8
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