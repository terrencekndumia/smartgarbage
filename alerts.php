<?php
require __DIR__ . '/layout.php';
$u = require_role();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {      // acknowledge alerts
    check_csrf();
    if ((isset($_POST['action']) ? $_POST['action'] : '') === 'read_all')
        $pdo->prepare("UPDATE notifications SET status='read' WHERE user_id=?")->execute([$u['id']]);
    else
        $pdo->prepare("UPDATE notifications SET status='read' WHERE id=? AND user_id=?")->execute([(int)$_POST['id'], $u['id']]);
    header('Location: alerts.php'); exit;
}
$st = $pdo->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY id DESC LIMIT 50');
$st->execute([$u['id']]);
$notes = $st->fetchAll();
page_start('Alerts', 'alerts.php', true);
?>
<h1>Notifications</h1>
<div class="card">
  <form method="post" style="margin-bottom:10px"><?= csrf() ?><input type="hidden" name="action" value="read_all"><button class="alt">Acknowledge all</button></form>
  <ul class="list">
  <?php foreach ($notes as $n): ?>
    <li class="<?= $n['status'] === 'unread' ? 'unread' : '' ?>">
      <span><?= e($n['message']) ?><br><small class="muted"><?= e(time_ago($n['sent_at'])) ?></small></span>
      <?php if ($n['status'] === 'unread'): ?>
        <form method="post"><?= csrf() ?><input type="hidden" name="id" value="<?= $n['id'] ?>"><button class="alt">Acknowledge</button></form>
      <?php endif; ?>
    </li>
  <?php endforeach; ?>
  <?php if (!$notes): ?><li class="muted">No alerts yet.</li><?php endif; ?>
  </ul>
</div>
<?php page_end();
