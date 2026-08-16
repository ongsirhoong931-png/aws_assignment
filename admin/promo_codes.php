<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Make sure admin is logged in
require_admin();

$msg = '';
$err = '';


// =====================================================
// CREATE PROMO CODE
// =====================================================

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'create'
) {

    $code = strtoupper(trim($_POST['code'] ?? ''));

    $discount = filter_input(
        INPUT_POST,
        'discount_percent',
        FILTER_VALIDATE_INT
    );

    $max_uses = filter_input(
        INPUT_POST,
        'max_uses',
        FILTER_VALIDATE_INT
    );

    if ($max_uses === false || $max_uses === null) {
        $max_uses = 0;
    }

    $expires_at = !empty($_POST['expires_at'])
        ? $_POST['expires_at']
        : null;


    // Validate
    if (
        $code === '' ||
        $discount === false ||
        $discount === null ||
        $discount < 1 ||
        $discount > 100
    ) {

        $err = 'Please provide a valid code and discount percentage (1-100%).';

    } else {

        // Check whether code already exists
        $stmt = $conn->prepare(
            'SELECT id FROM promo_codes WHERE code = ?'
        );

        if (!$stmt) {
            $err = 'Database error: ' . $conn->error;
        } else {

            $stmt->bind_param('s', $code);
            $stmt->execute();

            $existing = $stmt->get_result()->fetch_assoc();

            $stmt->close();


            if ($existing) {

                $err = 'Promo code already exists.';

            } else {

                // Insert new promo code
                $stmt = $conn->prepare(
                    'INSERT INTO promo_codes
                    (code, discount_percent, max_uses, expires_at)
                    VALUES (?, ?, ?, ?)'
                );

                if (!$stmt) {

                    $err = 'Database error: ' . $conn->error;

                } else {

                    $stmt->bind_param(
                        'siis',
                        $code,
                        $discount,
                        $max_uses,
                        $expires_at
                    );

                    if ($stmt->execute()) {

                        $msg = 'Promo code created successfully.';

                    } else {

                        $err = 'Unable to create promo code: ' . $stmt->error;
                    }

                    $stmt->close();
                }
            }
        }
    }
}


// =====================================================
// ENABLE / DISABLE PROMO CODE
// =====================================================

