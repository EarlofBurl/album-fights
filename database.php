<?php
require_once 'includes/config.php';
require_once 'includes/data_manager.php';
require_once 'includes/api_manager.php';

function normalizeDuplicateToken($value) {
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string)$value);
    $value = strtolower(trim((string)$value));
    $value = preg_replace('/\b(deluxe|edition|remaster(?:ed)?|expanded|anniversary|version)\b/', ' ', $value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

function similarityScore($a, $b) {
    if ($a === '' || $b === '') {
        return 0.0;
    }
    similar_text($a, $b, $percent);
    return (float)$percent;
}

function buildDuplicateGroups($albums) {
    $count = count($albums);
    if ($count < 2) {
        return [];
    }

    $parents = range(0, $count - 1);

    $find = function($x) use (&$parents, &$find) {
        if ($parents[$x] !== $x) {
            $parents[$x] = $find($parents[$x]);
        }
        return $parents[$x];
    };

    $union = function($a, $b) use (&$parents, $find) {
        $ra = $find($a);
        $rb = $find($b);
        if ($ra !== $rb) {
            $parents[$rb] = $ra;
        }
    };

    $normArtists = [];
    $normAlbums = [];
    for ($i = 0; $i < $count; $i++) {
        $normArtists[$i] = normalizeDuplicateToken($albums[$i]['Artist']);
        $normAlbums[$i] = normalizeDuplicateToken($albums[$i]['Album']);
    }

    for ($i = 0; $i < $count; $i++) {
        for ($j = $i + 1; $j < $count; $j++) {
            $artistScore = similarityScore($normArtists[$i], $normArtists[$j]);
            $albumScore = similarityScore($normAlbums[$i], $normAlbums[$j]);

            $isProbableDuplicate = ($artistScore >= 92 && $albumScore >= 86) || ($artistScore >= 96 && $albumScore >= 75);
            if ($isProbableDuplicate) {
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

    $groups = array_values(array_filter($groups, function($group) {
        return count($group) > 1;
    }));

    usort($groups, function($a, $b) {
        return count($b) <=> count($a);
    });

    return $groups;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $albums = loadCsv(FILE_ELO);
    $postedAction = (string)($_GET['action'] ?? $_POST['action'] ?? '');

    if ($postedAction === 'save_blacklist') {
        $raw = (string)($_POST['tag_blacklist'] ?? '');
        $parts = preg_split('/[\r\n,]+/', $raw);
        $tags = [];
        foreach ($parts as $tag) {
            $normalized = normalizeTagValue($tag);
            if ($normalized !== '') {
                $tags[] = $normalized;
            }
        }

        $APP_SETTINGS['tag_blacklist'] = array_values(array_unique($tags));
        file_put_contents(FILE_SETTINGS, json_encode($APP_SETTINGS, JSON_PRETTY_PRINT));
        $message = '✅ Tag blacklist saved.';
    }

    if ($postedAction === 'delete_duplicate') {
        $deleteIndex = isset($_POST['delete_index']) ? (int)$_POST['delete_index'] : (isset($_GET['delete_index']) ? (int)$_GET['delete_index'] : -1);
        if (isset($albums[$deleteIndex])) {
            array_splice($albums, $deleteIndex, 1);
            saveCsv(FILE_ELO, $albums);
            $message = '✅ Duplicate entry deleted.';
        }
    }

    if ($postedAction === 'merge_duplicates') {
        $indicesRaw = (string)($_POST['group_indices'] ?? '');
        $indices = array_values(array_unique(array_filter(array_map('intval', explode(',', $indicesRaw)), function($idx) use ($albums) {
            return isset($albums[$idx]);
        })));
        $primaryIndex = isset($_POST['primary_index']) ? (int)$_POST['primary_index'] : -1;

        if (count($indices) >= 2 && in_array($primaryIndex, $indices, true) && isset($albums[$primaryIndex])) {
            $sumPlaycount = 0;
            $sumDuels = 0;
            $eloTotal = 0;

            foreach ($indices as $idx) {
                $sumPlaycount += (int)$albums[$idx]['Playcount'];
                $sumDuels += (int)$albums[$idx]['Duels'];
                $eloTotal += (float)$albums[$idx]['Elo'];
            }

            $albums[$primaryIndex]['Playcount'] = $sumPlaycount;
            $albums[$primaryIndex]['Duels'] = $sumDuels;
            $albums[$primaryIndex]['Elo'] = $eloTotal / count($indices);

            rsort($indices);
            foreach ($indices as $idx) {
                if ($idx === $primaryIndex) {
                    continue;
                }
                array_splice($albums, $idx, 1);
            }

            saveCsv(FILE_ELO, $albums);
            $message = '✅ Duplicate group merged (plays summed, Elo averaged).';
        }
    }
}

$albums = loadCsv(FILE_ELO);
$blacklist = is_array($APP_SETTINGS['tag_blacklist'] ?? null) ? $APP_SETTINGS['tag_blacklist'] : [];

$tagCounts = [];
$blacklistLookup = [];
foreach ($blacklist as $tag) {
    $normalized = normalizeTagValue($tag);
    if ($normalized !== '') {
        $blacklistLookup[$normalized] = true;
    }
}

foreach (glob(DIR_CACHE . '*.json') as $jsonFile) {
    $info = json_decode(file_get_contents($jsonFile), true);
    if (!is_array($info) || !is_array($info['genres'] ?? null)) {
        continue;
    }

    foreach ($info['genres'] as $rawTag) {
        $normalized = normalizeTagValue($rawTag);
        if ($normalized === '') {
            continue;
        }

        $display = ucwords($normalized);
        if (!isset($tagCounts[$display])) {
            $tagCounts[$display] = 0;
        }
        $tagCounts[$display]++;
    }
}
arsort($tagCounts);

$duplicateGroups = buildDuplicateGroups($albums);

require_once 'includes/header.php';
?>

<div style="width: 100%; max-width: 1300px; margin: 0 auto;">
    <h2 style="margin-top: 0;">🗄️ Database</h2>

    <?php if ($message !== ''): ?>
        <div style="background: rgba(76, 175, 80, 0.2); border: 1px solid #4CAF50; padding: 10px 14px; border-radius: 8px; margin-bottom: 20px;">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 20px; align-items: start;">
        <section style="background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px;">
            <h3 style="margin-top: 0; color: var(--accent);">1) Tag Blacklisting</h3>
            <p style="color: var(--text-muted); margin-top: 0;">Blacklisted tags are removed from stats and album metadata displays.</p>

            <form method="post" style="margin-bottom: 14px;">
                <input type="hidden" name="action" value="save_blacklist">
                <label for="tag_blacklist" style="display: block; margin-bottom: 6px; font-weight: 600;">Blacklist (one tag per line or comma-separated)</label>
                <textarea id="tag_blacklist" name="tag_blacklist" rows="8" style="width: 100%; background: #111; color: #fff; border: 1px solid var(--border); border-radius: 8px; padding: 10px;"><?= htmlspecialchars(implode("\n", $blacklist)) ?></textarea>
                <button class="btn-small" type="submit" style="margin-top: 10px; background: var(--accent); color: #000;">Save blacklist</button>
            </form>

            <h4 style="margin-bottom: 8px;">Detected tags in cache</h4>
            <div style="max-height: 350px; overflow: auto; border: 1px solid var(--border); border-radius: 8px; padding: 10px;">
                <?php if (empty($tagCounts)): ?>
                    <p style="margin: 0; color: var(--text-muted);">No tags in cache yet. Open some duels first to build metadata.</p>
                <?php else: ?>
                    <ul style="margin: 0; padding-left: 18px;">
                        <?php foreach ($tagCounts as $tag => $count): ?>
                            <li style="margin-bottom: 4px;"><strong><?= htmlspecialchars($tag) ?></strong> <?php if (isset($blacklistLookup[normalizeTagValue($tag)])): ?><span style="color: #cf6679;">[blacklisted]</span><?php endif; ?> <span style="color: var(--text-muted);">(<?= (int)$count ?>)</span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section style="background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px;">
            <h3 style="margin-top: 0; color: var(--accent);">2) Duplicate Check & Resolving</h3>
            <p style="color: var(--text-muted); margin-top: 0;">Fuzzy duplicate detection by similar artist+album names. Merge keeps one main entry, sums plays, and averages Elo.</p>

            <p style="margin: 0 0 12px 0; color: var(--text-muted);">Found groups: <strong style="color: #fff;"><?= count($duplicateGroups) ?></strong></p>

            <div style="max-height: 600px; overflow: auto; display: grid; gap: 12px;">
                <?php if (empty($duplicateGroups)): ?>
                    <div style="padding: 12px; border: 1px solid var(--border); border-radius: 8px; color: var(--text-muted);">No duplicate groups found.</div>
                <?php else: ?>
                    <?php foreach ($duplicateGroups as $groupIndex => $group): ?>
                        <form method="post" style="border: 1px solid var(--border); border-radius: 8px; padding: 12px; background: #191919;">
                            <input type="hidden" name="action" value="merge_duplicates">
                            <input type="hidden" name="group_indices" value="<?= htmlspecialchars(implode(',', $group)) ?>">
                            <div style="margin-bottom: 8px;"><strong>Group #<?= $groupIndex + 1 ?></strong> <span style="color: var(--text-muted);">(<?= count($group) ?> entries)</span></div>

                            <?php foreach ($group as $idx): $entry = $albums[$idx]; ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid #2a2a2a; padding: 8px 0;">
                                    <label style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0;">
                                        <input type="radio" name="primary_index" value="<?= (int)$idx ?>" <?= $idx === $group[0] ? 'checked' : '' ?>>
                                        <span>
                                            <strong><?= htmlspecialchars($entry['Artist']) ?></strong> — <?= htmlspecialchars($entry['Album']) ?><br>
                                            <small style="color: var(--text-muted);">Elo: <?= round((float)$entry['Elo'], 2) ?> | Plays: <?= (int)$entry['Playcount'] ?> | Duels: <?= (int)$entry['Duels'] ?></small>
                                        </span>
                                    </label>

                                    <button class="btn-small btn-delete" type="submit" formaction="database.php?action=delete_duplicate&delete_index=<?= (int)$idx ?>" formmethod="post">Delete this one</button>
                                </div>
                            <?php endforeach; ?>

                            <button class="btn-small" type="submit" style="margin-top: 8px; background: #4CAF50; color: #fff;">Merge group into selected main entry</button>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
