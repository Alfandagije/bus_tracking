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
        SELECT d.*, b.bus_code, b.bus_name
        FROM drivers d
        LEFT JOIN buses b ON d.assigned_bus_id = b.id
        ORDER BY d.created_at DESC
    ");
    jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'create') {
    $data = json_decode(file_get_contents('php://input'), true);
    $full_name = sanitize($data['full_name'] ?? '');
    $phone = sanitize($data['phone'] ?? '');
    $license_number = sanitize($data['license_number'] ?? '');
    $assigned_bus_id = intval($data['assigned_bus_id'] ?? 0) ?: null;

    if (!$full_name || !$phone || !$license_number) {
        jsonResponse(['status' => 'error', 'message' => 'full_name, phone, and license_number required'], 400);
    }

    try {
        $stmt = $db->prepare("INSERT INTO drivers (full_name, phone, license_number, assigned_bus_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$full_name, $phone, $license_number, $assigned_bus_id]);
        $driver_id = $db->lastInsertId();

        if ($assigned_bus_id) {
            $db->prepare("UPDATE buses SET driver_id = ? WHERE id = ?")->execute([$driver_id, $assigned_bus_id]);
        }
        jsonResponse(['status' => 'success', 'message' => 'Driver created', 'id' => $driver_id], 201);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            jsonResponse(['status' => 'error', 'message' => 'License number already exists'], 409);
        }
        errorResponse('Failed to create driver');
    }
}

if ($method === 'POST' && $action === 'update') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    $full_name = sanitize($data['full_name'] ?? '');
    $phone = sanitize($data['phone'] ?? '');
    $license_number = sanitize($data['license_number'] ?? '');
    $assigned_bus_id = !empty($data['assigned_bus_id']) ? intval($data['assigned_bus_id']) : null;
    $status = sanitize($data['status'] ?? 'active');

    if (!$id) {
        jsonResponse(['status' => 'error', 'message' => 'Driver ID required'], 400);
    }

    $db->beginTransaction();
    // Unassign previous bus if changing
    if ($assigned_bus_id) {
        $db->prepare("UPDATE buses SET driver_id = NULL WHERE driver_id = ? AND id != ?")->execute([$id, $assigned_bus_id]);
        $db->prepare("UPDATE buses SET driver_id = ? WHERE id = ?")->execute([$id, $assigned_bus_id]);
    } else {
        $db->prepare("UPDATE buses SET driver_id = NULL WHERE driver_id = ?")->execute([$id]);
    }

    $db->prepare("UPDATE drivers SET full_name = ?, phone = ?, license_number = ?, assigned_bus_id = ?, status = ? WHERE id = ?")
       ->execute([$full_name, $phone, $license_number, $assigned_bus_id, $status, $id]);
    $db->commit();
    jsonResponse(['status' => 'success', 'message' => 'Driver updated']);
}

if ($method === 'POST' && $action === 'delete') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);
    if (!$id) {
        jsonResponse(['status' => 'error', 'message' => 'Driver ID required'], 400);
    }
    $db->prepare("UPDATE buses SET driver_id = NULL WHERE driver_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM drivers WHERE id = ?")->execute([$id]);
    jsonResponse(['status' => 'success', 'message' => 'Driver deleted']);
}

jsonResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
