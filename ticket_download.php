<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    die('Invalid order ID.');
}

// Fetch order, event, and user details
$stmt = $pdo->prepare("
    SELECT o.*, e.title AS event_title, e.date AS event_date, e.time AS event_time, 
           e.venue AS event_venue, u.name AS user_name, u.email AS user_email
    FROM orders o
    JOIN events e ON o.event_id = e.id
    JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND (o.user_id = ? OR ? = 1)
");
$stmt->execute([$order_id, $_SESSION['user_id'], $_SESSION['is_admin'] ?? 0]);
$order = $stmt->fetch();

if (!$order) {
    die('Ticket not found or access denied.');
}

$ticket_code = $order['ticket_code'] ?? ('TKT-' . str_pad($order['id'], 6, '0', STR_PAD_LEFT));
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($ticket_code);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>E-Ticket - <?= htmlspecialchars($order['event_title']) ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; margin: 0; padding: 40px; display: flex; justify-content: center; }
        .ticket { background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 650px; overflow: hidden; border: 1px solid #e1e4e8; }
        .header { background: #1a202c; color: #fff; padding: 24px; text-align: center; }
        .header h1 { margin: 0; font-size: 24px; }
        .content { padding: 30px; display: flex; justify-content: space-between; align-items: center; }
        .details { flex: 1; }
        .details p { margin: 8px 0; color: #4a5568; font-size: 15px; }
        .details strong { color: #1a202c; }
        .qr-section { text-align: center; margin-left: 20px; }
        .qr-section img { border: 2px solid #e2e8f0; border-radius: 8px; padding: 5px; }
        .footer { background: #f7fafc; padding: 15px; text-align: center; border-top: 1px dashed #cbd5e0; font-size: 13px; color: #718096; }
        .print-btn { display: block; margin: 20px auto; padding: 10px 20px; background: #3182ce; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
        @media print { .print-btn { display: none; } body { padding: 0; background: #fff; } .ticket { box-shadow: none; border: 1px solid #000; } }
    </style>
</head>
<body>
    <div>
        <button class="print-btn" onclick="window.print()">Print / Save as PDF</button>
        <div class="ticket">
            <div class="header">
                <h1><?= htmlspecialchars($order['event_title']) ?></h1>
            </div>
            <div class="content">
                <div class="details">
                    <p><strong>Attendee:</strong> <?= htmlspecialchars($order['user_name']) ?></p>
                    <p><strong>Date & Time:</strong> <?= htmlspecialchars($order['event_date']) ?> at <?= htmlspecialchars($order['event_time']) ?></p>
                    <p><strong>Venue:</strong> <?= htmlspecialchars($order['event_venue']) ?></p>
                    <p><strong>Seat:</strong> <?= htmlspecialchars($order['seat_number'] ?? 'General Admission') ?></p>
                    <p><strong>Total Paid:</strong> RM <?= number_format($order['total_price'] ?? 0, 2) ?></p>
                    <p><strong>Order ID:</strong> #<?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></p>
                </div>
                <div class="qr-section">
                    <img src="<?= $qr_url ?>" alt="Ticket QR Code">
                    <p style="font-size:12px; color:#a0aec0; margin-top:5px;"><?= htmlspecialchars($ticket_code) ?></p>
                </div>
            </div>
            <div class="footer">
                Please present this ticket QR code at the entrance for verification.
            </div>
        </div>
    </div>
</body>
</html>