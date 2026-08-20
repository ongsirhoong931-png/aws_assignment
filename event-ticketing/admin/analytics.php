<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../helpers.php';

require_admin();


// =====================================================
// OVERVIEW
// =====================================================

// Total Revenue
$sql = "
    SELECT COALESCE(SUM(total_price), 0) AS total_revenue
    FROM orders
";

$result = $conn->query($sql);

if (!$result) {
    die("Revenue SQL Error: " . $conn->error);
}

$row = $result->fetch_assoc();
$total_revenue = (float)$row['total_revenue'];


// Total Tickets Sold
$sql = "
    SELECT COALESCE(SUM(quantity), 0) AS total_tickets
    FROM orders
";

$result = $conn->query($sql);

if (!$result) {
    die("Tickets SQL Error: " . $conn->error);
}

$row = $result->fetch_assoc();
$total_tickets = (int)$row['total_tickets'];


// Total Events
$sql = "
    SELECT COUNT(*) AS total_events
    FROM events
";

$result = $conn->query($sql);

if (!$result) {
    die("Events SQL Error: " . $conn->error);
}

$row = $result->fetch_assoc();
$total_events = (int)$row['total_events'];


// Total Users
$sql = "
    SELECT COUNT(*) AS total_users
    FROM users
";

$result = $conn->query($sql);

if (!$result) {
    die("Users SQL Error: " . $conn->error);
}

$row = $result->fetch_assoc();
$total_users = (int)$row['total_users'];


// =====================================================
// SALES BY EVENT
// =====================================================

$sql = "
    SELECT
        e.event_name,
        COALESCE(SUM(o.quantity), 0) AS tickets_sold,
        COALESCE(SUM(o.total_price), 0) AS event_revenue

    FROM events e

    LEFT JOIN orders o
        ON e.id = o.event_id

    GROUP BY e.id, e.event_name

    ORDER BY event_revenue DESC
";

$result = $conn->query($sql);

if (!$result) {
    die("Sales SQL Error: " . $conn->error);
}

$sales_query = [];

while ($row = $result->fetch_assoc()) {
    $sales_query[] = $row;
}


// Chart data
$event_names = [];
$event_revenues = [];

foreach ($sales_query as $row) {

    $event_names[] = $row['event_name'];

    $event_revenues[] =
        (float)$row['event_revenue'];
}


$pageTitle = 'Sales & Revenue Analytics';

require_once __DIR__ . '/partials/header.php';
?>


<div class="admin-container" style="padding:20px;">

    <h2>Sales & Revenue Analytics</h2>


    <!-- KPI CARDS -->

    <div style="
        display:grid;
        grid-template-columns:repeat(4, 1fr);
        gap:20px;
        margin:20px 0;
    ">


        <!-- TOTAL REVENUE -->

        <div style="
            background:#fff;
            padding:20px;
            border-radius:8px;
            box-shadow:0 2px 4px rgba(0,0,0,0.05);
            text-align:center;
        ">

            <h3 style="
                margin:0;
                color:#718096;
                font-size:14px;
            ">
                Total Revenue
            </h3>

            <p style="
                font-size:24px;
                font-weight:bold;
                margin:10px 0 0;
                color:#2b6cb0;
            ">
                RM <?= number_format($total_revenue, 2) ?>
            </p>

        </div>


        <!-- TICKETS SOLD -->

        <div style="
            background:#fff;
            padding:20px;
            border-radius:8px;
            box-shadow:0 2px 4px rgba(0,0,0,0.05);
            text-align:center;
        ">

            <h3 style="
                margin:0;
                color:#718096;
                font-size:14px;
            ">
                Tickets Sold
            </h3>

            <p style="
                font-size:24px;
                font-weight:bold;
                margin:10px 0 0;
                color:#2f855a;
            ">
                <?= $total_tickets ?>
            </p>

        </div>


        <!-- TOTAL EVENTS -->

        <div style="
            background:#fff;
            padding:20px;
            border-radius:8px;
            box-shadow:0 2px 4px rgba(0,0,0,0.05);
            text-align:center;
        ">

            <h3 style="
                margin:0;
                color:#718096;
                font-size:14px;
            ">
                Total Events
            </h3>

            <p style="
                font-size:24px;
                font-weight:bold;
                margin:10px 0 0;
                color:#805ad5;
            ">
                <?= $total_events ?>
            </p>

        </div>


        <!-- REGISTERED USERS -->

        <div style="
            background:#fff;
            padding:20px;
            border-radius:8px;
            box-shadow:0 2px 4px rgba(0,0,0,0.05);
            text-align:center;
        ">

            <h3 style="
                margin:0;
                color:#718096;
                font-size:14px;
            ">
                Registered Users
            </h3>

            <p style="
                font-size:24px;
                font-weight:bold;
                margin:10px 0 0;
                color:#dd6b20;
            ">
                <?= $total_users ?>
            </p>

        </div>

    </div>


    <!-- REVENUE CHART -->

    <div style="
        background:#fff;
        padding:20px;
        border-radius:8px;
        margin-bottom:25px;
    ">

        <h3>Revenue by Event</h3>

        <canvas
            id="revenueChart"
            style="max-height:350px;">
        </canvas>

    </div>


    <!-- EVENT BREAKDOWN -->

    <div style="
        background:#fff;
        padding:20px;
        border-radius:8px;
    ">

        <h3>Event Breakdown</h3>

        <table style="
            width:100%;
            border-collapse:collapse;
            margin-top:10px;
        ">

            <thead>

                <tr style="
                    border-bottom:2px solid #edf2f7;
                    text-align:left;
                ">

                    <th style="padding:10px;">
                        Event Name
                    </th>

                    <th style="padding:10px;">
                        Tickets Sold
                    </th>

                    <th style="padding:10px;">
                        Total Revenue
                    </th>

                </tr>

            </thead>


            <tbody>

            <?php if (empty($sales_query)): ?>

                <tr>

                    <td
                        colspan="3"
                        style="
                            padding:20px;
                            text-align:center;
                        "
                    >
                        No sales data available.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($sales_query as $row): ?>

                    <tr style="
                        border-bottom:1px solid #edf2f7;
                    ">

                        <td style="padding:10px;">
                            <?= htmlspecialchars($row['event_name']) ?>
                        </td>

                        <td style="padding:10px;">
                            <?= (int)$row['tickets_sold'] ?>
                        </td>

                        <td style="padding:10px;">
                            RM <?= number_format(
                                (float)$row['event_revenue'],
                                2
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>


<!-- CHART.JS -->

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const ctx =
    document
        .getElementById('revenueChart')
        .getContext('2d');

new Chart(ctx, {

    type: 'bar',

    data: {

        labels:
            <?= json_encode($event_names) ?>,

        datasets: [{

            label: 'Revenue (RM)',

            data:
                <?= json_encode($event_revenues) ?>,

            backgroundColor: '#3182ce'

        }]

    },

    options: {

        responsive: true,

        plugins: {

            legend: {
                display: false
            }

        },

        scales: {

            y: {
                beginAtZero: true
            }

        }

    }

});

</script>


<?php
require_once __DIR__ . '/partials/footer.php';
?>