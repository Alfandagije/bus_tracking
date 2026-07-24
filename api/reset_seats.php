<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'driver'])) {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$bus_id = intval($data['bus_id'] ?? 0);
$bus_code = sanitize($data['bus_code'] ?? '');

if (!$bus_id && !$bus_code) {
    jsonResponse(['status' => 'error', 'message' => 'bus_id or bus_code required'], 400);
}

try {
    $db = getDb();
    $db->beginTransaction();

    if ($bus_code) {
        $stmt = $db->prepare("SELECT id, bus_code FROM buses WHERE bus_code = ?");
        $stmt->execute([$bus_code]);
        $bus = $stmt->fetch();
    } else {
        $stmt = $db->prepare("SELECT id, bus_code FROM buses WHERE id = ?");
        $stmt->execute([$bus_id]);
        $bus = $stmt->fetch();
    }

    if (!$bus) {
        $db->rollBack();
        jsonResponse(['status' => 'error', 'message' => 'Bus not found'], 404);
    }

    $target_bus_id = $bus['id'];

    $stmt = $db->prepare("SELECT id, seat_number FROM seats WHERE bus_id = ?");
    $stmt->execute([$target_bus_id]);
    $seats = $stmt->fetchAll();

    $updateSeat = $db->prepare("UPDATE seats SET status = 'available', ir_sensor_status = 'LOW' WHERE id = ?");
    foreach ($seats as $seat) {
        $updateSeat->execute([$seat['id']]);
    }

    $stmt = $db->prepare("
        SELECT bk.id, bk.user_id, u.phone, s.seat_number, b.bus_name
        FROM bookings bk
        JOIN users u ON bk.user_id = u.id
        JOIN seats s ON bk.seat_id = s.id
        JOIN buses b ON bk.bus_id = b.id
        WHERE bk.bus_id = ? AND bk.booking_date = CURDATE() AND bk.status != 'cancelled'
    ");
    $stmt->execute([$target_bus_id]);
    $activeBookings = $stmt->fetchAll();

    $cancelBooking = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $insertSms = $db->prepare("INSERT INTO sms_logs (booking_id, phone, message, status) VALUES (?, ?, ?, 'pending')");

    $resetCount = 0;
    foreach ($activeBookings as $bk) {
        $cancelBooking->execute([$bk['id']]);
        $msg = "TRIP COMPLETED\n";
        $msg .= "Bus: {$bk['bus_name']} ({$bus['bus_code']})\n";
        $msg .= "Seat: {$bk['seat_number']}\n";
        $msg .= "Booking #{$bk['id']} has been completed.\n";
        $msg .= "Thank you for traveling with us!";
        $insertSms->execute([$bk['id'], $bk['phone'], $msg]);
        $resetCount++;
    }

    $db->commit();

    jsonResponse([
        'status' => 'success',
        'message' => "Seats reset for {$bus['bus_code']}. {$resetCount} booking(s) completed.",
        'seats_reset' => count($seats),
        'bookings_completed' => $resetCount
    ]);
} catch (Exception $e) {
    if (isset($db)) {
        try { $db->rollBack(); } catch (Exception $re) {}
    }
    errorLog('Reset seats error: ' . $e->getMessage());
    errorResponse('Failed to reset seats');
}
