<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Config;
use App\Core\Security;
use App\Repository\AlbumRepository;
use App\Repository\SettingsRepository;
use App\Service\AiService;
use App\Service\EloService;
use App\Service\MetadataService;

$config     = Config::get();
$albumRepo  = new AlbumRepository($config);
$settings   = new SettingsRepository($config);
$eloService = new EloService($albumRepo);
$metaService = new MetadataService($config, $settings);
$aiService   = new AiService($config, $settings);

$reviewText = '';
$albums     = $albumRepo->loadElo();
$total      = count($albums);

// Process POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Security::requirePost();
    $action = Security::getString($_POST, 'action');

    if ($action === 'reload_metadata') {
        $targetIdx = Security::getInt($_POST, 'targetIdx', -1);
        if (isset($albums[$targetIdx])) {
            $metaService->getAlbumData((string)$albums[$targetIdx]['Artist'], (string)$albums[$targetIdx]['Album'], true);
        }
        header('Location: index.php');
        exit;
    }

    if ($action === 'vote' && isset($_POST['idxA'], $_POST['idxB'])) {
        $idxA = Security::getInt($_POST, 'idxA');
        $idxB = Security::getInt($_POST, 'idxB');
        $scoreA = Security::getFloat($_POST, 'scoreA');

        if (isset($albums[$idxA], $albums[$idxB])) {
            unset($_SESSION['keep_artist'], $_SESSION['keep_album']);

            if ($scoreA == 1.0) {
                $_SESSION['recent_picks'][] = $albums[$idxA]['Artist'] . ' - ' . $albums[$idxA]['Album'];
            } elseif ($scoreA == 0.0) {
                $_SESSION['recent_picks'][] = $albums[$idxB]['Artist'] . ' - ' . $albums[$idxB]['Album'];
            } else {
                $_SESSION['recent_picks'][] = '[Draw] ' . $albums[$idxA]['Artist'] . ' AND ' . $albums[$idxB]['Artist'];
            }
            if (count($_SESSION['recent_picks']) > 25) {
                array_shift($_SESSION['recent_picks']);
            }

            [$albums[$idxA], $albums[$idxB]] = $eloService->calculateResult($albums[$idxA], $albums[$idxB], $scoreA);
            $albumRepo->saveElo($albums);
            $_SESSION['duel_count']++;

            if ($_SESSION['duel_count'] > 0 && $_SESSION['duel_count'] % 25 === 0) {
                $picksText = implode(', ', $_SESSION['recent_picks']);
                $reviewText = $aiService->triggerNerdComment($picksText) ?: '';
                $_SESSION['recent_picks'] = [];
            }
        }
    }

    if (($action === 'queue' || $action === 'delete') && isset($_POST['targetIdx'])) {
        $targetIdx = Security::getInt($_POST, 'targetIdx');
        if (isset($albums[$targetIdx])) {
            if ($action === 'queue') {
                $row = $albums[$targetIdx];
                $albumRepo->moveToQueue(
                    (string)$row['Artist'],
                    (string)$row['Album'],
                    (float)$row['Elo'],
                    (int)$row['Duels'],
                    (int)$row['Playcount'],
                    (int)($row['Wins'] ?? 0),
                    (int)($row['Losses'] ?? 0)
                );
            }
            array_splice($albums, $targetIdx, 1);
            $albumRepo->saveElo($albums);
        }

        $keepArtist = Security::getString($_POST, 'survivorArtist');
        $keepAlbum  = Security::getString($_POST, 'survivorAlbum');
        if ($keepArtist !== '' && $keepAlbum !== '') {
            $_SESSION['keep_artist'] = $keepArtist;
            $_SESSION['keep_album']  = $keepAlbum;
        }
    }

    unset($_SESSION['current_duel']);

    if (empty($reviewText)) {
        header('Location: index.php');
        exit;
    }
}

// Select duel pair
$idxA = null;
$idxB = null;

if (isset($_SESSION['keep_artist'], $_SESSION['keep_album'])) {
    foreach ($albums as $i => $alb) {
        if ($alb['Artist'] === $_SESSION['keep_artist'] && $alb['Album'] === $_SESSION['keep_album']) {
            $idxA = $i;
            break;
        }
    }
    unset($_SESSION['keep_artist'], $_SESSION['keep_album']);
    unset($_SESSION['current_duel']);
}

if (isset($_SESSION['current_duel']) && $idxA === null) {
    $savedA = $_SESSION['current_duel']['idxA'];
    $savedB = $_SESSION['current_duel']['idxB'];
    if (isset($albums[$savedA], $albums[$savedB])) {
        $idxA = $savedA;
        $idxB = $savedB;
    } else {
        unset($_SESSION['current_duel']);
    }
}

if ($total >= 2 && ($idxA === null || $idxB === null)) {
    $weights = array_merge([
        'top_25_vs'         => 20,
        'top_50_vs'         => 20,
        'top_100_vs'        => 20,
        'playcount_gt_20'   => 15,
        'duel_counter_zero' => 15,
        'random'            => 10,
    ], $settings->getDuelCategoryWeights());

    [$idxA, $idxB] = $eloService->matchmake($albums, $weights);
    $_SESSION['current_duel'] = ['idxA' => $idxA, 'idxB' => $idxB];
}

$rankByIndex = $eloService->buildRankMap($albums);

$albumA = $idxA !== null ? $albums[$idxA] : null;
$albumB = $idxB !== null ? $albums[$idxB] : null;

$infoA = $albumA !== null ? $metaService->getAlbumData((string)$albumA['Artist'], (string)$albumA['Album']) : [];
$infoB = $albumB !== null ? $metaService->getAlbumData((string)$albumB['Artist'], (string)$albumB['Album']) : [];

if ($albumA !== null) {
    $albumA['OriginalIndex'] = $idxA;
    $albumA['Rank'] = $rankByIndex[$idxA] ?? null;
}
if ($albumB !== null) {
    $albumB['OriginalIndex'] = $idxB;
    $albumB['Rank'] = $rankByIndex[$idxB] ?? null;
}

$top20 = $albumRepo->getTop(20);

$csrfField = Security::csrfField();

require __DIR__ . '/templates/duel.php';
