<?php
// Email Settings API

// Yapılandırma dosyasını dahil et
require_once 'api_config.php';

// JWT kontrolü
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

// Bearer token'ı ayıkla
$jwt = '';
if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
    $jwt = $matches[1];
}

// Token doğrulama
$user_data = validate_jwt($jwt);
if (!$user_data) {
    send_error('Yetkilendirme hatası. Geçersiz token.', 401);
}

// Sadece admin rolüne sahip kullanıcılar e-posta ayarlarını yönetebilir
if ($user_data['role'] !== 'admin') {
    send_error('Bu işlem için yetkiniz bulunmamaktadır.', 403);
}

// Veritabanı bağlantısı
$pdo = connect_db();

// İstek tipine göre işlem yap
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // E-posta ayarı ID'si verilmişse tek ayar, değilse liste döndür
        $email_setting_id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if ($email_setting_id) {
            // Tek e-posta ayarını getir
            get_email_setting($pdo, $email_setting_id);
        } else {
            // E-posta ayarları listesini getir
            get_email_settings($pdo);
        }
        break;
        
    case 'POST':
        // Yeni e-posta ayarı oluştur
        create_email_setting($pdo);
        break;
        
    case 'PUT':
        // E-posta ayarını güncelle
        $email_setting_id = isset($_GET['id']) ? $_GET['id'] : null;
        if (!$email_setting_id) {
            send_error('E-posta ayarı ID gerekli');
        }
        update_email_setting($pdo, $email_setting_id);
        break;
        
    case 'DELETE':
        // E-posta ayarını sil
        $email_setting_id = isset($_GET['id']) ? $_GET['id'] : null;
        if (!$email_setting_id) {
            send_error('E-posta ayarı ID gerekli');
        }
        delete_email_setting($pdo, $email_setting_id);
        break;
        
    default:
        send_error('Desteklenmeyen istek metodu', 405);
}

// E-posta ayarları listesini getir
function get_email_settings($pdo) {
    try {
        $sql = "
            SELECT id, name, email_address, smtp_host, smtp_port, 
                   smtp_user, is_default, is_active, created_at, updated_at
            FROM email_settings
            ORDER BY is_default DESC, name ASC
        ";
        
        $stmt = $pdo->query($sql);
        $email_settings = $stmt->fetchAll();
        
        // SMTP şifrelerini gizle
        foreach ($email_settings as &$setting) {
            unset($setting['smtp_password']);
        }
        
        // Yanıt
        send_response([
            'email_settings' => $email_settings
        ]);
    } catch (PDOException $e) {
        send_error('E-posta ayarları alınırken hata oluştu: ' . $e->getMessage(), 500);
    }
}

// Tek e-posta ayarını getir
function get_email_setting($pdo, $email_setting_id) {
    try {
        $sql = "
            SELECT id, name, email_address, smtp_host, smtp_port,
                   smtp_user, is_default, is_active, created_at, updated_at
            FROM email_settings
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email_setting_id]);
        $email_setting = $stmt->fetch();
        
        if (!$email_setting) {
            send_error('E-posta ayarı bulunamadı', 404);
        }
        
        // SMTP şifresini gizle
        unset($email_setting['smtp_password']);
        
        // Yanıt
        send_response([
            'email_setting' => $email_setting
        ]);
    } catch (PDOException $e) {
        send_error('E-posta ayarı alınırken hata oluştu: ' . $e->getMessage(), 500);
    }
}

