-- MHYS Veritabanı Şeması

-- Kullanıcılar tablosu
CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(36) PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  email VARCHAR(100) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  first_name VARCHAR(50) NOT NULL,
  last_name VARCHAR(50) NOT NULL,
  role_id VARCHAR(36) NOT NULL,
  department_id VARCHAR(36),
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Roller tablosu
CREATE TABLE IF NOT EXISTS roles (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description TEXT,
  permissions JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Departmanlar tablosu
CREATE TABLE IF NOT EXISTS departments (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Biletler tablosu
CREATE TABLE IF NOT EXISTS tickets (
  id VARCHAR(36) PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  status ENUM('open', 'inProgress', 'resolved', 'closed', 'waitingCustomer') NOT NULL DEFAULT 'open',
  priority ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
  category ENUM('technical', 'account', 'billing', 'general', 'featureRequest', 'teknik', 'genel') NOT NULL,
  created_by VARCHAR(36) NOT NULL,
  assigned_to VARCHAR(36),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  closed_at TIMESTAMP NULL,
  metadata JSON,
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Bilet yorumları tablosu
CREATE TABLE IF NOT EXISTS ticket_comments (
  id VARCHAR(36) PRIMARY KEY,
  ticket_id VARCHAR(36) NOT NULL,
  text TEXT NOT NULL,
  created_by VARCHAR(36) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_internal BOOLEAN DEFAULT FALSE,
  is_email_sent BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Ekler tablosu
CREATE TABLE IF NOT EXISTS attachments (
  id VARCHAR(36) PRIMARY KEY,
  ticket_id VARCHAR(36) NOT NULL,
  comment_id VARCHAR(36),
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_type VARCHAR(100),
  file_size INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES tickets(id),
  FOREIGN KEY (comment_id) REFERENCES ticket_comments(id)
);

-- Bildirimler tablosu
CREATE TABLE IF NOT EXISTS notifications (
  id VARCHAR(36) PRIMARY KEY,
  user_id VARCHAR(36) NOT NULL,
  title VARCHAR(255) NOT NULL,
  message TEXT NOT NULL,
  is_read BOOLEAN DEFAULT FALSE,
  type ENUM('system', 'ticket', 'assignment') NOT NULL,
  related_id VARCHAR(36),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- E-posta ayarları tablosu
CREATE TABLE IF NOT EXISTS email_settings (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email_address VARCHAR(100) NOT NULL,
  smtp_host VARCHAR(100),
  smtp_port INT,
  smtp_user VARCHAR(100),
  smtp_password VARCHAR(255),
  is_default BOOLEAN DEFAULT FALSE,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Hazır yanıtlar tablosu
CREATE TABLE IF NOT EXISTS quick_replies (
  id VARCHAR(36) PRIMARY KEY,
  title VARCHAR(100) NOT NULL,
  content TEXT NOT NULL,
  category VARCHAR(50),
  created_by VARCHAR(36) NOT NULL,
  is_public BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Örnek veri: Roller
INSERT INTO roles (id, name, description, permissions) VALUES
('1', 'admin', 'Sistem yöneticisi', '{\"permissions\": [\"all\"]}'),
('2', 'employee', 'Destek personeli', '{\"permissions\": [\"ticket_read\", \"ticket_write\", \"ticket_assign\", \"ticket_close\"]}'),
('3', 'customer', 'Müşteri', '{\"permissions\": [\"ticket_create\", \"ticket_read_own\", \"ticket_reply_own\"]}')
ON DUPLICATE KEY UPDATE id=id;

-- Örnek veri: Departmanlar
INSERT INTO departments (id, name, description) VALUES
('1', 'Teknik Destek', 'Teknik destek ve yardım'),
('2', 'Muhasebe', 'Fatura ve ödemeler'),
('3', 'Satış', 'Satış ve pazarlama'),
('4', 'Genel', 'Genel destek')
ON DUPLICATE KEY UPDATE id=id;

-- Örnek veri: Kullanıcılar
INSERT INTO users (id, username, email, password_hash, first_name, last_name, role_id, department_id) VALUES
('1', 'admin', 'admin@mhys.com', '$2y$10$6jANQ2acZGkhplFjm.T9WO.3/vLzk6y9IoUjFif5oyY8HVQlcnQti', 'Admin', 'Kullanıcı', '1', '4'),
('2', 'personel', 'personel@mhys.com', '$2y$10$6jANQ2acZGkhplFjm.T9WO.3/vLzk6y9IoUjFif5oyY8HVQlcnQti', 'Destek', 'Personeli', '2', '1'),
('3', 'musteri', 'musteri@firma.com', '$2y$10$6jANQ2acZGkhplFjm.T9WO.3/vLzk6y9IoUjFif5oyY8HVQlcnQti', 'Test', 'Müşteri', '3', NULL)
ON DUPLICATE KEY UPDATE id=id; 