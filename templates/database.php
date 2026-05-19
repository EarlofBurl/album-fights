<?php
/**
 * @var string $csrfField
 * @var string $message
 * @var list<array<string, mixed>> $albums
 * @var list<string> $blacklist
 * @var array<string, true> $blacklistLookup
 * @var array<string, int> $tagCounts
 * @var list<list<int>> $duplicateGroups
 * @var MetadataService $metaService
 */

require __DIR__ . '/partials/header.php';
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
                <?= $csrfField ?>
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
                            <li style="margin-bottom: 4px;"><strong><?= htmlspecialchars($tag) ?></strong> <?php if (isset($blacklistLookup[strtolower($tag)])): ?><span style="color: #cf6679;">[blacklisted]</span><?php endif; ?> <span style="color: var(--text-muted);">(<?= (int)$count ?>)</span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section style="background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px;">
            <h3 style="margin-top: 0; color: var(--accent);">2) Duplicate Check & Resolving</h3>
            <p style="color: var(--text-muted); margin-top: 0;">Fuzzy duplicate detection by similar artist+album names. Merge keeps one main entry, sums plays + duels, and averages Elo.</p>
            <p style="margin: 0 0 12px 0; color: var(--text-muted);">Found groups: <strong style="color: #fff;"><?= count($duplicateGroups) ?></strong></p>
            <div style="max-height: 600px; overflow: auto; display: grid; gap: 12px;">
                <?php if (empty($duplicateGroups)): ?>
                    <div style="padding: 12px; border: 1px solid var(--border); border-radius: 8px; color: var(--text-muted);">No duplicate groups found.</div>
                <?php else: ?>
                    <?php foreach ($duplicateGroups as $groupIndex => $group): ?>
                        <form method="post" style="border: 1px solid var(--border); border-radius: 8px; padding: 12px; background: #191919;">
                            <?= $csrfField ?>
                            <input type="hidden" name="action" value="merge_duplicates">
                            <input type="hidden" name="group_indices" value="<?= htmlspecialchars(implode(',', $group)) ?>">
                            <div style="margin-bottom: 8px;"><strong>Group #<?= $groupIndex + 1 ?></strong> <span style="color: var(--text-muted);">(<?= count($group) ?> entries)</span></div>
                            <?php foreach ($group as $idx): $entry = $albums[$idx]; ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; border-top: 1px solid #2a2a2a; padding: 8px 0;">
                                    <label style="display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0;">
                                        <input type="radio" name="primary_index" value="<?= (int)$idx ?>" <?= $idx === $group[0] ? 'checked' : '' ?>>
                                        <span>
                                            <strong><?= htmlspecialchars((string)$entry['Artist']) ?></strong> — <?= htmlspecialchars((string)$entry['Album']) ?><br>
                                            <small style="color: var(--text-muted);">Elo: <?= round((float)$entry['Elo'], 2) ?> | Plays: <?= (int)$entry['Playcount'] ?> | Duels: <?= (int)$entry['Duels'] ?> | W/L: <?= (int)($entry['Wins'] ?? 0) ?>/<?= (int)($entry['Losses'] ?? 0) ?></small>
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

<?php require __DIR__ . '/partials/footer.php'; ?>
