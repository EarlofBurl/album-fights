<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';
require_once 'includes/api_manager.php';

$albums = loadCsv(FILE_ELO);
$bootcamp_text = "";
$last_generated_duels = null;
$bootcamp_file = DIR_DATA . 'bootcamp_last.json';
$top50_history = [];
$comment_history = [];

// Load previous assessment if it exists
if (file_exists($bootcamp_file)) {
    $saved_data = json_decode(file_get_contents($bootcamp_file), true);
    $bootcamp_text = $saved_data['comment'] ?? "";
    $last_generated_duels = $saved_data['duel_count'] ?? null;
    $top50_history = $saved_data['top50_history'] ?? [];
    $comment_history = $saved_data['comment_history'] ?? [];
}

// Check if an active API Key is provided for the selected AI
$has_api_key = false;
if ($APP_SETTINGS['ai_provider'] === 'gemini' && !empty($APP_SETTINGS['gemini_api_key'])) {
    $has_api_key = true;
} elseif ($APP_SETTINGS['ai_provider'] === 'openai' && !empty($APP_SETTINGS['openai_api_key'])) {
    $has_api_key = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    if ($has_api_key) {
        $top50 = getTopAlbums($albums, 50);
        $currentText = "";
        foreach($top50 as $i => $a) {
            $currentText .= ($i+1) . ". " . $a['Artist'] . " - " . $a['Album'] . "\n";
        }

        $history_for_prompt = array_slice($top50_history, -5);
        $comment_history_for_prompt = array_slice($comment_history, -3);
        
        $new_text = triggerBootcampComment($currentText, $history_for_prompt, $comment_history_for_prompt);
        
        if ($new_text) {
            $bootcamp_text = $new_text;
            $last_generated_duels = $_SESSION['duel_count'] ?? 0;

            $top50_history[] = $currentText;
            $comment_history[] = $bootcamp_text;

            $top50_history = array_slice($top50_history, -5);
            $comment_history = array_slice($comment_history, -3);
            
            // Save the state to persist the last commentary
            file_put_contents($bootcamp_file, json_encode([
                'comment' => $bootcamp_text,
                'duel_count' => $last_generated_duels,
                'top50_history' => $top50_history,
                'comment_history' => $comment_history,
                'timestamp' => time()
            ]));
        }
    }
}

require_once 'includes/header.php';
?>

<div style="width: 100%; max-width: 800px; margin: 0 auto; background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border);">
    <h2 style="margin-top: 0; text-align: center;">🧐 The Snob's Assessment</h2>
    <p style="color: var(--text-muted); text-align: center; margin-bottom: 30px;">
        Ready for a reality check? Submit your current Top 50 albums for an earnest, highly opinionated critique from the ultimate music snob.
    </p>

    <?php if (!$has_api_key): ?>
        <div style="background: #cf6679; color: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold;">
            ⚠️ No API Key found! Please configure your Gemini or OpenAI API key in the <a href="settings.php" style="color: #fff; text-decoration: underline;">Settings</a> to generate an assessment.
        </div>
    <?php endif; ?>

    <?php if (!empty($bootcamp_text)): ?>
        <div style="background: #2c2c2c; padding: 25px; border-radius: 8px; border-left: 5px solid var(--accent); line-height: 1.6; font-size: 1.1rem; margin-bottom: 20px; position: relative;">
            <?php if ($last_generated_duels !== null): ?>
                <span style="position: absolute; top: -12px; right: 20px; background: var(--accent); color: #000; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                    Generated after <?= $last_generated_duels ?> duels
                </span>
            <?php endif; ?>
            <?= nl2br(htmlspecialchars($bootcamp_text)) ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="text-align: center;">
        <input type="hidden" name="action" value="generate">
        <button type="submit" <?= !$has_api_key ? 'disabled style="opacity: 0.5; cursor: not-allowed;' : 'style="cursor: pointer;' ?> background-color: var(--accent); color: #000; font-size: 1.2rem; padding: 15px 30px; border: none; border-radius: 8px; font-weight: bold;">
            🔥 <?= empty($bootcamp_text) ? 'Generate Top 50 Assessment' : 'Regenerate Assessment' ?>
        </button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
