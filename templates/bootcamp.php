<?php
/**
 * @var string $csrfField
 * @var string $bootcampText
 * @var ?int $lastGeneratedDuels
 * @var bool $hasApiKey
 */

require __DIR__ . '/partials/header.php';
?>

<div style="width: 100%; max-width: 800px; margin: 0 auto; background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border);">
    <h2 style="margin-top: 0; text-align: center;">🧐 The Snob's Assessment</h2>
    <p style="color: var(--text-muted); text-align: center; margin-bottom: 30px;">Ready for a reality check? Submit your current Top 50 albums for an earnest, highly opinionated critique from the ultimate music snob.</p>

    <?php if (!$hasApiKey): ?>
        <div style="background: #cf6679; color: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; font-weight: bold;">
            ⚠️ No API Key found! Please configure your Gemini or OpenAI API key in the <a href="settings.php" style="color: #fff; text-decoration: underline;">Settings</a>.
        </div>
    <?php endif; ?>

    <?php if (!empty($bootcampText)): ?>
        <div style="background: #2c2c2c; padding: 25px; border-radius: 8px; border-left: 5px solid var(--accent); line-height: 1.6; font-size: 1.1rem; margin-bottom: 20px; position: relative;">
            <?php if ($lastGeneratedDuels !== null): ?>
                <span style="position: absolute; top: -12px; right: 20px; background: var(--accent); color: #000; padding: 4px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: bold; box-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                    Generated after <?= $lastGeneratedDuels ?> duels
                </span>
            <?php endif; ?>
            <?= nl2br(htmlspecialchars($bootcampText)) ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="text-align: center;">
        <?= $csrfField ?>
        <input type="hidden" name="action" value="generate">
        <button type="submit" <?= !$hasApiKey ? 'disabled style="opacity: 0.5; cursor: not-allowed;' : 'style="cursor: pointer;' ?> background-color: var(--accent); color: #000; font-size: 1.2rem; padding: 15px 30px; border: none; border-radius: 8px; font-weight: bold;">
            🔥 <?= empty($bootcampText) ? 'Generate Top 50 Assessment' : 'Regenerate Assessment' ?>
        </button>
    </form>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
