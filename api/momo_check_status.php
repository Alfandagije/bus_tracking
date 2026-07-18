<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/momo.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
}

$booking_id = intval($_GET['booking_id'] ?? 0);
if (!$booking_id) {
    jsonResponse(['status' => 'error', 'message' => 'booking_id required'], 400);
}

try {
    $db = getDb();
    $stmt = $db->prepare("
        SELECT p.*, b.bus_name, s.seat_number
        FROM payments p
        JOIN bookings bk ON p.booking_id = bk.id
        JOIN buses b ON bk.bus_id = b.id
        JOIN seats s ON bk.seat_id = s.id
        WHERE p.booking_id = ? AND p.user_id = ?
        ORDER BY p.created_at DESC LIMIT 1
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $payment = $stmt->fetch();

    if (!$payment) {
        jsonResponse(['status' => 'error', 'message' => 'No payment found'], 404);
    }

    if ($payment['status'] === 'pending' && $payment['transaction_id'] && MOMO_API_USER) {
        $token = momoGetToken(MOMO_BASE_URL, MOMO_API_USER, MOMO_API_KEY, MOMO_SUBSCRIPTION_KEY);
        if ($token) {
            $ch = curl_init(MOMO_BASE_URL . "/collection/v1_0/requesttopay/{$payment['transaction_id']}");
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'X-Target-Environment: sandbox',
                    'Ocp-Apim-Subscription-Key: ' . MOMO_SUBSCRIPTION_KEY
                ],
                CURLOPT_TIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => false
            ]);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                $momo_status = json_decode($response, true);
                $status = strtolower($momo_status['status'] ?? '');

                if (in_array($status, ['successful', 'success'])) {
                    $db->prepare("UPDATE payments SET status = 'successful' WHERE id = ?")->execute([$payment['id']]);
                    $db->prepare("UPDATE bookings SET status = 'paid', payment_method = 'MTN_MoMo' WHERE id = ? AND status = 'pending'")
                       ->execute([$payment['booking_id']]);
                    $payment['status'] = 'successful';
                } elseif (in_array($status, ['failed', 'rejected', 'timeout'])) {
                    $db->prepare("UPDATE payments SET status = 'failed' WHERE id = ?")->execute([$payment['id']]);
                    $payment['status'] = 'failed';
                }
            }
        }
    }

    jsonResponse(['status' => 'success', 'data' => $payment]);
} catch (Exception $e) {
    errorResponse('Failed to check payment status');
}

function momoGetToken($base_url, $api_user, $api_key, $sub_key) {
    $ch = curl_init("{$base_url}/collection/token/");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Basic ' . base64_encode("{$api_user}:{$api_key}"),
            'Ocp-Apim-Subscription-Key: ' . $sub_key
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $j = json_decode($resp, true);
        return $j['access_token'] ?? null;
    }
    return null;
}
