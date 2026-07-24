<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 403);
}

$db = getDb();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

if ($method === 'GET' && $action === 'list') {
    $stmt = $db->query("
        SELECT b.*, d.full_name as driver_name, d.phone as driver_phone,
               r.route_name, r.origin, r.destination,
               (SELECT COUNT(*) FROM seats WHERE bus_id = b.id) as seat_count,
               (SELECT COUNT(*) FROM seats WHERE bus_id = b.id AND status = 'booked') as booked_count
        FROM buses b
        LEFT JOIN drivers d ON b.driver_id = d.id
        LEFT JOIN routes r ON b.route_id = r.id
        ORDER BY b.bus_code
    ");
    jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    $bus_code = sanitize($data['bus_code'] ?? '');
    $bus_name = sanitize($data['bus_name'] ?? '');
    $total_seats = intval($data['total_seats'] ?? 30);
    $fare = floatval($data['fare'] ?? 500);
    $status = sanitize($data['status'] ?? 'active');
    $driver_id = !empty($data['driver_id']) ? intval($data['driver_id']) : null;
    $route_id = !empty($data['route_id']) ? intval($data['route_id']) : null;

    if (!$bus_code || !$bus_name) {
        jsonResponse(['status' => 'error', 'message' => 'bus_code and bus_name required'], 400);
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("INSERT INTO buses (bus_code, bus_name, total_seats, fare, status, driver_id, route_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$bus_code, $bus_name, $total_seats, $fare, $status, $driver_id, $route_id]);
        $bus_id = $db->lastInsertId();

        // Auto-create seats
        for ($i = 1; $i <= $total_seats; $i++) {
            $seat_label = 'A' . $i;
            $db->prepare("INSERT INTO seats (bus_id, seat_number, status) VALUES (?, ?, 'available')")->execute([$bus_id, $seat_label]);
        }

        if ($driver_id) {
            $db->prepare("UPDATE buses SET driver_id = ? WHERE id = ?")->execute([$driver_id, $bus_id]);
        }

        $db->commit();
        jsonResponse(['status' => 'success', 'message' => "Bus created with {$total_seats} seats", 'id' => $bus_id], 201);
    } catch (PDOException $e) {
        $db->rollBack();
        if ($e->getCode() == 23000) {
            jsonResponse(['status' => 'error', 'message' => 'Bus code already exists'], 409);
        }
        errorResponse('Failed to create bus');
    }
}

if ($method === 'POST' && $action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    $bus_name = sanitize($data['bus_name'] ?? '');
    $fare = floatval($data['fare'] ?? 500);
    $status = sanitize($data['status'] ?? 'active');
    $driver_id = !empty($data['driver_id']) ? intval($data['driver_id']) : null;
    $route_id = !empty($data['route_id']) ? intval($data['route_id']) : null;

    if (!$id) {
        jsonResponse(['status' => 'error', 'message' => 'Bus ID required'], 400);
    }

    $db->beginTransaction();
    if ($driver_id) {
        $db->prepare("UPDATE buses SET driver_id = NULL WHERE driver_id = ? AND id != ?")->execute([$driver_id, $id]);
    }
    $db->prepare("UPDATE buses SET bus_name = ?, fare = ?, status = ?, driver_id = ?, route_id = ? WHERE id = ?")
       ->execute([$bus_name, $fare, $status, $driver_id, $route_id, $id]);
    $db->commit();
    jsonResponse(['status' => 'success', 'message' => 'Bus updated']);
}

if ($method === 'POST' && $action === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    if (!$id) {
        jsonResponse(['status' => 'error', 'message' => 'Bus ID required'], 400);
    }
    $db->prepare("UPDATE buses SET driver_id = NULL WHERE driver_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM buses WHERE id = ?")->execute([$id]);
    jsonResponse(['status' => 'success', 'message' => 'Bus deleted']);
}

jsonResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
