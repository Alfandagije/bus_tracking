<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDb();
    $stmt = $db->query("
        SELECT b.id, b.bus_code, b.bus_name, b.total_seats, b.current_lat, b.current_lng,
               b.status, b.last_update, b.route_id,
               r.route_name, r.origin, r.destination,
               r.origin_lat, r.origin_lng, r.dest_lat, r.dest_lng
        FROM buses b
        LEFT JOIN routes r ON b.route_id = r.id
        ORDER BY b.bus_code
    ");
    $buses = $stmt->fetchAll();
    jsonResponse(['status' => 'success', 'data' => $buses]);
} catch (Exception $e) {
    errorResponse();
}
