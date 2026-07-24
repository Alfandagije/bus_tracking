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
        SELECT r.*, 
            (SELECT COUNT(*) FROM buses WHERE route_id = r.id) as bus_count,
            (SELECT COUNT(*) FROM buses b2 JOIN bookings bk ON b2.id = bk.bus_id WHERE b2.route_id = r.id AND bk.status = 'paid') as total_bookings
        FROM routes r
        ORDER BY r.route_name
    ");
    jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    $route_name = sanitize($data['route_name'] ?? '');
    $origin = sanitize($data['origin'] ?? '');
    $destination = sanitize($data['destination'] ?? '');
    $base_price = floatval($data['base_price'] ?? 500);
    $status = sanitize($data['status'] ?? 'active');

    if (!$route_name || !$origin || !$destination) {
        jsonResponse(['status' => 'error', 'message' => 'route_name, origin, and destination required'], 400);
    }

    try {
        $stmt = $db->prepare("INSERT INTO routes (route_name, origin, destination, base_price, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$route_name, $origin, $destination, $base_price, $status]);
        jsonResponse(['status' => 'success', 'message' => 'Route created', 'id' => $db->lastInsertId()], 201);
    } catch (Exception $e) {
        errorResponse('Failed to create route');
    }
}

if ($method === 'POST' && $action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    $route_name = sanitize($data['route_name'] ?? '');
    $origin = sanitize($data['origin'] ?? '');
    $destination = sanitize($data['destination'] ?? '');
    $base_price = floatval($data['base_price'] ?? 500);
    $status = sanitize($data['status'] ?? 'active');

    if (!$id) {
        jsonResponse(['status' => 'error', 'message' => 'Route ID required'], 400);
    }

    $db->prepare("UPDATE routes SET route_name = ?, origin = ?, destination = ?, base_price = ?, status = ? WHERE id = ?")
       ->execute([$route_name, $origin, $destination, $base_price, $status, $id]);

    $db->prepare("UPDATE buses SET fare = ? WHERE route_id = ?")->execute([$base_price, $id]);

    jsonResponse(['status' => 'success', 'message' => 'Route updated']);
}

if ($method === 'POST' && $action === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    if (!$id) {
        jsonResponse(['status' => 'error', 'message' => 'Route ID required'], 400);
    }
    $db->prepare("UPDATE buses SET route_id = NULL WHERE route_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM routes WHERE id = ?")->execute([$id]);
    jsonResponse(['status' => 'success', 'message' => 'Route deleted']);
}

jsonResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
