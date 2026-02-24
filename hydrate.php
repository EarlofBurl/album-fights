<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';
require_once 'includes/api_manager.php';
require_once 'includes/header.php';

$albums = loadCsv(FILE_ELO);
$total = count($albums);
$batchSize = 10;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

echo "<div style='width: 100%; max-width: 800px; margin: 0 auto; background: var(--card-bg); padding: 30px; border-radius: 12px; border: 1px solid var(--border);'>";

if ($offset >= $total) {
    echo "<h2 style='color: #4CAF50;'>✅ Hydration abgeschlossen!</h2>";
    echo "<p>Alle $total Alben wurden erfolgreich mit Metadaten (Genres & Jahren) ausgestattet.</p>";
    echo "<a href='stats.php' class='btn' style='display:inline-block; text-decoration:none; width:auto; margin-top: 20px;'>➡️ Zurück zu den Stats</a>";
    echo "</div>";
    require_once 'includes/footer.php';
    exit;
}

$currentBatch = array_slice($albums, $offset, $batchSize);
$nextOffset = $offset + $batchSize;
$progress = round(($offset / $total) * 100);

echo "<h2>📡 Lade Metadaten... ($progress%)</h2>";
echo "<p style='color: var(--text-muted);'>Verarbeite Alben " . ($offset + 1) . " bis " . min($nextOffset, $total) . " von $total.<br>Bitte das Fenster geöffnet lassen, es lädt automatisch neu.</p>";

echo "<ul style='list-style: none; padding: 0;'>";

foreach ($currentBatch as $album) {
    // getAlbumData macht die Abfragen und speichert das JSON im Cache
    $info = getAlbumData($album['Artist'], $album['Album']);
    
    $yearText = !empty($info['year']) ? "📅 " . $info['year'] : "⚠️ Kein Jahr gefunden";
    $genreText = !empty($info['genres']) ? "🏷️ " . implode(", ", $info['genres']) : "⚠️ Keine Genres";
    
    echo "<li style='padding: 10px; border-bottom: 1px solid var(--border);'>";
    echo "<strong style='color: var(--accent);'>{$album['Artist']}</strong> - {$album['Album']}<br>";
    echo "<small style='color: #888;'>$yearText | $genreText</small>";
    echo "</li>";
    
    // Eine halbe Sekunde Pause pro Album, damit wir nicht von Last.fm gebannt werden
    usleep(500000); 
}

echo "</ul>";

echo "<div style='margin-top: 20px; color: var(--warning);'>Lade nächsten Batch in 2 Sekunden... ⏳</div>";
echo "<script>setTimeout(function(){ window.location.href = '?offset=$nextOffset'; }, 2000);</script>";
echo "</div>";

require_once 'includes/footer.php';
?>