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
    $where = '';
    $params = [];
    if (!empty($_GET['status'])) {
        $where .= ' WHERE p.status = ?';
        $params[] = $_GET['status'];
    }
    if (!empty($_GET['bus_code'])) {
        $where .= ($where ? ' AND' : ' WHERE') . ' b.bus_code = ?';
        $params[] = $_GET['bus_code'];
    }
    $stmt = $db->prepare("
        SELECT p.*, bk.id as bk_id, b.bus_code, b.bus_name, s.seat_number, u.full_name, u.email, u.phone
        FROM payments p
        JOIN bookings bk ON p.booking_id = bk.id
        JOIN buses b ON bk.bus_id = b.id
        JOIN seats s ON bk.seat_id = s.id
        JOIN users u ON p.user_id = u.id
        {$where}
        ORDER BY p.created_at DESC
    ");
    $stmt->execute($params);
    jsonResponse(['status' => 'success', 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'confirm') {
    $data = json_decode(file_get_contents('php://input'), true);
    $payment_id = intval($data['payment_id'] ?? 0);
    if (!$payment_id) {
        jsonResponse(['status' => 'error', 'message' => 'payment_id required'], 400);
    }
    $stmt = $db->prepare("SELECT * FROM payments WHERE id = ?");
    $stmt->execute([$payment_id]);
    $payment = $stmt->fetch();
    if (!$payment) {
        jsonResponse(['status' => 'error', 'message' => 'Payment not found'], 404);
    }

    $db->beginTransaction();
    $db->prepare("UPDATE payments SET status = 'successful' WHERE id = ?")->execute([$payment_id]);
    $db->prepare("UPDATE bookings SET status = 'paid', payment_method = 'Confirmed by Admin' WHERE id = ? AND status = 'pending'")
       ->execute([$payment['booking_id']]);

    $stmt = $db->prepare("SELECT b.bus_name, s.seat_number, u.phone FROM bookings bk JOIN buses b ON bk.bus_id = b.id JOIN seats s ON bk.seat_id = s.id JOIN users u ON bk.user_id = u.id WHERE bk.id = ?");
    $stmt->execute([$payment['booking_id']]);
    $bk = $stmt->fetch();
    if ($bk) {
        $msg = "Payment confirmed for booking #{$payment['booking_id']} on {$bk['bus_name']} (Seat {$bk['seat_number']}). Amount: RWF " . number_format($payment['amount']) . ". Status: PAID. Travel safe!";
        $db->prepare("INSERT INTO sms_logs (booking_id, phone, message, status) VALUES (?, ?, ?, 'pending')")->execute([$payment['booking_id'], $bk['phone'], $msg]);
    }
    $db->commit();
    jsonResponse(['status' => 'success', 'message' => 'Payment confirmed']);
}

if ($method === 'POST' && $action === 'fail') {
    $data = json_decode(file_get_contents('php://input'), true);
    $payment_id = intval($data['payment_id'] ?? 0);
    $db->prepare("UPDATE payments SET status = 'failed' WHERE id = ?")->execute([$payment_id]);
    jsonResponse(['status' => 'success', 'message' => 'Payment marked as failed']);
}

jsonResponse(['status' => 'error', 'message' => 'Invalid action'], 400);
