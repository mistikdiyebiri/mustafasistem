<?php
// API Yapılandırma Dosyası
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// OPTIONS isteği için hemen yanıt ver (CORS için)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_NAME', 'mhys');
define('DB_USER', 'mhys_user');
define('DB_PASS', 'G53!uK#D9a');

// JWT yapılandırması
define('JWT_SECRET', 'mhys_jwt_secret_key_2025');
define('JWT_EXPIRATION', 3600); // 1 saat

// API isteklerini işleme
function connect_db() {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Veritabanı bağlantı hatası: ' . $e->getMessage()]);
        exit;
    }
}

// API yanıtı gönderme
function send_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data);
    exit;
}

// API hata yanıtı gönderme
function send_error($message, $status_code = 400) {
    http_response_code($status_code);
    echo json_encode(['error' => $message]);
    exit;
}

// JWT oluşturma
function generate_jwt($user_id, $username, $role) {
    $header = base64_encode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
    
    $issuedAt = time();
    $expiration = $issuedAt + JWT_EXPIRATION;
    
    $payload = base64_encode(json_encode([
        'iat' => $issuedAt,
        'exp' => $expiration,
        'user_id' => $user_id,
        'username' => $username,
        'role' => $role
    ]));
    
    $signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    
    return "$header.$payload.$signature";
}

// JWT doğrulama
function validate_jwt($jwt) {
    if (!$jwt) {
        return false;
    }
    
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        return false;
    }
    
    list($header, $payload, $signature) = $parts;
    
    $valid_signature = base64_encode(hash_hmac('sha256', "$header.$payload", JWT_SECRET, true));
    
    if ($signature !== $valid_signature) {
        return false;
    }
    
    $payload_data = json_decode(base64_decode($payload), true);
    
    if ($payload_data['exp'] < time()) {
        return false;
    }
    
    return $payload_data;
}

// İstek gövdesini alma
function get_request_data() {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        send_error('Geçersiz JSON formatı');
    }
    
    return $data;
}

// ID oluşturma
function generate_uuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
} 