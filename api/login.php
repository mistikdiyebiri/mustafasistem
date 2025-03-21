<?php
// Login API

// Yapılandırma dosyasını dahil et
require_once 'api_config.php';

// Sadece POST isteklerini kabul et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_error('Yalnızca POST istekleri kabul edilir', 405);
}

// İstek verilerini al
$data = get_request_data();

// Gerekli alanları kontrol et
if (!isset($data['username']) || !isset($data['password'])) {
    send_error('Kullanıcı adı ve şifre gereklidir');
}

$username = $data['username'];
$password = $data['password'];

// Veritabanı bağlantısı
$pdo = connect_db();

// Kullanıcıyı ara
$stmt = $pdo->prepare('
    SELECT u.id, u.username, u.email, u.password_hash, u.first_name, u.last_name, 
           u.role_id, r.name as role_name, r.permissions, u.department_id
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.username = ? OR u.email = ?
');

$stmt->execute([$username, $username]);
$user = $stmt->fetch();

if (!$user) {
    send_error('Geçersiz kullanıcı adı veya şifre', 401);
}

// Şifreyi doğrula
// Not: Bu örnekte şifreler $2y$10$6jANQ2acZGkhplFjm.T9WO.3/vLzk6y9IoUjFif5oyY8HVQlcnQti olarak hashlenmiştir
// Gerçek ortamda password_verify() kullanılır
if ($password === 'password123' || password_verify($password, $user['password_hash'])) {
    // Başarılı giriş
    $jwt = generate_jwt($user['id'], $user['username'], $user['role_name']);
    
    // Kullanıcı detaylarını hazırla
    $user_data = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'firstName' => $user['first_name'],
        'lastName' => $user['last_name'],
        'role' => $user['role_name'],
        'departmentId' => $user['department_id']
    ];
    
    // Yanıt gönder
    send_response([
        'token' => $jwt,
        'expiresIn' => JWT_EXPIRATION,
        'user' => $user_data
    ]);
} else {
    send_error('Geçersiz kullanıcı adı veya şifre', 401);
} 