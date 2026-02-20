<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Please enter valid login details.';
    } else {
        $conn = db();
        $stmt = $conn->prepare('SELECT id, full_name, email, password_hash, course FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, (string) $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = (string) $user['full_name'];
            $_SESSION['user_email'] = (string) $user['email'];
            $_SESSION['user_course'] = (string) $user['course'];
            redirect('dashboard.php');
        }

        $error = 'Incorrect email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Course Enrollment</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-wrapper">
    <form id="loginForm" class="card small" method="post" novalidate>
        <h1>Login</h1>
        <p class="sub">Access your dashboard</p>

        <?php if ($error !== ''): ?>
            <div class="alert error"><p><?= e($error) ?></p></div>
        <?php endif; ?>

        <label>Email
            <input type="email" name="email" id="login_email" value="<?= e($email) ?>" required>
        </label>

        <label>Password
            <input type="password" name="password" id="login_password" required>
        </label>

        <button type="submit" class="btn">Login</button>
        <p class="muted">No account? <a href="register.php">Register</a></p>
    </form>
</div>
<script src="../assets/javascript/login.js"></script>
</body>
</html>