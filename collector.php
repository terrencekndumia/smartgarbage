<?php
require __DIR__ . '/layout.php';
$me = require_role('collector');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {      // mark collected: empties the bin
    check_csrf();
    $pdo = db();
    $id = (int)$_POST['alert_id'];
    $st = $pdo->prepare("SELECT bin_id FROM alerts WHERE id = ? AND status = 'open'");
    $st->execute([$id]);
    if ($binId = $st->fetchColumn()) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE alerts SET status = 'done', collected_by = ?, done_on = NOW() WHERE id = ?")->execute([$me['name'], $id]);
        $pdo->prepare('UPDATE bins SET level = 0, updated = NOW() WHERE id = ?')->execute([$binId]);
        $pdo->commit();
        flash('Marked as collected.');
    }
    header('Location: collector.php'); exit;
}

$alerts = db()->query("SELECT a.id, a.created, b.house, b.level, u.name AS resident
    FROM alerts a JOIN bins b ON b.id = a.bin_id JOIN users u ON u.id = b.resident_id
    WHERE a.status = 'open' ORDER BY a.created")->fetchAll();
page_start('Pickups', 10);
?>
<div class="card"><h2>Pickup alerts</h2>
  <table><tr><th>House</th><th>Resident</th><th>Fill level</th><th>Alerted</th><th></th></tr>
  <?php foreach ($alerts as $a): ?>
    <tr><td><?= e($a['house']) ?></td><td><?= e($a['resident']) ?></td><td><?= bar($a['level']) ?></td><td><?= e($a['created']) ?></td>
      <td><form method="post"><?= csrf() ?><input type="hidden" name="alert_id" value="<?= $a['id'] ?>"><button>Mark collected</button></form></td></tr>
  <?php endforeach; ?>
  <?php if (!$alerts): ?><tr><td colspan="5">No pickups pending 🎉</td></tr><?php endif; ?>
  </table>
</div>
<?php page_end();
