<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$full_name = sanitize($data['full_name'] ?? $_POST['full_name'] ?? '');
$email = sanitize($data['email'] ?? $_POST['email'] ?? '');
$phone = sanitize($data['phone'] ?? $_POST['phone'] ?? '');
$password = $data['password'] ?? $_POST['password'] ?? '';

if (!$full_name || !$email || !$phone || !$password) {
    jsonResponse(['status' => 'error', 'message' => 'All fields required'], 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid email'], 400);
}

if (strlen($password) < 6) {
    jsonResponse(['status' => 'error', 'message' => 'Password must be at least 6 characters'], 400);
}

try {
    $db = getDb();

    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['status' => 'error', 'message' => 'Email already registered'], 409);
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'passenger')");
    $stmt->execute([$full_name, $email, $phone, $hashed]);

    $user_id = $db->lastInsertId();

    session_start();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['full_name'] = $full_name;
    $_SESSION['email'] = $email;
    $_SESSION['role'] = 'passenger';

    jsonResponse(['status' => 'success', 'message' => 'Registration successful', 'user_id' => $user_id], 201);
} catch (Exception $e) {
    errorResponse();
}
