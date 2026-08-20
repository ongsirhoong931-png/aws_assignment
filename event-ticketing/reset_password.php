<?php

require 'config.php';
require 'auth.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

$error = '';
$success = '';

if ($token === '') {
    die('Invalid or missing reset token.');
}

/*
 * Check whether token exists and has not expired
 */
$stmt = $conn->prepare('
    SELECT email
    FROM password_resets
    WHERE token = ?
      AND expires_at > NOW()
    LIMIT 1
');

if (!$stmt) {
    die('Database error: ' . $conn->error);
}

$stmt->bind_param('s', $token);
$stmt->execute();

$result = $stmt->get_result();
$reset = $result->fetch_assoc();

$stmt->close();

if (!$reset) {
    die('This password reset link is invalid or has expired.');
}


/*
 * Process new password
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($password === '') {

        $error = 'Please enter a new password.';

    } elseif (strlen($password) < 6) {

        $error = 'Password must be at least 6 characters.';

    } elseif ($password !== $confirm_password) {

        $error = 'Passwords do not match.';

    } else {

        $password_hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        /*
         * Update user's password
         */
        $stmt = $conn->prepare('
            UPDATE users
            SET password_hash = ?
            WHERE email = ?
        ');

        $stmt->bind_param(
            'ss',
            $password_hash,
            $reset['email']
        );

        $stmt->execute();
        $stmt->close();


        /*
         * Delete the reset token
         * so it cannot be reused.
         */
        $stmt = $conn->prepare('
            DELETE FROM password_resets
            WHERE token = ?
        ');

        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();

        $success = 'Your password has been successfully changed.';
    }
}

$pageTitle = 'Reset Password';

require 'partials/header.php';
?>

<div class="form-card">

    <h1>Reset Password</h1>

    <?php if ($error): ?>

        <p class="alert alert-error">
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>


    <?php if ($success): ?>

        <div class="alert alert-success">

            <p>
                <?= htmlspecialchars($success) ?>
            </p>

            <p>
                <a href="login.php">
                    Go to Login
                </a>
            </p>

        </div>

    <?php else: ?>

        <form method="post">

            <input
                type="hidden"
                name="token"
                value="<?= htmlspecialchars($token) ?>"
            >

            <label>
                New Password

                <input
                    type="password"
                    name="password"
                    required
                    minlength="6"
                >
            </label>

            <label>
                Confirm New Password

                <input
                    type="password"
                    name="confirm_password"
                    required
                    minlength="6"
                >
            </label>

            <button type="submit">
                Change Password
            </button>

        </form>

    <?php endif; ?>

</div>

<?php require 'partials/footer.php'; ?>