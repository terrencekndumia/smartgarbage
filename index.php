<?php
require __DIR__ . '/layout.php';
if (user()) { header('Location: dashboard.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''))]);
    $u = $st->fetch();
    if ($u && password_verify(isset($_POST['password']) ? $_POST['password'] : '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$u['id'], 'name' => $u['name'], 'role' => $u['role']];
        header('Location: dashboard.php'); exit;
    }
    $err = 'Wrong email or password';
}
page_start('Log in');
?>
<form method="post" class="authcard">
  <h1>Welcome back</h1><p class="muted">Log in to monitor your estate's bins.</p>
  <?= csrf() ?>
  <label>Email</label><input name="email" type="email" placeholder="you@example.com" required>
  <label>Password</label><input name="password" type="password" placeholder="Your password" required>
  <div class="err"><?= e($err) ?></div>
  <button class="btn-lg" style="width:100%">Log in</button>
  <p class="center">New resident? <a href="register.php">Create an account</a></p>
</form>
<?php page_end();
