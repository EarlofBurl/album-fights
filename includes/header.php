<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <title>The ultimate Album Battle</title>
    <style>
        :root { 
            --bg-color: #121212; 
            --card-bg: #1e1e1e; 
            --text-main: #e0e0e0; 
            --text-muted: #a0a0a0; 
            --accent: #bb86fc; 
            --accent-hover: #3700b3; 
            --border: #333; 
            --danger: #cf6679; 
            --warning: #f2a600; 
        }
        body { 
            font-family: system-ui, -apple-system, sans-serif; 
            background-color: var(--bg-color); 
            color: var(--text-main); 
            margin: 0; 
            padding: 20px; 
            display: flex; 
            flex-direction: column; 
            align-items: center; 
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            width: 100%; 
            max-width: 1400px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .nav-links a { 
            color: var(--accent); 
            text-decoration: none; 
            margin-left: 15px; 
            font-weight: bold; 
        }
        .nav-links a:hover { 
            text-decoration: underline; 
        }
        
        .duel-container { 
            display: flex; 
            gap: 20px; 
            width: 100%; 
            max-width: 1400px;
            justify-content: center;
        }

        /* Standard Duel-Card */
        .duel-card {
            flex: 1; 
            background: var(--card-bg); 
            padding: 20px; 
            border-radius: 12px; 
            text-align: center; 
            border: 2px solid var(--border); /* Auf 2px erhöht, damit es nicht springt */
            display: flex; 
            flex-direction: column;
            transition: all 0.3s ease;
        }

        /* --- VISUELLE TIERS (Schlagen jetzt richtig durch!) --- */

        /* PLATINUM (Platz 1) */
        .tier-platinum {
            background: linear-gradient(135deg, rgba(229, 228, 226, 0.15) 0%, rgba(30, 30, 30, 0) 100%);
            border-color: #e5e4e2;
            box-shadow: 0 0 30px rgba(229, 228, 226, 0.15);
            transform: translateY(-4px);
        }
        .tier-platinum .duel-rank-badge { background: #e5e4e2; color: #000; border-color: #e5e4e2; font-weight: 900; box-shadow: 0 0 10px rgba(229, 228, 226, 0.5); }
        .tier-platinum .artist-name { color: #e5e4e2 !important; text-shadow: 0 0 8px rgba(229, 228, 226, 0.4); }

        /* GOLD (Platz 2) */
        .tier-gold {
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.15) 0%, rgba(30, 30, 30, 0) 100%);
            border-color: #ffd700;
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.15);
            transform: translateY(-4px);
        }
        .tier-gold .duel-rank-badge { background: #ffd700; color: #000; border-color: #ffd700; font-weight: 900; box-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
        .tier-gold .artist-name { color: #ffd700 !important; text-shadow: 0 0 8px rgba(255, 215, 0, 0.4); }

        /* BRONZE (Platz 3) */
        .tier-bronze {
            background: linear-gradient(135deg, rgba(205, 127, 50, 0.15) 0%, rgba(30, 30, 30, 0) 100%);
            border-color: #cd7f32;
            box-shadow: 0 0 20px rgba(205, 127, 50, 0.15);
        }
        .tier-bronze .duel-rank-badge { background: #cd7f32; color: #000; border-color: #cd7f32; font-weight: 900; }
        .tier-bronze .artist-name { color: #cd7f32 !important; }

        /* TOP 10 */
        .tier-top10 {
            background: linear-gradient(135deg, rgba(187, 134, 252, 0.12) 0%, rgba(30, 30, 30, 0) 100%);
            border-color: var(--accent);
            box-shadow: 0 0 20px rgba(187, 134, 252, 0.1);
        }
        .tier-top10 .duel-rank-badge { background: var(--accent); color: #000; border-color: var(--accent); font-weight: 900; }
        
        /* TOP 25 */
        .tier-top25 {
            border-color: rgba(187, 134, 252, 0.5);
            background: rgba(187, 134, 252, 0.04);
        }
        .tier-top25 .duel-rank-badge { border-color: rgba(187, 134, 252, 0.8); color: #d4b3ff; }

        /* --- Buttons & Utilities --- */
        .btn-vote {
            width: 100%; 
            padding: 15px; 
            font-size: 1.2rem; 
            background-color: var(--accent); 
            color: #000; 
            border: none; 
            border-radius: 8px; 
            cursor: pointer; 
            font-weight: bold; 
            margin-bottom: 10px;
        }

        .top-list {
            width: 100%;
            max-width: 1000px;
            margin-top: 40px;
        }
        .top-list table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
        }
        .top-list th, .top-list td { 
            padding: 12px 15px; 
            text-align: left; 
            border-bottom: 1px solid var(--border); 
        }
        .top-list th { 
            background: #111; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            font-size: 0.8rem; 
        }
        .rank-col { 
            font-weight: bold; 
            color: var(--text-muted); 
            width: 50px; 
        }
        .duel-rank-badge {
            align-self: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 62px;
            padding: 6px 12px;
            margin: -6px auto 12px;
            border-radius: 999px;
            border: 1px solid rgba(187, 134, 252, 0.65);
            background: rgba(187, 134, 252, 0.14);
            color: var(--accent);
            font-size: 0.85rem;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.08);
            transition: all 0.3s ease;
        }

        .btn-small {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .btn-queue { background-color: #333; color: #fff; }
        .btn-delete { background-color: var(--danger); color: #fff; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🎧 The Ultimate Album Battle</h1>
            <p style="margin:0; color:#888;">Duels this session: <?= $_SESSION['duel_count'] ?? 0 ?></p>
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

    <script>
    // Protection against multiple clicks (Race Condition)
    document.addEventListener('submit', function(e) {
        const form = e.target;
        const buttons = form.querySelectorAll('button');
        
        // We set a minimal timeout (0ms) so the browser can still include 
        // the value of the clicked button in the POST request 
        // before the button is disabled.
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