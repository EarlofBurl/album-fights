<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Config;
use App\Core\Security;
use App\Repository\AlbumRepository;
use App\Utils\CsvHelper;

$config    = Config::get();
$albumRepo = new AlbumRepository($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    Security::requirePost();
    $action = Security::getString($_POST, 'action');
    $idx    = Security::getInt($_POST, 'targetIdx');
    $queue  = $albumRepo->loadQueue();

    if (isset($queue[$idx])) {
        if ($action === 'restore') {
            $eloData = $albumRepo->loadElo();
            $eloData[] = $queue[$idx];
            $albumRepo->saveElo($eloData);
        }
        array_splice($queue, $idx, 1);
        $albumRepo->saveQueue($queue);
    }
    header('Location: queue.php');
    exit;
}

$queue = $albumRepo->loadQueue();
$csrfField = Security::csrfField();

require __DIR__ . '/templates/queue.php';
