<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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