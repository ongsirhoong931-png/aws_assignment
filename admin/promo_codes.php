<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: ../login.php');
    exit;
}

$msg = '';
$err = '';

// Handle creating new promo code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $discount = filter_input(INPUT_POST, 'discount_percent', FILTER_VALIDATE_INT);
    $max_uses = filter_input(INPUT_POST, 'max_uses', FILTER_VALIDATE_INT) ?: 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

    if (empty($code) || !$discount || $discount < 1 || $discount > 100) {
        $err = 'Please provide a valid code and discount percentage (1-100%).';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO promo_codes (code, discount_percent, max_uses, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$code, $discount, $max_uses, $expires_at]);
            $msg = 'Promo code created successfully.';
        } catch (PDOException $e) {
            $err = 'Code already exists or database error.';
        }
    }
}

// Handle toggle status / delete
if (isset($_GET['toggle_id'])) {
    $stmt = $pdo->prepare("UPDATE promo_codes SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$_GET['toggle_id']]);
    header('Location: promo_codes.php');
    exit;
}

$promos = $pdo->query("SELECT * FROM promo_codes ORDER BY id DESC")->fetchAll();

require_once __DIR__ . '/partials/header.php';
?>

<div class="admin-container" style="padding: 20px;">
    <h2>Promo Codes & Discounts</h2>

    <?php if ($msg): ?><div style="color:green; margin-bottom:10px;"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div style="color:red; margin-bottom:10px;"><?= $err ?></div><?php endif; ?>

    <!-- Add Promo Code Form -->
    <div style="background:#fff; padding:20px; border-radius:8px; margin-bottom:20px; max-width:600px;">
        <h3>Create Promo Code</h3>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div style="margin-bottom:10px;">
                <label>Code (e.g., STUDENT10):</label>
                <input type="text" name="code" required style="width:100%; padding:8px; text-transform:uppercase;">
            </div>
            <div style="margin-bottom:10px;">
                <label>Discount (%):</label>
                <input type="number" name="discount_percent" min="1" max="100" required style="width:100%; padding:8px;">
            </div>
            <div style="margin-bottom:10px;">
                <label>Max Uses (0 for unlimited):</label>
                <input type="number" name="max_uses" value="0" style="width:100%; padding:8px;">
            </div>
            <div style="margin-bottom:10px;">
                <label>Expiry Date (Optional):</label>
                <input type="date" name="expires_at" style="width:100%; padding:8px;">
            </div>
            <button type="submit" class="btn" style="padding:8px 16px;">Create Code</button>
        </form>
    </div>

    <!-- Promo Codes Table -->
    <div style="background:#fff; padding:20px; border-radius:8px;">
        <h3>Active Promo Codes</h3>
        <table class="table" style="width:100%; border-collapse:collapse; margin-top:10px;">
            <thead>
                <tr style="border-bottom:2px solid #edf2f7; text-align:left;">
                    <th style="padding:8px;">Code</th>
                    <th style="padding:8px;">Discount</th>
                    <th style="padding:8px;">Usage</th>
                    <th style="padding:8px;">Expires</th>
                    <th style="padding:8px;">Status</th>
                    <th style="padding:8px;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($promos as $p): ?>
                    <tr style="border-bottom:1px solid #edf2f7;">
                        <td style="padding:8px; font-weight:bold;"><?= htmlspecialchars($p['code']) ?></td>
                        <td style="padding:8px;"><?= $p['discount_percent'] ?>%</td>
                        <td style="padding:8px;"><?= $p['used_count'] ?> / <?= $p['max_uses'] ?: '∞' ?></td>
                        <td style="padding:8px;"><?= $p['expires_at'] ?? 'No Expiry' ?></td>
                        <td style="padding:8px; color:<?= $p['is_active'] ? 'green' : 'gray' ?>;">
                            <?= $p['is_active'] ? 'Active' : 'Disabled' ?>
                        </td>
                        <td style="padding:8px;">
                            <a href="promo_codes.php?toggle_id=<?= $p['id'] ?>" class="btn" style="font-size:12px; padding:4px 8px;">
                                <?= $p['is_active'] ? 'Disable' : 'Enable' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>