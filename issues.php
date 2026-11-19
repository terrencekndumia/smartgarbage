<?php
require __DIR__ . '/layout.php';
$u = require_role('admin');
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)$_POST['id'];
    $st = $pdo->prepare("SELECT * FROM bin_reports WHERE id=? AND status='open'");
    $st->execute([$id]);
    if ($r = $st->fetch()) {
        $pdo->prepare("UPDATE bin_reports SET status='resolved', resolved_at=NOW() WHERE id=?")->execute([$id]);
        notify($r['user_id'], $r['bin_id'], 'Your damage report for ' . bin_label($r['bin_id']) . ' has been resolved.');
        flash('Report marked as resolved.');
    }
    header('Location: issues.php'); exit;
}
$types = report_types();
$rows = $pdo->query("SELECT r.*, u.name AS resident, u.unit, b.location FROM bin_reports r JOIN users u ON u.id=r.user_id JOIN garbage_bins b ON b.id=r.bin_id ORDER BY (r.status='resolved'), r.id DESC LIMIT 60")->fetchAll();
page_start('Issues', 'issues.php');
?>
<h1>Damage reports</h1>
<div class="card scroll"><table>
<tr><th>Bin</th><th>Resident</th><th>Problem</th><th>Details</th><th>Reported</th><th>Status</th><th></th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><b><?= e(bin_label($r['bin_id'])) ?></b><br><small class="muted"><?= e($r['location']) ?></small></td>
  <td><?= e($r['resident']) ?> <?= $r['unit'] ? '(' . e($r['unit']) . ')' : '' ?></td>
  <td><?= e(isset($types[$r['type']]) ? $types[$r['type']] : $r['type']) ?></td>
  <td><?= $r['description'] !== '' ? e($r['description']) : '<span class="muted">-</span>' ?></td>
  <td><?= e(time_ago($r['created_at'])) ?></td>
  <td><span class="tag <?= $r['status'] === 'resolved' ? 'green' : 'amber' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
  <td><?php if ($r['status'] === 'open'): ?><form method="post" class="inline"><?= csrf() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><button>Mark resolved</button></form><?php endif; ?></td></tr>
<?php endforeach; ?>
<?php if (!$rows): ?><tr><td colspan="7" class="muted">No damage reports. &#127881;</td></tr><?php endif; ?>
</table></div>
<?php page_end();
