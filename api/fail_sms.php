<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/../config/database.php';

$ticket_id = $_GET['ticket_id'] ?? null;

if (!$ticket_id) {
    echo "ERROR: ticket_id required";
    exit;
}

try {
    $db = getDb();

    $stmt = $db->prepare("UPDATE sms_logs SET status = 'failed', sent_at = NOW() WHERE booking_id = ? AND status = 'pending'");
    $stmt->execute([$ticket_id]);

    echo $stmt->rowCount() > 0 ? "OK" : "ERROR: SMS not found";
} catch (Exception $e) {
    echo "ERROR: Failed to mark SMS";
}
