<?php
require_once 'includes/config.php';

$message = '';

$duelWeightLabels = [
    'top_25_vs' => 'Top 25 vs (lowest duel counter)',
    'top_50_vs' => 'Top 50 vs (ranks 26-50, lowest duel counter)',
    'top_100_vs' => 'Top 100 vs (ranks 51-100, lowest duel counter)',
    'playcount_gt_20' => 'Playcount > 20',
    'duel_counter_zero' => 'Duel counter zero',
    'random' => 'Random'
];

$defaultDuelWeights = [
    'top_25_vs' => 20,
    'top_50_vs' => 20,
    'top_100_vs' => 20,
    'playcount_gt_20' => 15,
    'duel_counter_zero' => 15,
    'random' => 10
];

$activeDuelWeights = $APP_SETTINGS['duel_category_weights'] ?? $defaultDuelWeights;
$activeDuelWeights = array_merge($defaultDuelWeights, $activeDuelWeights);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    $APP_SETTINGS['lastfm_api_key'] = trim($_POST['lastfm_api_key']);
    $APP_SETTINGS['listenbrainz_api_key'] = trim($_POST['listenbrainz_api_key']);
    $APP_SETTINGS['listenbrainz_username'] = trim($_POST['listenbrainz_username']);
    $APP_SETTINGS['gemini_api_key'] = trim($_POST['gemini_api_key']);
    $APP_SETTINGS['openai_api_key'] = trim($_POST['openai_api_key']);
    
    $APP_SETTINGS['ai_provider'] = $_POST['ai_provider'];
    $APP_SETTINGS['gemini_model'] = $_POST['gemini_model'];
    $APP_SETTINGS['openai_model'] = $_POST['openai_model'];
    
    $APP_SETTINGS['nerd_comments_enabled'] = isset($_POST['nerd_comments_enabled']);
    $APP_SETTINGS['import_min_plays'] = (int)$_POST['import_min_plays'];

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

    $APP_SETTINGS['duel_category_weights'] = $normalized;
    $activeDuelWeights = $normalized;

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

    <form method="POST" id="settings-form">
        <input type="hidden" name="action" value="save_settings">

        <h3>🔌 API Keys</h3>
        <label>Last.fm API Key:</label>
        <input type="text" name="lastfm_api_key" value="<?= htmlspecialchars($APP_SETTINGS['lastfm_api_key']) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;">

        <label>Listenbrainz API Key:</label>
        <input type="text" name="listenbrainz_api_key" value="<?= htmlspecialchars($APP_SETTINGS['listenbrainz_api_key']) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;">

        <label>Listenbrainz Username:</label>
        <input type="text" name="listenbrainz_username" value="<?= htmlspecialchars($APP_SETTINGS['listenbrainz_username']) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;" placeholder="Your ListenBrainz username">

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

        <h3 style="margin-top: 30px;">⚔️ Duel Category Weights</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0;">Adjust matchmaking probabilities. Total is always 100%. Lock a slider to keep it fixed while the others move.</p>
        <div id="duel-weight-editor" style="border: 1px solid var(--border); border-radius: 8px; padding: 15px;">
            <?php foreach ($duelWeightLabels as $key => $label): ?>
                <div class="duel-weight-row" data-key="<?= htmlspecialchars($key) ?>" style="display: grid; grid-template-columns: minmax(220px, 1.6fr) 2fr auto auto; align-items: center; gap: 10px; margin-bottom: 10px;">
                    <label for="weight-<?= htmlspecialchars($key) ?>" style="font-size: 0.9rem;"><?= htmlspecialchars($label) ?></label>
                    <input type="range" id="weight-<?= htmlspecialchars($key) ?>" min="0" max="100" step="1" value="<?= (int)$activeDuelWeights[$key] ?>" data-weight-slider>
                    <span style="min-width: 45px; text-align: right; font-weight: bold;" data-weight-value><?= (int)$activeDuelWeights[$key] ?>%</span>
                    <label style="font-size: 0.8rem; display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" data-weight-lock>
                        lock
                    </label>
                    <input type="hidden" name="duel_category_weights[<?= htmlspecialchars($key) ?>]" value="<?= (int)$activeDuelWeights[$key] ?>" data-weight-hidden>
                </div>
            <?php endforeach; ?>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 10px;">Total: <strong id="weight-total">100%</strong></div>
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

