<?php
require_once __DIR__ . '/secrets.php';
loadSecrets();

// Production error handling
$isProduction = (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'bustracking.kesug.com')
    || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'kesug.com') !== false)
    || getenv('APP_ENV') === 'production'
    || getenv('RENDER') === 'true';
if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Session security (only if session not yet started)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    // Only set secure flag on HTTPS
    if ($isProduction || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
        ini_set('session.cookie_secure', 1);
    }
}

class Database {
    private $host;
    private $port;
    private $db_name;
    private $username;
    private $password;
    private $ssl_ca;
    private $conn;

    public function __construct() {
        if (getenv('DB_HOST') && getenv('DB_NAME') && getenv('DB_USER')) {
            $this->host = getenv('DB_HOST');
            $this->port = getenv('DB_PORT') ?: '3306';
            $this->db_name = getenv('DB_NAME');
            $this->username = getenv('DB_USER');
            $this->password = getenv('DB_PASSWORD') ?: '';
            $this->ssl_ca = getenv('DB_SSL_CA');
        } elseif ((isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] === 'bustracking.kesug.com') || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'kesug.com') !== false)) {
            $this->host = 'sql302.infinityfree.com';
            $this->port = '3306';
            $this->db_name = 'if0_42236207_bustracking';
            $this->username = 'if0_42236207';
            $this->password = 'Alfred2026';
            $this->ssl_ca = null;
        } else {
            $this->host = 'localhost';
            $this->port = '3306';
            $this->db_name = 'bus_tracking_db';
            $this->username = 'root';
            $this->password = '';
            $this->ssl_ca = null;
        }
    }

    public function connect() {
        $this->conn = null;
        try {
            $options = [
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            if ($this->ssl_ca) {
                $caPath = '/tmp/aiven-ca.pem';
                file_put_contents($caPath, $this->ssl_ca);
                $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
            }
            $this->conn = new PDO(
                "mysql:host={$this->host};port={$this->port};dbname={$this->db_name}",
                $this->username,
                $this->password,
                $options
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            jsonResponse(['status' => 'error', 'message' => 'Service unavailable']);
            exit;
        }
        return $this->conn;
    }
}

function getDb() {
    $db = new Database();
    return $db->connect();
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function isAuthenticated() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
    }
    return $_SESSION;
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function sec($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function errorResponse($message = 'Something went wrong') {
    return jsonResponse(['status' => 'error', 'message' => $message], 500);
}

function requireRole($roles) {
    $session = isAuthenticated();
    if (!is_array($roles)) $roles = [$roles];
    if (!in_array($session['role'], $roles)) {
        jsonResponse(['status' => 'error', 'message' => 'Forbidden'], 403);
    }
    return $session;
}

function isManager() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'manager';
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isDriver() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'driver';
}

function isPassenger() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'passenger';
}

function canManage() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager']);
}

define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: 'AIzaSyCJLOPxr9PgRtV_aOMJwXu4q6II_cPgSME');

require_once __DIR__ . '/helpers.php';

