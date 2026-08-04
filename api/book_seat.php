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
$passenger_lat = isset($data['passenger_lat']) && $data['passenger_lat'] !== '' ? (float)$data['passenger_lat'] : null;
$passenger_lng = isset($data['passenger_lng']) && $data['passenger_lng'] !== '' ? (float)$data['passenger_lng'] : null;
$booking_date = date('Y-m-d');

if (!$bus_code || !$seat_number) {
    jsonResponse(['status' => 'error', 'message' => 'bus_code and seat_number required'], 400);
}

try {
    $db = getDb();
    $db->beginTransaction();

    // Get bus
    $stmt = $db->prepare("
        SELECT b.id, b.bus_name, b.fare, b.departure_time, b.current_lat, b.current_lng, b.last_update,
               r.origin_lat, r.origin_lng, r.dest_lat, r.dest_lng
        FROM buses b
        LEFT JOIN routes r ON b.route_id = r.id
        WHERE b.bus_code = ? AND b.status = 'active'
    ");
    $stmt->execute([$bus_code]);
    $bus = $stmt->fetch();

    if (!$bus) {
        $db->rollBack();
        jsonResponse(['status' => 'error', 'message' => 'Bus not found or inactive'], 404);
    }

    // Block booking after scheduled departure time
    if (!empty($bus['departure_time']) && date('H:i:s') > $bus['departure_time']) {
        $db->rollBack();
        jsonResponse(['status' => 'error', 'message' => 'This bus has already departed. Booking is closed.'], 409);
    }

    // Block booking if the bus has already passed the passenger's location along the route
    if ($passenger_lat !== null && $passenger_lng !== null
        && !empty($bus['origin_lat']) && !empty($bus['dest_lat'])
        && (float)$bus['current_lat'] != 0 && (float)$bus['current_lng'] != 0) {

        $bus_t = routeProgress($bus['current_lat'], $bus['current_lng'],
                               $bus['origin_lat'], $bus['origin_lng'],
                               $bus['dest_lat'], $bus['dest_lng']);
        $pass_t = routeProgress($passenger_lat, $passenger_lng,
                                $bus['origin_lat'], $bus['origin_lng'],
                                $bus['dest_lat'], $bus['dest_lng']);

        if ($bus_t > $pass_t + 0.01) {
            $db->rollBack();
            jsonResponse(['status' => 'error', 'message' => 'The bus has already passed your location. Booking is closed.'], 409);
        }
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
    
    $phone = $user['phone'] ?? '';
    if (empty($phone)) {
        $db->rollBack();
        jsonResponse(['status' => 'error', 'message' => 'Phone number not found in your profile. Please update your profile first.'], 400);
    }
    
    $departure_time = $bus['departure_time'] ? date('g:i A', strtotime($bus['departure_time'])) : 'TBA';
    $amount = $bus['fare'] ?? 500;

    $message = "BOOKING CONFIRMED\n";
    $message .= "Bus: {$bus['bus_name']}\n";
    $message .= "Seat: {$seat_number}\n";
    $message .= "Departs: {$departure_time}\n";
    $message .= "ID: #{$booking_id}\n";
    $message .= "Date: {$booking_date}\n";
    $message .= "Amount: RWF " . number_format($amount) . "\n";
    $message .= "Pay: MTN MoMo\n";
    $message .= "Travel safe!";
    
    $stmt = $db->prepare("INSERT INTO sms_logs (booking_id, bus_id, phone, message, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->execute([$booking_id, $bus['id'], $phone, $message]);

    $db->commit();

    jsonResponse([
        'status' => 'success',
        'message' => 'Booking successful! Ticket SMS will be sent shortly.',
        'booking_id' => $booking_id,
        'bus_code' => $bus_code,
        'seat_number' => $seat_number,
        'departure_time' => $bus['departure_time'],
        'amount' => $amount
    ], 201);
} catch (Exception $e) {
    if ($db) {
        try { $db->rollBack(); } catch (Exception $re) {}
    }
    error_log('Booking error: ' . $e->getMessage());
    errorResponse('Booking failed: ' . $e->getMessage());
}
