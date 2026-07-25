<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// ESP32 polls: /api/get_pending_sms.php?bus_id=BUS001
// Returns oldest pending SMS for this bus. Status changes handled by confirm_sms.php / fail_sms.php

$bus_code = $_GET['bus_id'] ?? null;

if (!$bus_code) {
    echo json_encode(['status' => 'error', 'message' => 'bus_id required']);
    exit;
}

try {
    $db = getDb();

    $stmt = $db->prepare("
        SELECT s.id AS sms_id, s.booking_id, s.phone, s.message
        FROM sms_logs s
        JOIN buses bu ON s.bus_id = bu.id
        WHERE s.status = 'pending' AND bu.bus_code = ?
        ORDER BY s.id ASC
        LIMIT 1
    ");
    $stmt->execute([$bus_code]);
    $sms = $stmt->fetch();

    if (!$sms) {
        echo json_encode(['status' => 'error', 'message' => 'No pending SMS']);
        exit;
    }

    echo json_encode([
        'status'    => 'success',
        'ticket_id' => (string)$sms['booking_id'],
        'sms_id'    => (string)$sms['sms_id'],
        'phone'     => $sms['phone'],
        'message'   => $sms['message']
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Service unavailable']);
}
