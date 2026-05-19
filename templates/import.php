<?php
use App\Service\ImportService;

/**
 * @var string $csrfField
 * @var string $message
 * @var string $messageType
 * @var list<array{artist: string, album: string, playcount: int}> $candidates
 * @var string $candidatesState
 * @var string $importUsernameValue
 * @var int $minPlays
 */

require __DIR__ . '/partials/header.php';
?>

<style>
    .import-container { max-width: 1100px; margin: 0 auto; padding: 20px 0; }
    .import-card { background: var(--card-bg, #222); border: 1px solid var(--border, #333); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
    .import-card.sync-card { border-left: 5px solid var(--accent, #b088ff); }
    .import-card h3 { margin-top: 0; margin-bottom: 8px; }
    .import-card p { font-size: 0.9rem; color: var(--text-muted, #999); margin-top: 0; margin-bottom: 20px; }
    .grid-2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin-bottom: 30px; align-items: stretch; }
    .grid-2 .import-card { display: flex; flex-direction: column; margin-bottom: 0; }
    .grid-2 .import-card form { flex: 1; display: flex; flex-direction: column; }
    .grid-2 .import-card .btn-full { margin-top: auto; }
    .flex-row { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
    .form-group { margin-bottom: 15px; }
    .form-control { width: 100%; padding: 12px; background: #111; color: #fff; border: 1px solid #333; border-radius: 6px; font-size: 1rem; box-sizing: border-box; }
    .form-control:focus { border-color: var(--accent, #b088ff); outline: none; }
    input[type="file"].form-control { padding: 9px; }
    .btn { padding: 12px 20px; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; transition: opacity 0.2s; text-align: center; font-size: 0.95rem; }
    .btn:hover { opacity: 0.8; }
    .btn-accent { background: var(--accent, #b088ff); color: #000; }
    .btn-dark { background: #333; color: #fff; }
    .btn-success { background: #2ecc71; color: #000; }
    .btn-full { width: 100%; display: block; }
    .alert { padding: 15px; border-radius: 8px; margin-bottom: 24px; font-weight: bold; }
    .alert-success { background: rgba(46, 204, 113, 0.15); color: #2ecc71; border: 1px solid rgba(46, 204, 113, 0.3); }
    .alert-error { background: rgba(231, 76, 60, 0.15); color: #e74c3c; border: 1px solid rgba(231, 76, 60, 0.3); }
    .alert-info { background: rgba(52, 152, 219, 0.15); color: #3498db; border: 1px solid rgba(52, 152, 219, 0.3); }
    .table-toolbar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 15px; margin-top: 10px; }
    .candidates-table { width: 100%; border-collapse: collapse; background: var(--card-bg, #222); border-radius: 8px; overflow: hidden; font-size: 0.95rem; }
    .candidates-table th, .candidates-table td { padding: 14px; text-align: left; border-bottom: 1px solid var(--border, #333); }
    .candidates-table th { background: #1a1a1a; font-weight: bold; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
    .candidates-table th.center, .candidates-table td.center { text-align: center; }
    .candidates-table td.artist { color: var(--accent, #b088ff); font-weight: bold; }
    .table-actions { display: flex; gap: 10px; justify-content: center; }
    .table-actions form { margin: 0; }
</style>

<div class="import-container">
    <h2 style="margin-top: 0;">📥 Import & Maintenance</h2>
    <p style="color: var(--text-muted); margin-bottom: 24px;">
        Active Rules: Minimum <strong><?= $minPlays ?> Scrobbles</strong> required.
        (Changeable in <a href="settings.php" style="color: var(--accent);">Settings</a>).
    </p>

    <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <div class="import-card sync-card">
        <h3 style="color: var(--accent);">🔄 Sync Live Playcounts</h3>
        <p>This pulls your Top 1000 albums from Last.fm, ListenBrainz, or Navidrome/Subsonic and updates matching playcounts.</p>
        <form method="POST" class="flex-row">
            <?= $csrfField ?>
            <input type="hidden" name="action" value="sync_playcounts">
            <select name="source" class="form-control" style="flex: 0 0 170px;">
                <option value="lastfm">Last.fm</option>
                <option value="listenbrainz">ListenBrainz</option>
                <option value="subsonic">Navidrome/Subsonic</option>
            </select>
            <input type="text" name="username" class="form-control" style="flex: 1;" value="<?= htmlspecialchars($importUsernameValue) ?>" placeholder="Username">
            <button type="submit" class="btn btn-accent" style="flex: 0 0 200px;">Update Playcounts</button>
        </form>
    </div>

    <div class="grid-2">
        <div class="import-card">
            <h3>🎯 API Import</h3>
            <p>Choose source and mode: Top/Recent for all APIs, plus Liked for Navidrome/Subsonic.</p>
            <form method="POST">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="fetch_candidates">
                <div class="form-group">
                    <select name="source" class="form-control">
                        <option value="lastfm">Last.fm</option>
                        <option value="listenbrainz">ListenBrainz</option>
                        <option value="subsonic">Navidrome/Subsonic</option>
                    </select>
                </div>
                <div class="form-group">
                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($importUsernameValue) ?>" placeholder="Username">
                </div>
                <div class="form-group flex-row" id="mode-row">
                    <select name="fetch_mode" id="fetch_mode" class="form-control" style="flex: 1;">
                        <option value="recent">Recent top albums</option>
                        <option value="top">Most played (all time)</option>
                        <option value="liked">Liked / Starred albums (Subsonic only)</option>
                    </select>
                    <select name="period" id="period_select" class="form-control" style="flex: 0 0 140px;">
                        <option value="7day">Last 7 days</option>
                        <option value="1month" selected>Last month</option>
                        <option value="3month">Last 3 months</option>
                        <option value="6month">Last 6 months</option>
                        <option value="12month">Last 12 months</option>
                    </select>
                    <select name="top_limit" id="top_limit" class="form-control" style="flex: 0 0 120px; display: none;">
                        <option value="100">Top 100</option>
                        <option value="200">Top 200</option>
                        <option value="500">Top 500</option>
                        <option value="1000">Top 1000</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-dark btn-full">Fetch & Preview</button>
            </form>
        </div>

        <div class="import-card">
            <h3>📄 Upload CSV List</h3>
            <p>Expected Format: <code>Artist, Album, Playcount</code>. Full preview first.</p>
            <form method="POST" enctype="multipart/form-data">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="upload_csv">
                <div class="form-group">
                    <input type="file" name="csv_file" accept=".csv" class="form-control">
                </div>
                <button type="submit" class="btn btn-success btn-full">Upload & Preview CSV</button>
            </form>
        </div>

        <div class="import-card">
            <h3>🏆 Bundled Top 1000</h3>
            <p>One-click preview from <code>1000_best_albums.csv</code>. Only albums not already in DB/Queue are shown.</p>
            <form method="POST">
                <?= $csrfField ?>
                <input type="hidden" name="action" value="load_best_albums">
                <button type="submit" class="btn btn-accent btn-full">Load 1000 Best Albums</button>
            </form>
        </div>
    </div>

    <?php if (!empty($candidates)): ?>
        <h3 style="margin-top: 40px; margin-bottom: 5px;">🔍 Found Candidates (<?= count($candidates) ?>)</h3>
        <form method="POST" id="bulk-import-form" class="table-toolbar">
            <?= $csrfField ?>
            <input type="hidden" name="candidates_state" value="<?= htmlspecialchars($candidatesState) ?>">
            <button type="submit" name="action" value="import_selected_db" class="btn btn-accent">➕ Import Selected to DB</button>
            <button type="submit" name="action" value="import_selected_queue" class="btn btn-dark">🎧 Import Selected to Queue</button>
            <button type="submit" name="action" value="import_all_db" class="btn btn-success">✅ Import ALL to DB</button>
        </form>

        <table class="candidates-table">
            <thead>
                <tr>
                    <th class="center" style="width: 50px;"><input type="checkbox" id="select-all-candidates"></th>
                    <th>Artist</th>
                    <th>Album</th>
                    <th>Plays</th>
                    <th class="center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $album): ?>
                    <?php $candidateKey = ImportService::getCandidateKey($album); ?>
                    <tr>
                        <td class="center">
                            <input type="checkbox" name="selected_candidates[]" value="<?= htmlspecialchars($candidateKey) ?>" form="bulk-import-form" class="candidate-checkbox">
                        </td>
                        <td class="artist"><?= htmlspecialchars($album['artist']) ?></td>
                        <td><?= htmlspecialchars($album['album']) ?></td>
                        <td><?= (int)$album['playcount'] ?></td>
                        <td>
                            <div class="table-actions">
                                <form method="POST">
                                    <?= $csrfField ?>
                                    <input type="hidden" name="action" value="import_db">
                                    <input type="hidden" name="candidate_key" value="<?= htmlspecialchars($candidateKey) ?>">
                                    <input type="hidden" name="candidates_state" value="<?= htmlspecialchars($candidatesState) ?>">
                                    <button type="submit" class="btn btn-accent" style="padding: 8px 12px; font-size: 0.85rem;">➕ DB</button>
                                </form>
                                <form method="POST">
                                    <?= $csrfField ?>
                                    <input type="hidden" name="action" value="import_queue">
                                    <input type="hidden" name="candidate_key" value="<?= htmlspecialchars($candidateKey) ?>">
                                    <input type="hidden" name="candidates_state" value="<?= htmlspecialchars($candidatesState) ?>">
                                    <button type="submit" class="btn btn-dark" style="padding: 8px 12px; font-size: 0.85rem;">🎧 Queue</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('select-all-candidates');
                const checkboxes = document.querySelectorAll('.candidate-checkbox');
                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        checkboxes.forEach(cb => cb.checked = selectAll.checked);
                    });
                }

                const modeSelect = document.getElementById('fetch_mode');
                const periodSelect = document.getElementById('period_select');
                const topLimit = document.getElementById('top_limit');

                function toggleFields() {
                    const mode = modeSelect.value;
                    if (mode === 'top') {
                        periodSelect.style.display = 'none';
                        topLimit.style.display = 'block';
                    } else if (mode === 'liked') {
                        periodSelect.style.display = 'none';
                        topLimit.style.display = 'none';
                    } else {
                        periodSelect.style.display = 'block';
                        topLimit.style.display = 'none';
                    }
                }

                if (modeSelect) {
                    modeSelect.addEventListener('change', toggleFields);
                    toggleFields();
                }
            });
        </script>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>
