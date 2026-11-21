<?php
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Go to the right home page.
if ($u = current_user()) {
    redirect($u['role'] === 'admin' ? 'admin/dashboard.php' : 'today.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $error = 'Your session expired. Please try again.';
    } else {
        $user = attempt_login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '');
        if ($user) {
            redirect($user['role'] === 'admin' ? 'admin/dashboard.php' : 'today.php');
        }
        $error = 'Wrong username or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Log in - e-TALA</title>
  <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>?v=<?= (int) @filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body class="login">
  <main class="login-box">
    <img src="<?= e(url('assets/img/fire-rescue-logo.jpg')) ?>" alt="Barangay San Juan Fire and Rescue logo" width="140" height="140">
    <h1>e-TALA</h1>
    <p class="login-sub">Incident recording and reporting<br>Barangay San Juan, Cainta, Rizal</p>

    <?php if ($error): ?>
      <p role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <p>
        <label for="username">Username</label>
        <input id="username" name="username" type="text" required autofocus>
      </p>
      <p>
        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>
      </p>
      <button type="submit">Log in</button>
    </form>
    <p class="login-help">Forgot your password? Ask the secretary.</p>
  </main>
</body>
</html>