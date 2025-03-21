# MHYS Uygulama Kurulum Kılavuzu

Bu belge, MHYS (Müşteri Hizmetleri Yönetim Sistemi) uygulamasının EC2 veya cPanel sunucusuna kurulumu için adımları içermektedir.

## İçindekiler

1. [Dosya Yapısı](#dosya-yapısı)
2. [EC2 Kurulumu](#ec2-kurulumu)
3. [cPanel Kurulumu](#cpanel-kurulumu)
4. [Sorun Giderme](#sorun-giderme)

## Dosya Yapısı

Deploy paketi aşağıdaki dosyaları içerir:

- `index.html`: Ana HTML dosyası
- `static/`: JavaScript, CSS ve medya dosyaları
- `.htaccess`: URL yönlendirme kuralları
- Diğer statik dosyalar (favicon.ico, manifest.json, vs.)

## EC2 Kurulumu

### 1. Nginx Kurulumu

```bash
# Amazon Linux
sudo yum update -y
sudo yum install -y nginx

# veya Ubuntu
# sudo apt update
# sudo apt install -y nginx
```

### 2. Dosyaları Yükleme

```bash
# Web dizinini temizle
sudo rm -rf /usr/share/nginx/html/*

# Dosyaları web dizinine kopyala
sudo cp -r /path/to/deploy-package/* /usr/share/nginx/html/
sudo cp /path/to/deploy-package/.htaccess /usr/share/nginx/html/
```

### 3. Nginx Konfigürasyonu

```bash
sudo nano /etc/nginx/conf.d/default.conf
```

Aşağıdaki içeriği ekleyin:

```nginx
server {
    listen 80;
    server_name _;  # Veya domain adınız (örn: destek.pazmanya.com)

    root /usr/share/nginx/html;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Gzip sıkıştırma
    gzip on;
    gzip_vary on;
    gzip_min_length 10240;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml;
    gzip_disable "MSIE [1-6]\.";
}
```

### 4. Nginx'i Yeniden Başlatma

```bash
sudo systemctl restart nginx
sudo systemctl enable nginx
```

### 5. HTTPS Kurulumu (Önerilen)

```bash
# Certbot yükle
sudo yum install -y certbot python3-certbot-nginx

# SSL sertifikası al
sudo certbot --nginx -d sizindomain.com
```

## cPanel Kurulumu

### 1. cPanel'de Domain/Subdomain Oluşturma

1. cPanel hesabınıza giriş yapın
2. "Domains" veya "Add-on Domains" menüsünde yeni bir domain/subdomain oluşturun
3. Domain için belirtilen root dizini not edin (örn: public_html/destek)

### 2. Dosyaları Yükleme

cPanel'in File Manager aracını kullanarak:

1. File Manager'ı açın
2. Domain için oluşturulan klasöre gidin
3. "Upload" butonuyla tüm deploy-package içeriğini yükleyin
4. .htaccess dosyasının da yüklendiğinden emin olun (gizli dosyaları göster seçeneğini aktif edin)

### 3. Dosya İzinleri

Aşağıdaki izinleri ayarlamanız gerekmektedir:

- HTML, CSS, JS dosyaları: 644
- Dizinler: 755
- .htaccess: 644

## Sorun Giderme

### SPA Yönlendirme Sorunları

Eğer sayfa yenileme yapıldığında 404 hatası alıyorsanız, .htaccess dosyasının doğru yüklendiğinden emin olun. cPanel'de .htaccess dosyalarını görmek için "Show Hidden Files" seçeneğini aktifleştirmeniz gerekebilir.

### Ana Dizinden Farklı Bir Yolda Kurulum

Eğer uygulamayı ana domain yerine bir alt dizinde çalıştırıyorsanız (örn: example.com/mhys), .htaccess dosyasında RewriteBase değerini buna göre ayarlayın:

```apache
RewriteBase /mhys/
RewriteRule . /mhys/index.html [L]
```

### İletişim

Teknik destek için: teknik-destek@pazmanya.com 