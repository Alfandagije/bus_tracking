<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

$bus_code = $_GET['bus_code'] ?? null;

try {
    $db = getDb();

    if ($bus_code) {
        $stmt = $db->prepare("SELECT bus_code, bus_name, fare, departure_time, current_lat, current_lng, last_update FROM buses WHERE bus_code = ?");
        $stmt->execute([$bus_code]);
        $bus = $stmt->fetch();
        if (!$bus) {
            jsonResponse(['status' => 'error', 'message' => 'Bus not found'], 404);
        }
        jsonResponse(['status' => 'success', 'data' => $bus]);
    } else {
        $stmt = $db->query("SELECT bus_code, bus_name, fare, departure_time, current_lat, current_lng, last_update FROM buses WHERE status = 'active'");
        $buses = $stmt->fetchAll();
        jsonResponse(['status' => 'success', 'data' => $buses]);
    }
} catch (Exception $e) {
    errorResponse();
}
