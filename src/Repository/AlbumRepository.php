<?php
declare(strict_types=1);

namespace App\Repository;

use App\Core\Config;
use App\Utils\CsvHelper;

class AlbumRepository
{
    public function __construct(private Config $config)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loadElo(): array
    {
        return CsvHelper::read($this->config->getEloFile());
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    public function saveElo(array $data): void
    {
        CsvHelper::write($this->config->getEloFile(), $data);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function loadQueue(): array
    {
        return CsvHelper::read($this->config->getQueueFile());
    }

    /**
     * @param list<array<string, mixed>> $data
     */
    public function saveQueue(array $data): void
    {
        CsvHelper::write($this->config->getQueueFile(), $data);
    }

    /**
     * @return array<string, true>
     */
    public function buildExistingLookup(): array
    {
        $existing = [];
        foreach (array_merge($this->loadElo(), $this->loadQueue()) as $row) {
            $key = strtolower(trim((string)$row['Artist']) . '_' . trim((string)$row['Album']));
            $existing[$key] = true;
        }
        return $existing;
    }

    public function moveToQueue(
        string $artist,
        string $album,
        float $elo,
        int $duels,
        int $playcount,
        int $wins = 0,
        int $losses = 0
    ): void {
        $queue = $this->loadQueue();
        $queue[] = CsvHelper::normalizeRow([
            'Artist'    => $artist,
            'Album'     => $album,
            'Elo'       => $elo,
            'Duels'     => $duels,
            'Playcount' => $playcount,
            'Wins'      => $wins,
            'Losses'    => $losses,
        ]);
        $this->saveQueue($queue);
    }

    /**
     * @param list<array<string, mixed>>|null $albums Pre-loaded album data to avoid redundant CSV reads
     * @return list<array<string, mixed>>
     */
    public function getTop(int $limit = 100, ?array $albums = null): array
    {
        $data = $albums ?? $this->loadElo();
        usort($data, fn(array $a, array $b): int => $b['Elo'] <=> $a['Elo']);
        return array_slice($data, 0, $limit);
    }

    public function getTop50Text(): string
    {
        $top = $this->getTop(50);
        $lines = [];
        foreach ($top as $i => $a) {
            $lines[] = ($i + 1) . '. ' . $a['Artist'] . ' - ' . $a['Album'];
        }
        return implode("\n", $lines);
    }
}
