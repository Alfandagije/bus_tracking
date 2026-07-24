<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'driver') {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 403);
}

try {
    $db = getDb();

    $stmt = $db->prepare("
        SELECT d.id as driver_id, d.full_name, d.assigned_bus_id, b.bus_code, b.bus_name
        FROM drivers d
        LEFT JOIN buses b ON d.assigned_bus_id = b.id
        WHERE d.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $driver = $stmt->fetch();

    if (!$driver || !$driver['assigned_bus_id']) {
        jsonResponse(['status' => 'success', 'data' => null, 'message' => 'No bus assigned']);
    }

    $stmt = $db->prepare("
        SELECT bk.id, bk.booking_date, bk.status, bk.payment_method, bk.amount,
               s.seat_number, u.full_name, u.phone, u.email
        FROM bookings bk
        JOIN seats s ON bk.seat_id = s.id
        JOIN users u ON bk.user_id = u.id
        WHERE bk.bus_id = ? AND bk.booking_date = CURDATE() AND bk.status != 'cancelled'
        ORDER BY s.seat_number
    ");
    $stmt->execute([$driver['assigned_bus_id']]);
    $bookings = $stmt->fetchAll();

    jsonResponse([
        'status' => 'success',
        'driver' => $driver,
        'bookings' => $bookings,
        'total_booked' => count($bookings),
        'total_seats' => $driver['assigned_bus_id'] ? (function() use ($db, $driver) {
            $s = $db->prepare("SELECT COUNT(*) FROM seats WHERE bus_id = ?");
            $s->execute([$driver['assigned_bus_id']]);
            return $s->fetchColumn();
        })() : 0
    ]);
} catch (Exception $e) {
    errorResponse('Failed to load driver data');
}
