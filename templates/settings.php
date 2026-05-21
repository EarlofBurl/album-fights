<?php
/**
 * @var string $csrfField
 * @var string $message
 * @var array<string, mixed> $all
 * @var array<string, string> $duelWeightLabels
 * @var array<string, int> $activeDuelWeights
 */

require __DIR__ . '/partials/header.php';
?>

<div style="width: 100%; max-width: 800px; margin: 0 auto; background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border);">
    <h2 style="margin-top: 0;">⚙️ App Settings</h2>

    <?php if ($message): ?>
        <div style="background: #4CAF50; color: white; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <form method="POST" id="settings-form">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="save_settings">

        <h3>🔌 API Keys</h3>
        <label>Last.fm API Key:</label>
        <input type="text" name="lastfm_api_key" value="<?= htmlspecialchars((string)($all['lastfm_api_key'] ?? '')) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;">

        <label>Listenbrainz API Key:</label>
        <input type="text" name="listenbrainz_api_key" value="<?= htmlspecialchars((string)($all['listenbrainz_api_key'] ?? '')) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;">

        <label>Listenbrainz Username:</label>
        <input type="text" name="listenbrainz_username" value="<?= htmlspecialchars((string)($all['listenbrainz_username'] ?? '')) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;" placeholder="Your ListenBrainz username">

        <h3>🏠 Navidrome / Subsonic</h3>
        <label>Subsonic Base URL:</label>
        <input type="text" name="subsonic_base_url" value="<?= htmlspecialchars((string)($all['subsonic_base_url'] ?? '')) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;" placeholder="http://navidrome.local:4533">

        <label>Subsonic Username:</label>
        <input type="text" name="subsonic_username" value="<?= htmlspecialchars((string)($all['subsonic_username'] ?? '')) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;" placeholder="Your Navidrome username">

        <label>Subsonic Password / API Token:</label>
        <input type="password" name="subsonic_password" value="<?= htmlspecialchars((string)($all['subsonic_password'] ?? '')) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;" placeholder="Password or token">

        <h3>🤖 AI Nerd Commentator</h3>
        <label>
            <input type="checkbox" name="nerd_comments_enabled" <?= !empty($all['nerd_comments_enabled']) ? 'checked' : '' ?>>
            Enable AI Nerd Comments (Triggered every 25 duels on the Duel page)
        </label>
        <br><br>

        <div style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <label>Active Provider:</label>
                <select name="ai_provider" style="width: 100%; padding: 8px; margin-bottom: 15px;">
                    <option value="gemini" <?= ($all['ai_provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>Google Gemini</option>
                    <option value="openai" <?= ($all['ai_provider'] ?? '') === 'openai' ? 'selected' : '' ?>>OpenAI</option>
                </select>
            </div>
        </div>

        <div style="display: flex; gap: 20px;">
            <div style="flex: 1; border: 1px solid var(--border); padding: 15px; border-radius: 8px;">
                <h4 style="margin-top:0;">Gemini Configuration</h4>
                <label>Gemini API Key:</label>
                <input type="password" name="gemini_api_key" value="<?= htmlspecialchars((string)($all['gemini_api_key'] ?? '')) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;">
                <label>Gemini Model:</label>
                <select name="gemini_model" style="width: 100%; padding: 8px;">
                    <option value="gemini-3-flash-preview" <?= ($all['gemini_model'] ?? '') === 'gemini-3-flash-preview' ? 'selected' : '' ?>>gemini-3-flash-preview (Fast & Cheap)</option>
                    <option value="gemini-1.5-pro" <?= ($all['gemini_model'] ?? '') === 'gemini-1.5-pro' ? 'selected' : '' ?>>gemini-1.5-pro (Legacy Pro)</option>
                </select>
            </div>
            <div style="flex: 1; border: 1px solid var(--border); padding: 15px; border-radius: 8px;">
                <h4 style="margin-top:0;">OpenAI Configuration</h4>
                <label>OpenAI API Key:</label>
                <input type="password" name="openai_api_key" value="<?= htmlspecialchars((string)($all['openai_api_key'] ?? '')) ?>" style="width: 100%; padding: 8px; margin-bottom: 15px;">
                <label>OpenAI Model:</label>
                <select name="openai_model" style="width: 100%; padding: 8px;">
                    <option value="gpt-4o-mini" <?= ($all['openai_model'] ?? '') === 'gpt-4o-mini' ? 'selected' : '' ?>>gpt-4o-mini (Fast & Cheap)</option>
                    <option value="gpt-4o" <?= ($all['openai_model'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>gpt-4o (Smart & Expensive)</option>
                </select>
            </div>
        </div>

        <h3 style="margin-top: 30px;">⚔️ Duel Category Weights</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0;">Adjust matchmaking probabilities. Total is always 100%. Lock a slider to keep it fixed while the others move.</p>
        
        <!-- Presets Toolbar -->
        <div class="weight-presets-container">
            <span style="font-size: 0.9rem; font-weight: bold; color: var(--text-muted); align-self: center; margin-right: 8px;">Presets:</span>
            <button type="button" class="btn-preset" data-preset="default">Balanced (Default)</button>
            <button type="button" class="btn-preset" data-preset="equal">Equal Split</button>
            <button type="button" class="btn-preset" data-preset="discovery">Discovery Focus</button>
            <button type="button" class="btn-preset" data-preset="championship">Championship</button>
            <button type="button" class="btn-preset" data-preset="chaos">Chaos</button>
        </div>

        <!-- Weight Stacked Visualizer -->
        <div class="weight-visualizer-bar" id="weight-visualizer"></div>

        <div id="duel-weight-editor" style="border: 1px solid var(--border); border-radius: 8px; padding: 15px;">
            <?php 
            $colors = [
                'top_25_vs'         => '#ff5e62',
                'top_50_vs'         => '#ff9966',
                'top_100_vs'        => '#ff5e97',
                'playcount_gt_20'   => '#4ca1af',
                'duel_counter_zero' => '#2ecc71',
                'random'            => '#bb86fc',
            ];
            foreach ($duelWeightLabels as $key => $label): 
                $color = $colors[$key] ?? '#bb86fc';
            ?>
                <div class="duel-weight-row" data-key="<?= htmlspecialchars($key) ?>" data-color="<?= htmlspecialchars($color) ?>" style="display: grid; grid-template-columns: minmax(200px, 1.8fr) auto 2.2fr auto 55px auto; align-items: center; gap: 12px; margin-bottom: 12px;">
                    <label for="weight-<?= htmlspecialchars($key) ?>" style="font-size: 0.9rem; font-weight: 500; cursor: pointer;"><?= htmlspecialchars($label) ?></label>
                    
                    <button type="button" class="weight-adjust-btn" data-adjust="-1" data-row-btn>&minus;</button>
                    
                    <input type="range" id="weight-<?= htmlspecialchars($key) ?>" min="0" max="100" step="1" value="<?= (int)($activeDuelWeights[$key] ?? 0) ?>" data-weight-slider>
                    
                    <button type="button" class="weight-adjust-btn" data-adjust="1" data-row-btn>&plus;</button>
                    
                    <span class="weight-number-display" style="text-align: right; font-weight: bold; font-family: monospace; font-size: 1rem;" data-weight-value><?= (int)($activeDuelWeights[$key] ?? 0) ?>%</span>
                    
                    <button type="button" class="weight-lock-toggle" title="Lock weight" data-weight-lock-btn>
                        🔓
                    </button>
                    
                    <input type="checkbox" data-weight-lock style="display: none;">
                    <input type="hidden" name="duel_category_weights[<?= htmlspecialchars($key) ?>]" value="<?= (int)($activeDuelWeights[$key] ?? 0) ?>" data-weight-hidden>
                </div>
            <?php endforeach; ?>
            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 10px;">Total: <strong id="weight-total">100%</strong></div>
        </div>

        <h3 style="margin-top: 30px;">📥 Import Settings</h3>
        <div style="display: flex; gap: 20px;">
            <div style="flex: 1;">
                <label><strong>Min. Plays needed for Last.fm / CSV Import:</strong></label>
                <input type="number" name="import_min_plays" value="<?= (int)($all['import_min_plays'] ?? 8) ?>" style="width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 15px;">
            </div>
        </div>

        <button type="submit" class="btn-small" style="background-color: var(--accent); color: #000; font-size: 1.1rem; padding: 10px 20px; cursor: pointer; border: none; border-radius: 5px; margin-top: 20px;">
            💾 Save Settings
        </button>
    </form>
</div>

<script>
(() => {
    const PRESETS = {
        default: { top_25_vs: 20, top_50_vs: 20, top_100_vs: 20, playcount_gt_20: 15, duel_counter_zero: 15, random: 10 },
        equal: { top_25_vs: 17, top_50_vs: 17, top_100_vs: 16, playcount_gt_20: 17, duel_counter_zero: 17, random: 16 },
        discovery: { top_25_vs: 5, top_50_vs: 5, top_100_vs: 10, playcount_gt_20: 30, duel_counter_zero: 40, random: 10 },
        championship: { top_25_vs: 40, top_50_vs: 30, top_100_vs: 20, playcount_gt_20: 5, duel_counter_zero: 5, random: 0 },
        chaos: { top_25_vs: 5, top_50_vs: 5, top_100_vs: 5, playcount_gt_20: 5, duel_counter_zero: 5, random: 75 }
    };

    const rows = Array.from(document.querySelectorAll('.duel-weight-row'));
    if (!rows.length) return;
    
    const totalLabel = document.getElementById('weight-total');
    
    const getState = () => rows.map((row) => ({
        row,
        key: row.dataset.key,
        color: row.dataset.color,
        label: row.querySelector('label').textContent.split('(')[0].trim(), // Short label for visualizer
        slider: row.querySelector('[data-weight-slider]'),
        valueLabel: row.querySelector('[data-weight-value]'),
        hidden: row.querySelector('[data-weight-hidden]'),
        lockCheckbox: row.querySelector('[data-weight-lock]'),
        lockBtn: row.querySelector('[data-weight-lock-btn]'),
        btnMinus: row.querySelector('[data-adjust="-1"]'),
        btnPlus: row.querySelector('[data-adjust="1"]')
    }));

    const updateVisualizer = (state, values) => {
        const visualizer = document.getElementById('weight-visualizer');
        if (!visualizer) return;
        visualizer.innerHTML = '';
        state.forEach((item, index) => {
            const val = Math.round(values[index]);
            if (val <= 0) return;
            const segment = document.createElement('div');
            segment.className = 'weight-visualizer-segment';
            segment.style.width = `${val}%`;
            segment.style.backgroundColor = item.color;
            if (val >= 8) {
                segment.textContent = `${val}%`;
            }
            segment.title = `${item.label}: ${val}%`;
            
            // Interaction: hover segment highlights row
            segment.addEventListener('mouseenter', () => {
                item.row.style.boxShadow = `0 0 10px ${item.color}33`;
                item.row.style.borderColor = item.color;
            });
            segment.addEventListener('mouseleave', () => {
                item.row.style.boxShadow = '';
                item.row.style.borderColor = '';
            });
            
            visualizer.appendChild(segment);
        });
    };

    const writeValues = (state, values) => {
        state.forEach((item, index) => {
            const value = Math.max(0, Math.min(100, Math.round(values[index])));
            item.slider.value = value;
            item.hidden.value = value;
            item.valueLabel.textContent = `${value}%`;
            
            // Dynamic track gradient
            const color = item.color;
            if (item.lockCheckbox.checked) {
                item.slider.style.background = '#2a2a2a';
            } else {
                item.slider.style.background = `linear-gradient(to right, ${color} 0%, ${color} ${value}%, #2a2a2a ${value}%, #2a2a2a 100%)`;
            }
        });
        
        const sum = values.reduce((acc, v) => acc + Math.round(v), 0);
        totalLabel.textContent = `${sum}%`;

        // Update visualizer
        updateVisualizer(state, values);

        // Check for matching preset
        let activePreset = null;
        Object.entries(PRESETS).forEach(([key, preset]) => {
            const matches = state.every(item => {
                const val = Math.round(Number(item.slider.value));
                return preset[item.key] === val;
            });
            if (matches) {
                activePreset = key;
            }
        });
        
        document.querySelectorAll('.btn-preset').forEach(btn => {
            if (btn.dataset.preset === activePreset) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
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
            if (diff > 0 && rounded[idx] < 100) { rounded[idx] += 1; diff -= 1; }
            else if (diff < 0 && rounded[idx] > 0) { rounded[idx] -= 1; diff += 1; }
            cursor++;
            if (cursor > 500) break;
        }
        return rounded;
    };

    const rebalance = (changedIndex) => {
        const state = getState();
        const values = state.map((item) => Number(item.slider.value));
        const locked = state.map((item) => item.lockCheckbox.checked);
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
            others.forEach((i) => { values[i] = (values[i] / sumOthers) * remaining; });
        }
        writeValues(state, finalize(values, locked));
    };

    // Initialize events
    const state = getState();
    
    rows.forEach((row, index) => {
        const item = state[index];
        
        // Slider drag
        item.slider.addEventListener('input', () => rebalance(index));
        
        // Fine-tuning buttons
        item.btnMinus.addEventListener('click', () => {
            if (item.lockCheckbox.checked) return;
            const val = Number(item.slider.value);
            if (val > 0) {
                item.slider.value = val - 1;
                rebalance(index);
            }
        });
        
        item.btnPlus.addEventListener('click', () => {
            if (item.lockCheckbox.checked) return;
            const val = Number(item.slider.value);
            if (val < 100) {
                item.slider.value = val + 1;
                rebalance(index);
            }
        });
        
        // Padlock lock button
        item.lockBtn.addEventListener('click', () => {
            const isLocked = !item.lockCheckbox.checked;
            
            // Prevent locking the last unlocked slider
            if (isLocked) {
                const lockedCount = state.filter(s => s.lockCheckbox.checked).length;
                if (lockedCount >= state.length - 1) {
                    return;
                }
            }
            
            item.lockCheckbox.checked = isLocked;
            if (isLocked) {
                item.lockBtn.classList.add('active');
                item.lockBtn.textContent = '🔒';
                item.row.classList.add('locked');
                item.slider.disabled = true;
            } else {
                item.lockBtn.classList.remove('active');
                item.lockBtn.textContent = '🔓';
                item.row.classList.remove('locked');
                item.slider.disabled = false;
            }
            rebalance(index);
        });
    });

    // Preset click handlers
    document.querySelectorAll('.btn-preset').forEach(btn => {
        btn.addEventListener('click', () => {
            const presetKey = btn.dataset.preset;
            const preset = PRESETS[presetKey];
            if (!preset) return;
            
            state.forEach(item => {
                item.lockCheckbox.checked = false;
                item.lockBtn.classList.remove('active');
                item.lockBtn.textContent = '🔓';
                item.row.classList.remove('locked');
                item.slider.disabled = false;
                
                const val = preset[item.key] ?? 0;
                item.slider.value = val;
            });
            rebalance(0);
        });
    });

    // Run first rebalance to set backgrounds/visualizer on load
    rebalance(rows.length - 1);
})();
</script>

<?php require __DIR__ . '/partials/footer.php'; ?>
