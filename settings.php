<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Config;
use App\Core\Security;
use App\Repository\SettingsRepository;

$config    = Config::get();
$settings  = new SettingsRepository($config);

$message = '';

$duelWeightLabels = [
    'top_25_vs'         => 'Top 25 vs (lowest duel counter)',
    'top_50_vs'         => 'Top 50 vs (ranks 26-50, lowest duel counter)',
    'top_100_vs'        => 'Top 100 vs (ranks 51-100, lowest duel counter)',
    'playcount_gt_20'   => 'Playcount > 20',
    'duel_counter_zero' => 'Duel counter zero',
    'random'            => 'Random',
];

$defaultDuelWeights = [
    'top_25_vs'         => 20,
    'top_50_vs'         => 20,
    'top_100_vs'        => 20,
    'playcount_gt_20'   => 15,
    'duel_counter_zero' => 15,
    'random'            => 10,
];

$activeDuelWeights = array_merge($defaultDuelWeights, $settings->getDuelCategoryWeights());

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    Security::requirePost();

    $settings->set('lastfm_api_key', Security::getString($_POST, 'lastfm_api_key'));
    $settings->set('listenbrainz_api_key', Security::getString($_POST, 'listenbrainz_api_key'));
    $settings->set('listenbrainz_username', Security::getString($_POST, 'listenbrainz_username'));
    $settings->set('subsonic_base_url', Security::getString($_POST, 'subsonic_base_url'));
    $settings->set('subsonic_username', Security::getString($_POST, 'subsonic_username'));
    $settings->set('subsonic_password', Security::getString($_POST, 'subsonic_password'));
    $settings->set('gemini_api_key', Security::getString($_POST, 'gemini_api_key'));
    $settings->set('openai_api_key', Security::getString($_POST, 'openai_api_key'));
    $settings->set('ai_provider', Security::getString($_POST, 'ai_provider'));
    $settings->set('gemini_model', Security::getString($_POST, 'gemini_model'));
    $settings->set('openai_model', Security::getString($_POST, 'openai_model'));
    $settings->set('nerd_comments_enabled', Security::getBool($_POST, 'nerd_comments_enabled'));
    $settings->set('import_min_plays', Security::getInt($_POST, 'import_min_plays'));

    $postedWeights = $_POST['duel_category_weights'] ?? [];
    $rawWeights = [];
    $rawTotal = 0;
    foreach ($duelWeightLabels as $key => $label) {
        $value = isset($postedWeights[$key]) ? (int)$postedWeights[$key] : 0;
        $value = max(0, min(100, $value));
        $rawWeights[$key] = $value;
        $rawTotal += $value;
    }

    if ($rawTotal <= 0) {
        $rawWeights = array_fill_keys(array_keys($duelWeightLabels), 0);
        $rawWeights['random'] = 100;
        $rawTotal = 100;
    }

    $normalized = [];
    $remainders = [];
    $assigned = 0;
    foreach ($rawWeights as $key => $value) {
        $exact = ($value / $rawTotal) * 100;
        $base = (int)floor($exact);
        $normalized[$key] = $base;
        $remainders[$key] = $exact - $base;
        $assigned += $base;
    }

    $missing = 100 - $assigned;
    if ($missing > 0) {
        arsort($remainders);
        foreach (array_keys($remainders) as $key) {
            if ($missing <= 0) {
                break;
            }
            $normalized[$key]++;
            $missing--;
        }
    }

    $settings->setDuelCategoryWeights($normalized);
    $settings->save();
    $activeDuelWeights = $normalized;
    $message = '✅ Settings saved successfully!';
}

$all = $settings->all();
$csrfField = Security::csrfField();

require __DIR__ . '/templates/settings.php';
