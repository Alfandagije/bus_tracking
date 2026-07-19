<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['status' => 'error', 'message' => 'Please login first'], 401);
}

$data = json_decode(file_get_contents('php://input'), true);
$bus_code = sanitize($data['bus_code'] ?? $_POST['bus_code'] ?? '');
$seat_number = sanitize($data['seat_number'] ?? $_POST['seat_number'] ?? '');
$payment_method = sanitize($data['payment_method'] ?? 'MTN_MoMo');
$booking_date = date('Y-m-d');

if (!$bus_code || !$seat_number) {
    jsonResponse(['status' => 'error', 'message' => 'bus_code and seat_number required'], 400);
}

try {
    $db = getDb();
    $db->beginTransaction();

    // Get bus
    $stmt = $db->prepare("SELECT id, bus_name, fare FROM buses WHERE bus_code = ? AND status = 'active'");
    $stmt->execute([$bus_code]);
    $bus = $stmt->fetch();

    if (!$bus) {
        $db->rollBack();
        jsonResponse(['status' => 'error', 'message' => 'Bus not found or inactive'], 404);
    }

    // Check if user already has a booking on this bus for today
    $stmt = $db->prepare("
        SELECT id FROM bookings 
        WHERE user_id = ? AND bus_id = ? AND booking_date = ? AND status != 'cancelled'
    ");
    $stmt->execute([$_SESSION['user_id'], $bus['id'], $booking_date]);
    if ($stmt->fetch()) {
        $db->rollBack();
        jsonResponse(['status' => 'error', 'message' => 'You already have a booking on this bus today'], 409);
    }

    // Get seat
    $stmt = $db->prepare("
        SELECT id, status FROM seats 
        WHERE bus_id = ? AND seat_number = ? AND status IN ('available', 'booked')
        FOR UPDATE
    ");
    $stmt->execute([$bus['id'], $seat_number]);
    $seat = $stmt->fetch();

    if (!$seat) {
        $db->rollBack();
        jsonResponse(['status' => 'error', 'message' => 'Seat not available or does not exist'], 409);
    }

    // Book seat
    $stmt = $db->prepare("UPDATE seats SET status = 'booked' WHERE id = ?");
    $stmt->execute([$seat['id']]);

    // Create booking
    $stmt = $db->prepare("
        INSERT INTO bookings (user_id, bus_id, seat_id, booking_date, status, payment_method, amount) 
        VALUES (?, ?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $bus['id'], $seat['id'], $booking_date, $payment_method, $bus['fare'] ?? 500]);
    $booking_id = $db->lastInsertId();

    // Create SMS log
    $stmt = $db->prepare("SELECT phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    $message = "🎫 BOOKING CONFIRMED\n";
    $message .= "━━━━━━━━━━━━━━━━━\n";
    $message .= "Bus: {$bus['bus_name']} ({$bus_code})\n";
    $message .= "Seat: {$seat_number}\n";
    $message .= "Booking ID: #{$booking_id}\n";
    $message .= "Date: {$booking_date}\n";
    $message .= "Amount: RWF " . number_format($bus['fare'] ?? 500) . "\n";
    $message .= "Payment: MTN MoMo\n";
    $message .= "━━━━━━━━━━━━━━━━━\n";
    $message .= "Show this message to the driver.\n";
    $message .= "Travel safe! 🚌";
    
    $stmt = $db->prepare("INSERT INTO sms_logs (booking_id, phone, message, status) VALUES (?, ?, ?, 'pending')");
    $stmt->execute([$booking_id, $user['phone'], $message]);

    $db->commit();

    jsonResponse([
        'status' => 'success',
        'message' => 'Booking successful! Ticket SMS will be sent shortly.',
        'booking_id' => $booking_id,
        'bus_code' => $bus_code,
        'seat_number' => $seat_number
    ], 201);
} catch (Exception $e) {
    if ($db) {
        try { $db->rollBack(); } catch (Exception $re) {}
    }
    error_log('Booking error: ' . $e->getMessage());
    errorResponse('Booking failed: ' . $e->getMessage());
}
