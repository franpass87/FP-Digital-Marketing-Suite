# FP Digital Marketing Suite - Standalone Application

> Automates marketing performance reporting, anomaly detection, and multi-channel alerts - **now as a standalone PHP application**.

## 🚀 Overview

This is the **standalone version** of FP Digital Marketing Suite, converted from a WordPress plugin to an independent PHP application. It maintains all the original functionality while removing WordPress dependencies.

## ✨ Features

- **Multi-Channel Data Sources**: Google Analytics 4, Google Search Console, Google Ads, Meta Ads, Microsoft Clarity, and CSV imports
- **Automated Reporting**: Schedule and generate PDF reports with customizable HTML templates
- **Anomaly Detection**: Advanced statistical analysis using z-score, EWMA, CUSUM, and seasonal baselines
- **Multi-Channel Notifications**: Email, Slack, Microsoft Teams, Telegram, Webhooks, and Twilio SMS
- **REST API**: Comprehensive API for automation and integrations
- **CLI Commands**: Powerful command-line interface for administrative tasks
- **Queue Management**: Background job processing with health monitoring

## 📋 Requirements

- PHP 8.1 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Composer
- Extensions: PDO, JSON, MBString
- Web server (Apache, Nginx, or PHP built-in server for development)

## 🔧 Installation

### 1. Clone and Install Dependencies

```bash
# Clone the repository
git clone https://github.com/francescopasseri/FP-Digital-Marketing-Suite.git
cd FP-Digital-Marketing-Suite

# Install dependencies
composer install
```

### 2. Environment Configuration

```bash
# Copy the environment file
cp .env.example .env

# Edit .env with your configuration
nano .env
```

**Required environment variables:**

```env
# Database
DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=fpdms
DB_USERNAME=root
DB_PASSWORD=your_password
DB_PREFIX=fpdms_

# Application
APP_KEY=your-secret-key-here
APP_URL=https://yoursite.com
APP_TIMEZONE=UTC

# Email/SMTP
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your@email.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
```

### 3. Database Setup

```bash
# Run migrations
php cli.php db:migrate

# This will create all required tables
```

### 4. Create Admin User

```bash
# TODO: Implement user creation command
php cli.php user:create admin admin@example.com
```

### 5. Configure Web Server

#### Apache (.htaccess)

Create `public/.htaccess`:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

#### Nginx

```nginx
server {
    listen 80;
    server_name yoursite.com;
    root /var/www/fpdms/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

#### Development Server

For development, use PHP's built-in server:

```bash
composer serve
# Or manually:
php -S localhost:8080 -t public
```

### 6. Set Up Cron Jobs

```bash
# Edit crontab
crontab -e

# Add this line (runs every 5 minutes)
*/5 * * * * cd /var/www/fpdms && php cli.php queue:tick >> storage/logs/cron.log 2>&1
```

## 📚 Usage

### Web Interface

Access the web interface at `https://yoursite.com`

- **Dashboard**: Overview of all clients and recent activity
- **Clients**: Manage clients and their configurations
- **Data Sources**: Configure connections to GA4, GSC, Google Ads, etc.
- **Schedules**: Set up automated report generation
- **Templates**: Design HTML templates for PDF reports
- **Anomalies**: Monitor detected anomalies
- **Settings**: Global configuration

### CLI Commands

```bash
# Run a report
php cli.php run --client=1 --from=2024-01-01 --to=2024-01-31

# List queue
php cli.php queue:list

# Scan for anomalies
php cli.php anomalies:scan --client=1

# Evaluate anomalies
php cli.php anomalies:evaluate --client=1

# Send anomaly notifications
php cli.php anomalies:notify --client=1
```

### REST API

#### Authentication

All API endpoints (except `/api/v1/tick`) require authentication via session or API key.

#### Endpoints

```bash
# Force queue tick
curl -X POST https://yoursite.com/api/v1/tick?key=YOUR_TICK_KEY

# Evaluate anomalies
curl -X POST https://yoursite.com/api/v1/anomalies/evaluate \
  -H "Content-Type: application/json" \
  -d '{"client_id": 1, "from": "2024-01-01", "to": "2024-01-31"}'

# Notify anomalies
curl -X POST https://yoursite.com/api/v1/anomalies/notify \
  -H "Content-Type: application/json" \
  -d '{"client_id": 1}'
```

## 🔄 Migration from WordPress Plugin

If you're migrating from the WordPress plugin version:

1. **Export your data** from the WordPress installation
2. **Set up the standalone application** following the installation guide above
3. **Import your data** using the migration script:

```bash
php cli.php migrate:from-wordpress /path/to/wordpress/wp-config.php
```

See [MIGRATION_GUIDE.md](./MIGRATION_GUIDE.md) for detailed instructions.

## 🏗️ Architecture

```
FP Digital Marketing Suite
├── public/              # Web root
│   └── index.php        # Application entry point
├── src/
│   ├── App/             # Application layer (NEW)
│   │   ├── Bootstrap.php
│   │   ├── Router.php
│   │   ├── Commands/    # CLI commands
│   │   ├── Controllers/ # HTTP controllers
│   │   ├── Database/    # Database abstraction
│   │   └── Middleware/  # HTTP middleware
│   ├── Domain/          # Domain layer
│   │   ├── Entities/
│   │   ├── Repos/
│   │   └── Templates/
│   ├── Infra/           # Infrastructure layer
│   │   ├── Config.php   # Configuration management
│   │   ├── Cron.php
│   │   ├── Logger.php
│   │   ├── Mailer.php
│   │   └── Queue.php
│   ├── Services/        # Business logic
│   │   ├── Connectors/
│   │   ├── Reports/
│   │   └── Anomalies/
│   └── Support/         # Utilities
├── storage/             # Storage directory
│   ├── logs/
│   ├── pdfs/
│   └── uploads/
├── cli.php              # CLI entry point
├── composer.json
└── .env                 # Environment configuration
```

## 🔐 Security

- All passwords and sensitive data are encrypted using AES-256
- CSRF protection on all forms
- SQL injection prevention via prepared statements
- XSS protection via output escaping
- Rate limiting on API endpoints

## 🧪 Testing

```bash
# Run tests
composer test

# Run static analysis
composer phpstan

# Run code style checks
composer cs-check

# Fix code style
composer cs-fix
```

## 📝 License

GPLv2 or later

## 👨‍💻 Author

**Francesco Passeri**
- Email: info@francescopasseri.com
- Website: https://francescopasseri.com

## 🐛 Support

- GitHub Issues: https://github.com/francescopasseri/FP-Digital-Marketing-Suite/issues
- Email: info@francescopasseri.com

## 📦 Changelog

See [CHANGELOG.md](./CHANGELOG.md) for release notes.

## 🙏 Acknowledgments

This standalone version was converted from the WordPress plugin while maintaining all core functionality. Special thanks to the open-source community for the excellent libraries used in this project:

- Slim Framework
- Symfony Console
- Monolog
- PHPMailer
- mPDF
- and many more...
