<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/helpers.php';

// auth.php already starts the session
// Do NOT call session_start() again here.

// =====================================================
// CHECK LOGIN
// =====================================================

$current_user_id = current_user_id();

if (!$current_user_id) {
    header('Location: login.php');
    exit;
}

$is_admin = current_user_is_admin();

// =====================================================
// GET ORDER ID
// =====================================================

$order_id = filter_input(
    INPUT_GET,
    'order_id',
    FILTER_VALIDATE_INT
);

if (!$order_id) {
    die('Invalid order ID.');
}

// =====================================================
// GET ORDER INFORMATION
// =====================================================

$sql = "
    SELECT
        o.id AS order_id,
        o.user_id,
        o.quantity,
        o.total_price,
        o.created_at,

        e.id AS event_id,
        e.event_name,
        e.event_date,
        e.venue,

        u.name AS user_name,
        u.email AS user_email

    FROM orders o

    INNER JOIN events e
        ON o.event_id = e.id

    INNER JOIN users u
        ON o.user_id = u.id

    WHERE o.id = ?
      AND (o.user_id = ? OR ? = 1)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die('SQL Prepare Error: ' . $conn->error);
}

$stmt->bind_param(
    'iii',
    $order_id,
    $current_user_id,
    $is_admin
);

if (!$stmt->execute()) {
    die('SQL Execute Error: ' . $stmt->error);
}

$result = $stmt->get_result();

$order = $result->fetch_assoc();

$stmt->close();

if (!$order) {
    die('Ticket not found or access denied.');
}

// =====================================================
// GET TICKET / SEAT INFORMATION
// =====================================================

$ticket_code = null;
$seat_name = 'General Admission';

$ticket_sql = "
    SELECT
        t.qr_token,
        s.row_label,
        s.seat_number

    FROM tickets t

    LEFT JOIN seats s
        ON t.seat_id = s.id

    WHERE t.order_id = ?

    LIMIT 1
";

$ticket_stmt = $conn->prepare($ticket_sql);

if ($ticket_stmt) {

    $ticket_stmt->bind_param(
        'i',
        $order_id
    );

    if ($ticket_stmt->execute()) {

        $ticket_result =
            $ticket_stmt->get_result();

        $ticket =
            $ticket_result->fetch_assoc();

        if ($ticket) {

            $ticket_code =
                $ticket['qr_token'];

            if (
                !empty($ticket['row_label']) &&
                !empty($ticket['seat_number'])
            ) {

                $seat_name =
                    $ticket['row_label'] .
                    $ticket['seat_number'];
            }
        }
    }

    $ticket_stmt->close();
}

// =====================================================
// FALLBACK QR CODE
// =====================================================

if (!$ticket_code) {

    $ticket_code =
        'ORDER-' .
        str_pad(
            $order['order_id'],
            6,
            '0',
            STR_PAD_LEFT
        );
}

// =====================================================
// QR CODE
// =====================================================

$qr_url =
    'https://api.qrserver.com/v1/create-qr-code/' .
    '?size=180x180&data=' .
    urlencode($ticket_code);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<title>
E-Ticket -
<?= htmlspecialchars($order['event_name']) ?>
</title>

<style>

body {
    font-family:
        'Segoe UI',
        Tahoma,
        Geneva,
        Verdana,
        sans-serif;

    background: #f0f2f5;

    margin: 0;

    padding: 40px;

    display: flex;

    justify-content: center;
}

.ticket {
    background: #fff;

    border-radius: 12px;

    box-shadow:
        0 10px 30px rgba(0,0,0,0.1);

    width: 650px;

    overflow: hidden;

    border: 1px solid #e1e4e8;
}

.header {
    background: #1a202c;

    color: #fff;

    padding: 24px;

    text-align: center;
}

.header h1 {
    margin: 0;

    font-size: 24px;
}

.content {
    padding: 30px;

    display: flex;

    justify-content: space-between;

    align-items: center;
}

.details {
    flex: 1;
}

.details p {
    margin: 8px 0;

    color: #4a5568;

    font-size: 15px;
}

.details strong {
    color: #1a202c;
}

.qr-section {
    text-align: center;

    margin-left: 20px;
}

.qr-section img {
    border: 2px solid #e2e8f0;

    border-radius: 8px;

    padding: 5px;
}

.footer {
    background: #f7fafc;

    padding: 15px;

    text-align: center;

    border-top: 1px dashed #cbd5e0;

    font-size: 13px;

    color: #718096;
}

.print-btn {
    display: block;

    margin: 20px auto;

    padding: 10px 20px;

    background: #3182ce;

    color: #fff;

    border: none;

    border-radius: 6px;

    cursor: pointer;

    font-size: 14px;
}

@media print {

    .print-btn {
        display: none;
    }

    body {
        padding: 0;

        background: #fff;
    }

    .ticket {
        box-shadow: none;

        border: 1px solid #000;
    }
}

</style>

</head>

<body>

<div>

<button
    class="print-btn"
    onclick="window.print()">
    Print / Save as PDF
</button>

<div class="ticket">

    <div class="header">

        <h1>
            <?= htmlspecialchars(
                $order['event_name']
            ) ?>
        </h1>

    </div>

    <div class="content">

        <div class="details">

            <p>
                <strong>Attendee:</strong>
                <?= htmlspecialchars(
                    $order['user_name']
                ) ?>
            </p>

            <p>
                <strong>Email:</strong>
                <?= htmlspecialchars(
                    $order['user_email']
                ) ?>
            </p>

            <p>
                <strong>Date:</strong>
                <?= htmlspecialchars(
                    $order['event_date']
                ) ?>
            </p>

            <p>
                <strong>Venue:</strong>
                <?= htmlspecialchars(
                    $order['venue']
                ) ?>
            </p>

            <p>
                <strong>Seat:</strong>
                <?= htmlspecialchars(
                    $seat_name
                ) ?>
            </p>

            <p>
                <strong>Quantity:</strong>
                <?= (int)$order['quantity'] ?>
            </p>

            <p>
                <strong>Total Paid:</strong>
                RM <?= number_format(
                    $order['total_price'],
                    2
                ) ?>
            </p>

            <p>
                <strong>Order ID:</strong>
                #
                <?= str_pad(
                    $order['order_id'],
                    6,
                    '0',
                    STR_PAD_LEFT
                ) ?>
            </p>

        </div>

        <div class="qr-section">

            <img
                src="<?= htmlspecialchars($qr_url) ?>"
                alt="Ticket QR Code">

            <p
                style="
                    font-size:12px;
                    color:#a0aec0;
                    margin-top:5px;
                "
            >
                <?= htmlspecialchars(
                    $ticket_code
                ) ?>
            </p>

        </div>

    </div>

    <div class="footer">

        Please present this ticket QR code
        at the entrance for verification.

    </div>

</div>

</div>

</body>

</html>