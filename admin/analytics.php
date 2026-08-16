<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Ensure user is admin
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header('Location: ../login.php');
    exit;
}

// 1. Overview KPIs
$total_revenue = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$total_tickets = $pdo->query("SELECT COUNT(*) FROM orders WHERE status != 'cancelled'")->fetchColumn();
$total_events  = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
$total_users   = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// 2. Sales per event
$sales_query = $pdo->query("
    SELECT e.title, COUNT(o.id) AS tickets_sold, COALESCE(SUM(o.total_price), 0) AS event_revenue
    FROM events e
    LEFT JOIN orders o ON e.id = o.event_id AND o.status != 'cancelled'
    GROUP BY e.id, e.title
    ORDER BY event_revenue DESC
")->fetchAll();

$event_names = array_column($sales_query, 'title');
$event_revenues = array_column($sales_query, 'event_revenue');

require_once __DIR__ . '/partials/header.php';
?>

<div class="admin-container" style="padding: 20px;">
    <h2>Sales & Revenue Analytics</h2>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
        <div style="background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); text-align:center;">
            <h3 style="margin:0; color:#718096; font-size:14px;">Total Revenue</h3>
            <p style="font-size:24px; font-weight:bold; margin:10px 0 0; color:#2b6cb0;">RM <?= number_format($total_revenue, 2) ?></p>
        </div>
        <div style="background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); text-align:center;">
            <h3 style="margin:0; color:#718096; font-size:14px;">Tickets Sold</h3>
            <p style="font-size:24px; font-weight:bold; margin:10px 0 0; color:#2f855a;"><?= $total_tickets ?></p>
        </div>
        <div style="background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); text-align:center;">
            <h3 style="margin:0; color:#718096; font-size:14px;">Total Events</h3>
            <p style="font-size:24px; font-weight:bold; margin:10px 0 0; color:#805ad5;"><?= $total_events ?></p>
        </div>
        <div style="background:#fff; padding:20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.05); text-align:center;">
            <h3 style="margin:0; color:#718096; font-size:14px;">Registered Users</h3>
            <p style="font-size:24px; font-weight:bold; margin:10px 0 0; color:#dd6b20;"><?= $total_users ?></p>
        </div>
    </div>

    <div style="background:#fff; padding:20px; border-radius:8px; margin-bottom:25px;">
        <h3>Revenue by Event</h3>
        <canvas id="revenueChart" style="max-height: 350px;"></canvas>
    </div>

    <div style="background:#fff; padding:20px; border-radius:8px;">
        <h3>Event Breakdown</h3>
        <table class="table" style="width:100%; border-collapse:collapse; margin-top:10px;">
            <thead>
                <tr style="border-bottom:2px solid #edf2f7; text-align:left;">
                    <th style="padding:10px;">Event Title</th>
                    <th style="padding:10px;">Tickets Sold</th>
                    <th style="padding:10px;">Total Revenue</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sales_query as $row): ?>
                    <tr style="border-bottom:1px solid #edf2f7;">
                        <td style="padding:10px;"><?= htmlspecialchars($row['title']) ?></td>
                        <td style="padding:10px;"><?= $row['tickets_sold'] ?></td>
                        <td style="padding:10px;">RM <?= number_format($row['event_revenue'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($event_names) ?>,
        datasets: [{
            label: 'Revenue (RM)',
            data: <?= json_encode($event_revenues) ?>,
            backgroundColor: '#3182ce'
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});
</script>

<?php require_once __DIR__ . '/partials/footer.php'; ?>