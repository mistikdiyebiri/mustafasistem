-- E-posta ayarları için örnek veri
INSERT INTO email_settings (id, name, email_address, smtp_host, smtp_port, smtp_user, smtp_password, is_default, is_active)
VALUES 
('1', 'MHYS Sistem', 'destek@mhys.com', 'smtp.mhys.com', 587, 'destek@mhys.com', 'password123', TRUE, TRUE)
ON DUPLICATE KEY UPDATE name=name;

-- Eğer henüz hiç kayıt yoksa varsayılan bir ayar daha ekle
INSERT INTO email_settings (id, name, email_address, smtp_host, smtp_port, smtp_user, smtp_password, is_default, is_active)
VALUES 
('2', 'Bilgi Hattı', 'bilgi@mhys.com', 'smtp.mhys.com', 587, 'bilgi@mhys.com', 'password123', FALSE, TRUE)
ON DUPLICATE KEY UPDATE name=name; 