# 🚀 Guida Deployment - FP Digital Marketing Suite

**Versione:** 0.1.1  
**Data:** 2025-10-08  
**Status Sicurezza:** ✅ Production Ready

---

## 📋 Indice

1. [Pre-requisiti](#pre-requisiti)
2. [Checklist Pre-Deployment](#checklist-pre-deployment)
3. [Deployment WordPress Plugin](#deployment-wordpress-plugin)
4. [Deployment Standalone](#deployment-standalone)
5. [Deployment Docker](#deployment-docker)
6. [Configurazione Sicurezza](#configurazione-sicurezza)
7. [Monitoring e Logging](#monitoring-e-logging)
8. [Backup e Recovery](#backup-e-recovery)
9. [Troubleshooting](#troubleshooting)

---

## Pre-requisiti

### Sistema
- **PHP:** 8.1+ (raccomandato 8.2+)
- **Database:** MySQL 5.7+ o MariaDB 10.3+
- **Web Server:** Apache 2.4+ o Nginx 1.18+
- **Memoria:** Min 128MB, raccomandato 256MB+
- **Disk Space:** Min 50MB per plugin + spazio per PDF/logs

### Estensioni PHP Richieste
```bash
# Obbligatorie
php -m | grep -E "(pdo|json|mbstring|curl)"

# Raccomandate per sicurezza
php -m | grep -E "(sodium|openssl)"
```

### Verifica Ambiente
```bash
# Test completo requisiti
php -r "
echo 'PHP Version: ' . PHP_VERSION . PHP_EOL;
echo 'PDO: ' . (extension_loaded('pdo') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'JSON: ' . (extension_loaded('json') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'mbstring: ' . (extension_loaded('mbstring') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'cURL: ' . (extension_loaded('curl') ? 'OK' : 'MISSING') . PHP_EOL;
echo 'Sodium: ' . (function_exists('sodium_crypto_secretbox') ? 'OK' : 'NO') . PHP_EOL;
echo 'OpenSSL: ' . (function_exists('openssl_encrypt') ? 'OK' : 'NO') . PHP_EOL;
"
```

**Output Atteso:**
```
PHP Version: 8.2.x
PDO: OK
JSON: OK
mbstring: OK
cURL: OK
Sodium: OK      ← Importante per sicurezza!
OpenSSL: OK     ← Fallback per sicurezza
```

---

## Checklist Pre-Deployment

### ✅ Sicurezza
- [ ] PHP 8.1+ installato
- [ ] Estensione Sodium attiva (raccomandato)
- [ ] SSL/TLS configurato per HTTPS
- [ ] Firewall configurato
- [ ] Database user con privilegi minimi
- [ ] `.env` con credenziali sicure
- [ ] WordPress salts generati (se WordPress)

### ✅ Database
- [ ] Database creato
- [ ] User database con privilegi appropriati
- [ ] Backup database pre-migration
- [ ] Collation: `utf8mb4_unicode_ci`
- [ ] InnoDB engine (per transactions)

### ✅ File System
- [ ] Directory writable: `storage/logs`, `storage/cache`, `storage/pdfs`
- [ ] Permessi corretti (755 directory, 644 files)
- [ ] `.htaccess` o nginx config appropriati
- [ ] Logs rotation configurato

### ✅ Test
- [ ] Test suite eseguiti (80%+ pass)
- [ ] Test connessioni data sources
- [ ] Test generazione PDF
- [ ] Test notifiche email/webhook
- [ ] Test scheduler/cron

---

## Deployment WordPress Plugin

### 1. Upload File

**Metodo A: Via WP Admin**
```bash
# Crea zip
zip -r fp-digital-marketing-suite.zip . \
  -x "*.git*" -x "node_modules/*" -x "vendor/*" -x "tests/*"

# Upload via WordPress Admin > Plugins > Add New > Upload
```

**Metodo B: Via FTP/SSH**
```bash
# Upload directory in
/wp-content/plugins/fp-digital-marketing-suite/

# Imposta permessi
chmod 755 /wp-content/plugins/fp-digital-marketing-suite
chmod 644 /wp-content/plugins/fp-digital-marketing-suite/*.php
```

### 2. Installa Dipendenze

```bash
cd /wp-content/plugins/fp-digital-marketing-suite
composer install --no-dev --optimize-autoloader
```

### 3. Attiva Plugin

```bash
# Via WP-CLI
wp plugin activate fp-digital-marketing-suite

# O via WordPress Admin > Plugins
```

### 4. Configurazione Iniziale

1. **Genera Chiavi di Sicurezza**
   - Vai in Settings > FP Digital Marketing
   - Verifica che "Encryption Available: Yes"
   - Copia QA Key per integrations

2. **Configura Email SMTP** (opzionale)
   ```
   SMTP Host: smtp.example.com
   SMTP Port: 587
   SMTP Security: TLS
   SMTP User/Password: [cifrati automaticamente]
   ```

3. **Setup Cron Jobs**
   ```bash
   # Aggiungi a crontab
   */5 * * * * curl -s "https://yoursite.com/wp-admin/admin-ajax.php?action=fpdms_cron&key=YOUR_TICK_KEY"
   ```

---

## Deployment Standalone

### 1. Clone/Upload Repository

```bash
# Via Git
git clone https://github.com/yourusername/fp-digital-marketing-suite.git
cd fp-digital-marketing-suite

# O upload via FTP/rsync
rsync -avz --exclude='.git' ./ user@server:/var/www/fpdms/
```

### 2. Installa Dipendenze

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Configura Ambiente

```bash
# Copia environment file
cp env.example .env

# Modifica configurazioni
nano .env
```

**Configurazioni .env:**
```env
# Database
DB_HOST=localhost
DB_NAME=fpdms_db
DB_USER=fpdms_user
DB_PASS=secure_password_here
DB_PREFIX=fpdms_

# Security
APP_KEY=random-32-char-key-here
APP_ENV=production
APP_DEBUG=false

# WordPress (per compatibilità)
WORDPRESS_DB_PREFIX=wp_
WORDPRESS_AUTH_KEY=random-key-here
WORDPRESS_SECURE_AUTH_KEY=random-key-here
# ... altri salts
```

**Genera Chiavi Sicure:**
```bash
# APP_KEY
php -r "echo bin2hex(random_bytes(16)) . PHP_EOL;"

# WordPress salts
curl https://api.wordpress.org/secret-key/1.1/salt/
```

### 4. Migrazione Database

```bash
# Esegui migrations
php cli.php db:migrate

# Verifica tabelle create
php cli.php db:status
```

### 5. Configura Web Server

**Nginx:**
```nginx
server {
    listen 80;
    server_name fpdms.example.com;
    root /var/www/fpdms/public;
    index index.php;

    # SSL redirect
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name fpdms.example.com;
    root /var/www/fpdms/public;
    index index.php;

    # SSL certificates
    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
    }

    location ~ ^/(vendor|storage|tests)/ {
        deny all;
    }
}
```

**Apache (.htaccess):**
```apache
# Già presente nel repository
# Verifica che mod_rewrite sia attivo
a2enmod rewrite
systemctl restart apache2
```

### 6. Imposta Permessi

```bash
# Owner files
chown -R www-data:www-data /var/www/fpdms

# Directories
find /var/www/fpdms -type d -exec chmod 755 {} \;

# Files
find /var/www/fpdms -type f -exec chmod 644 {} \;

# Writable directories
chmod 775 /var/www/fpdms/storage/{logs,cache,pdfs,uploads}
```

### 7. Setup Scheduler

```bash
# Aggiungi a crontab (crontab -e)
*/5 * * * * curl -s "https://fpdms.example.com/api/cron?key=YOUR_TICK_KEY" > /dev/null 2>&1

# O usa lo scheduler integrato
*/5 * * * * cd /var/www/fpdms && php cli.php schedule:run >> /var/log/fpdms-cron.log 2>&1
```

---

## Deployment Docker

### 1. Build Image

```bash
# Usa Dockerfile esistente
docker build -t fpdms:latest .
```

### 2. Docker Compose

```yaml
version: '3.8'

services:
  app:
    image: fpdms:latest
    container_name: fpdms-app
    restart: unless-stopped
    ports:
      - "8080:80"
    environment:
      - DB_HOST=db
      - DB_NAME=fpdms
      - DB_USER=fpdms
      - DB_PASS=secure_password
      - APP_ENV=production
    volumes:
      - ./storage:/var/www/html/storage
      - ./uploads:/var/www/html/uploads
    depends_on:
      - db
    networks:
      - fpdms-network

  db:
    image: mariadb:10.11
    container_name: fpdms-db
    restart: unless-stopped
    environment:
      - MYSQL_ROOT_PASSWORD=root_password
      - MYSQL_DATABASE=fpdms
      - MYSQL_USER=fpdms
      - MYSQL_PASSWORD=secure_password
    volumes:
      - db-data:/var/lib/mysql
    networks:
      - fpdms-network

  redis:
    image: redis:7-alpine
    container_name: fpdms-redis
    restart: unless-stopped
    networks:
      - fpdms-network

volumes:
  db-data:

networks:
  fpdms-network:
    driver: bridge
```

### 3. Deploy

```bash
# Start containers
docker-compose up -d

# Run migrations
docker-compose exec app php cli.php db:migrate

# Check logs
docker-compose logs -f app
```

---

## Configurazione Sicurezza

### 1. HTTPS/SSL
```bash
# Usa Let's Encrypt
certbot --nginx -d fpdms.example.com

# O carica certificati manualmente
```

### 2. Firewall Rules
```bash
# UFW
ufw allow 22/tcp   # SSH
ufw allow 80/tcp   # HTTP
ufw allow 443/tcp  # HTTPS
ufw enable

# iptables
iptables -A INPUT -p tcp --dport 80 -j ACCEPT
iptables -A INPUT -p tcp --dport 443 -j ACCEPT
```

### 3. Database Security
```sql
-- Crea user con privilegi minimi
CREATE USER 'fpdms_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER 
  ON fpdms_db.* TO 'fpdms_user'@'localhost';
FLUSH PRIVILEGES;
```

### 4. File Permissions
```bash
# Production permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 775 storage/{logs,cache,pdfs}
```

### 5. Rate Limiting (Nginx)
```nginx
limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;

location /api/ {
    limit_req zone=api burst=20 nodelay;
}
```

---

## Monitoring e Logging

### 1. Logs Location
```
storage/logs/
├── app.log         # Application logs
├── error.log       # Error logs
├── access.log      # Access logs
└── scheduler.log   # Cron/scheduler logs
```

### 2. Log Rotation
```bash
# /etc/logrotate.d/fpdms
/var/www/fpdms/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0644 www-data www-data
    sharedscripts
    postrotate
        systemctl reload php8.2-fpm > /dev/null
    endscript
}
```

### 3. Monitoring Health
```bash
# Health check endpoint
curl https://fpdms.example.com/api/health

# Expected response:
{
  "status": "ok",
  "timestamp": "2025-10-08T12:00:00Z",
  "database": "connected",
  "cache": "available"
}
```

### 4. Alert Configuration
```bash
# Setup webhook per anomalie
wp option update fpdms_global_settings '{"error_webhook_url":"https://alerts.example.com/webhook"}'
```

---

## Backup e Recovery

### 1. Backup Database
```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u fpdms_user -p fpdms_db | gzip > /backups/fpdms_db_$DATE.sql.gz

# Mantieni ultimi 7 giorni
find /backups -name "fpdms_db_*.sql.gz" -mtime +7 -delete
```

### 2. Backup Files
```bash
# Backup storage directory
tar -czf /backups/fpdms_storage_$(date +%Y%m%d).tar.gz \
  /var/www/fpdms/storage

# Backup configuration
cp /var/www/fpdms/.env /backups/fpdms_env_$(date +%Y%m%d).bak
```

### 3. Recovery Procedure
```bash
# 1. Restore database
gunzip < /backups/fpdms_db_20251008.sql.gz | mysql -u fpdms_user -p fpdms_db

# 2. Restore files
tar -xzf /backups/fpdms_storage_20251008.tar.gz -C /

# 3. Restore config
cp /backups/fpdms_env_20251008.bak /var/www/fpdms/.env

# 4. Clear cache
php cli.php cache:clear
```

---

## Troubleshooting

### Problema: Plugin non si attiva
```bash
# Verifica errori PHP
tail -f /var/log/nginx/error.log
# O
tail -f /var/log/apache2/error.log

# Verifica permessi
ls -la /wp-content/plugins/fp-digital-marketing-suite

# Verifica dipendenze
composer install --no-dev
```

### Problema: Database connection error
```bash
# Test connessione MySQL
mysql -h localhost -u fpdms_user -p fpdms_db

# Verifica credenziali in .env
cat .env | grep DB_

# Test da PHP
php -r "
\$pdo = new PDO('mysql:host=localhost;dbname=fpdms_db', 'fpdms_user', 'password');
echo 'Connected!' . PHP_EOL;
"
```

### Problema: Errori crittografia
```bash
# Verifica Sodium
php -r "var_dump(function_exists('sodium_crypto_secretbox'));"

# Se false, installa libsodium
apt-get install php8.2-sodium
# O
yum install php-sodium

# Restart PHP-FPM
systemctl restart php8.2-fpm
```

### Problema: Scheduler non funziona
```bash
# Verifica cron è attivo
systemctl status cron

# Test manuale
curl "https://fpdms.example.com/api/cron?key=YOUR_TICK_KEY"

# Verifica logs
tail -f storage/logs/scheduler.log
```

### Problema: PDF non genera
```bash
# Verifica directory writable
ls -la storage/pdfs/
chmod 775 storage/pdfs/

# Verifica memoria PHP
php -i | grep memory_limit

# Aumenta se necessario in php.ini
memory_limit = 256M
```

---

## 📞 Supporto

### Logs da Includere
Quando richiedi supporto, includi:
1. PHP version: `php -v`
2. Error logs: `tail -100 storage/logs/error.log`
3. PHP-FPM logs: `tail -100 /var/log/php8.2-fpm.log`
4. Environment info: `php -i` (rimuovi dati sensibili)

### Risorse
- GitHub Issues: https://github.com/franpass87/FP-Digital-Marketing-Suite/issues
- Documentation: `docs/` directory
- Security: Vedi `SECURITY_AUDIT_FINAL_2025-10-08.md`

---

**✅ Deployment completato con successo quando:**
- [ ] Application risponde su HTTPS
- [ ] Database connesso e migrations eseguite
- [ ] Test endpoint `/api/health` ritorna `{"status":"ok"}`
- [ ] Scheduler funziona (verifica logs dopo 5 minuti)
- [ ] PDF genera correttamente
- [ ] Email/notifiche inviate

**🎉 Il sistema è pronto per la produzione!**
