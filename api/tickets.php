<?php
// Tickets API

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

// Veritabanı bağlantısı
$pdo = connect_db();

// İstek tipine göre işlem yap
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // Bilet ID'si verilmişse tek bilet, değilse liste döndür
        $ticket_id = isset($_GET['id']) ? $_GET['id'] : null;
        
        if ($ticket_id) {
            // Tek bilet getir
            get_ticket($pdo, $ticket_id, $user_data);
        } else {
            // Bilet listesini getir
            get_tickets($pdo, $user_data);
        }
        break;
        
    case 'POST':
        // Yeni bilet oluştur
        create_ticket($pdo, $user_data);
        break;
        
    case 'PUT':
        // Bileti güncelle
        $ticket_id = isset($_GET['id']) ? $_GET['id'] : null;
        if (!$ticket_id) {
            send_error('Bilet ID gerekli');
        }
        update_ticket($pdo, $ticket_id, $user_data);
        break;
        
    case 'DELETE':
        // Bileti sil
        $ticket_id = isset($_GET['id']) ? $_GET['id'] : null;
        if (!$ticket_id) {
            send_error('Bilet ID gerekli');
        }
        delete_ticket($pdo, $ticket_id, $user_data);
        break;
        
    default:
        send_error('Desteklenmeyen istek metodu', 405);
}

// Bilet listesini getir
function get_tickets($pdo, $user_data) {
    // Sayfalama parametreleri
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? min(100, max(1, intval($_GET['limit']))) : 10;
    $offset = ($page - 1) * $limit;
    
    // Filtreler
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $priority = isset($_GET['priority']) ? $_GET['priority'] : null;
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    $search = isset($_GET['search']) ? $_GET['search'] : null;
    
    // SQL ve parametreler
    $where_clauses = [];
    $params = [];
    
    // Rol kontrolü - müşteri ise sadece kendi biletlerini görebilir
    if ($user_data['role'] === 'customer') {
        $where_clauses[] = 'created_by = ?';
        $params[] = $user_data['user_id'];
    }
    
    // Filtre koşulları
    if ($status) {
        $where_clauses[] = 'status = ?';
        $params[] = $status;
    }
    
    if ($priority) {
        $where_clauses[] = 'priority = ?';
        $params[] = $priority;
    }
    
    if ($category) {
        $where_clauses[] = 'category = ?';
        $params[] = $category;
    }
    
    if ($search) {
        $where_clauses[] = '(title LIKE ? OR description LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    // WHERE koşulunu oluştur
    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }
    
    // Toplam kayıt sayısını al
    $count_sql = "SELECT COUNT(*) FROM tickets $where_sql";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();
    
    // Biletleri al
    $sql = "
        SELECT t.id, t.title, t.description, t.status, t.priority, t.category,
               t.created_by, t.assigned_to, t.created_at, t.updated_at, t.closed_at,
               u_creator.username AS creator_username,
               u_assignee.username AS assignee_username
        FROM tickets t
        LEFT JOIN users u_creator ON t.created_by = u_creator.id
        LEFT JOIN users u_assignee ON t.assigned_to = u_assignee.id
        $where_sql
        ORDER BY 
            CASE 
                WHEN t.status = 'open' THEN 1
                WHEN t.status = 'inProgress' THEN 2
                WHEN t.status = 'waitingCustomer' THEN 3
                WHEN t.status = 'resolved' THEN 4
                WHEN t.status = 'closed' THEN 5
            END,
            CASE 
                WHEN t.priority = 'urgent' THEN 1
                WHEN t.priority = 'high' THEN 2
                WHEN t.priority = 'medium' THEN 3
                WHEN t.priority = 'low' THEN 4
            END,
            t.updated_at DESC
        LIMIT ? OFFSET ?
    ";
    
    $all_params = array_merge($params, [$limit, $offset]);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($all_params);
    $tickets = $stmt->fetchAll();
    
    // Yanıt
    send_response([
        'tickets' => $tickets,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

// Tek bilet getir
function get_ticket($pdo, $ticket_id, $user_data) {
    // Bileti al
    $sql = "
        SELECT t.id, t.title, t.description, t.status, t.priority, t.category,
               t.created_by, t.assigned_to, t.created_at, t.updated_at, t.closed_at,
               u_creator.username AS creator_username,
               u_assignee.username AS assignee_username
        FROM tickets t
        LEFT JOIN users u_creator ON t.created_by = u_creator.id
        LEFT JOIN users u_assignee ON t.assigned_to = u_assignee.id
        WHERE t.id = ?
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        send_error('Bilet bulunamadı', 404);
    }
    
    // Rol kontrolü - müşteri ise sadece kendi biletlerini görebilir
    if ($user_data['role'] === 'customer' && $ticket['created_by'] !== $user_data['user_id']) {
        send_error('Bu bileti görüntüleme yetkiniz yok', 403);
    }
    
    // Yorumları al
    $sql = "
        SELECT tc.id, tc.ticket_id, tc.text, tc.created_by, tc.created_at, tc.is_internal,
               u.username AS creator_username, u.first_name, u.last_name, r.name AS role
        FROM ticket_comments tc
        JOIN users u ON tc.created_by = u.id
        JOIN roles r ON u.role_id = r.id
        WHERE tc.ticket_id = ?
        ORDER BY tc.created_at ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ticket_id]);
    $comments = $stmt->fetchAll();
    
    // Müşteri ise dahili yorumları filtrele
    if ($user_data['role'] === 'customer') {
        $comments = array_filter($comments, function($comment) {
            return $comment['is_internal'] == 0;
        });
        $comments = array_values($comments); // Dizin anahtarlarını yeniden düzenle
    }
    
    // Yanıt
    send_response([
        'ticket' => $ticket,
        'comments' => $comments
    ]);
}

