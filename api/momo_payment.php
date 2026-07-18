<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/momo.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['status' => 'error', 'message' => 'Please login first'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$booking_id = intval($data['booking_id'] ?? 0);
$payer_phone = sanitize($data['phone'] ?? '');

if (!$booking_id || !$payer_phone) {
    jsonResponse(['status' => 'error', 'message' => 'booking_id and phone required'], 400);
}

try {
    $db = getDb();

    $stmt = $db->prepare("
        SELECT bk.id, bk.user_id, bk.bus_id, bk.seat_id, bk.booking_date, bk.status,
               b.bus_code, b.bus_name, b.fare, s.seat_number, u.full_name, u.email
        FROM bookings bk
        JOIN buses b ON bk.bus_id = b.id
        JOIN seats s ON bk.seat_id = s.id
        JOIN users u ON bk.user_id = u.id
        WHERE bk.id = ? AND bk.user_id = ?
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        jsonResponse(['status' => 'error', 'message' => 'Booking not found'], 404);
    }

    if ($booking['status'] === 'paid') {
        jsonResponse(['status' => 'error', 'message' => 'Booking is already paid'], 400);
    }

    $amount = $booking['fare'] ?: MOMO_FARE_DEFAULT;
    $external_id = 'BK' . str_pad($booking_id, 6, '0', STR_PAD_LEFT);
    $payer_msg = "Pay {$booking['bus_name']} ticket - Seat {$booking['seat_number']}";

    $api_user = MOMO_API_USER;
    $api_key = MOMO_API_KEY;
    $sub_key = MOMO_SUBSCRIPTION_KEY;
    $base_url = MOMO_BASE_URL;

    if (empty($api_user) || empty($api_key) || empty($sub_key)) {
        $db->prepare("
            INSERT INTO payments (booking_id, user_id, amount, currency, payment_method, payer_phone, status)
            VALUES (?, ?, ?, 'RWF', 'MTN_MoMo', ?, 'pending')
        ")->execute([$booking_id, $_SESSION['user_id'], $amount, $payer_phone]);

        jsonResponse([
            'status' => 'success',
            'message' => 'Payment queued. MTN MoMo credentials not configured - admin will confirm manually.',
            'booking_id' => $booking_id,
            'amount' => $amount,
            'sandbox' => true
        ]);
    }

    // Step 1: Get access token
    $token = momoGetToken($base_url, $api_user, $api_key, $sub_key);
    if (!$token) {
        jsonResponse(['status' => 'error', 'message' => 'Failed to connect to MTN MoMo. Please try again.'], 502);
    }

    // Step 2: Request to Pay
    $ref_id = momoRequestToPay($base_url, $token, $sub_key, $external_id, $amount, $payer_phone, $payer_msg, MOMO_CALLBACK_URL);

    if ($ref_id) {
        $db->prepare("
            INSERT INTO payments (booking_id, user_id, amount, currency, payment_method, transaction_id, payer_phone, status)
            VALUES (?, ?, ?, 'RWF', 'MTN_MoMo', ?, ?, 'pending')
        ")->execute([$booking_id, $_SESSION['user_id'], $amount, $ref_id, $payer_phone]);

        $db->prepare("UPDATE bookings SET payment_method = 'MTN_MoMo' WHERE id = ?")->execute([$booking_id]);

        jsonResponse([
            'status' => 'success',
            'message' => 'Payment request sent to your MTN MoMo. Please approve on your phone.',
            'booking_id' => $booking_id,
            'transaction_id' => $ref_id,
            'amount' => $amount
        ]);
    } else {
        jsonResponse(['status' => 'error', 'message' => 'Failed to initiate payment. Please try again.'], 502);
    }
} catch (Exception $e) {
    errorResponse('Payment initiation failed');
}

function momoGetToken($base_url, $api_user, $api_key, $sub_key) {
    $ch = curl_init("{$base_url}/collection/token/");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode("{$api_user}:{$api_key}"),
            'Ocp-Apim-Subscription-Key: ' . $sub_key,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 200 || $http_code === 201) {
        $json = json_decode($response, true);
        return $json['access_token'] ?? null;
    }
    return null;
}

function momoRequestToPay($base_url, $token, $sub_key, $external_id, $amount, $phone, $message, $callback_url) {
    $ref_id = strtoupper(bin2hex(random_bytes(16)));
    $payload = json_encode([
        'amount' => (string) $amount,
        'currency' => MOMO_CURRENCY,
        'externalId' => $external_id,
        'payer' => ['partyIdType' => 'MSISDN', 'partyId' => preg_replace('/[^0-9]/', '', $phone)],
        'payerMessage' => $message,
        'payeeNote' => "Bus Ticket Payment - {$external_id}"
    ]);

    $headers = [
        'X-Reference-Id: ' . $ref_id,
        'X-Target-Environment: sandbox',
        'Ocp-Apim-Subscription-Key: ' . $sub_key,
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ];
    if ($callback_url) {
        $headers[] = 'X-Callback-Url: ' . $callback_url;
    }

    $ch = curl_init("{$base_url}/collection/v1_0/requesttopay");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code === 202 || $http_code === 200 || $http_code === 201) {
        return $ref_id;
    }
    return null;
}
