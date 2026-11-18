<?php
require __DIR__ . '/layout.php';
if (user()) { header('Location: ' . user()['role'] . '.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([strtolower(trim(isset($_POST['email']) ? $_POST['email'] : ''))]);
    $u = $st->fetch();
    if ($u && password_verify(isset($_POST['password']) ? $_POST['password'] : '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $u['id'], 'name' => $u['name'], 'role' => $u['role']];
        header('Location: ' . $u['role'] . '.php'); exit;
    }
    $err = 'Wrong email or password';
}
page_start('Log in');
?>
<form method="post" id="login" class="card">
  <h2>Log in</h2>
  <?= csrf() ?>
  <input name="email" type="email" placeholder="Email" required>
  <input name="password" type="password" placeholder="Password" required>
  <div class="err"><?= e($err) ?></div>
  <button style="width:100%">Log in</button>
  <p><small>Demo admin: admin@estate.com / admin123</small></p>
</form>
<?php page_end();
