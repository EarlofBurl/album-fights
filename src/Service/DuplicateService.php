<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\Security;

class DuplicateService
{
    public function similarityScore(string $a, string $b): float
    {
        if ($a === '' || $b === '') {
            return 0.0;
        }
        similar_text($a, $b, $percent);
        return (float)$percent;
    }

    /**
     * @param list<array<string, mixed>> $albums
     * @return list<list<int>>
     */
    public function buildGroups(array $albums): array
    {
        $count = count($albums);
        if ($count < 2) {
            return [];
        }

        $parents = range(0, $count - 1);

        $find = function (int $x) use (&$parents, &$find): int {
            if ($parents[$x] !== $x) {
                $parents[$x] = $find($parents[$x]);
            }
            return $parents[$x];
        };

        $union = function (int $a, int $b) use (&$parents, $find): void {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parents[$rb] = $ra;
            }
        };

        $normArtists = [];
        $normAlbums = [];
        for ($i = 0; $i < $count; $i++) {
            $normArtists[$i] = Security::slugifyToken((string)$albums[$i]['Artist']);
            $normAlbums[$i] = Security::slugifyToken((string)$albums[$i]['Album']);
        }

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $artistScore = $this->similarityScore($normArtists[$i], $normArtists[$j]);
                $albumScore  = $this->similarityScore($normAlbums[$i], $normAlbums[$j]);

                $isDup = ($artistScore >= 92 && $albumScore >= 86) || ($artistScore >= 96 && $albumScore >= 75);
                if ($isDup) {
                    $union($i, $j);
                }
            }
        }

        $groups = [];
        for ($i = 0; $i < $count; $i++) {
            $root = $find($i);
            if (!isset($groups[$root])) {
                $groups[$root] = [];
            }
            $groups[$root][] = $i;
        }

        $groups = array_values(array_filter($groups, fn(array $g): bool => count($g) > 1));
        usort($groups, fn(array $a, array $b): int => count($b) <=> count($a));

        return $groups;
    }

    /**
     * @param list<array<string, mixed>> $albums
     * @param list<int> $indices
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}
     */
    public function mergeGroup(array $albums, int $primaryIndex, array $indices): array
    {
        $sumPlaycount = 0;
        $sumDuels = 0;
        $sumWins = 0;
        $sumLosses = 0;
        $eloTotal = 0.0;

        foreach ($indices as $idx) {
            $sumPlaycount += (int)$albums[$idx]['Playcount'];
            $sumDuels     += (int)$albums[$idx]['Duels'];
            $sumWins      += (int)($albums[$idx]['Wins'] ?? 0);
            $sumLosses    += (int)($albums[$idx]['Losses'] ?? 0);
            $eloTotal     += (float)$albums[$idx]['Elo'];
        }

        $albums[$primaryIndex]['Playcount'] = $sumPlaycount;
        $albums[$primaryIndex]['Duels']     = $sumDuels;
        $albums[$primaryIndex]['Wins']      = $sumWins;
        $albums[$primaryIndex]['Losses']    = $sumLosses;
        $albums[$primaryIndex]['Elo']       = $eloTotal / count($indices);

        rsort($indices);
        foreach ($indices as $idx) {
            if ($idx === $primaryIndex) {
                continue;
            }
            array_splice($albums, $idx, 1);
        }

        return [$albums, $albums[$primaryIndex]];
    }
}
