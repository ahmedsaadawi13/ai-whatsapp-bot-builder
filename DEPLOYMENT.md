# Deployment Guide - AI WhatsApp Bot Builder

This guide covers deploying the AI WhatsApp Bot Builder to production environments.

## Pre-Deployment Checklist

### 1. Server Requirements

#### Minimum Specifications
- **CPU**: 2 cores
- **RAM**: 2GB
- **Storage**: 20GB SSD
- **OS**: Ubuntu 20.04 LTS or higher, CentOS 7+, Debian 10+

#### Software Requirements
- **PHP**: 7.4 or 8.0+ with extensions:
  - `php-fpm`
  - `php-mysql`
  - `php-mbstring`
  - `php-xml`
  - `php-curl`
  - `php-zip`
  - `php-fileinfo`
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Web Server**: Nginx (recommended) or Apache 2.4+
- **SSL Certificate**: Let's Encrypt or commercial SSL

### 2. Security Hardening

#### PHP Configuration (`php.ini`)
```ini
display_errors = Off
log_errors = On
error_log = /var/log/php/error.log
expose_php = Off
max_execution_time = 30
max_input_time = 60
memory_limit = 128M
post_max_size = 20M
upload_max_filesize = 10M
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1
```

#### MySQL Security
```sql
-- Create dedicated database user
CREATE USER 'whatsapp_bot'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON ai_whatsapp_bot.* TO 'whatsapp_bot'@'localhost';
FLUSH PRIVILEGES;

-- Remove default accounts
DELETE FROM mysql.user WHERE User='';
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
FLUSH PRIVILEGES;
```

#### File Permissions
```bash
# Set ownership
chown -R www-data:www-data /var/www/ai-whatsapp-bot-builder

# Set directory permissions
find /var/www/ai-whatsapp-bot-builder -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/ai-whatsapp-bot-builder -type f -exec chmod 644 {} \;

# Writable directories
chmod -R 775 /var/www/ai-whatsapp-bot-builder/storage
```

## Production Deployment

### Option 1: Nginx + PHP-FPM (Recommended)

#### 1. Install Required Packages

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install Nginx
sudo apt install nginx -y

# Install PHP and extensions
sudo apt install php8.0-fpm php8.0-mysql php8.0-mbstring php8.0-xml php8.0-curl php8.0-zip -y

# Install MySQL
sudo apt install mysql-server -y

# Secure MySQL installation
sudo mysql_secure_installation
```

#### 2. Configure Nginx

Create `/etc/nginx/sites-available/whatsapp-bot`:

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;

    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    root /var/www/ai-whatsapp-bot-builder/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Logging
    access_log /var/log/nginx/whatsapp-bot-access.log;
    error_log /var/log/nginx/whatsapp-bot-error.log;

    # Main location
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to sensitive files
    location ~ /(\.env|database\.sql|\.git) {
        deny all;
        return 404;
    }

    # File upload size
    client_max_body_size 10M;
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/whatsapp-bot /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

#### 3. Configure PHP-FPM

Edit `/etc/php/8.0/fpm/pool.d/www.conf`:

```ini
[www]
user = www-data
group = www-data
listen = /var/run/php/php8.0-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.0-fpm
```

### Option 2: Apache + mod_php

#### 1. Install Apache and PHP

```bash
sudo apt install apache2 libapache2-mod-php8.0 php8.0 php8.0-mysql -y
```

#### 2. Enable Required Modules

```bash
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers
sudo systemctl restart apache2
```

#### 3. Configure Virtual Host

Create `/etc/apache2/sites-available/whatsapp-bot.conf`:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    DocumentRoot /var/www/ai-whatsapp-bot-builder/public

    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem

    <Directory /var/www/ai-whatsapp-bot-builder/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Security headers
        Header set X-Frame-Options "SAMEORIGIN"
        Header set X-Content-Type-Options "nosniff"
        Header set X-XSS-Protection "1; mode=block"
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/whatsapp-bot-error.log
    CustomLog ${APACHE_LOG_DIR}/whatsapp-bot-access.log combined
</VirtualHost>
```

Enable the site:

```bash
sudo a2ensite whatsapp-bot.conf
sudo apache2ctl configtest
sudo systemctl restart apache2
```

## SSL Certificate Setup

### Using Let's Encrypt (Free)

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx -y

# For Nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# For Apache
sudo apt install python3-certbot-apache -y
sudo certbot --apache -d yourdomain.com -d www.yourdomain.com

# Auto-renewal (already configured by Certbot)
sudo certbot renew --dry-run
```

## Database Setup

### 1. Create Database and User

```bash
mysql -u root -p
```

```sql
CREATE DATABASE ai_whatsapp_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'whatsapp_bot'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON ai_whatsapp_bot.* TO 'whatsapp_bot'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 2. Import Schema

```bash
mysql -u whatsapp_bot -p ai_whatsapp_bot < /var/www/ai-whatsapp-bot-builder/database.sql
```

