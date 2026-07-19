<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized admin access required'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$booking_ids = $data['booking_ids'] ?? [];
$status = sanitize($data['status'] ?? '');
$payment_method = sanitize($data['payment_method'] ?? '');

if (empty($booking_ids) || !is_array($booking_ids)) {
    jsonResponse(['status' => 'error', 'message' => 'booking_ids must be a non-empty array'], 400);
}

$valid_statuses = ['pending', 'paid', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid status. Must be pending, paid, or cancelled'], 400);
}

try {
    $db = getDb();
    $db->beginTransaction();

    $success_ids = [];
    $errors = [];

    // Statements to reuse
    $get_booking_stmt = $db->prepare("
        SELECT bk.id, bk.status, bk.bus_id, bk.seat_id, bk.booking_date, bk.user_id, bk.amount,
               b.bus_name, s.seat_number, u.phone
        FROM bookings bk
        JOIN buses b ON bk.bus_id = b.id
        JOIN seats s ON bk.seat_id = s.id
        JOIN users u ON bk.user_id = u.id
        WHERE bk.id = ?
        FOR UPDATE
    ");

    $check_seat_stmt = $db->prepare("
        SELECT id FROM bookings
        WHERE bus_id = ? AND seat_id = ? AND booking_date = ? AND status != 'cancelled' AND id != ?
    ");

    $update_booking_stmt = $db->prepare("
        UPDATE bookings 
        SET status = ?, payment_method = IF(? = 'paid', ?, payment_method)
        WHERE id = ?
    ");

    $update_seat_status_stmt = $db->prepare("
        UPDATE seats SET status = ? WHERE id = ?
    ");

    $insert_sms_stmt = $db->prepare("
        INSERT INTO sms_logs (booking_id, phone, message, status) VALUES (?, ?, ?, 'pending')
    ");

    foreach ($booking_ids as $id) {
        $id = intval($id);
        $get_booking_stmt->execute([$id]);
        $booking = $get_booking_stmt->fetch();

        if (!$booking) {
            $errors[] = "Booking #{$id} not found.";
            continue;
        }

        $old_status = $booking['status'];
        $bus_id = $booking['bus_id'];
        $seat_id = $booking['seat_id'];
        $booking_date = $booking['booking_date'];
        $bus_name = $booking['bus_name'];
        $seat_number = $booking['seat_number'];
        $phone = $booking['phone'];

        if ($old_status === $status) {
            $success_ids[] = $id; // No change needed but technically success
            continue;
        }

        // Handle seat booking state changes
        if ($status === 'cancelled') {
            // Releasing the seat
            $update_seat_status_stmt->execute(['available', $seat_id]);
            
            // Queue cancellation SMS
            $amount = $booking['amount'] ?? 500;
            $message = "BOOKING CANCELLED\n";
            $message .= "-------------------\n";
            $message .= "Bus: {$bus_name}\n";
            $message .= "Seat: {$seat_number}\n";
            $message .= "Booking ID: #{$id}\n";
            $message .= "Date: {$booking_date}\n";
            $message .= "Amount: RWF " . number_format($amount) . "\n";
            $message .= "-------------------\n";
            $message .= "This booking has been cancelled.";
            $insert_sms_stmt->execute([$id, $phone, $message]);
        } 
        else if ($old_status === 'cancelled') {
            // Re-activating a cancelled booking: check if seat is taken
            $check_seat_stmt->execute([$bus_id, $seat_id, $booking_date, $id]);
            if ($check_seat_stmt->fetch()) {
                $errors[] = "Booking #{$id} cannot be activated: Seat {$seat_number} on {$bus_name} is already booked by another active passenger today.";
                continue;
            }
            // Mark seat as booked
            $update_seat_status_stmt->execute(['booked', $seat_id]);

            // Queue SMS
            $amount = $booking['amount'] ?? 500;
            $message = "BOOKING REACTIVATED\n";
            $message .= "-------------------\n";
            $message .= "Bus: {$bus_name}\n";
            $message .= "Seat: {$seat_number}\n";
            $message .= "Booking ID: #{$id}\n";
            $message .= "Date: {$booking_date}\n";
            $message .= "Amount: RWF " . number_format($amount) . "\n";
            $message .= "Status: " . strtoupper($status) . "\n";
            $message .= "-------------------\n";
            $message .= "Travel safe!";
            $insert_sms_stmt->execute([$id, $phone, $message]);
        } 
        else if ($status === 'paid' && $old_status === 'pending') {
            // Payment confirmation SMS with full ticket details
            $amount = $booking['amount'] ?? 500;
            $message = "PAYMENT CONFIRMED\n";
            $message .= "-------------------\n";
            $message .= "Bus: {$bus_name}\n";
            $message .= "Seat: {$seat_number}\n";
            $message .= "Booking ID: #{$id}\n";
            $message .= "Date: {$booking_date}\n";
            $message .= "Amount: RWF " . number_format($amount) . "\n";
            $message .= "Payment: PAID\n";
            $message .= "-------------------\n";
            $message .= "Show this message to the driver.\n";
            $message .= "Travel safe!";
            $insert_sms_stmt->execute([$id, $phone, $message]);
        } 
        else if ($status === 'pending' && $old_status === 'paid') {
            // Simple update SMS
            $message = "Your booking #{$id} status has been updated back to PENDING.";
            $insert_sms_stmt->execute([$id, $phone, $message]);
        }

        // Perform booking update
        $pay_method = !empty($payment_method) ? $payment_method : 'Confirmed by Admin';
        $update_booking_stmt->execute([$status, $status, $pay_method, $id]);
        $success_ids[] = $id;
    }

    $db->commit();

    if (!empty($errors) && empty($success_ids)) {
        jsonResponse([
            'status' => 'error',
            'message' => 'All updates failed.',
            'errors' => $errors
        ], 400);
    } elseif (!empty($errors)) {
        jsonResponse([
            'status' => 'warning',
            'message' => 'Some updates failed.',
            'success_ids' => $success_ids,
            'errors' => $errors
        ], 200);
    } else {
        jsonResponse([
            'status' => 'success',
            'message' => 'Booking status updated successfully.',
            'success_ids' => $success_ids
        ], 200);
    }

} catch (Exception $e) {
    if (isset($db)) {
        $db->rollBack();
    }
    errorResponse('Database update failed: ' . $e->getMessage());
}
