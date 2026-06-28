<?php
require_once __DIR__ . '/secrets.php';
loadSecrets();

// Production error handling
$isProduction = ($_SERVER['SERVER_NAME'] === 'bustracking.kesug.com' || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'kesug.com') !== false))
    || getenv('APP_ENV') === 'production';
if ($isProduction) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Session security
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Strict');

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;

    public function __construct() {
        if (getenv('DB_HOST') && getenv('DB_NAME') && getenv('DB_USER')) {
            $this->host = getenv('DB_HOST');
            $this->db_name = getenv('DB_NAME');
            $this->username = getenv('DB_USER');
            $this->password = getenv('DB_PASSWORD') ?: '';
        } elseif ($_SERVER['SERVER_NAME'] === 'bustracking.kesug.com' || (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'kesug.com') !== false)) {
            $this->host = 'sql302.infinityfree.com';
            $this->db_name = 'if0_42236207_bustracking';
            $this->username = 'if0_42236207';
            $this->password = 'Alfred2026';
        } else {
            $this->host = 'localhost';
            $this->db_name = 'bus_tracking_db';
            $this->username = 'root';
            $this->password = '';
        }
    }

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name}",
                $this->username,
                $this->password,
                [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]
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

define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: 'AIzaSyCJLOPxr9PgRtV_aOMJwXu4q6II_cPgSME');

require_once __DIR__ . '/helpers.php';

