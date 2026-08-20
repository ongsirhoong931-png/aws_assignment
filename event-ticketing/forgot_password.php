<?php

require 'config.php';
require 'auth.php';
require 'helpers.php';

$error = '';
$message = '';

$step = 1;

$email = '';
$security_question = '';


// =====================================================
// STEP 1 - ENTER EMAIL
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'email') {

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {

        $error = 'Please enter your email address.';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } else {

        $stmt = $conn->prepare(
            'SELECT id, email, security_question
             FROM users
             WHERE email = ?'
        );

        if (!$stmt) {
            die('Database Error: ' . $conn->error);
        }

        $stmt->bind_param('s', $email);

        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        $stmt->close();


        if (!$user) {

            $error = 'No account was found with this email address.';

        } elseif (
            empty($user['security_question'])
        ) {

            $error = 'This account does not have a security question. Please create a new account with a security question.';

        } else {

            // Save email for next step
            $_SESSION['reset_email'] = $email;

            $security_question = $user['security_question'];

            $_SESSION['reset_question'] = $security_question;

            $step = 2;
        }
    }
}


// =====================================================
// STEP 2 - VERIFY SECURITY ANSWER
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'answer') {

    $email = $_SESSION['reset_email'] ?? '';

    $answer = trim($_POST['security_answer'] ?? '');

    if ($email === '') {

        $error = 'Your password reset session has expired. Please start again.';

        $step = 1;

    } elseif ($answer === '') {

        $error = 'Please enter your security answer.';

        $step = 2;

        $security_question = $_SESSION['reset_question'] ?? '';

    } else {

        $stmt = $conn->prepare(
            'SELECT id, security_question, security_answer_hash
             FROM users
             WHERE email = ?'
        );

        if (!$stmt) {
            die('Database Error: ' . $conn->error);
        }

        $stmt->bind_param('s', $email);

        $stmt->execute();

        $user = $stmt->get_result()->fetch_assoc();

        $stmt->close();


        if (!$user) {

            $error = 'Account not found.';

            $step = 1;

        } elseif (
            empty($user['security_answer_hash'])
        ) {

            $error = 'This account does not have a security answer.';

            $step = 1;

        } elseif (
            password_verify(
                strtolower($answer),
                $user['security_answer_hash']
            )
        ) {

            // Security answer is correct
            $_SESSION['reset_verified'] = true;

            $_SESSION['reset_user_id'] = $user['id'];

            $step = 3;

        } else {

            $error = 'Incorrect security answer.';

            $step = 2;

            $security_question =
                $user['security_question'];
        }
    }
}


// =====================================================
// STEP 3 - CHANGE PASSWORD
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'password') {

    $user_id = $_SESSION['reset_user_id'] ?? 0;

    $verified = $_SESSION['reset_verified'] ?? false;

    $new_password = $_POST['new_password'] ?? '';

    $confirm_password = $_POST['confirm_password'] ?? '';


    if (!$verified || !$user_id) {

        $error = 'Password reset verification expired. Please start again.';

        $step = 1;

    } elseif ($new_password === '') {

        $error = 'Please enter a new password.';

        $step = 3;

    } elseif (strlen($new_password) < 6) {

        $error = 'New password must be at least 6 characters.';

        $step = 3;

    } elseif ($new_password !== $confirm_password) {

        $error = 'Passwords do not match.';

        $step = 3;

    } else {

        $password_hash =
            password_hash(
                $new_password,
                PASSWORD_DEFAULT
            );


        $stmt = $conn->prepare(
            'UPDATE users
             SET password_hash = ?
             WHERE id = ?'
        );

        if (!$stmt) {
            die('Database Error: ' . $conn->error);
        }

        $stmt->bind_param(
            'si',
            $password_hash,
            $user_id
        );

        $stmt->execute();

        $stmt->close();


        // Clear reset session
        unset($_SESSION['reset_email']);
        unset($_SESSION['reset_question']);
        unset($_SESSION['reset_verified']);
        unset($_SESSION['reset_user_id']);


        $message =
            'Your password has been successfully reset. You can now login.';

        $step = 4;
    }
}


$pageTitle = 'Forgot Password';

require 'partials/header.php';
?>


<div class="form-card">

<h1>Forgot Password?</h1>


<?php if ($error): ?>

    <p class="alert alert-error">
        <?= htmlspecialchars($error) ?>
    </p>

<?php endif; ?>


<?php if ($message): ?>

    <p class="alert alert-success">
        <?= htmlspecialchars($message) ?>
    </p>

<?php endif; ?>


<!-- ================================================= -->
<!-- STEP 1 -->
<!-- ================================================= -->

<?php if ($step === 1): ?>

    <p>
        Enter your registered email address.
    </p>


    <form method="post">

        <input
            type="hidden"
            name="step"
            value="email">


        <label>
            Email Address

            <input
                type="email"
                name="email"
                value="<?= htmlspecialchars($email) ?>"
                required>
        </label>


        <button type="submit">
            Continue
        </button>

    </form>


<!-- ================================================= -->
<!-- STEP 2 -->
<!-- ================================================= -->

<?php elseif ($step === 2): ?>

    <p>
        Answer your security question to continue.
    </p>


    <form method="post">

        <input
            type="hidden"
            name="step"
            value="answer">


        <label>
            Security Question

            <input
                type="text"
                value="<?= htmlspecialchars($security_question) ?>"
                disabled>
        </label>


        <label>
            Your Answer

            <input
                type="text"
                name="security_answer"
                required
                autocomplete="off">
        </label>


        <button type="submit">
            Verify Answer
        </button>

    </form>


<!-- ================================================= -->
<!-- STEP 3 -->
<!-- ================================================= -->

<?php elseif ($step === 3): ?>

    <p>
        Verification successful. Please create a new password.
    </p>


    <form method="post">

        <input
            type="hidden"
            name="step"
            value="password">


        <label>
            New Password

            <input
                type="password"
                name="new_password"
                required>
        </label>


        <label>
            Confirm New Password

            <input
                type="password"
                name="confirm_password"
                required>
        </label>


        <button type="submit">
            Reset Password
        </button>

    </form>


<!-- ================================================= -->
<!-- STEP 4 -->
<!-- ================================================= -->

<?php elseif ($step === 4): ?>

    <p>
        Your password has been reset successfully.
    </p>


    <p>
        <a
            class="btn"
            href="login.php">

            Login Now

        </a>
    </p>

<?php endif; ?>


<?php if ($step !== 4): ?>

    <p style="margin-top:20px;">

        <a href="login.php">
            ← Back to Login
        </a>

    </p>

<?php endif; ?>

</div>


<?php require 'partials/footer.php'; ?>