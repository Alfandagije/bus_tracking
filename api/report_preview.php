<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 403);
}

$db = getDb();
$report_type = $_GET['type'] ?? 'bookings';
$date_from = $_GET['from'] ?? date('Y-m-01');
$date_to = $_GET['to'] ?? date('Y-m-d');
$bus_code = $_GET['bus_code'] ?? '';

$where = "WHERE DATE(bk.created_at) BETWEEN ? AND ?";
$params = [$date_from, $date_to];

if ($bus_code) {
    $where .= " AND b.bus_code = ?";
    $params[] = $bus_code;
}

try {
    $stats = [];

    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bookings bk JOIN buses b ON bk.bus_id = b.id {$where}");
    $stmt->execute($params);
    $stats['total_bookings'] = (int)$stmt->fetch()['c'];

    $where_paid = str_replace('bk.created_at', 'bk.created_at', $where) . " AND bk.status = 'paid'";
    $stmt = $db->prepare("SELECT COUNT(*) as c, COALESCE(SUM(bk.amount),0) as total FROM bookings bk JOIN buses b ON bk.bus_id = b.id {$where_paid}");
    $stmt->execute($params);
    $paid = $stmt->fetch();
    $stats['paid_bookings'] = (int)$paid['c'];
    $stats['revenue'] = (float)$paid['total'];

    $where_pending = str_replace('bk.created_at', 'bk.created_at', $where) . " AND bk.status = 'pending'";
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM bookings bk JOIN buses b ON bk.bus_id = b.id {$where_pending}");
    $stmt->execute($params);
    $stats['pending_bookings'] = (int)$stmt->fetch()['c'];

    $stmt = $db->prepare("SELECT COUNT(*) as c FROM payments WHERE status = 'successful' AND DATE(created_at) BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $stats['successful_payments'] = (int)$stmt->fetch()['c'];

    jsonResponse(['status' => 'success', 'stats' => $stats]);
} catch (Exception $e) {
    jsonResponse(['status' => 'error', 'message' => 'Failed to load stats'], 500);
}
