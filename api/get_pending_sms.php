<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// ESP32 calls via GET: /api/get_pending_sms.php?bus_id=BUS001
// Expects JSON with status, phone, message, ticket_id

$bus_code = $_GET['bus_id'] ?? null;

try {
    $db = getDb();

    // Step 1: Find oldest pending SMS for this bus
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
        // Fallback: try pending SMS without bus_id filter (legacy records)
        $stmt = $db->prepare("
            SELECT s.id AS sms_id, s.booking_id, s.phone, s.message
            FROM sms_logs s
            WHERE s.status = 'pending' AND (s.bus_id IS NULL)
            ORDER BY s.id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $sms = $stmt->fetch();
    }

    if (!$sms) {
        echo json_encode(['status' => 'error', 'message' => 'No pending SMS']);
        exit;
    }

    // Step 2: Atomically claim the SMS (mark as 'sent' to prevent duplicate pickup)
    $claim = $db->prepare("
        UPDATE sms_logs SET status = 'sent', sent_at = NOW() WHERE id = ? AND status = 'pending'
    ");
    $claim->execute([$sms['sms_id']]);

    if ($claim->rowCount() === 0) {
        // Another ESP32 grabbed it first
        echo json_encode(['status' => 'error', 'message' => 'No pending SMS']);
        exit;
    }

    // Step 3: Update booking sms_sent flag
    $db->prepare("UPDATE bookings SET sms_sent = 1 WHERE id = ?")->execute([$sms['booking_id']]);

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
