<?php
function loadCsv($filename) {
    $data = [];
    if (file_exists($filename) && ($handle = fopen($filename, "r")) !== FALSE) {
        $headers = fgetcsv($handle); 
        while (($row = fgetcsv($handle)) !== FALSE) {
            if (count($row) >= 5) {
                $data[] = [
                    'Artist' => $row[0] ?? 'Unknown', 
                    'Album' => $row[1] ?? 'Unknown',
                    'Elo' => (float)($row[2] ?? 1200), 
                    'Duels' => (int)($row[3] ?? 0), 
                    'Playcount' => (int)($row[4] ?? 0)
                ];
            }
        }
        fclose($handle);
    }
    return $data;
}

function saveCsv($filename, $data) {
    $handle = fopen($filename, "w");
    fputcsv($handle, ['Artist', 'Album', 'Elo', 'Duels', 'Playcount']);
    foreach ($data as $row) {
        // Ensure all keys exist before writing
        $artist = $row['Artist'] ?? 'Unknown';
        $album = $row['Album'] ?? 'Unknown';
        $elo = $row['Elo'] ?? 1200;
        $duels = $row['Duels'] ?? 0;
        $playcount = $row['Playcount'] ?? 0;
        fputcsv($handle, [$artist, $album, $elo, $duels, $playcount]);
    }
    fclose($handle);
}

function moveToQueue($artist, $album, $elo, $duels, $playcount) {
    $queue = loadCsv(FILE_QUEUE);
    $queue[] = ['Artist' => $artist, 'Album' => $album, 'Elo' => $elo, 'Duels' => $duels, 'Playcount' => $playcount];
    saveCsv(FILE_QUEUE, $queue);
}

function removeFromEloState($index) {
    $albums = loadCsv(FILE_ELO);
    if (isset($albums[$index])) {
        array_splice($albums, $index, 1);
        saveCsv(FILE_ELO, $albums);
    }
}

function getTopAlbums($albums, $limit = 100) {
    usort($albums, function($a, $b) { return $b['Elo'] <=> $a['Elo']; });
    return array_slice($albums, 0, $limit);
}

function loadSnapshot() {
    $file = DIR_DATA . 'top100_snapshot.json';
    return file_exists($file) ? json_decode(file_get_contents($file), true) : [];
}

function saveSnapshot($top100) {
    file_put_contents(DIR_DATA . 'top100_snapshot.json', json_encode($top100));
}