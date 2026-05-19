<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Config;
use App\Core\Security;
use App\Repository\AlbumRepository;
use App\Repository\SettingsRepository;
use App\Service\AiService;

$config    = Config::get();
$albumRepo = new AlbumRepository($config);
$settings  = new SettingsRepository($config);
$aiService = new AiService($config, $settings);

$bootcampFile = $config->getDataDir() . 'bootcamp_last.json';
$bootcampText = '';
$lastGeneratedDuels = null;
$top50History = [];
$commentHistory = [];

if (file_exists($bootcampFile)) {
    $saved = json_decode(file_get_contents($bootcampFile), true);
    $bootcampText = $saved['comment'] ?? '';
    $lastGeneratedDuels = $saved['duel_count'] ?? null;
    $top50History = $saved['top50_history'] ?? [];
    $commentHistory = $saved['comment_history'] ?? [];
}

$hasApiKey = false;
if ($settings->getAiProvider() === 'gemini' && !empty($settings->getGeminiApiKey())) {
    $hasApiKey = true;
} elseif ($settings->getAiProvider() === 'openai' && !empty($settings->getOpenAiApiKey())) {
    $hasApiKey = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    Security::requirePost();
    if ($hasApiKey) {
        $currentText = $albumRepo->getTop50Text();
        $historyForPrompt = array_slice($top50History, -5);
        $commentForPrompt = array_slice($commentHistory, -3);

        $newText = $aiService->triggerBootcampComment($currentText, $historyForPrompt, $commentForPrompt);
        if ($newText) {
            $bootcampText = $newText;
            $lastGeneratedDuels = $_SESSION['duel_count'] ?? 0;
            $top50History[] = $currentText;
            $commentHistory[] = $bootcampText;
            $top50History = array_slice($top50History, -5);
            $commentHistory = array_slice($commentHistory, -3);

            file_put_contents($bootcampFile, json_encode([
                'comment'         => $bootcampText,
                'duel_count'      => $lastGeneratedDuels,
                'top50_history'   => $top50History,
                'comment_history' => $commentHistory,
                'timestamp'       => time(),
            ]));
        }
    }
}

$csrfField = Security::csrfField();
require __DIR__ . '/templates/bootcamp.php';