// Yeni e-posta ayarı oluştur
function create_email_setting($pdo) {
    // İstek verilerini al
    $data = get_request_data();
    
    // Gerekli alanları kontrol et
    if (!isset($data['name']) || !isset($data['email_address'])) {
        send_error('İsim ve e-posta adresi alanları gereklidir');
    }
    
    // E-posta ayarı ID oluştur
    $email_setting_id = generate_uuid();
    
    // Varsayılan ayar kontrolü
    $is_default = isset($data['is_default']) ? (bool)$data['is_default'] : false;
    
    // Eğer bu ayar varsayılan olarak işaretlendiyse, diğer tüm ayarların varsayılan durumunu kaldır
    if ($is_default) {
        try {
            $sql = "UPDATE email_settings SET is_default = FALSE";
            $pdo->exec($sql);
        } catch (PDOException $e) {
            send_error('Varsayılan e-posta ayarları güncellenirken hata oluştu: ' . $e->getMessage(), 500);
        }
    }
    
    // E-posta ayarı oluştur
    $sql = "
        INSERT INTO email_settings (
            id, name, email_address, smtp_host, smtp_port, 
            smtp_user, smtp_password, is_default, is_active
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?
        )
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $email_setting_id,
            $data['name'],
            $data['email_address'],
            $data['smtp_host'] ?? null,
            $data['smtp_port'] ?? null,
            $data['smtp_user'] ?? null,
            $data['smtp_password'] ?? null,
            $is_default,
            isset($data['is_active']) ? (bool)$data['is_active'] : true
        ]);
        
        // Yeni oluşturulan ayarı getir
        $sql = "
            SELECT id, name, email_address, smtp_host, smtp_port,
                   smtp_user, is_default, is_active, created_at, updated_at
            FROM email_settings
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email_setting_id]);
        $email_setting = $stmt->fetch();
        
        // SMTP şifresini gizle
        unset($email_setting['smtp_password']);
        
        // Yanıt
        send_response(['email_setting' => $email_setting], 201);
    } catch (PDOException $e) {
        send_error('E-posta ayarı oluşturulamadı: ' . $e->getMessage(), 500);
    }
}

// E-posta ayarını güncelle
function update_email_setting($pdo, $email_setting_id) {
    // İstek verilerini al
    $data = get_request_data();
    
    // Güncelleme yapılacak alanların varlığını kontrol et
    if (empty($data)) {
        send_error('Güncellenecek alan bulunamadı');
    }
    
    // E-posta ayarını kontrol et
    $sql = "SELECT * FROM email_settings WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email_setting_id]);
    $email_setting = $stmt->fetch();
    
    if (!$email_setting) {
        send_error('E-posta ayarı bulunamadı', 404);
    }
    
    // Varsayılan ayar kontrolü
    $is_default = isset($data['is_default']) ? (bool)$data['is_default'] : null;
    
    // Eğer bu ayar varsayılan olarak işaretlendiyse, diğer tüm ayarların varsayılan durumunu kaldır
    if ($is_default === true) {
        try {
            $sql = "UPDATE email_settings SET is_default = FALSE";
            $pdo->exec($sql);
        } catch (PDOException $e) {
            send_error('Varsayılan e-posta ayarları güncellenirken hata oluştu: ' . $e->getMessage(), 500);
        }
    }
    
    // Güncelleme alanlarını ve değerlerini hazırla
    $update_fields = [];
    $params = [];
    
    foreach ($data as $field => $value) {
        // Geçerli alanları kontrol et
        if (in_array($field, ['name', 'email_address', 'smtp_host', 'smtp_port', 'smtp_user', 'smtp_password', 'is_default', 'is_active'])) {
            $update_fields[] = "$field = ?";
            $params[] = $value;
        }
    }
    
    if (empty($update_fields)) {
        send_error('Güncellenecek geçerli alan bulunamadı');
    }
    
    // SQL
    $sql = "UPDATE email_settings SET " . implode(', ', $update_fields) . " WHERE id = ?";
    
    // ID parametresini ekle
    $params[] = $email_setting_id;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Güncellenmiş ayarı getir
        $sql = "
            SELECT id, name, email_address, smtp_host, smtp_port,
                   smtp_user, is_default, is_active, created_at, updated_at
            FROM email_settings
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email_setting_id]);
        $updated_setting = $stmt->fetch();
        
        // SMTP şifresini gizle
        unset($updated_setting['smtp_password']);
        
        // Yanıt
        send_response(['email_setting' => $updated_setting]);
    } catch (PDOException $e) {
        send_error('E-posta ayarı güncellenemedi: ' . $e->getMessage(), 500);
    }
}

// E-posta ayarını sil
function delete_email_setting($pdo, $email_setting_id) {
    // E-posta ayarını kontrol et
    $sql = "SELECT * FROM email_settings WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email_setting_id]);
    $email_setting = $stmt->fetch();
    
    if (!$email_setting) {
        send_error('E-posta ayarı bulunamadı', 404);
    }
    
    // Varsayılan e-posta ayarı silinemez
    if ($email_setting['is_default']) {
        send_error('Varsayılan e-posta ayarı silinemez', 400);
    }
    
    try {
        // E-posta ayarını sil
        $sql = "DELETE FROM email_settings WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email_setting_id]);
        
        // Yanıt
        send_response(['message' => 'E-posta ayarı başarıyla silindi']);
    } catch (PDOException $e) {
        send_error('E-posta ayarı silinemedi: ' . $e->getMessage(), 500);
    }
} 