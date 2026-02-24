<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';
require_once 'includes/header.php';

$file = FILE_ELO;
$backupFile = DIR_DATA . 'elo_state_backup_' . date('Y-m-d_H-i-s') . '.csv';

echo "<div style='width: 100%; max-width: 800px; margin: 0 auto; background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border);'>";

if (!file_exists($file)) {
    echo "<h2 style='color: var(--danger);'>❌ Fehler: elo_state.csv nicht gefunden!</h2></div>";
    exit;
}

// 1. Daten laden
$oldData = loadCsv($file);
$totalBefore = count($oldData);

// 2. Backup erstellen
if (copy($file, $backupFile)) {
    echo "<p style='color: #4CAF50;'>✅ Sicherheits-Backup erstellt: <br><small>$backupFile</small></p>";
} else {
    echo "<h2 style='color: var(--danger);'>❌ Abbruch: Backup konnte nicht erstellt werden!</h2></div>";
    exit;
}

// 3. Filtern (Nur Alben mit Playcount >= 10 behalten)
$newData = array_filter($oldData, function($album) {
    return (int)$album['Playcount'] >= 10;
});

$totalAfter = count($newData);
$removed = $totalBefore - $totalAfter;

// 4. Speichern
saveCsv($file, $newData);

echo "<h2>🧹 Datenbank-Bereinigung abgeschlossen</h2>";
echo "<ul style='list-style: none; padding: 0; font-size: 1.2rem;'>";
echo "<li style='margin-bottom: 10px;'>Alben vorher: <strong>$totalBefore</strong></li>";
echo "<li style='margin-bottom: 10px;'>Alben gelöscht: <strong style='color: var(--danger);'>$removed</strong></li>";
echo "<li style='margin-bottom: 10px;'>Alben übrig: <strong style='color: var(--accent);'>$totalAfter</strong></li>";
echo "</ul>";

echo "<p style='color: var(--text-muted); margin-top: 20px;'>Der Fokus liegt nun auf deinen relevanten Alben. Die 'Leichen' wurden aus dem Ranking entfernt.</p>";
echo "<a href='index.php' class='btn' style='display:inline-block; text-decoration:none; width:auto; margin-top: 20px;'>➡️ Zurück zum Duell</a>";
echo "</div>";

require_once 'includes/footer.php';
?>