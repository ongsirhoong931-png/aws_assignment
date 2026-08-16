<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $ins = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $ins->execute([$email, $token, $expires]);

            // In production, send via mail(). For local testing, display the link:
            $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
            $message = "Reset link generated! <br><a href='{$reset_link}'>Click here to reset your password</a> (Valid for 1 hour).";
        } else {
            // Consistent message to prevent email enumeration
            $message = "If this email is registered, a reset link has been issued.";
        }
    }
}

require_once __DIR__ . '/partials/header.php';
?>

<div class="container" style="max-width: 480px; margin: 50px auto;">
    <h2>Forgot Password</h2>
    <p>Enter your registered email address to receive a password reset link.</p>

    <?php if ($error): ?>
        <div class="alert alert-danger" style="color:red; margin-bottom:15px;"><?= $error ?></div>
    <?php endif; ?>
    <?php if ($message): ?>
        <div class="alert alert-success" style="color:green; margin-bottom:15px;"><?= $message ?></div>
    <?php endif; ?>

    <form method="POST" action="forgot_password.php">
        <div style="margin-bottom: 15px;">
            <label for="email">Email Address:</label>
            <input type="email" name="email" id="email" required style="width: 100%; padding: 10px; margin-top: 5px;">
        </div>
        <button type="submit" class="btn" style="padding: 10px 20px;">Send Reset Link</button>
        <a href="login.php" style="margin-left: 15px;">Back to Login</a>
    </form>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>