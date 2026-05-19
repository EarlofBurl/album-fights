<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Repository\AlbumRepository;
use App\Repository\SettingsRepository;

class StatsService
{
    public function __construct(
        private AlbumRepository $albumRepo,
        private SettingsRepository $settings,
        private Config $config
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStats(): array
    {
        $albums = $this->albumRepo->loadElo();
        $total  = count($albums);
        $totalDuels = array_reduce($albums, fn(float $c, array $i): float => $c + (int)$i['Duels'], 0.0) / 2;

        $sortedByElo = $albums;
        usort($sortedByElo, fn(array $a, array $b): int => $b['Elo'] <=> $a['Elo']);
        $top10 = array_slice($sortedByElo, 0, 10);
        $flop10 = array_slice(array_reverse($sortedByElo), 0, 10);

        $sortedByDuels = $albums;
        usort($sortedByDuels, fn(array $a, array $b): int => $b['Duels'] <=> $a['Duels']);
        $veterans = array_slice($sortedByDuels, 0, 5);

        $hiddenGems = array_filter($albums, fn(array $a): bool => (float)$a['Elo'] > 1210 && (int)$a['Playcount'] > 0);
        usort($hiddenGems, fn(array $a, array $b): int => $a['Playcount'] <=> $b['Playcount']);
        $hiddenGems = array_slice($hiddenGems, 0, 5);

        $disappointments = array_filter($albums, fn(array $a): bool => (int)$a['Playcount'] > 50 && (float)$a['Elo'] < 1200);
        usort($disappointments, fn(array $a, array $b): int => $a['Elo'] <=> $b['Elo']);
        $disappointments = array_slice($disappointments, 0, 5);

        $artistStats = [];
        foreach ($albums as $a) {
            $name = (string)$a['Artist'];
            if (!isset($artistStats[$name])) {
                $artistStats[$name] = ['elo_above_baseline' => 0.0, 'count' => 0];
            }
            $artistStats[$name]['elo_above_baseline'] += ((float)$a['Elo'] - 1200);
            $artistStats[$name]['count']++;
        }
        $topArtists = [];
        foreach ($artistStats as $name => $stat) {
            if ($stat['count'] >= 2 && $stat['elo_above_baseline'] > 0) {
                $topArtists[] = [
                    'Artist'     => $name,
                    'PowerScore' => $stat['elo_above_baseline'],
                    'Count'      => $stat['count'],
                ];
            }
        }
        usort($topArtists, fn(array $a, array $b): int => $b['PowerScore'] <=> $a['PowerScore']);
        $topArtists = array_slice($topArtists, 0, 5);

        $genreStats  = [];
        $decadeStats = [];
        $metaService = new MetadataService($this->config, $this->settings);

        foreach ($albums as $a) {
            $cacheBase = $metaService->getAlbumCacheBaseName((string)$a['Artist'], (string)$a['Album']);
            $jsonFile  = $this->config->getCacheDir() . $cacheBase . '.json';

            // legacy fallback
            if (!file_exists($jsonFile)) {
                $legacy = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower('album_' . $a['Artist'] . '_' . $a['Album']));
                $legacyFile = $this->config->getCacheDir() . $legacy . '.json';
                if (file_exists($legacyFile)) {
                    $jsonFile = $legacyFile;
                }
            }

            if (!file_exists($jsonFile)) {
                continue;
            }

            $info = json_decode(file_get_contents($jsonFile), true);
            if (!is_array($info)) {
                continue;
            }

            $albumGenres = $metaService->applyTagBlacklist($info['genres'] ?? []);
            if (!empty($albumGenres)) {
                foreach ($albumGenres as $genre) {
                    $g = ucwords(strtolower($genre));
                    if (!isset($genreStats[$g])) {
                        $genreStats[$g] = ['elo_sum' => 0.0, 'count' => 0];
                    }
                    $genreStats[$g]['elo_sum'] += (float)$a['Elo'];
                    $genreStats[$g]['count']++;
                }
            }

            if (!empty($info['year'])) {
                $year = (int)$info['year'];
                if ($year > 1900 && $year <= (int)date('Y')) {
                    $decade = (int)floor($year / 10) * 10 . 's';
                    if (!isset($decadeStats[$decade])) {
                        $decadeStats[$decade] = ['elo_sum' => 0.0, 'count' => 0];
                    }
                    $decadeStats[$decade]['elo_sum'] += (float)$a['Elo'];
                    $decadeStats[$decade]['count']++;
                }
            }
        }

        $genreResult  = $this->sliceBestWorst($genreStats, 3, 5);
        $decadeResult = $this->sliceBestWorst($decadeStats, 2, 5);

        return [
            'totalAlbums'     => $total,
            'totalDuels'      => floor($totalDuels),
            'top10'           => $top10,
            'flop10'          => $flop10,
            'veterans'        => $veterans,
            'hiddenGems'      => $hiddenGems,
            'disappointments' => $disappointments,
            'topArtists'      => $topArtists,
            'bestGenres'      => $genreResult['best'],
            'worstGenres'     => $genreResult['worst'],
            'bestDecades'     => $decadeResult['best'],
            'worstDecades'    => $decadeResult['worst'],
        ];
    }

    /**
     * @param array<string, array{elo_sum: float, count: int}> $stats
     * @return array{best: list<array<string, mixed>>, worst: list<array<string, mixed>>}
     */
    private function sliceBestWorst(array $stats, int $minCount, int $limit): array
    {
        $filtered = [];
        foreach ($stats as $name => $stat) {
            if ($stat['count'] >= $minCount) {
                $filtered[] = [
                    'Name'    => $name,
                    'AvgElo'  => $stat['elo_sum'] / $stat['count'],
                    'Count'   => $stat['count'],
                ];
            }
        }
        usort($filtered, fn(array $a, array $b): int => $b['AvgElo'] <=> $a['AvgElo']);

        return [
            'best'  => array_slice($filtered, 0, $limit),
            'worst' => array_slice(array_reverse($filtered), 0, $limit),
        ];
    }
}
