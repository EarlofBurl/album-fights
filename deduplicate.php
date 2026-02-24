<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';
require_once 'includes/header.php';

$albums = loadCsv(FILE_ELO);
$merged = [];

// Muster, die wir aus dem Albumtitel entfernen wollen
$patterns = [
    '/\s*\(Remastered\)/i',
    '/\s*\(Deluxe.*Edition\)/i',
    '/\s*\(Super Deluxe.*\)/i',
    '/\s*\(Anniversary.*Edition\)/i',
    '/\s*\[Remastered\]/i',
    '/\s*\(Special Edition\)/i',
    '/\s*\(20\d{2} Remaster\)/i'
];

foreach ($albums as $a) {
    $cleanAlbum = trim(preg_replace($patterns, '', $a['Album']));
    $key = $a['Artist'] . "|||" . $cleanAlbum;

    if (!isset($merged[$key])) {
        $merged[$key] = [
            'Artist' => $a['Artist'],
            'Album' => $cleanAlbum,
            'EloSum' => 0,
            'WeightSum' => 0,
            'Duels' => 0,
            'Playcount' => 0
        ];
    }

    // Gewichtung: Duelle + 1 (damit auch 0-Duel-Alben zählen, aber weniger)
    $weight = $a['Duels'] + 1;
    $merged[$key]['EloSum'] += ($a['Elo'] * $weight);
    $merged[$key]['WeightSum'] += $weight;
    $merged[$key]['Duels'] += $a['Duels'];
    $merged[$key]['Playcount'] += $a['Playcount'];
}

$finalData = [];
foreach ($merged as $m) {
    $finalData[] = [
        'Artist' => $m['Artist'],
        'Album' => $m['Album'],
        'Elo' => round($m['EloSum'] / $m['WeightSum'], 2),
        'Duels' => $m['Duels'],
        'Playcount' => $m['Playcount']
    ];
}

$removedCount = count($albums) - count($finalData);

if ($removedCount > 0) {
    // Backup zur Sicherheit
    copy(FILE_ELO, DIR_DATA . 'elo_before_dedup_' . date('Ymd_His') . '.csv');
    saveCsv(FILE_ELO, $finalData);
}

echo "<div style='width: 100%; max-width: 800px; margin: 0 auto; background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border);'>";
echo "<h2>🧬 Deduplication Report</h2>";
echo "<p>Alben bereinigt: <strong style='color: var(--accent);'>$removedCount</strong></p>";
echo "<p style='color: var(--text-muted);'>Die Einträge wurden nach Artist und bereinigtem Albumtitel gruppiert. Elo-Werte wurden gewichtet gemittelt.</p>";
echo "<a href='index.php' class='btn' style='display:inline-block; text-decoration:none; width:auto; margin-top: 20px;'>➡️ Zurück zum Duell</a>";
echo "</div>";

require_once 'includes/footer.php';