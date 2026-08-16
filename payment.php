<?php
require 'config.php';
require 'auth.php';
require 'helpers.php';
require_login();

$uid = current_user_id();
$error = '';
$promo_error = '';
$promo_success = '';

$event_id = (int)($_POST['event_id'] ?? 0);
$seatIds = array_values(
    array_unique(
        array_map('intval', $_POST['seat_ids'] ?? [])
    )
);

$confirmed = isset($_POST['confirm']);
$payment_method = $_POST['payment_method'] ?? 'card';
$promo_code = strtoupper(trim($_POST['promo_code'] ?? ''));
$discount_percent = 0;
$discount_amount = 0;
$original_price = 0;

/*
|--------------------------------------------------------------------------
| PROMO CODE
|--------------------------------------------------------------------------
*/
$promo_code = strtoupper(trim($_POST['promo_code'] ?? ''));

$discount_percent = 0;
$discount_amount = 0;
$promo_id = null;

/*
|--------------------------------------------------------------------------
| Validate Event
|--------------------------------------------------------------------------
*/
if ($event_id < 1) {
    header('Location: create.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Event
|--------------------------------------------------------------------------
*/
$stmt = $conn->prepare('SELECT * FROM events WHERE id = ?');
$stmt->bind_param('i', $event_id);
$stmt->execute();

$event = $stmt->get_result()->fetch_assoc();

$stmt->close();

if (!$event) {
    die('Event not found.');
}

/*
|--------------------------------------------------------------------------
| Seating
|--------------------------------------------------------------------------
*/
$isSeated = (bool)$event['has_seating'];
$seatDetails = [];

if ($isSeated) {

    if (empty($seatIds)) {
        header('Location: seat_select.php?event_id=' . $event_id);
        exit;
    }

    $placeholders = implode(
        ',',
        array_fill(0, count($seatIds), '?')
    );

    $stmt = $conn->prepare(
        "SELECT id, row_label, seat_number, is_booked
         FROM seats
         WHERE event_id = ?
         AND id IN ($placeholders)"
    );

    $types = str_repeat('i', count($seatIds) + 1);

    $params = array_merge([$event_id], $seatIds);

    $stmt->bind_param($types, ...$params);

    $stmt->execute();

    $seatDetails = $stmt
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);

    $stmt->close();

    $quantity = count($seatDetails);

    if (
        $quantity !== count($seatIds)
        || $quantity < 1
    ) {

        $error =
            'One or more selected seats are invalid.';

    } elseif (
        array_filter(
            $seatDetails,
            fn($s) => $s['is_booked']
        )
    ) {

        $error =
            'One or more selected seats have just been taken by someone else. Please choose again.';
    }

} else {

    $quantity = (int)($_POST['quantity'] ?? 0);

    if ($quantity < 1) {

        header('Location: create.php');
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Calculate Original Price
|--------------------------------------------------------------------------
*/
$remaining =
    $event['total_tickets']
    - $event['tickets_sold'];

$subtotal =
    $event['ticket_price']
    * $quantity;


/*
|--------------------------------------------------------------------------
| Check Ticket Availability
|--------------------------------------------------------------------------
*/
if (
    !$isSeated
    && $quantity > $remaining
) {

    $error =
        'Not enough tickets remaining for this event.';
}


/*
|--------------------------------------------------------------------------
| PROMO CODE VALIDATION
|--------------------------------------------------------------------------
*/
if ($promo_code !== '') {

    $stmt = $conn->prepare(
        "SELECT
            id,
            code,
            discount_percent,
            max_uses,
            used_count,
            expires_at,
            is_active
         FROM promo_codes
         WHERE code = ?
         LIMIT 1"
    );

    $stmt->bind_param(
        's',
        $promo_code
    );

    $stmt->execute();

    $promo = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();


    if (!$promo) {

        $promo_error =
            'Invalid promo code.';

    } elseif ((int)$promo['is_active'] !== 1) {

        $promo_error =
            'This promo code is disabled.';

    } elseif (
        !empty($promo['expires_at'])
        && strtotime($promo['expires_at']) < time()
    ) {

        $promo_error =
            'This promo code has expired.';

    } elseif (
        (int)$promo['max_uses'] > 0
        && (int)$promo['used_count']
           >= (int)$promo['max_uses']
    ) {

        $promo_error =
            'This promo code has reached its usage limit.';

    } else {

        $promo_id =
            (int)$promo['id'];

        $discount_percent =
            (int)$promo['discount_percent'];

        $discount_amount =
            $subtotal
            * ($discount_percent / 100);

        $promo_success =
            'Promo code applied! You saved RM'
            . number_format(
                $discount_amount,
                2
            )
            . '.';
    }
}


/*
|--------------------------------------------------------------------------
| Calculate Final Price
|--------------------------------------------------------------------------
*/
$total_price =
    $subtotal
    - $discount_amount;


/*
|--------------------------------------------------------------------------
| Confirm Payment
|--------------------------------------------------------------------------
*/
if ($confirmed && !$error) {
// Check promo code
if ($promo_code !== '') {

    $stmt = $conn->prepare("
        SELECT discount_percent, max_uses, used_count, expires_at, is_active
        FROM promo_codes
        WHERE code = ?
        LIMIT 1
    ");

    $stmt->bind_param('s', $promo_code);
    $stmt->execute();

    $promo = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$promo) {

        $error = 'Invalid promo code.';

    } elseif (!$promo['is_active']) {

        $error = 'This promo code is disabled.';

    } elseif (
        $promo['expires_at'] !== null &&
        strtotime($promo['expires_at']) < time()
    ) {

        $error = 'This promo code has expired.';

    } elseif (
        $promo['max_uses'] > 0 &&
        $promo['used_count'] >= $promo['max_uses']
    ) {

        $error = 'This promo code has reached its usage limit.';

    } else {

        $discount_percent = (float)$promo['discount_percent'];

        $discount_amount =
            $original_price * ($discount_percent / 100);

        $total_price =
            $original_price - $discount_amount;
    }
}
    /*
    |--------------------------------------------------------------------------
    | Payment Validation
    |--------------------------------------------------------------------------
    */

    if ($payment_method === 'fpx') {

        $bank =
            trim($_POST['bank'] ?? '');

        if ($bank === '') {

            $error =
                'Please select your bank.';
        }

    } else {

        $card_name =
            trim($_POST['card_name'] ?? '');

        $card_number =
            preg_replace(
                '/\s+/',
                '',
                $_POST['card_number'] ?? ''
            );

        $card_expiry =
            trim($_POST['card_expiry'] ?? '');

        $card_cvv =
            trim($_POST['card_cvv'] ?? '');


        if ($card_name === '') {

            $error =
                'Please enter the name on the card.';

        } elseif (
            !preg_match(
                '/^\d{13,19}$/',
                $card_number
            )
        ) {

            $error =
                'Card number must be 13-19 digits.';

        } elseif (
            !preg_match(
                '/^(0[1-9]|1[0-2])\/\d{2}$/',
                $card_expiry
            )
        ) {

            $error =
                'Expiry must be in MM/YY format.';

        } elseif (
            !preg_match(
                '/^\d{3,4}$/',
                $card_cvv
            )
        ) {

            $error =
                'CVV must be 3 or 4 digits.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Process Order
    |--------------------------------------------------------------------------
    */

    if (!$error) {

        $conn->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Lock Event
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                'SELECT
                    ticket_price,
                    total_tickets,
                    tickets_sold
                 FROM events
                 WHERE id = ?
                 FOR UPDATE'
            );

            $stmt->bind_param(
                'i',
                $event_id
            );

            $stmt->execute();

            $lockedEvent =
                $stmt
                    ->get_result()
                    ->fetch_assoc();

            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Lock Seats
            |--------------------------------------------------------------------------
            */

            $lockedSeatIds = [];

            if ($isSeated) {

                $placeholders =
                    implode(
                        ',',
                        array_fill(
                            0,
                            count($seatIds),
                            '?'
                        )
                    );

                $stmt = $conn->prepare(
                    "SELECT id
                     FROM seats
                     WHERE event_id = ?
                     AND is_booked = 0
                     AND id IN ($placeholders)
                     FOR UPDATE"
                );

                $types =
                    str_repeat(
                        'i',
                        count($seatIds) + 1
                    );

                $params =
                    array_merge(
                        [$event_id],
                        $seatIds
                    );

                $stmt->bind_param(
                    $types,
                    ...$params
                );

                $stmt->execute();

                $lockedSeatIds =
                    array_column(
                        $stmt
                            ->get_result()
                            ->fetch_all(
                                MYSQLI_ASSOC
                            ),
                        'id'
                    );

                $stmt->close();
            }


            /*
            |--------------------------------------------------------------------------
            | Re-check Availability
            |--------------------------------------------------------------------------
            */

            if (
                $isSeated
                && count($lockedSeatIds)
                   !== count($seatIds)
            ) {

                throw new Exception(
                    'One or more selected seats have just been taken by someone else.'
                );
            }


            if (
                !$isSeated
                && (
                    !$lockedEvent
                    || $lockedEvent['tickets_sold']
                       + $quantity
                       > $lockedEvent['total_tickets']
                )
            ) {

                throw new Exception(
                    'Not enough tickets remaining for this event.'
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Recalculate Price
            |--------------------------------------------------------------------------
            */

            $subtotal =
                $lockedEvent['ticket_price']
                * $quantity;

            $discount_amount = 0;

            /*
            |--------------------------------------------------------------------------
            | Re-check Promo Code During Payment
            |--------------------------------------------------------------------------
            */

            if ($promo_code !== '') {

                $stmt = $conn->prepare(
                    "SELECT
                        id,
                        discount_percent,
                        max_uses,
                        used_count,
                        expires_at,
                        is_active
                     FROM promo_codes
                     WHERE code = ?
                     FOR UPDATE"
                );

                $stmt->bind_param(
                    's',
                    $promo_code
                );

                $stmt->execute();

                $lockedPromo =
                    $stmt
                        ->get_result()
                        ->fetch_assoc();

                $stmt->close();


                if (!$lockedPromo) {

                    throw new Exception(
                        'Invalid promo code.'
                    );
                }


                if (
                    (int)$lockedPromo['is_active'] !== 1
                ) {

                    throw new Exception(
                        'This promo code is disabled.'
                    );
                }


                if (
                    !empty($lockedPromo['expires_at'])
                    && strtotime(
                        $lockedPromo['expires_at']
                    ) < time()
                ) {

                    throw new Exception(
                        'This promo code has expired.'
                    );
                }


                if (
                    (int)$lockedPromo['max_uses'] > 0
                    && (int)$lockedPromo['used_count']
                       >= (int)$lockedPromo['max_uses']
                ) {

                    throw new Exception(
                        'This promo code has reached its usage limit.'
                    );
                }


                $promo_id =
                    (int)$lockedPromo['id'];

                $discount_percent =
                    (int)$lockedPromo['discount_percent'];

                $discount_amount =
                    $subtotal
                    * ($discount_percent / 100);
            }


            /*
            |--------------------------------------------------------------------------
            | Final Total
            |--------------------------------------------------------------------------
            */

            $total_price =
                $subtotal
                - $discount_amount;


            /*
            |--------------------------------------------------------------------------
            | Create Order
            |--------------------------------------------------------------------------
            |
            | This uses your existing orders columns.
            |
            */

            $stmt = $conn->prepare(
                'INSERT INTO orders
                (
                    user_id,
                    event_id,
                    quantity,
                    total_price
                )
                VALUES (?, ?, ?, ?)'
            );

            $stmt->bind_param(
                'iiid',
                $uid,
                $event_id,
                $quantity,
                $total_price
            );

            $stmt->execute();

            $orderId =
                $stmt->insert_id;

            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Increase Tickets Sold
            |--------------------------------------------------------------------------
            */

            $stmt = $conn->prepare(
                'UPDATE events
                 SET tickets_sold =
                     tickets_sold + ?
                 WHERE id = ?'
            );

            $stmt->bind_param(
                'ii',
                $quantity,
                $event_id
            );

            $stmt->execute();

            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Create Tickets
            |--------------------------------------------------------------------------
            */

            $ticketStmt =
                $conn->prepare(
                    'INSERT INTO tickets
                    (
                        order_id,
                        seat_id,
                        qr_token
                    )
                    VALUES (?, ?, ?)'
                );


            if ($isSeated) {

                $markSeatStmt =
                    $conn->prepare(
                        'UPDATE seats
                         SET is_booked = 1
                         WHERE id = ?'
                    );


                foreach ($lockedSeatIds as $seatId) {

                    $markSeatStmt->bind_param(
                        'i',
                        $seatId
                    );

                    $markSeatStmt->execute();


                    $token =
                        generate_qr_token();


                    $ticketStmt->bind_param(
                        'iis',
                        $orderId,
                        $seatId,
                        $token
                    );

                    $ticketStmt->execute();
                }

                $markSeatStmt->close();

            } else {

                $nullSeatId = null;

                for (
                    $i = 0;
                    $i < $quantity;
                    $i++
                ) {

                    $token =
                        generate_qr_token();


                    $ticketStmt->bind_param(
                        'iis',
                        $orderId,
                        $nullSeatId,
                        $token
                    );

                    $ticketStmt->execute();
                }
            }

            $ticketStmt->close();


            /*
            |--------------------------------------------------------------------------
            | Update Promo Usage
            |--------------------------------------------------------------------------
            */

            if ($promo_id !== null) {

                $stmt = $conn->prepare(
                    'UPDATE promo_codes
                     SET used_count = used_count + 1
                     WHERE id = ?'
                );

                $stmt->bind_param(
                    'i',
                    $promo_id
                );

                $stmt->execute();

                $stmt->close();
            }


            /*
            |--------------------------------------------------------------------------
            | Finish Transaction
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            header(
                'Location: confirmation.php?id='
                . $orderId
            );

            exit;

        } catch (Exception $e) {

            $conn->rollback();

            $error =
                $e->getMessage();
        }
    }
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$pageTitle = 'Payment';

require 'partials/header.php';
?>

<div class="form-card" style="max-width:480px;">

<h1>Payment</h1>


<?php if ($error): ?>

<p class="alert alert-error">
    <?= htmlspecialchars($error) ?>
</p>

<?php endif; ?>


<?php if ($promo_error): ?>

<p class="alert alert-error">
    <?= htmlspecialchars($promo_error) ?>
</p>

<?php endif; ?>


<?php if ($promo_success): ?>

<p class="alert alert-success">
    <?= htmlspecialchars($promo_success) ?>
</p>

<?php endif; ?>


<!-- ORDER SUMMARY -->
<div style="margin-bottom:20px;">

    <label>
        Promo Code

        <input
            type="text"
            name="promo_code"
            value="<?= htmlspecialchars($promo_code) ?>"
            placeholder="Enter promo code"
            style="text-transform:uppercase;">
    </label>

</div>
<div class="order-summary">

    <div class="order-summary-row">

        <span>
            <?= htmlspecialchars($event['event_name']) ?>
        </span>

        <span>
            <?= htmlspecialchars(
                date(
                    'd M Y',
                    strtotime(
                        $event['event_date']
                    )
                )
            ) ?>
        </span>

    </div>


    <div class="order-summary-row">

        <span>
            Ticket price
        </span>

        <span>
            RM<?= number_format(
                $event['ticket_price'],
                2
            ) ?>
        </span>

    </div>


    <?php if ($isSeated): ?>

        <div class="order-summary-row">

            <span>
                Seats
            </span>

            <span>

                <?php

                $seatLabels =
                    array_map(
                        fn($s) =>
                            $s['row_label']
                            . $s['seat_number'],
                        $seatDetails
                    );

                echo htmlspecialchars(
                    implode(
                        ', ',
                        $seatLabels
                    )
                );

                ?>

            </span>

        </div>

    <?php else: ?>

        <div class="order-summary-row">

            <span>
                Quantity
            </span>

            <span>
                &times; <?= (int)$quantity ?>
            </span>

        </div>

    <?php endif; ?>


    <div class="order-summary-row">

        <span>
            Subtotal
        </span>

        <span>
            RM<?= number_format(
                $subtotal,
                2
            ) ?>
        </span>

    </div>


    <?php if ($discount_amount > 0): ?>

        <div
            class="order-summary-row"
            style="color:green;"
        >

            <span>
                Promo
                (<?= htmlspecialchars($promo_code) ?>)
                -<?= (int)$discount_percent ?>%
            </span>

            <span>
                - RM<?= number_format(
                    $discount_amount,
                    2
                ) ?>
            </span>

        </div>

    <?php endif; ?>


    <div class="order-summary-row total">

        <span>
            Total
        </span>

        <span>
            RM<?= number_format(
                $total_price,
                2
            ) ?>
        </span>

    </div>

</div>


<form
    method="post"
    action="payment.php"
    id="payment-form"
>


<input
    type="hidden"
    name="event_id"
    value="<?= (int)$event_id ?>"
>


<?php if ($isSeated): ?>

    <?php foreach ($seatIds as $sid): ?>

        <input
            type="hidden"
            name="seat_ids[]"
            value="<?= (int)$sid ?>"
        >

    <?php endforeach; ?>

<?php else: ?>

    <input
        type="hidden"
        name="quantity"
        value="<?= (int)$quantity ?>"
    >

<?php endif; ?>


<input
    type="hidden"
    name="confirm"
    value="1"
>


<!-- PROMO CODE -->

<div
    style="
        margin:20px 0;
        padding:15px;
        background:#f7fafc;
        border-radius:8px;
        border:1px solid #e2e8f0;
    "
>

    <label>
        <strong>Promo Code</strong>

        <span
            style="
                color:#718096;
                font-size:13px;
            "
        >
            (Optional)
        </span>

        <input
            type="text"
            name="promo_code"
            value="<?= htmlspecialchars($promo_code) ?>"
            placeholder="Enter promo code"
            maxlength="50"
            style="text-transform:uppercase;"
        >

    </label>

    <button
        type="submit"
        style="
            margin-top:8px;
            background:#718096;
        "
    >
        Apply Promo Code
    </button>

</div>


<!-- PAYMENT METHOD -->

<div class="payment-methods">

<label class="payment-method-option">

<input
    type="radio"
    name="payment_method"
    value="card"
    <?= $payment_method !== 'fpx'
        ? 'checked'
        : '' ?>
>

Credit / Debit Card

</label>


<label class="payment-method-option">

<input
    type="radio"
    name="payment_method"
    value="fpx"
    <?= $payment_method === 'fpx'
        ? 'checked'
        : '' ?>
>

Online Banking (FPX)

</label>

</div>


<!-- CARD -->

<div id="card-fields">

<label>
Name on Card

<input
    type="text"
    name="card_name"
    placeholder="e.g. TAN AH KOW"
>
</label>


<label>
Card Number

<input
    type="text"
    name="card_number"
    placeholder="4111 1111 1111 1111"
    maxlength="19"
>
</label>


<div class="card-row">

<label>
Expiry (MM/YY)

<input
    type="text"
    name="card_expiry"
    placeholder="12/28"
    maxlength="5"
>
</label>


<label>
CVV

<input
    type="text"
    name="card_cvv"
    placeholder="123"
    maxlength="4"
>
</label>

</div>

</div>


<!-- FPX -->

<div id="fpx-fields">

<label>

Select Bank

<select name="bank">

<option value="">
-- Choose your bank --
</option>

<option value="maybank2u">
Maybank2u
</option>

<option value="cimb_clicks">
CIMB Clicks
</option>

<option value="public_bank_pbe">
Public Bank PBe
</option>

<option value="rhb_now">
RHB Now
</option>

<option value="hong_leong_connect">
Hong Leong Connect
</option>

<option value="bank_islam">
Bank Islam Go Online
</option>

</select>

</label>

</div>


<button type="submit">

Pay RM<?= number_format(
    $total_price,
    2
) ?>

</button>

</form>


<p class="payment-note">

This is a simulated checkout for a class project -
no real payment is processed and no card or bank login
details are stored.

</p>

</div>


<script>

(function () {

    var cardRadio =
        document.querySelector(
            'input[name="payment_method"][value="card"]'
        );

    var fpxRadio =
        document.querySelector(
            'input[name="payment_method"][value="fpx"]'
        );

    var cardFields =
        document.getElementById(
            'card-fields'
        );

    var fpxFields =
        document.getElementById(
            'fpx-fields'
        );


    if (
        !cardRadio ||
        !fpxRadio ||
        !cardFields ||
        !fpxFields
    ) {
        return;
    }


    function update() {

        var isCard =
            cardRadio.checked;


        cardFields.style.display =
            isCard ? '' : 'none';


        fpxFields.style.display =
            isCard ? 'none' : '';


        cardFields
            .querySelectorAll('input')
            .forEach(function (el) {

                el.required =
                    isCard;

            });


        fpxFields
            .querySelectorAll('select')
            .forEach(function (el) {

                el.required =
                    !isCard;

            });

    }


    cardRadio.addEventListener(
        'change',
        update
    );

    fpxRadio.addEventListener(
        'change',
        update
    );

    update();

})();

</script>

<?php require 'partials/footer.php'; ?>