<script>
(() => {
    const rows = Array.from(document.querySelectorAll('.duel-weight-row'));
    if (!rows.length) return;

    const totalLabel = document.getElementById('weight-total');

    const getState = () => rows.map((row) => ({
        row,
        slider: row.querySelector('[data-weight-slider]'),
        valueLabel: row.querySelector('[data-weight-value]'),
        hidden: row.querySelector('[data-weight-hidden]'),
        lock: row.querySelector('[data-weight-lock]')
    }));

    const writeValues = (state, values) => {
        state.forEach((item, index) => {
            const value = Math.max(0, Math.min(100, Math.round(values[index])));
            item.slider.value = value;
            item.hidden.value = value;
            item.valueLabel.textContent = `${value}%`;
        });
        const sum = values.reduce((acc, v) => acc + Math.round(v), 0);
        totalLabel.textContent = `${sum}%`;
    };

    const rebalance = (changedIndex) => {
        const state = getState();
        const values = state.map((item) => Number(item.slider.value));
        const locked = state.map((item) => item.lock.checked);

        const lockedSum = values.reduce((acc, value, index) => acc + (locked[index] ? value : 0), 0);
        let available = Math.max(0, 100 - lockedSum);

        if (locked[changedIndex]) {
            const unlockedIndices = state.map((_, i) => i).filter((i) => !locked[i]);
            if (unlockedIndices.length === 0) {
                values[changedIndex] = Math.min(100, values[changedIndex]);
                writeValues(state, values);
                return;
            }
            const each = available / unlockedIndices.length;
            unlockedIndices.forEach((i) => { values[i] = each; });
            writeValues(state, finalize(values, locked));
            return;
        }

        const unlockedIndices = state.map((_, i) => i).filter((i) => !locked[i]);
        const others = unlockedIndices.filter((i) => i !== changedIndex);
        values[changedIndex] = Math.max(0, Math.min(available, values[changedIndex]));
        let remaining = Math.max(0, available - values[changedIndex]);

        if (others.length === 0) {
            values[changedIndex] = available;
            writeValues(state, finalize(values, locked));
            return;
        }

        const sumOthers = others.reduce((acc, i) => acc + values[i], 0);
        if (sumOthers <= 0) {
            const each = remaining / others.length;
            others.forEach((i) => { values[i] = each; });
        } else {
            others.forEach((i) => {
                values[i] = (values[i] / sumOthers) * remaining;
            });
        }

        writeValues(state, finalize(values, locked));
    };

    const finalize = (values, locked) => {
        const rounded = values.map((v) => Math.max(0, Math.min(100, Math.round(v))));
        let diff = 100 - rounded.reduce((a, b) => a + b, 0);
        const candidates = rounded.map((_, i) => i).filter((i) => !locked[i]);

        if (!candidates.length && diff !== 0) {
            rounded[rounded.length - 1] = Math.max(0, Math.min(100, rounded[rounded.length - 1] + diff));
            diff = 0;
        }

        let cursor = 0;
        while (diff !== 0 && candidates.length > 0) {
            const idx = candidates[cursor % candidates.length];
            if (diff > 0 && rounded[idx] < 100) {
                rounded[idx] += 1;
                diff -= 1;
            } else if (diff < 0 && rounded[idx] > 0) {
                rounded[idx] -= 1;
                diff += 1;
            }
            cursor++;
            if (cursor > 500) break;
        }

        return rounded;
    };

    rows.forEach((row, index) => {
        const slider = row.querySelector('[data-weight-slider]');
        const lock = row.querySelector('[data-weight-lock]');
        slider.addEventListener('input', () => rebalance(index));
        lock.addEventListener('change', () => rebalance(index));
    });

    rebalance(rows.length - 1);
})();
</script>

<?php require_once 'includes/footer.php'; ?>