if (isset($_GET['toggle_id'])) {

    $toggle_id = filter_input(
        INPUT_GET,
        'toggle_id',
        FILTER_VALIDATE_INT
    );

    if ($toggle_id) {

        $stmt = $conn->prepare(
            'UPDATE promo_codes
             SET is_active = NOT is_active
             WHERE id = ?'
        );

        if ($stmt) {

            $stmt->bind_param('i', $toggle_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    header('Location: promo_codes.php');
    exit;
}


// =====================================================
// GET PROMO CODES
// =====================================================

$result = $conn->query(
    'SELECT *
     FROM promo_codes
     ORDER BY id DESC'
);

if (!$result) {

    die(
        'Database error: ' .
        htmlspecialchars($conn->error)
    );
}

$promos = $result->fetch_all(MYSQLI_ASSOC);


require_once __DIR__ . '/partials/header.php';

?>


<div class="admin-container" style="padding:20px;">

    <h2>Promo Codes & Discounts</h2>


    <?php if ($msg): ?>

        <div
            style="
                color:green;
                margin-bottom:10px;
                padding:10px;
                background:#f0fff4;
                border-radius:6px;
            "
        >
            <?= htmlspecialchars($msg) ?>
        </div>

    <?php endif; ?>


    <?php if ($err): ?>

        <div
            style="
                color:red;
                margin-bottom:10px;
                padding:10px;
                background:#fff5f5;
                border-radius:6px;
            "
        >
            <?= htmlspecialchars($err) ?>
        </div>

    <?php endif; ?>


    <!-- ============================================= -->
    <!-- CREATE PROMO CODE -->
    <!-- ============================================= -->

    <div
        style="
            background:#fff;
            padding:20px;
            border-radius:8px;
            margin-bottom:20px;
            max-width:600px;
        "
    >

        <h3>Create Promo Code</h3>


        <form method="POST">

            <input
                type="hidden"
                name="action"
                value="create"
            >


            <div style="margin-bottom:10px;">

                <label>
                    Code (e.g., STUDENT10):
                </label>

                <input
                    type="text"
                    name="code"
                    required
                    style="
                        width:100%;
                        padding:8px;
                        text-transform:uppercase;
                    "
                >

            </div>


            <div style="margin-bottom:10px;">

                <label>
                    Discount (%):
                </label>

                <input
                    type="number"
                    name="discount_percent"
                    min="1"
                    max="100"
                    required
                    style="
                        width:100%;
                        padding:8px;
                    "
                >

            </div>


            <div style="margin-bottom:10px;">

                <label>
                    Max Uses (0 for unlimited):
                </label>

                <input
                    type="number"
                    name="max_uses"
                    value="0"
                    min="0"
                    style="
                        width:100%;
                        padding:8px;
                    "
                >

            </div>


            <div style="margin-bottom:10px;">

                <label>
                    Expiry Date (Optional):
                </label>

                <input
                    type="date"
                    name="expires_at"
                    style="
                        width:100%;
                        padding:8px;
                    "
                >

            </div>


            <button
                type="submit"
                class="btn"
                style="padding:8px 16px;"
            >
                Create Code
            </button>

        </form>

    </div>


    <!-- ============================================= -->
    <!-- PROMO CODES TABLE -->
    <!-- ============================================= -->

    <div
        style="
            background:#fff;
            padding:20px;
            border-radius:8px;
        "
    >

        <h3>Active Promo Codes</h3>


        <table
            class="table"
            style="
                width:100%;
                border-collapse:collapse;
                margin-top:10px;
            "
        >

            <thead>

                <tr
                    style="
                        border-bottom:2px solid #edf2f7;
                        text-align:left;
                    "
                >

                    <th style="padding:8px;">
                        Code
                    </th>

                    <th style="padding:8px;">
                        Discount
                    </th>

                    <th style="padding:8px;">
                        Usage
                    </th>

                    <th style="padding:8px;">
                        Expires
                    </th>

                    <th style="padding:8px;">
                        Status
                    </th>

                    <th style="padding:8px;">
                        Action
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php if (empty($promos)): ?>

                <tr>

                    <td
                        colspan="6"
                        style="
                            padding:20px;
                            text-align:center;
                            color:#718096;
                        "
                    >
                        No promo codes found.
                    </td>

                </tr>

            <?php else: ?>


                <?php foreach ($promos as $p): ?>

                    <tr
                        style="
                            border-bottom:1px solid #edf2f7;
                        "
                    >

                        <td
                            style="
                                padding:8px;
                                font-weight:bold;
                            "
                        >
                            <?= htmlspecialchars($p['code']) ?>
                        </td>


                        <td style="padding:8px;">

                            <?= (int)$p['discount_percent'] ?>%

                        </td>


                        <td style="padding:8px;">

                            <?= (int)$p['used_count'] ?>

                            /

                            <?= ((int)$p['max_uses'] > 0)
                                ? (int)$p['max_uses']
                                : '∞'
                            ?>

                        </td>


                        <td style="padding:8px;">

                            <?= !empty($p['expires_at'])
                                ? htmlspecialchars($p['expires_at'])
                                : 'No Expiry'
                            ?>

                        </td>


                        <td
                            style="
                                padding:8px;
                                color:<?= !empty($p['is_active'])
                                    ? 'green'
                                    : 'gray'
                                ?>;
                            "
                        >

                            <?= !empty($p['is_active'])
                                ? 'Active'
                                : 'Disabled'
                            ?>

                        </td>


                        <td style="padding:8px;">

                            <a
                                href="promo_codes.php?toggle_id=<?= (int)$p['id'] ?>"
                                class="btn"
                                style="
                                    font-size:12px;
                                    padding:4px 8px;
                                "
                            >

                                <?= !empty($p['is_active'])
                                    ? 'Disable'
                                    : 'Enable'
                                ?>

                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<?php require_once __DIR__ . '/partials/footer.php'; ?>