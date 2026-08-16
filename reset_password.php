<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$token = trim($_GET['token'] ?? '');
$error = '';
$success = '';

if (!$token) {
    die('Invalid or missing reset token.');
}

// Validate token
$stmt = $pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
$stmt->execute([$token]);
$reset_req = $stmt->fetch();

if (!$reset_req) {
    die('This password reset link is invalid or has expired.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $new_hash = password_hash($password, PASSWORD_DEFAULT);

        $update = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $update->execute([$new_hash, $reset_req['email']]);

        // Invalidate token
        $del = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
        $del->execute([$reset_req['email']]);

        $success = 'Password successfully reset! <a href="login.php">Click here to Login</a>';
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<div class="container" style="max-width: 480px; margin: 50px auto;">
    <h2>Reset Password</h2>
    <p>Resetting password for: <strong><?= htmlspecialchars($reset_req['email']) ?></strong></p>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="color:red; margin-bottom:15px;"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success" style="color:green; margin-bottom:15px;"><?= $success ?></div>
    <?php else: ?>
        <form method="POST">
            <div style="margin-bottom: 15px;">
                <label for="password">New Password:</label>
                <input type="password" name="password" id="password" required style="width: 100%; padding: 10px; margin-top: 5px;">
            </div>
            <div style="margin-bottom: 15px;">
                <label for="confirm_password">Confirm New Password:</label>
                <input type="password" name="confirm_password" id="confirm_password" required style="width: 100%; padding: 10px; margin-top: 5px;">
            </div>
            <button type="submit" class="btn" style="padding: 10px 20px;">Update Password</button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>