<?php
require __DIR__ . '/layout.php';
if (user()) { header('Location: dashboard.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $r = create_user($_POST['name'] ?? '', $_POST['email'] ?? '', $_POST['phone'] ?? '', 'resident', $_POST['password'] ?? '', $_POST['unit'] ?? '');
    if ($r === true) {
        notify_roles(['admin'], null, 'New resident registered: ' . trim($_POST['name']) . ' (Unit ' . trim($_POST['unit']) . '). Register a bin for them.');
        flash('Account created. You can log in now. The estate manager will allocate your bin.');
        header('Location: index.php'); exit;
    }
    $err = $r;
}
page_start('Register');
?>
<form method="post" class="authcard">
  <h1>Create your account</h1><p class="muted">The estate manager will allocate your smart bin.</p><?= csrf() ?>
  <label>Full name</label><input name="name" placeholder="Your full name" required>
  <label>Email</label><input name="email" type="email" placeholder="you@example.com" required>
  <div class="two-in"><div><label>Phone</label><input name="phone" placeholder="07xx xxx xxx"></div>
  <div><label>House / unit</label><input name="unit" placeholder="e.g. A1" required></div></div>
  <label>Password</label><input name="password" type="password" placeholder="At least 6 characters" required>
  <div class="err"><?= e($err) ?></div>
  <button class="btn-lg" style="width:100%">Create account</button>
  <p class="center"><a href="index.php">Back to log in</a></p>
</form>
<?php page_end();
