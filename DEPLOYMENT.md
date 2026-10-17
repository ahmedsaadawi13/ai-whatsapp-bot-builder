# Deployment Guide - AI WhatsApp Bot Builder

## 1. Server requirements

- PHP 7.0 – 8.x (8.1+ recommended) with: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `json`, `curl` (needed for webhooks to hostnames), and `zip` (optional, DOCX text extraction). PHP-FPM is recommended because it lets webhook delivery run after the response is sent (`fastcgi_finish_request`).
- MySQL 5.7+ or MariaDB 10.3+ (InnoDB, utf8mb4).
- Nginx or Apache 2.4 with `mod_rewrite`.
- HTTPS. The session cookie is marked `Secure` automatically on HTTPS.

Ubuntu example:

```bash
sudo apt install nginx mariadb-server php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-curl php8.3-zip php8.3-xml
```

## 2. Code and configuration

```bash
sudo mkdir -p /var/www && cd /var/www
sudo git clone <repo-url> ai-whatsapp-bot-builder
cd ai-whatsapp-bot-builder
sudo cp .env.example .env
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"   # paste the output as APP_KEY in .env
sudo nano .env    # APP_URL=https://bots.example.com, APP_DEBUG=false, DB_*, APP_KEY, TRUSTED_PROXIES
```

- `APP_KEY` encrypts channel credentials. Back it up. If you change it, stored credentials can no longer be decrypted.
- Set `TRUSTED_PROXIES` only if you sit behind a load balancer or CDN (its IPs). Otherwise `X-Forwarded-For` is ignored.

## 3. Database

```bash
sudo mysql
```
```sql
CREATE USER 'awb'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES ON ai_whatsapp_bot.* TO 'awb'@'localhost';
FLUSH PRIVILEGES;
```
```bash
sudo mysql < database.sql     # creates ai_whatsapp_bot, the tables and the demo data
```

`database.sql` **drops and re-creates all tables**. Run it only on a fresh install. For production you may want to delete the demo tenants after importing (log in as `admin@platform.com`, go to Tenants, then Delete) and change the platform admin password at once:

```bash
php -r "echo password_hash('NEW-PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
mysql ai_whatsapp_bot -e "UPDATE users SET password='<hash>' WHERE email='admin@platform.com'"
```

## 4. File permissions

Only `storage/` needs to be writable (uploads + mail log). It is outside the web root, and files are served through `/files` with access checks.

```bash
sudo chown -R root:root /var/www/ai-whatsapp-bot-builder
sudo chown -R www-data:www-data /var/www/ai-whatsapp-bot-builder/storage
sudo find /var/www/ai-whatsapp-bot-builder -type d -exec chmod 755 {} \;
sudo find /var/www/ai-whatsapp-bot-builder -type f -exec chmod 644 {} \;
sudo chmod 640 /var/www/ai-whatsapp-bot-builder/.env && sudo chgrp www-data /var/www/ai-whatsapp-bot-builder/.env
```

## 5. Web server

The document root must be **`public/`**. Never point it at the project root.

### Nginx

```nginx
server {
    listen 80;
    server_name bots.example.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name bots.example.com;
    root /var/www/ai-whatsapp-bot-builder/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/bots.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/bots.example.com/privkey.pem;
    add_header Strict-Transport-Security "max-age=31536000" always;

    client_max_body_size 12M;   # uploads are limited to 10 MB by the app

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\. { deny all; }

    location ~* \.(css|js|png|jpg|svg|woff2?)$ {
        expires 7d;
        add_header Cache-Control "public";
    }
}
```

### Apache

```apache
<VirtualHost *:443>
    ServerName bots.example.com
    DocumentRoot /var/www/ai-whatsapp-bot-builder/public

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/bots.example.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/bots.example.com/privkey.pem

    <Directory /var/www/ai-whatsapp-bot-builder/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

```bash
sudo a2enmod rewrite ssl headers && sudo systemctl reload apache2
```

`public/.htaccess` routes every request to `index.php`, and `storage/.htaccess` denies direct access as a second safety net.

TLS certificates: `sudo certbot --nginx -d bots.example.com` (or `--apache`).

## 6. PHP settings (production)

```ini
display_errors = Off
log_errors = On
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 60
session.cookie_httponly = 1
opcache.enable = 1
```

Large "Send now" broadcasts run inside the web request. For very large segments, schedule them instead so the cron worker sends them.

## 7. Cron

```cron
* * * * *  www-data  php /var/www/ai-whatsapp-bot-builder/cron/run.php all   >> /var/log/awb-cron.log 2>&1
0 7 * * *  www-data  php /var/www/ai-whatsapp-bot-builder/cron/run.php daily >> /var/log/awb-cron.log 2>&1
```

`all` sends due scheduled broadcasts (and resumes crashed ones), retries failed webhooks, refreshes usage snapshots, and issues renewal invoices 3 days before a subscription expires. Tenants keep working for 7 days after expiry, then the API returns 429 until the renewal invoice is paid.

## 8. Connecting a WhatsApp provider (simulated)

For each channel, the Channels page shows the webhook URL (`{APP_URL}/api/v1/webhook`) and the channel's verify token. Configure the provider (or your own adapter) to:

1. Verify: `GET /api/v1/webhook?hub.mode=subscribe&hub.verify_token=<token>&hub.challenge=<n>`
2. Deliver incoming messages: `POST /api/v1/webhook` with header `X-Webhook-Token: <token>` and JSON `{"to_phone","from_phone","message"}`.

## 9. Verify the installation

```bash
curl -s https://bots.example.com/login | grep -c csrf_token        # 1
curl -s -o /dev/null -w "%{http_code}\n" https://bots.example.com/.env   # 404
curl -s -X POST https://bots.example.com/api/v1/send-message -H "Content-Type: application/json" -d '{}'   # 401 JSON
TEST_DB_NAME=awb_ci_test php tests/run.php                           # on a staging box with a test DB user
```

## 10. Backups

```bash
mysqldump --single-transaction ai_whatsapp_bot | gzip > /backups/awb-$(date +%F).sql.gz
tar czf /backups/awb-uploads-$(date +%F).tgz -C /var/www/ai-whatsapp-bot-builder storage/uploads
```

Keep `.env` (especially `APP_KEY`) in your secrets manager.
