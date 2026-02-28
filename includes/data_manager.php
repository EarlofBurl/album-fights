<?php
function calculateWinLossRatio($wins, $losses) {
    $wins = (int)$wins;
    $losses = (int)$losses;

    if ($losses === 0) {
        return $wins > 0 ? 'INF' : '0.00';
    }

    return number_format($wins / $losses, 2, '.', '');
}

function normalizeAlbumRow(array $row): array {
    $wins = (int)($row['Wins'] ?? 0);
    $losses = (int)($row['Losses'] ?? 0);

    return [
        'Artist' => $row['Artist'] ?? 'Unknown',
        'Album' => $row['Album'] ?? 'Unknown',
        'Elo' => (float)($row['Elo'] ?? 1200),
        'Duels' => (int)($row['Duels'] ?? 0),
        'Playcount' => (int)($row['Playcount'] ?? 0),
        'Wins' => $wins,
        'Losses' => $losses,
        'Ratio' => $row['Ratio'] ?? calculateWinLossRatio($wins, $losses)
    ];
}

function loadCsv($filename) {
    $data = [];

    if (file_exists($filename) && ($handle = fopen($filename, "r")) !== false) {
        $headers = fgetcsv($handle, 0, ',', '"', '');
        $headerMap = [];
        if (is_array($headers)) {
            foreach ($headers as $idx => $header) {
                $headerMap[strtolower(trim((string)$header))] = $idx;
            }
        }

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) >= 5) {
                if (!empty($headerMap)) {
                    $wins = (int)($row[$headerMap['wins'] ?? -1] ?? 0);
                    $losses = (int)($row[$headerMap['losses'] ?? -1] ?? 0);
                    $ratio = $row[$headerMap['ratio'] ?? -1] ?? calculateWinLossRatio($wins, $losses);

                    $data[] = normalizeAlbumRow([
                        'Artist' => $row[$headerMap['artist'] ?? 0] ?? 'Unknown',
                        'Album' => $row[$headerMap['album'] ?? 1] ?? 'Unknown',
                        'Elo' => $row[$headerMap['elo'] ?? 2] ?? 1200,
                        'Duels' => $row[$headerMap['duels'] ?? 3] ?? 0,
                        'Playcount' => $row[$headerMap['playcount'] ?? 4] ?? 0,
                        'Wins' => $wins,
                        'Losses' => $losses,
                        'Ratio' => $ratio,
                    ]);
                } else {
                    $data[] = normalizeAlbumRow([
                        'Artist' => $row[0] ?? 'Unknown',
                        'Album' => $row[1] ?? 'Unknown',
                        'Elo' => $row[2] ?? 1200,
                        'Duels' => $row[3] ?? 0,
                        'Playcount' => $row[4] ?? 0,
                    ]);
                }
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

    fputcsv($handle, ['Artist', 'Album', 'Elo', 'Duels', 'Playcount', 'Wins', 'Losses', 'Ratio'], ',', '"', '');

    foreach ($data as $row) {
        $normalized = normalizeAlbumRow($row);

        $artist = $normalized['Artist'];
        $album = $normalized['Album'];
        $elo = $normalized['Elo'];
        $duels = $normalized['Duels'];
        $playcount = $normalized['Playcount'];
        $wins = $normalized['Wins'];
        $losses = $normalized['Losses'];
        $ratio = calculateWinLossRatio($wins, $losses);

        fputcsv($handle, [$artist, $album, $elo, $duels, $playcount, $wins, $losses, $ratio], ',', '"', '');
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

function moveToQueue($artist, $album, $elo, $duels, $playcount, $wins = 0, $losses = 0) {
    $queue = loadCsv(FILE_QUEUE);
    $queue[] = normalizeAlbumRow([
        'Artist' => $artist,
        'Album' => $album,
        'Elo' => $elo,
        'Duels' => $duels,
        'Playcount' => $playcount,
        'Wins' => $wins,
        'Losses' => $losses,
    ]);
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
