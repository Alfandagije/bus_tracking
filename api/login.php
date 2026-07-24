<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
$email = sanitize($data['email'] ?? $_POST['email'] ?? '');
$password = $data['password'] ?? $_POST['password'] ?? '';

if (!$email || !$password) {
    jsonResponse(['status' => 'error', 'message' => 'Email and password required'], 400);
}

try {
    $db = getDb();
    $stmt = $db->prepare("SELECT id, full_name, email, phone, password, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        jsonResponse(['status' => 'error', 'message' => 'Invalid credentials'], 401);
    }

    session_start();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];

    $redirect = '../index.php';
    if ($user['role'] === 'admin' || $user['role'] === 'manager') {
        $redirect = '../dashboard/index.php';
    } elseif ($user['role'] === 'driver') {
        $redirect = '../dashboard/driver/index.php';
    }

    jsonResponse([
        'status' => 'success',
        'message' => 'Login successful',
        'redirect' => $redirect,
        'user' => [
            'id' => $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
} catch (Exception $e) {
    errorResponse();
}
