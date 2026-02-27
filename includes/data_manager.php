<?php
function loadCsv($filename) {
    $data = [];

    if (file_exists($filename) && ($handle = fopen($filename, "r")) !== false) {
        $headers = fgetcsv($handle, 0, ',', '"', '');

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
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
    $tmpFile = $filename . '.tmp';
    $handle = fopen($tmpFile, "w");

    if ($handle === false) {
        return;
    }

    fputcsv($handle, ['Artist', 'Album', 'Elo', 'Duels', 'Playcount'], ',', '"', '');

    foreach ($data as $row) {
        $artist = $row['Artist'] ?? 'Unknown';
        $album = $row['Album'] ?? 'Unknown';
        $elo = $row['Elo'] ?? 1200;
        $duels = $row['Duels'] ?? 0;
        $playcount = $row['Playcount'] ?? 0;

        fputcsv($handle, [$artist, $album, $elo, $duels, $playcount], ',', '"', '');
    }

    fclose($handle);

    if (!file_exists($tmpFile) || filesize($tmpFile) <= 0) {
        if (file_exists($tmpFile)) {
            unlink($tmpFile);
        }

        return;
    }

    $bak1 = $filename . '.bak1';
    $bak2 = $filename . '.bak2';
    $bak3 = $filename . '.bak3';

    if (file_exists($bak3)) {
        unlink($bak3);
    }

    if (file_exists($bak2)) {
        rename($bak2, $bak3);
    }

    if (file_exists($bak1)) {
        rename($bak1, $bak2);
    }

    if (file_exists($filename)) {
        rename($filename, $bak1);
    }

    if (!rename($tmpFile, $filename)) {
        unlink($tmpFile);
    }
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
?>
