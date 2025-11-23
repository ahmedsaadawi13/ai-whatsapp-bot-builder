# AI WhatsApp Bot Builder

A complete multi-tenant SaaS platform for building and managing AI-powered WhatsApp bots with visual flow design, knowledge base integration, and agent inbox functionality.

## Features

### Core Functionality
- **Multi-Tenant Architecture**: Complete tenant isolation with subscription-based access
- **Bot Builder**: Create bots with flow-based conversation design
- **AI Integration**: Knowledge base-powered AI responses with customizable tone
- **Agent Inbox**: Human agents can take over conversations when needed
- **Broadcast Campaigns**: Send targeted messages to contact segments
- **Analytics Dashboard**: Track bot performance and usage metrics
- **Webhook Integration**: Connect with external services
- **Public REST API**: Programmatic access for sending messages

### Multi-Tenancy Features
- Tenant management with custom branding
- User roles: `platform_admin`, `tenant_admin`, `agent`, `developer`
- Subscription plans with usage limits
- Usage tracking and quota enforcement
- Billing and invoice management

### Bot Features
- Visual flow designer with multiple trigger types
- AI-powered responses with knowledge base search
- Conversation variables for context management
- Message templates with variable substitution
- Multiple WhatsApp channels per tenant
- Flow actions: send text, send media, ask questions, call AI, webhooks

## Requirements

- **PHP**: 7.0 or higher (compatible up to PHP 8.x)
- **MySQL**: 5.7 or higher
- **Web Server**: Apache or Nginx
- **PHP Extensions**: PDO, PDO_MySQL, mbstring, fileinfo

## Installation

### 1. Clone or Download

```bash
git clone <repository-url> ai-whatsapp-bot-builder
cd ai-whatsapp-bot-builder
```

### 2. Configure Environment

Copy the example environment file:

```bash
cp .env.example .env
```

Edit `.env` and configure your database:

```env
APP_URL=http://localhost
APP_DEBUG=true

DB_HOST=localhost
DB_PORT=3306
DB_NAME=ai_whatsapp_bot
DB_USER=root
DB_PASS=your_password

TIMEZONE=UTC
```

### 3. Create Database

Create a MySQL database:

```bash
mysql -u root -p
```

```sql
CREATE DATABASE ai_whatsapp_bot CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 4. Import Database Schema

Import the database schema with seed data:

```bash
mysql -u root -p ai_whatsapp_bot < database.sql
```

### 5. Configure Web Server

#### Apache

Create a virtual host configuration:

```apache
<VirtualHost *:80>
    ServerName whatsapp-bot.local
    DocumentRoot /path/to/ai-whatsapp-bot-builder/public

    <Directory /path/to/ai-whatsapp-bot-builder/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/whatsapp-bot-error.log
    CustomLog ${APACHE_LOG_DIR}/whatsapp-bot-access.log combined
