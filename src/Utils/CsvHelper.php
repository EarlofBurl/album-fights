<?php
declare(strict_types=1);

namespace App\Utils;

class CsvHelper
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function read(string $file): array
    {
        $data = [];
        if (!file_exists($file) || ($handle = fopen($file, 'r')) === false) {
            return $data;
        }

        $headers = fgetcsv($handle, 0, ',', '"', '');
        $headerMap = [];
        if (is_array($headers)) {
            foreach ($headers as $idx => $header) {
                $headerMap[strtolower(trim((string)$header))] = $idx;
            }
        }

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) < 5) {
                continue;
            }

            if (!empty($headerMap)) {
                $wins   = (int)($row[$headerMap['wins'] ?? -1] ?? 0);
                $losses = (int)($row[$headerMap['losses'] ?? -1] ?? 0);
                $ratio  = $row[$headerMap['ratio'] ?? -1] ?? self::winLossRatio($wins, $losses);

                $data[] = self::normalizeRow([
                    'Artist'    => $row[$headerMap['artist'] ?? 0] ?? 'Unknown',
                    'Album'     => $row[$headerMap['album'] ?? 1] ?? 'Unknown',
                    'Elo'       => $row[$headerMap['elo'] ?? 2] ?? 1200,
                    'Duels'     => $row[$headerMap['duels'] ?? 3] ?? 0,
                    'Playcount' => $row[$headerMap['playcount'] ?? 4] ?? 0,
                    'Wins'      => $wins,
                    'Losses'    => $losses,
                    'Ratio'     => $ratio,
                ]);
            } else {
                $data[] = self::normalizeRow([
                    'Artist' => $row[0] ?? 'Unknown',
                    'Album'  => $row[1] ?? 'Unknown',
                    'Elo'    => $row[2] ?? 1200,
                    'Duels'  => $row[3] ?? 0,
                    'Playcount' => $row[4] ?? 0,
                ]);
            }
        }

        fclose($handle);
        return $data;
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    public static function write(string $file, array $data): void
    {
        $tmpFile = $file . '.tmp';
        $handle = fopen($tmpFile, 'w');
        if ($handle === false) {
            return;
        }

        fputcsv($handle, ['Artist', 'Album', 'Elo', 'Duels', 'Playcount', 'Wins', 'Losses', 'Ratio'], ',', '"', '');

        foreach ($data as $row) {
            $n = self::normalizeRow($row);
            fputcsv($handle, [
                $n['Artist'],
                $n['Album'],
                $n['Elo'],
                $n['Duels'],
                $n['Playcount'],
                $n['Wins'],
                $n['Losses'],
                self::winLossRatio((int)$n['Wins'], (int)$n['Losses']),
            ], ',', '"', '');
        }

        fclose($handle);

        if (!file_exists($tmpFile) || filesize($tmpFile) <= 0) {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
            return;
        }

        self::rotateBackups($file);

        if (!rename($tmpFile, $file)) {
            unlink($tmpFile);
        }
    }

    private static function rotateBackups(string $file): void
    {
        $bak1 = $file . '.bak1';
        $bak2 = $file . '.bak2';
        $bak3 = $file . '.bak3';

        if (file_exists($bak3)) {
            unlink($bak3);
        }
        if (file_exists($bak2)) {
            rename($bak2, $bak3);
        }
        if (file_exists($bak1)) {
            rename($bak1, $bak2);
        }
        if (file_exists($file)) {
            rename($file, $bak1);
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function normalizeRow(array $row): array
    {
        $wins   = (int)($row['Wins'] ?? 0);
        $losses = (int)($row['Losses'] ?? 0);

        return [
            'Artist'    => $row['Artist'] ?? 'Unknown',
            'Album'     => $row['Album'] ?? 'Unknown',
            'Elo'       => (float)($row['Elo'] ?? 1200),
            'Duels'     => (int)($row['Duels'] ?? 0),
            'Playcount' => (int)($row['Playcount'] ?? 0),
            'Wins'      => $wins,
            'Losses'    => $losses,
            'Ratio'     => self::winLossRatio($wins, $losses),
        ];
    }

    public static function winLossRatio(int $wins, int $losses): string
    {
        if ($losses === 0) {
            return $wins > 0 ? 'INF' : '0.00';
        }
        return number_format($wins / $losses, 2, '.', '');
    }
}