// Yeni bilet oluştur
function create_ticket($pdo, $user_data) {
    // İstek verilerini al
    $data = get_request_data();
    
    // Gerekli alanları kontrol et
    if (!isset($data['title']) || !isset($data['description']) || !isset($data['category'])) {
        send_error('Başlık, açıklama ve kategori alanları gereklidir');
    }
    
    // Bilet ID oluştur
    $ticket_id = generate_uuid();
    
    // Kategori kontrolü
    $valid_categories = ['technical', 'account', 'billing', 'general', 'featureRequest', 'teknik', 'genel'];
    if (!in_array($data['category'], $valid_categories)) {
        send_error('Geçersiz kategori');
    }
    
    // Öncelik kontrolü
    $priority = isset($data['priority']) ? $data['priority'] : 'medium';
    $valid_priorities = ['low', 'medium', 'high', 'urgent'];
    if (!in_array($priority, $valid_priorities)) {
        send_error('Geçersiz öncelik');
    }
    
    // Bilet oluştur
    $sql = "
        INSERT INTO tickets (
            id, title, description, status, priority, category, 
            created_by, created_at, updated_at
        ) VALUES (
            ?, ?, ?, 'open', ?, ?, ?, NOW(), NOW()
        )
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $ticket_id,
            $data['title'],
            $data['description'],
            $priority,
            $data['category'],
            $user_data['user_id']
        ]);
        
        // Bileti getir
        $sql = "
            SELECT id, title, description, status, priority, category,
                   created_by, assigned_to, created_at, updated_at, closed_at
            FROM tickets
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();
        
        // Yanıt
        send_response(['ticket' => $ticket], 201);
    } catch (PDOException $e) {
        send_error('Bilet oluşturulamadı: ' . $e->getMessage(), 500);
    }
}

// Bileti güncelle
function update_ticket($pdo, $ticket_id, $user_data) {
    // İstek verilerini al
    $data = get_request_data();
    
    // Güncelleme yapılacak alanların varlığını kontrol et
    if (empty($data)) {
        send_error('Güncellenecek alan bulunamadı');
    }
    
    // Bileti kontrol et
    $sql = "SELECT * FROM tickets WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        send_error('Bilet bulunamadı', 404);
    }
    
    // Rol kontrolü - müşteri ise sadece kendi biletlerini güncelleyebilir
    if ($user_data['role'] === 'customer') {
        if ($ticket['created_by'] !== $user_data['user_id']) {
            send_error('Bu bileti güncelleme yetkiniz yok', 403);
        }
        
        // Müşteri sadece başlık ve açıklamayı güncelleyebilir
        $allowed_fields = ['title', 'description'];
        foreach (array_keys($data) as $field) {
            if (!in_array($field, $allowed_fields)) {
                send_error("Müşteri olarak '$field' alanını güncelleyemezsiniz", 403);
            }
        }
    }
    
    // Güncelleme alanlarını ve değerlerini hazırla
    $update_fields = [];
    $params = [];
    
    foreach ($data as $field => $value) {
        // Geçerli alanları kontrol et
        if (in_array($field, ['title', 'description', 'status', 'priority', 'category', 'assigned_to'])) {
            $update_fields[] = "$field = ?";
            $params[] = $value;
        }
    }
    
    // Kapatılmış bilet ise closed_at alanını güncelle
    if (isset($data['status']) && $data['status'] === 'closed' && $ticket['status'] !== 'closed') {
        $update_fields[] = "closed_at = NOW()";
    } else if (isset($data['status']) && $data['status'] !== 'closed' && $ticket['status'] === 'closed') {
        $update_fields[] = "closed_at = NULL";
    }
    
    // updated_at alanını güncelle
    $update_fields[] = "updated_at = NOW()";
    
    // SQL
    $sql = "UPDATE tickets SET " . implode(', ', $update_fields) . " WHERE id = ?";
    
    // ID parametresini ekle
    $params[] = $ticket_id;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Güncellenmiş bileti getir
        $sql = "
            SELECT id, title, description, status, priority, category,
                   created_by, assigned_to, created_at, updated_at, closed_at
            FROM tickets
            WHERE id = ?
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticket_id]);
        $updated_ticket = $stmt->fetch();
        
        // Yanıt
        send_response(['ticket' => $updated_ticket]);
    } catch (PDOException $e) {
        send_error('Bilet güncellenemedi: ' . $e->getMessage(), 500);
    }
}

// Bileti sil
function delete_ticket($pdo, $ticket_id, $user_data) {
    // Bileti kontrol et
    $sql = "SELECT * FROM tickets WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch();
    
    if (!$ticket) {
        send_error('Bilet bulunamadı', 404);
    }
    
    // Sadece admin bilet silebilir
    if ($user_data['role'] !== 'admin') {
        send_error('Bilet silme yetkiniz yok', 403);
    }
    
    try {
        // İlişkili yorumları sil
        $sql = "DELETE FROM ticket_comments WHERE ticket_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticket_id]);
        
        // İlişkili ekleri sil
        $sql = "DELETE FROM attachments WHERE ticket_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticket_id]);
        
        // Bileti sil
        $sql = "DELETE FROM tickets WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$ticket_id]);
        
        // Yanıt
        send_response(['message' => 'Bilet başarıyla silindi']);
    } catch (PDOException $e) {
        send_error('Bilet silinemedi: ' . $e->getMessage(), 500);
    }
} 