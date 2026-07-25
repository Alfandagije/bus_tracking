<?php
header('Content-Type: text/plain');
require_once __DIR__ . '/../config/database.php';

// ESP32 sends GET via SIM900 AT+HTTPACTION=0
// Params: bus_id, lat, lng, s1, s2, s3, s4

$bus_code = $_GET['bus_id'] ?? $_POST['bus_id'] ?? $_GET['bus_code'] ?? $_POST['bus_code'] ?? null;
$lat = $_GET['lat'] ?? $_POST['lat'] ?? null;
$lng = $_GET['lng'] ?? $_POST['lng'] ?? null;
$s1 = $_GET['s1'] ?? $_POST['s1'] ?? null;
$s2 = $_GET['s2'] ?? $_POST['s2'] ?? null;
$s3 = $_GET['s3'] ?? $_POST['s3'] ?? null;
$s4 = $_GET['s4'] ?? $_POST['s4'] ?? null;

if (!$bus_code) {
    echo "ERROR: bus_id required";
    exit;
}

try {
    $db = getDb();

    $stmt = $db->prepare("SELECT id, bus_code FROM buses WHERE bus_code = ?");
    $stmt->execute([$bus_code]);
    $bus = $stmt->fetch();

    if (!$bus) {
        echo "ERROR: Bus not found";
        exit;
    }

    $bus_id = $bus['id'];

    // Update GPS — reject null-island (0,0) to preserve last known location
    if ($lat !== null && $lng !== null) {
        $latVal = floatval($lat);
        $lngVal = floatval($lng);
        if ($latVal != 0.0 && $lngVal != 0.0) {
            $stmt = $db->prepare("UPDATE buses SET current_lat = ?, current_lng = ?, last_update = NOW() WHERE id = ?");
            $stmt->execute([$lat, $lng, $bus_id]);
        }
    }

    // Update seats — map digital values (0/1) to sensor status
    // IR sensors are active-low: 0 = person detected (occupied), 1 = empty (available)
    $seatMap = [
        's1' => ['num' => 'A1', 'val' => $s1],
        's2' => ['num' => 'A2', 'val' => $s2],
        's3' => ['num' => 'A3', 'val' => $s3],
        's4' => ['num' => 'A4', 'val' => $s4],
    ];

    $stmt = $db->prepare("
        UPDATE seats 
        SET ir_sensor_status = ?, 
            status = IF(? = 'HIGH', 'occupied', IF(status = 'booked', 'booked', 'available')) 
        WHERE bus_id = ? AND seat_number = ?
    ");

    foreach ($seatMap as $key => $seat) {
        if ($seat['val'] !== null) {
            // 0 = LOW signal = person detected = occupied
            $sensorStatus = ($seat['val'] == 0) ? 'HIGH' : 'LOW';
            $stmt->execute([$sensorStatus, $sensorStatus, $bus_id, $seat['num']]);
        }
    }

    echo "OK";
} catch (Exception $e) {
    echo "ERROR: Update failed";
}
