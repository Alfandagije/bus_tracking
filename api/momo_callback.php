<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/momo.php';

// MTN MoMo callback endpoint
$raw = file_get_contents('php://input');
$callback_data = json_decode($raw, true);

$transaction_id = sanitize($callback_data['transactionId'] ?? $callback_data['externalId'] ?? '');
$status_raw = strtolower(sanitize($callback_data['status'] ?? ''));

if (!$transaction_id) {
    http_response_code(400);
    exit('Missing transaction ID');
}

try {
    $db = getDb();

    $stmt = $db->prepare("SELECT id, booking_id, status FROM payments WHERE transaction_id = ? OR momo_ref = ?");
    $stmt->execute([$transaction_id, $transaction_id]);
    $payment = $stmt->fetch();

    if (!$payment) {
        http_response_code(404);
        exit('Payment not found');
    }

    $new_status = 'pending';
    if (in_array($status_raw, ['successful', 'success'])) {
        $new_status = 'successful';
    } elseif (in_array($status_raw, ['failed', 'rejected', 'timeout'])) {
        $new_status = 'failed';
    } elseif ($status_raw === 'reversed') {
        $new_status = 'reversed';
    }

    if ($new_status !== 'pending') {
        $db->prepare("UPDATE payments SET status = ?, api_response = ?, momo_ref = ? WHERE id = ?")
           ->execute([$new_status, $raw, $transaction_id, $payment['id']]);

        if ($new_status === 'successful') {
            $db->prepare("UPDATE bookings SET status = 'paid', payment_method = 'MTN_MoMo' WHERE id = ? AND status = 'pending'")
               ->execute([$payment['booking_id']]);

            $stmt = $db->prepare("
                SELECT b.bus_name, s.seat_number, u.phone, u.full_name
                FROM bookings bk
                JOIN buses b ON bk.bus_id = b.id
                JOIN seats s ON bk.seat_id = s.id
                JOIN users u ON bk.user_id = u.id
                WHERE bk.id = ?
            ");
            $stmt->execute([$payment['booking_id']]);
            $booking = $stmt->fetch();

            if ($booking) {
                $msg = "Payment confirmed for booking #{$payment['booking_id']} on {$booking['bus_name']} (Seat {$booking['seat_number']}). Amount: RWF " . number_format($booking['fare'] ?? 500) . ". Status: PAID. Travel safe!";
                $db->prepare("INSERT INTO sms_logs (booking_id, phone, message, status) VALUES (?, ?, ?, 'pending')")
                   ->execute([$payment['booking_id'], $booking['phone'], $msg]);
            }
        } elseif ($new_status === 'failed') {
            $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ? AND status = 'pending'")
               ->execute([$payment['booking_id']]);
        }
    }

    http_response_code(200);
    exit('OK');
} catch (Exception $e) {
    http_response_code(500);
    exit('Error');
}