</VirtualHost>
```

Enable Apache modules:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx

```nginx
server {
    listen 80;
    server_name whatsapp-bot.local;
    root /path/to/ai-whatsapp-bot-builder/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### 6. Set Permissions

```bash
chmod -R 755 storage/uploads
chmod -R 755 storage
```

### 7. Access the Application

Open your browser and navigate to:

```
http://whatsapp-bot.local
```

Or your configured URL.

## Default Credentials

The database comes with pre-seeded demo accounts:

### Platform Admin
- **Email**: admin@platform.com
- **Password**: password

### Tenant Admin (TechCorp Solutions)
- **Email**: john@techcorp.com
- **Password**: password

### Agent (TechCorp Solutions)
- **Email**: sarah@techcorp.com
- **Password**: password

### Tenant Admin (Marketing Masters Agency)
- **Email**: emma@marketingmasters.com
- **Password**: password

## Public API Usage

The platform provides a REST API for sending messages and receiving webhooks.

### Authentication

All API requests require an API key in the header:

```
X-API-Key: your_api_key_here
```

Generate API keys from: Settings → API Keys

### Send Message

**Endpoint**: `POST /api/v1/send-message`

**Request**:

```json
{
    "bot_id": 1,
    "phone_number": "+15551234567",
    "message": "Hello, I need help with pricing",
    "contact_name": "John Doe"
}
```

**Response**:

```json
{
    "success": true,
    "conversation_id": 123,
    "contact_id": 45,
    "messages": [
        {
            "id": 789,
            "type": "text",
            "content": "Great! I can help you with pricing information..."
        }
    ]
}
```

### Webhook Receiver

**Endpoint**: `POST /api/v1/webhook`

**Request** (simulates incoming WhatsApp message):

```json
{
    "to_phone": "+15551234567",
    "from_phone": "+15559876543",
    "message": "Hello"
}
```

**Response**:

```json
{
    "success": true
}
```

## Architecture

### Folder Structure

```
/app
  /controllers    - Application controllers
  /models         - Database models
  /views          - View templates
  /core           - Core framework classes
/public
  /assets
    /css          - Stylesheets
    /js           - JavaScript files
  index.php       - Application entry point
  .htaccess       - Apache rewrite rules
/config           - Configuration files
/storage
  /uploads        - File uploads
/tests            - Test files
/database.sql     - Database schema
/README.md        - This file
/.env.example     - Example environment file
```

### MVC Pattern

- **Models**: Handle database operations with tenant isolation
- **Views**: HTML templates with minimal PHP logic
- **Controllers**: Business logic and request handling
- **Router**: URL routing and dispatching

### Security Features

- **CSRF Protection**: All forms protected with CSRF tokens
- **Password Hashing**: bcrypt password hashing
- **Prepared Statements**: All database queries use PDO prepared statements
- **Input Validation**: Server-side validation for all user input
- **Tenant Isolation**: Strict data separation between tenants
- **File Upload Validation**: Type and size checking with MIME validation

## Testing

Run the test suite:

```bash
php tests/AuthTest.php
php tests/TenantIsolationTest.php
```

### Test Categories

1. **Authentication Tests**: User login, password verification
2. **Tenant Isolation Tests**: Ensures proper data separation
3. **CRUD Tests**: Basic model operations
4. **Subscription Tests**: Quota enforcement and limits

## Deployment

### Production Checklist

1. **Disable Debug Mode**
   - Set `APP_DEBUG=false` in `.env`

2. **Secure Database Credentials**
   - Use strong passwords
   - Restrict database user permissions

3. **Configure SSL**
   - Use HTTPS in production
   - Redirect HTTP to HTTPS

4. **Set Proper Permissions**
   ```bash
   chmod -R 755 /path/to/project
   chmod -R 775 storage/uploads
   chown -R www-data:www-data /path/to/project
   ```

5. **Optimize Performance**
   - Enable OPcache
   - Configure MySQL query cache
   - Use CDN for static assets

6. **Backup Strategy**
   - Regular database backups
   - Backup uploaded files
   - Store backups off-site

7. **Monitoring**
   - Set up error logging
   - Monitor server resources
   - Track application errors

## Subscription Plans

The platform comes with 3 predefined plans:

### Starter ($29/month)
- 1 Channel
- 2 Bots
- 500 Contacts
- 5,000 Messages/month
- 500MB Storage

### Professional ($99/month)
- 3 Channels
- 10 Bots
- 5,000 Contacts
- 50,000 Messages/month
- 2GB Storage

### Enterprise ($299/month)
- 10 Channels
- 50 Bots
- 50,000 Contacts
- 500,000 Messages/month
- 10GB Storage

## Customization

### Adding New Features

1. Create model in `/app/models/`
2. Create controller in `/app/controllers/`
3. Add routes in `/public/index.php`
4. Create views in `/app/views/`
5. Update database schema

### Extending the Bot Engine

The bot engine is located in `/app/core/BotEngine.php`. You can:

- Add new flow action types
- Integrate with real AI services (OpenAI, Anthropic)
- Add custom trigger conditions
- Implement advanced NLP

### Integrating WhatsApp Providers

Update the Channel model and bot engine to integrate with:

- WhatsApp Business API (Cloud API)
- Twilio
- MessageBird
- Other WhatsApp providers

## Troubleshooting

### Common Issues

**Database connection error**
- Verify database credentials in `.env`
- Ensure MySQL is running
- Check firewall settings

**404 errors on all pages**
- Enable mod_rewrite (Apache)
- Check `.htaccess` file exists
- Verify DocumentRoot points to `/public`

**Permission denied errors**
- Set correct folder permissions
- Ensure web server user owns files

**Blank page / no errors**
- Enable error reporting in PHP
- Check PHP error logs
- Set `APP_DEBUG=true` in development

## Support & Contributing

This is a demonstration project showcasing a complete SaaS architecture in pure PHP.

## License

This project is provided as-is for educational and commercial use.

## Credits

Built with:
- PHP 7.x - 8.x
- MySQL
- Vanilla JavaScript
- CSS3

---

**AI WhatsApp Bot Builder** - Build, Deploy, and Manage WhatsApp Bots at Scale
