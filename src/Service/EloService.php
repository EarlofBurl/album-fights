<?php
declare(strict_types=1);

namespace App\Service;

use App\Repository\AlbumRepository;

class EloService
{
    public function __construct(private AlbumRepository $albumRepo)
    {
    }

    public function getKFactor(float $elo, int $duels): int
    {
        if ($duels < 10) {
            return 40;
        }
        if ($elo >= 1350) {
            return 16;
        }
        if ($elo >= 1280) {
            return 24;
        }
        return 32;
    }

    /**
     * @param array<string, mixed> $albumA
     * @param array<string, mixed> $albumB
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    public function calculateResult(array $albumA, array $albumB, float $scoreA): array
    {
        $kA = $this->getKFactor((float)$albumA['Elo'], (int)$albumA['Duels']);
        $kB = $this->getKFactor((float)$albumB['Elo'], (int)$albumB['Duels']);

        $expectedA = 1 / (1 + 10 ** (($albumB['Elo'] - $albumA['Elo']) / 400));
        $expectedB = 1 / (1 + 10 ** (($albumA['Elo'] - $albumB['Elo']) / 400));

        $albumA['Elo'] = (float)$albumA['Elo'] + $kA * ($scoreA - $expectedA);
        $albumB['Elo'] = (float)$albumB['Elo'] + $kB * ((1 - $scoreA) - $expectedB);
        $albumA['Duels'] = (int)$albumA['Duels'] + 1;
        $albumB['Duels'] = (int)$albumB['Duels'] + 1;

        if ($scoreA == 1.0) {
            $albumA['Wins'] = (int)($albumA['Wins'] ?? 0) + 1;
            $albumB['Losses'] = (int)($albumB['Losses'] ?? 0) + 1;
        } elseif ($scoreA == 0.0) {
            $albumB['Wins'] = (int)($albumB['Wins'] ?? 0) + 1;
            $albumA['Losses'] = (int)($albumA['Losses'] ?? 0) + 1;
        }

        return [$albumA, $albumB];
    }

    public function getRankClass(int $rank): string
    {
        return match (true) {
            $rank === 1 => 'tier-platinum',
            $rank === 2 => 'tier-gold',
            $rank === 3 => 'tier-bronze',
            $rank <= 10 => 'tier-top10',
            $rank <= 25 => 'tier-top25',
            default => '',
        };
    }

    /**
     * @param list<array<string, mixed>> $albums
     * @return array<int, int>
     */
    public function buildRankMap(array $albums): array
    {
        $rankByIndex = [];
        if (empty($albums)) {
            return $rankByIndex;
        }

        $rankedIndexes = array_keys($albums);
        usort($rankedIndexes, function (int $a, int $b) use ($albums): int {
            return $albums[$b]['Elo'] <=> $albums[$a]['Elo'];
        });

        foreach ($rankedIndexes as $position => $originalIndex) {
            $rankByIndex[$originalIndex] = $position + 1;
        }

        return $rankByIndex;
    }

