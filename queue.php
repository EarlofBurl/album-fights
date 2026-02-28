<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';

// ==========================================
// HANDLE ACTIONS (Restore, Delete)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $idx = (int)$_POST['targetIdx'];
    
    $queue = loadCsv(FILE_QUEUE);
    
    if (isset($queue[$idx])) {
        // If Restore: Copy back into elo_state.csv first
        if ($action === 'restore') {
            $eloData = loadCsv(FILE_ELO);
            $eloData[] = $queue[$idx];
            saveCsv(FILE_ELO, $eloData);
        }
        
        // In both cases (Restore & Delete) the album leaves the queue
        array_splice($queue, $idx, 1);
        saveCsv(FILE_QUEUE, $queue);
    }
    
    header("Location: queue.php");
    exit;
}

$queue = loadCsv(FILE_QUEUE);

// ==========================================
// RENDER HTML
// ==========================================
require_once 'includes/header.php';
?>

<div style="width: 100%; max-width: 1000px; margin: 0 auto; background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border);">
    <h2 style="margin-top: 0;">🎧 Listening Queue</h2>
    <p style="color: var(--text-muted); margin-bottom: 30px;">
        Albums you need to give another listen before they face the tough duel land here.
    </p>

    <?php if (empty($queue)): ?>
        <p style="text-align: center; color: #666; font-style: italic; padding: 40px 0;">
            The queue is currently empty.
        </p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid var(--border);">
                    <th style="padding: 15px 10px; text-align: left; color: var(--text-muted);">Artist</th>
                    <th style="padding: 15px 10px; text-align: left; color: var(--text-muted);">Album</th>
                    <th style="padding: 15px 10px; text-align: left; color: var(--text-muted);">Stats</th>
                    <th style="padding: 15px 10px; text-align: right; color: var(--text-muted);">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($queue as $index => $album): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 15px 10px; font-weight: bold; color: var(--accent);">
                            <?= htmlspecialchars($album['Artist']) ?>
                        </td>
                        <td style="padding: 15px 10px;">
                            <?= htmlspecialchars($album['Album']) ?>
                        </td>
                        <td style="padding: 15px 10px; color: var(--text-muted); font-size: 0.9rem;">
                            Elo: <?= round((float)$album['Elo']) ?> | Duels: <?= (int)$album['Duels'] ?><br>
                            W/L: <?= (int)($album['Wins'] ?? 0) ?>/<?= (int)($album['Losses'] ?? 0) ?> (<?= htmlspecialchars(calculateWinLossRatio($album['Wins'] ?? 0, $album['Losses'] ?? 0)) ?>)
                        </td>
                        <td style="padding: 15px 10px; text-align: right;">
                            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="restore">
                                    <input type="hidden" name="targetIdx" value="<?= $index ?>">
                                    <button type="submit" class="btn-small" style="background-color: #4CAF50; color: white; width: 140px;" title="Back to duels">
                                        🔙 Restore
                                    </button>
                                </form>
                                <form method="POST" style="margin: 0;" onsubmit="return confirm('Do you really want to delete this album from the database forever?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="targetIdx" value="<?= $index ?>">
                                    <button type="submit" class="btn-small btn-delete" style="width: 120px;" title="Delete completely">
                                        🗑️ Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>