### 3. Optimize MySQL

Edit `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```ini
[mysqld]
max_connections = 151
connect_timeout = 10
wait_timeout = 600
max_allowed_packet = 64M
thread_cache_size = 128
sort_buffer_size = 4M
bulk_insert_buffer_size = 16M
tmp_table_size = 32M
max_heap_table_size = 32M
myisam_recover_options = BACKUP
key_buffer_size = 128M
table_open_cache = 400
myisam_sort_buffer_size = 512M
concurrent_insert = 2
read_buffer_size = 2M
read_rnd_buffer_size = 1M
query_cache_limit = 128K
query_cache_size = 64M
innodb_buffer_pool_size = 256M
innodb_log_file_size = 64M
```

Restart MySQL:

```bash
sudo systemctl restart mysql
```

## Application Configuration

### 1. Environment Configuration

```bash
cd /var/www/ai-whatsapp-bot-builder
cp .env.example .env
nano .env
```

Update `.env`:

```env
APP_URL=https://yourdomain.com
APP_DEBUG=false

DB_HOST=localhost
DB_PORT=3306
DB_NAME=ai_whatsapp_bot
DB_USER=whatsapp_bot
DB_PASS=your_secure_password

TIMEZONE=UTC
```

### 2. Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/ai-whatsapp-bot-builder
sudo chmod -R 755 /var/www/ai-whatsapp-bot-builder
sudo chmod -R 775 /var/www/ai-whatsapp-bot-builder/storage
```

## Monitoring & Maintenance

### 1. Log Rotation

Create `/etc/logrotate.d/whatsapp-bot`:

```
/var/log/nginx/whatsapp-bot-*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data adm
    sharedscripts
    postrotate
        if [ -f /var/run/nginx.pid ]; then
            kill -USR1 `cat /var/run/nginx.pid`
        fi
    endscript
}
```

### 2. Database Backup Script

Create `/usr/local/bin/backup-whatsapp-bot.sh`:

```bash
#!/bin/bash

BACKUP_DIR="/var/backups/whatsapp-bot"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="ai_whatsapp_bot"
DB_USER="whatsapp_bot"
DB_PASS="your_password"

mkdir -p $BACKUP_DIR

# Backup database
mysqldump -u$DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Backup uploads
tar -czf $BACKUP_DIR/uploads_$DATE.tar.gz /var/www/ai-whatsapp-bot-builder/storage/uploads

# Remove backups older than 30 days
find $BACKUP_DIR -type f -mtime +30 -delete

echo "Backup completed: $DATE"
```

Make executable and schedule:

```bash
chmod +x /usr/local/bin/backup-whatsapp-bot.sh

# Add to crontab (daily at 2 AM)
crontab -e
```

Add:

```
0 2 * * * /usr/local/bin/backup-whatsapp-bot.sh >> /var/log/whatsapp-bot-backup.log 2>&1
```

### 3. Monitoring with Monit (Optional)

```bash
sudo apt install monit -y
```

Create `/etc/monit/conf.d/whatsapp-bot`:

```
check process nginx with pidfile /var/run/nginx.pid
    start program = "/usr/sbin/service nginx start"
    stop program = "/usr/sbin/service nginx stop"
    if failed host localhost port 80 protocol http then restart

check process php-fpm with pidfile /var/run/php/php8.0-fpm.pid
    start program = "/usr/sbin/service php8.0-fpm start"
    stop program = "/usr/sbin/service php8.0-fpm stop"

check process mysql with pidfile /var/run/mysqld/mysqld.pid
    start program = "/usr/sbin/service mysql start"
    stop program = "/usr/sbin/service mysql stop"
```

## Performance Optimization

### 1. Enable OPcache

Edit `/etc/php/8.0/fpm/conf.d/10-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
opcache.fast_shutdown=1
```

### 2. Redis Cache (Optional)

```bash
sudo apt install redis-server php-redis -y
```

Update code to use Redis for session storage.

## Troubleshooting

### Check PHP-FPM Status
```bash
sudo systemctl status php8.0-fpm
```

### Check Nginx Configuration
```bash
sudo nginx -t
```

### View Error Logs
```bash
tail -f /var/log/nginx/whatsapp-bot-error.log
tail -f /var/log/php8.0-fpm.log
```

### Test Database Connection
```bash
mysql -u whatsapp_bot -p ai_whatsapp_bot -e "SHOW TABLES;"
```

## Post-Deployment Verification

1. ✓ Access homepage loads correctly
2. ✓ Login with demo credentials works
3. ✓ Dashboard displays properly
4. ✓ Create new bot works
5. ✓ API endpoint responds correctly
6. ✓ File uploads work
7. ✓ Database queries execute
8. ✓ SSL certificate is valid
9. ✓ Security headers present
10. ✓ Error logging configured

---

**Deployment Complete!** Your AI WhatsApp Bot Builder is now live in production.
