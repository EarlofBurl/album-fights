<?php
require_once 'includes/config.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $APP_SETTINGS['lastfm_api_key'] = trim($_POST['lastfm_api_key']);
    $APP_SETTINGS['listenbrainz_api_key'] = trim($_POST['listenbrainz_api_key']);
    $APP_SETTINGS['gemini_api_key'] = trim($_POST['gemini_api_key']);
    $APP_SETTINGS['openai_api_key'] = trim($_POST['openai_api_key']);
    
    $APP_SETTINGS['ai_provider'] = $_POST['ai_provider'];
    $APP_SETTINGS['gemini_model'] = $_POST['gemini_model'];
    $APP_SETTINGS['openai_model'] = $_POST['openai_model'];
    
    $APP_SETTINGS['nerd_comments_enabled'] = isset($_POST['nerd_comments_enabled']);
    $APP_SETTINGS['import_min_plays'] = (int)$_POST['import_min_plays'];

    file_put_contents(FILE_SETTINGS, json_encode($APP_SETTINGS, JSON_PRETTY_PRINT));
    $message = "✅ Settings saved successfully!";
}

require_once 'includes/header.php';
?>

<div style="width: 100%; max-width: 800px; margin: 0 auto; background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border);">
    <h2 style="margin-top: 0;">⚙️ App Settings</h2>
    
    <?php if ($message): ?>
        <div style="background: #4CAF50; color: white; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="save_settings">

        <h3>🔌 API Keys</h3>
        <label>Last.fm API Key:</label>
        <input type="text" name="lastfm_api_key" value="<?= htmlspecialchars($APP_SETTINGS['lastfm_api_key']) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;">

        <label>Listenbrainz API Key:</label>
        <input type="text" name="listenbrainz_api_key" value="<?= htmlspecialchars($APP_SETTINGS['listenbrainz_api_key']) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;">

        <h3>🤖 AI Nerd Commentator</h3>
        <label>
            <input type="checkbox" name="nerd_comments_enabled" <?= $APP_SETTINGS['nerd_comments_enabled'] ? 'checked' : '' ?>>
            Enable AI Nerd Comments (Triggered every 25 duels on the Duel page)
        </label>
        <br><br>

        <div style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <label>Active Provider:</label>
                <select name="ai_provider" style="width: 100%; padding: 8px; margin-bottom: 15px;">
                    <option value="gemini" <?= $APP_SETTINGS['ai_provider'] === 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
                    <option value="openai" <?= $APP_SETTINGS['ai_provider'] === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                </select>
            </div>
        </div>

        <div style="display: flex; gap: 20px;">
            <div style="flex: 1; border: 1px solid var(--border); padding: 15px; border-radius: 8px;">
                <h4 style="margin-top:0;">Gemini Configuration</h4>
                <label>Gemini API Key:</label>
                <input type="password" name="gemini_api_key" value="<?= htmlspecialchars($APP_SETTINGS['gemini_api_key']) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;">
                
                <label>Gemini Model:</label>
                <select name="gemini_model" style="width: 100%; padding: 8px;">
                    <option value="gemini-3-flash-preview" <?= $APP_SETTINGS['gemini_model'] === 'gemini-3-flash-preview' ? 'selected' : '' ?>>gemini-3-flash-preview (Fast & Cheap)</option>
                    <option value="gemini-1.5-pro" <?= $APP_SETTINGS['gemini_model'] === 'gemini-1.5-pro' ? 'selected' : '' ?>>gemini-1.5-pro (Legacy Pro)</option>
                </select>
            </div>
            <div style="flex: 1; border: 1px solid var(--border); padding: 15px; border-radius: 8px;">
                <h4 style="margin-top:0;">OpenAI Configuration</h4>
                <label>OpenAI API Key:</label>
                <input type="password" name="openai_api_key" value="<?= htmlspecialchars($APP_SETTINGS['openai_api_key']) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;">
                
                <label>OpenAI Model:</label>
                <select name="openai_model" style="width: 100%; padding: 8px;">
                    <option value="gpt-4o-mini" <?= $APP_SETTINGS['openai_model'] === 'gpt-4o-mini' ? 'selected' : '' ?>>gpt-4o-mini (Fast & Cheap)</option>
                    <option value="gpt-4o" <?= $APP_SETTINGS['openai_model'] === 'gpt-4o' ? 'selected' : '' ?>>gpt-4o (Smart & Expensive)</option>
                </select>
            </div>
        </div>

        <h3 style="margin-top: 30px;">📥 Import Settings</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0;">These thresholds control which albums are allowed into the candidates list when importing.</p>
        <div style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <label><strong>Min. Plays needed for Last.fm / CSV Import:</strong></label>
                <input type="number" name="import_min_plays" value="<?= htmlspecialchars($APP_SETTINGS['import_min_plays']) ?>" style="width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 15px;" title="Minimum number of scrobbles required">
            </div>
        </div>

        <button type="submit" class="btn-small" style="background-color: var(--accent); color: #000; font-size: 1.1rem; padding: 10px 20px; cursor: pointer; border: none; border-radius: 5px; margin-top: 20px;">
            💾 Save Settings
        </button>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>