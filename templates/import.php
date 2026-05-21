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
