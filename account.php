<?php
require __DIR__ . '/layout.php';
$u = require_role();
$pdo = db();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $st = $pdo->prepare('SELECT password_hash FROM users WHERE id=?');
    $st->execute([$u['id']]);
    $new = isset($_POST['new']) ? $_POST['new'] : '';
    if (!password_verify(isset($_POST['current']) ? $_POST['current'] : '', $st->fetchColumn())) flash('Current password is wrong.');
    elseif (strlen($new) < 6) flash('New password must be at least 6 characters.');
    else { $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $u['id']]); flash('Password changed.'); }
    header('Location: account.php'); exit;
}
$st = $pdo->prepare('SELECT * FROM users WHERE id=?');
$st->execute([$u['id']]);
$me = $st->fetch();
page_start('Account', 'account.php');
?>
<h1>Account</h1>
<div class="card"><p><b><?= e($me['name']) ?></b> (<?= e($me['role']) ?>)<br><?= e($me['email']) ?><br><?= e($me['phone']) ?> <?= $me['unit'] ? ' - Unit ' . e($me['unit']) : '' ?></p></div>
<form method="post" class="card" style="max-width:380px"><h2>Change password</h2><?= csrf() ?>
  <input type="password" name="current" placeholder="Current password" required>
  <input type="password" name="new" placeholder="New password (min 6)" required>
  <button>Change password</button></form>
<?php page_end();
