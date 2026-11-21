<?php
require_once __DIR__ . '/includes/auth.php';
$user = require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $errors[] = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'profile') {
            $first   = trim($_POST['first_name'] ?? '');
            $last    = trim($_POST['last_name'] ?? '');
            $contact = trim($_POST['contact_number'] ?? '');
            $email   = trim($_POST['email'] ?? '');

            if ($first === '' || mb_strlen($first) > 60) {
                $errors[] = 'First name is required (up to 60 characters).';
            }
            if ($last === '' || mb_strlen($last) > 60) {
                $errors[] = 'Last name is required (up to 60 characters).';
            }
            if (!preg_match('/^[0-9+\-() ]{0,20}$/', $contact)) {
                $errors[] = 'The contact number may only have digits, spaces and + - ( ).';
            }
            if ($email !== '' && (mb_strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
                $errors[] = 'Please enter a valid email address.';
            }

            if (!$errors) {
                update_profile((int) $user['user_id'], $first, $last, $contact ?: null, $email ?: null);
                redirect('profile.php?saved=profile');
            }

        } elseif ($action === 'password') {
            $current = $_POST['current_password'] ?? '';
            $new     = $_POST['new_password'] ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!password_verify($current, $user['password_hash'])) {
                $errors[] = 'Your current password is not correct.';
            }
            if (strlen($new) < 8 || strlen($new) > 72) {
                $errors[] = 'The new password must be 8 to 72 characters long.';
            }
            if ($new !== $confirm) {
                $errors[] = 'The new password and its confirmation do not match.';
            }

            if (!$errors) {
                update_password((int) $user['user_id'], password_hash($new, PASSWORD_DEFAULT));
                session_regenerate_id(true);
                redirect('profile.php?saved=password');
            }
        }
    }
}

// Show what was just typed, or what is saved.
$f_first   = $_POST['first_name'] ?? $user['first_name'];
$f_last    = $_POST['last_name'] ?? $user['last_name'];
$f_contact = $_POST['contact_number'] ?? $user['contact_number'];
$f_email   = $_POST['email'] ?? $user['email'];

$page_title = 'Profile';
require __DIR__ . '/includes/header.php';
?>
<h1>Profile</h1>

<?php if (($_GET['saved'] ?? '') === 'profile'): ?>
  <p role="status">Your details were saved.</p>
<?php elseif (($_GET['saved'] ?? '') === 'password'): ?>
  <p role="status">Your password was changed.</p>
<?php endif; ?>
<?php foreach ($errors as $err): ?>
  <p role="alert"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="profile">

  <p>
    <label for="first_name">First name *</label><br>
    <input id="first_name" name="first_name" type="text" maxlength="60" required value="<?= e($f_first) ?>">
  </p>
  <p>
    <label for="last_name">Last name *</label><br>
    <input id="last_name" name="last_name" type="text" maxlength="60" required value="<?= e($f_last) ?>">
  </p>
  <p>
    <label for="contact_number">Contact number</label><br>
    <input id="contact_number" name="contact_number" type="text" maxlength="20" value="<?= e((string) $f_contact) ?>">
  </p>
  <p>
    <label for="email">Email</label><br>
    <input id="email" name="email" type="email" maxlength="100" value="<?= e((string) $f_email) ?>">
  </p>
  <p>
    <label for="username">Username (set by the admin)</label><br>
    <input id="username" type="text" value="<?= e($user['username']) ?>" readonly>
  </p>
  <p>
    <label for="role">Role</label><br>
    <input id="role" type="text" value="<?= e($user['role'] === 'admin' ? 'Admin' : 'Responder') ?>" readonly>
  </p>
  <p>
    <label for="position">Position</label><br>
    <input id="position" type="text" value="<?= e((string) $user['position']) ?>" readonly>
  </p>
  <button type="submit">Save changes</button>
</form>

<h2>Change password</h2>
<form method="post" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="password">

  <p>
    <label for="current_password">Current password</label><br>
    <input id="current_password" name="current_password" type="password" required>
  </p>
  <p>
    <label for="new_password">New password (8 to 72 characters)</label><br>
    <input id="new_password" name="new_password" type="password" minlength="8" maxlength="72" required>
  </p>
  <p>
    <label for="confirm_password">Confirm new password</label><br>
    <input id="confirm_password" name="confirm_password" type="password" required>
  </p>
  <button type="submit">Change password</button>
</form>
<p>Forgot your password? Ask the secretary.</p>
<?php require __DIR__ . '/includes/footer.php'; ?>