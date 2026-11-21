
<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');


try {
    $config = require __DIR__ . '/includes/config.php';

    $pdo = new PDO(
        'mysql:host=' . $config['db_host'] .
        ';dbname=' . $config['db_name'] .
        ';charset=utf8mb4',
        $config['db_user'],
        $config['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $count = (int) $pdo
        ->query("SELECT COUNT(*) FROM `user` WHERE role = 'admin'")
        ->fetchColumn();

    if ($count > 0) {
        exit('An Admin account already exists. Delete setup-admin.php.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');

        if (
            strlen($username) < 3 ||
            strlen($password) < 12 ||
            $firstName === '' ||
            $lastName === ''
        ) {
            exit('Complete all fields. Password must be at least 12 characters.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "INSERT INTO `user`
             (role, first_name, last_name, username, password_hash, status)
             VALUES ('admin', ?, ?, ?, ?, 'active')"
        );

        $stmt->execute([
            $firstName,
            $lastName,
            $username,
            $hash
        ]);

        exit('Admin created successfully! Delete setup-admin.php and log in.');
    }

} catch (Throwable $e) {
    http_response_code(500);
    exit('SETUP ERROR: ' . htmlspecialchars(
        $e->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    ));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>e-TALA Admin Setup</title>
</head>
<body>
    <h2>Create First Admin Account</h2>

    <form method="POST">
        <p><input name="first_name" placeholder="First Name" required></p>
        <p><input name="last_name" placeholder="Last Name" required></p>
        <p><input name="username" placeholder="Username" minlength="3" required></p>
        <p><input type="password" name="password"
                  placeholder="Password (12+ characters)"
                  minlength="12" required></p>

        <button type="submit">Create Admin</button>
    </form>
</body>
</html>