    /**
     * @param list<array<string, mixed>> $albums
     * @param array<string, int> $weights
     * @param list<int> $recentHistory Original indices of recently seen albums to exclude
     * @return array{0: ?int, 1: ?int}
     */
    public function matchmake(array $albums, array $weights, array $recentHistory = []): array
    {
        $total = count($albums);
        if ($total < 2) {
            return [null, null];
        }

        // Pre-sort once by Elo descending and keep original index
        $ranked = [];
        foreach ($albums as $k => $album) {
            $album['_OriginalIndex'] = $k;
            $ranked[] = $album;
        }
        usort($ranked, fn(array $a, array $b): int => $b['Elo'] <=> $a['Elo']);

        $buildRankedSubset = function (int $start, int $end) use ($ranked, $total): array {
            if ($total <= $start + 1) {
                return [];
            }
            $actualEnd = min($end, $total - 1);
            if ($actualEnd <= $start) {
                return [];
            }
            return array_slice($ranked, $start, $actualEnd - $start + 1);
        };

        $pickLeastDueledPair = function (array $subset) use ($recentHistory): ?array {
            if (count($subset) < 2) {
                return null;
            }
            usort($subset, function (array $a, array $b): int {
                if ($a['Duels'] === $b['Duels']) {
                    return $b['Elo'] <=> $a['Elo'];
                }
                return $a['Duels'] <=> $b['Duels'];
            });

            $bottomCount = max(2, (int)ceil(count($subset) * 0.2));
            $bottomSubset = array_slice($subset, 0, $bottomCount);

            // Build pool of valid albums (not in recent history)
            $valid = [];
            foreach ($bottomSubset as $item) {
                if (!in_array($item['_OriginalIndex'], $recentHistory, true)) {
                    $valid[] = $item;
                }
            }

            // Fallback to full subset if bottom pool is too small
            if (count($valid) < 2) {
                $valid = [];
                foreach ($subset as $item) {
                    if (!in_array($item['_OriginalIndex'], $recentHistory, true)) {
                        $valid[] = $item;
                    }
                }
                if (count($valid) < 2) {
                    return null;
                }
            }

            // Pick two random different albums from valid pool
            $validCount = count($valid);
            if ($validCount === 2) {
                return [$valid[0]['_OriginalIndex'], $valid[1]['_OriginalIndex']];
            }

            $i = random_int(0, $validCount - 1);
            do {
                $j = random_int(0, $validCount - 1);
            } while ($i === $j);

            return [$valid[$i]['_OriginalIndex'], $valid[$j]['_OriginalIndex']];
        };

        $getRandomPair = function () use ($albums, $recentHistory): array {
            $available = [];
            foreach (array_keys($albums) as $k) {
                if (!in_array($k, $recentHistory, true)) {
                    $available[] = $k;
                }
            }
            if (count($available) < 2) {
                // Fallback: ignore recent history if too few albums left
                $available = array_keys($albums);
            }
            $a = $available[array_rand($available)];
            do {
                $b = $available[array_rand($available)];
            } while ($a === $b);
            return [$a, $b];
        };

        $matchmakers = [
            'top_25_vs' => function () use ($buildRankedSubset, $pickLeastDueledPair) {
                return $pickLeastDueledPair($buildRankedSubset(0, 24));
            },
            'top_50_vs' => function () use ($buildRankedSubset, $pickLeastDueledPair) {
                return $pickLeastDueledPair($buildRankedSubset(25, 49));
            },
            'top_100_vs' => function () use ($buildRankedSubset, $pickLeastDueledPair) {
                return $pickLeastDueledPair($buildRankedSubset(50, 99));
            },
            'playcount_gt_20' => function () use ($albums, $pickLeastDueledPair) {
                $subset = [];
                foreach ($albums as $k => $album) {
                    if ((int)$album['Playcount'] > 20) {
                        $album['_OriginalIndex'] = $k;
                        $subset[] = $album;
                    }
                }
                return $pickLeastDueledPair($subset);
            },
            'duel_counter_zero' => function () use ($albums, $pickLeastDueledPair) {
                $subset = [];
                foreach ($albums as $k => $album) {
                    if ((int)$album['Duels'] === 0) {
                        $album['_OriginalIndex'] = $k;
                        $subset[] = $album;
                    }
                }
                return $pickLeastDueledPair($subset);
            },
            'random' => $getRandomPair,
        ];

        $weightedPool = [];
        foreach ($matchmakers as $category => $picker) {
            $weight = max(0, (int)($weights[$category] ?? 0));
            if ($weight <= 0) {
                continue;
            }
            $candidate = $picker();
            if ($candidate !== null) {
                $weightedPool[] = [
                    'category' => $category,
                    'weight'   => $weight,
                    'pair'     => $candidate,
                ];
            }
        }

        $idxA = null;
        $idxB = null;

        if (!empty($weightedPool)) {
            $weightSum = array_sum(array_column($weightedPool, 'weight'));
            $pickValue = random_int(1, max(1, $weightSum));
            $rolling = 0;
            foreach ($weightedPool as $entry) {
                $rolling += $entry['weight'];
                if ($pickValue <= $rolling) {
                    [$idxA, $idxB] = $entry['pair'];
                    break;
                }
            }
        }

        if ($idxA === null || $idxB === null) {
            [$idxA, $idxB] = $getRandomPair();
        }

        return [$idxA, $idxB];
    }
}
