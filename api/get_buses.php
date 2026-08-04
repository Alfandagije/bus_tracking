<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

try {
    $db = getDb();
    $stmt = $db->query("
        SELECT b.id, b.bus_code, b.bus_name, b.total_seats, b.fare, b.departure_time,
               b.current_lat, b.current_lng,
               b.status, b.last_update, b.route_id,
               r.route_name, r.origin, r.destination,
               r.origin_lat, r.origin_lng, r.dest_lat, r.dest_lng
        FROM buses b
        LEFT JOIN routes r ON b.route_id = r.id
        ORDER BY CASE b.status WHEN 'active' THEN 0 ELSE 1 END, b.departure_time ASC, b.bus_code
    ");
    $buses = $stmt->fetchAll();

    // Compute departed status using the app server clock so it matches the
    // timezone managers use when setting departure_time.
    $nowTime = date('H:i:s');
    foreach ($buses as &$bus) {
        $bus['departed'] = (!empty($bus['departure_time']) && $nowTime > $bus['departure_time']) ? 1 : 0;
    }
    unset($bus);

    jsonResponse(['status' => 'success', 'data' => $buses]);
} catch (Exception $e) {
    errorResponse();
}
