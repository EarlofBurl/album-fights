<?php
declare(strict_types=1);

use App\Core\Security;

$duelCount = $_SESSION['duel_count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="stylesheet" href="css/app.css">
    <title>The ultimate Album Battle</title>
</head>
<body>
    <div class="header">
        <div>
            <h1>🎧 The Ultimate Album Battle</h1>
            <p style="margin:0; color:#888;">Duels this session: <?= (int)$duelCount ?></p>
        </div>
        <div class="nav-links">
            <a href="index.php">Duel</a>
            <a href="bootcamp.php">🏕️ Boot Camp</a>
            <a href="queue.php">Queue</a>
            <a href="import.php">Import</a>
            <a href="stats.php">Stats</a>
            <a href="list.php">The List</a>
            <a href="database.php">Database</a>
            <a href="settings.php">⚙️ Settings</a>
        </div>
    </div>

    <!-- Loading overlay for duel page API calls -->
    <div id="duel-loading-overlay" style="display:none; position:fixed; inset:0; background:rgba(18,18,18,0.92); z-index:9999; flex-direction:column; align-items:center; justify-content:center; color:var(--text-main);">
        <div style="font-size:2.5rem; margin-bottom:16px; animation:pulse 1.5s infinite;">🎵</div>
        <div style="font-size:1.3rem; font-weight:bold; margin-bottom:8px;">Fetching album info...</div>
        <div style="color:var(--text-muted); font-size:0.95rem;">Please wait while we cache the next albums</div>
    </div>

    <script>
    document.addEventListener('submit', function(e) {
        const form = e.target;
        const buttons = form.querySelectorAll('button');

        // Show loading overlay only for duel page forms that trigger a page reload
        const isDuelForm = form.closest('.duel-container') !== null || form.closest('.duel-card') !== null;
        if (isDuelForm) {
            const overlay = document.getElementById('duel-loading-overlay');
            if (overlay) {
                overlay.style.display = 'flex';
            }
        }

        setTimeout(() => {
            buttons.forEach(btn => {
                btn.disabled = true;
                if(btn.innerText.includes('Delete') || btn.innerText.includes('Queue')) {
                    btn.style.opacity = '0.5';
                } else if (!btn.innerText.includes('To DB') && !btn.innerText.includes('Boot Camp')) {
                    btn.innerText = 'Processing...';
                }
            });
        }, 0);
    });
    </script>
