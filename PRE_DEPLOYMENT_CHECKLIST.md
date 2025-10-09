# ✅ Checklist Pre-Deployment - FP Digital Marketing Suite

**Versione:** 0.1.1  
**Data Checklist:** 2025-10-08  
**Security Status:** ✅ Production Ready

> **Usare questa checklist prima di ogni deployment in produzione**

---

## 🚦 Quick Health Check

```bash
# Esegui health check automatico
php tools/health-check.php --verbose

# Output atteso: "✅ System is healthy and ready!"
```

---

## 📋 CHECKLIST COMPLETA

### 1. ⚙️ Ambiente Sistema

- [ ] **PHP Version ≥ 8.1** (raccomandato 8.2+)
  ```bash
  php -v
  # Output atteso: PHP 8.x.x
  ```

- [ ] **Estensioni PHP Obbligatorie**
  ```bash
  php -m | grep -E "(pdo|json|mbstring|curl)"
  # Tutte devono essere presenti
  ```

- [ ] **Estensioni PHP Raccomandate**
  ```bash
  php -m | grep -E "(sodium|openssl)"
  # Almeno una deve essere presente per crittografia
  ```

- [ ] **Sodium Disponibile** (RACCOMANDATO)
  ```bash
  php -r "var_dump(function_exists('sodium_crypto_secretbox'));"
  # Output atteso: bool(true)
  ```

- [ ] **Memory Limit ≥ 128MB**
  ```bash
  php -i | grep memory_limit
  # memory_limit => 128M o superiore
  ```

### 2. 🗄️ Database

- [ ] **Database Creato**
  ```bash
  mysql -u root -p -e "SHOW DATABASES LIKE 'fpdms%';"
  ```

- [ ] **User Database con Privilegi Corretti**
  ```sql
  SHOW GRANTS FOR 'fpdms_user'@'localhost';
  -- Deve avere: SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER
  ```

- [ ] **Collation UTF8MB4**
  ```sql
  SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME 
  FROM information_schema.SCHEMATA 
  WHERE SCHEMA_NAME = 'fpdms_db';
  -- Atteso: utf8mb4, utf8mb4_unicode_ci
  ```

- [ ] **InnoDB Engine Disponibile**
  ```sql
  SHOW ENGINES;
  -- InnoDB deve essere "DEFAULT" o "YES"
  ```

- [ ] **Backup Database Creato**
  ```bash
  mysqldump -u fpdms_user -p fpdms_db > backup_pre_deploy_$(date +%Y%m%d).sql
  ```

### 3. 📁 File System

- [ ] **Directory Storage Esistono**
  ```bash
  ls -la storage/{logs,cache,pdfs,uploads}
  ```

- [ ] **Permessi Directory Corretti**
  ```bash
  # Directory: 755
  find . -type d -exec stat -c "%a %n" {} \; | grep -E "storage|uploads"
  # Deve mostrare 755 o 775
  ```

- [ ] **Directory Storage Scrivibili**
  ```bash
  touch storage/logs/test.log && rm storage/logs/test.log
  touch storage/cache/test.cache && rm storage/cache/test.cache
  touch storage/pdfs/test.pdf && rm storage/pdfs/test.pdf
  # Nessun errore = OK
  ```

- [ ] **Spazio Disco Sufficiente**
  ```bash
  df -h /var/www
  # Almeno 1GB libero
  ```

### 4. 🔐 Sicurezza

- [ ] **HTTPS Configurato**
  ```bash
  curl -I https://yourdomain.com
  # Status: 200 OK, no certificate errors
  ```

- [ ] **SSL Certificate Valido**
  ```bash
  openssl s_client -connect yourdomain.com:443 -servername yourdomain.com < /dev/null 2>/dev/null | openssl x509 -noout -dates
  # Verifica notBefore e notAfter
  ```

- [ ] **File .env NON Accessibile via Web**
  ```bash
  curl https://yourdomain.com/.env
  # Deve ritornare 403 Forbidden o 404 Not Found
  ```

- [ ] **Directory vendor/ NON Accessibile**
  ```bash
  curl https://yourdomain.com/vendor/autoload.php
  # Deve ritornare 403 Forbidden o 404 Not Found
  ```

- [ ] **Error Display Disabilitato**
  ```bash
  php -i | grep display_errors
  # display_errors => Off
  ```

- [ ] **WordPress Salts Generati** (se WordPress)
  ```bash
  grep "AUTH_KEY" wp-config.php
  # Deve contenere chiavi uniche, non 'put your unique phrase here'
  ```

### 5. 📦 Dipendenze

- [ ] **Composer Installato**
  ```bash
  composer --version
  # Composer version 2.x
  ```

- [ ] **Dipendenze Installate**
  ```bash
  composer install --no-dev --optimize-autoloader
  # No errors
  ```

- [ ] **Autoload Ottimizzato**
  ```bash
  composer dump-autoload --optimize --classmap-authoritative
  ```

- [ ] **Vendor Directory Presente**
  ```bash
  ls -la vendor/autoload.php
  # File deve esistere
  ```

### 6. 🧪 Test

- [ ] **Test Suite Eseguiti**
  ```bash
  ./vendor/bin/phpunit tests
  # At least 80% tests passing
  ```

- [ ] **Analisi Statica PHPStan**
  ```bash
  ./vendor/bin/phpstan analyse src --level=5
  # No critical errors
  ```

- [ ] **Health Check Passato**
  ```bash
  php tools/health-check.php
  # Exit code 0, status: healthy
  ```

### 7. ⚙️ Configurazione

- [ ] **.env File Configurato**
  ```bash
  cat .env | grep -E "DB_|APP_"
  # Tutte le variabili configurate
  ```

- [ ] **APP_ENV=production**
  ```bash
  grep "APP_ENV" .env
  # APP_ENV=production
  ```

- [ ] **APP_DEBUG=false**
  ```bash
  grep "APP_DEBUG" .env
  # APP_DEBUG=false
  ```

- [ ] **Database Credentials Corrette**
  ```bash
  php -r "
  \$env = parse_ini_file('.env');
  \$pdo = new PDO(
    'mysql:host='.\$env['DB_HOST'].';dbname='.\$env['DB_NAME'],
    \$env['DB_USER'],
    \$env['DB_PASS']
  );
  echo 'Connection OK' . PHP_EOL;
  "
  ```

### 8. 🔄 Migrazione

- [ ] **Migrations Eseguite**
  ```bash
  php cli.php db:migrate
  # All migrations completed
  ```

- [ ] **Tabelle Create Verificate**
  ```bash
  php cli.php db:status
  # O via MySQL:
  mysql -u fpdms_user -p -e "USE fpdms_db; SHOW TABLES;"
  ```

### 9. 📅 Scheduler/Cron

- [ ] **Cron Job Configurato**
  ```bash
  crontab -l | grep fpdms
  # Deve mostrare entry cron
  ```

- [ ] **Tick Key Presente**
  ```bash
  grep "tick_key" .env
  # O recupera da options table
  ```

- [ ] **Cron Test Eseguito**
  ```bash
  curl "https://yourdomain.com/api/cron?key=YOUR_TICK_KEY"
  # {"status":"ok"} o similar
  ```

### 10. 📧 Notifiche

- [ ] **SMTP Configurato** (se usato)
  ```bash
  grep "SMTP" .env
  # Credenziali SMTP presenti
  ```

- [ ] **Email Test Inviata**
  ```bash
  # Test tramite interfaccia admin
  # Verifica ricezione email
  ```

- [ ] **Webhook Configurati** (se usati)
  ```bash
  # Test webhook endpoints
  curl -X POST https://webhook.example.com/test
  ```

### 11. 🔍 Monitoring

- [ ] **Logs Directory Monitorabile**
  ```bash
  tail -f storage/logs/app.log
  # Deve mostrare logs recenti
  ```

- [ ] **Log Rotation Configurato**
  ```bash
  cat /etc/logrotate.d/fpdms
  # Config presente
  ```

- [ ] **Health Endpoint Risponde**
  ```bash
  curl https://yourdomain.com/api/health
  # {"status":"ok"}
  ```

### 12. 💾 Backup

- [ ] **Backup Strategy Definita**
  - Backup database: Giornaliero
  - Backup files: Settimanale
  - Retention: 30 giorni

- [ ] **Backup Script Testato**
  ```bash
  ./tools/backup.sh
  # Crea backup senza errori
  ```

- [ ] **Backup Restore Testato** (su env test)
  ```bash
  # Test restore su staging/test environment
  ```

### 13. 🌐 Web Server

- [ ] **Virtual Host Configurato**
  ```bash
  # Nginx
  nginx -t
  # Apache
  apache2ctl configtest
  ```

- [ ] **Rewrite Rules Attive**
  ```bash
  # Test friendly URLs
  curl https://yourdomain.com/dashboard
  # Deve funzionare (non 404)
  ```

- [ ] **Security Headers Presenti**
  ```bash
  curl -I https://yourdomain.com | grep -E "X-Frame-Options|X-Content-Type"
  # Headers di sicurezza presenti
  ```

### 14. 🔒 WordPress Specifico (se applicabile)

- [ ] **WordPress Version ≥ 6.0**
  ```bash
  wp core version
  ```

- [ ] **Plugin Attivato Correttamente**
  ```bash
  wp plugin list | grep fp-digital-marketing-suite
  # Status: active
  ```

- [ ] **Permalink Settings Check**
  ```bash
  wp rewrite structure
  # Non deve essere "Plain"
  ```

### 15. 📊 Performance

- [ ] **OPcache Abilitato**
  ```bash
  php -i | grep opcache.enable
  # opcache.enable => On
  ```

- [ ] **Cache Directory Configurata**
  ```bash
  ls -la storage/cache/
  # Directory presente e writable
  ```

- [ ] **Query Optimization Verificata**
  ```bash
  # Enable slow query log temporaneamente
  # Verifica nessuna query > 1s
  ```

---

## 🎯 CHECKLIST RAPIDA (5 Minuti)

Per deployment rapido, verifica almeno questi punti critici:

```bash
# 1. Health Check
php tools/health-check.php

# 2. Database Connection
php -r "
\$pdo = new PDO('mysql:host=localhost;dbname=fpdms_db', 'user', 'pass');
echo 'DB: OK' . PHP_EOL;
"

# 3. Storage Writable
touch storage/logs/test && rm storage/logs/test && echo "Storage: OK"

# 4. HTTPS Active
curl -I https://yourdomain.com 2>&1 | grep "200 OK" && echo "HTTPS: OK"

# 5. Migrations Done
php cli.php db:status | grep "Up" && echo "Migrations: OK"
```

**Se tutti i 5 check passano: ✅ READY TO DEPLOY**

---

## 🚨 Pre-Deployment Security Checklist

### Critical Security Items

- [ ] `.env` file permissions = 600
  ```bash
  chmod 600 .env
  ls -la .env
  # -rw------- 1 user group
  ```

- [ ] No sensitive data in git
  ```bash
  git log --all --pretty=format: --name-only --diff-filter=A | grep -E "\.env$|password|secret"
  # Deve essere vuoto
  ```

- [ ] Firewall rules active
  ```bash
  ufw status
  # Status: active
  ```

- [ ] Database password strong
  ```bash
  # Min 16 caratteri, mix di maiuscole, minuscole, numeri, simboli
  ```

- [ ] WordPress admin strong password (se WP)

- [ ] All API keys encrypted
  ```bash
  # Verifica in database che secrets siano encrypted
  ```

---

## 📝 Documentazione Pre-Deployment

### Informazioni da Raccogliere

- [ ] Server details (IP, hostname, credentials)
- [ ] Database connection details
- [ ] SSL certificate details (issuer, expiry)
- [ ] Cron schedule
- [ ] SMTP/Email provider details
- [ ] Backup schedule
- [ ] Monitoring endpoints
- [ ] Emergency contacts
- [ ] Rollback procedure

### Log del Deployment

```
Deployment Date: ___________
Deployed By: _______________
Version: 0.1.1
Git Commit: ________________
Database Backup: ___________
Files Backup: ______________
Tests Status: PASS/FAIL
Health Check: PASS/FAIL
```

---

## ✅ FIRMA FINALE

```
☐ Tutti i check critici completati
☐ Health check passato (exit code 0)
☐ Backup creato
☐ Team notificato
☐ Monitoring attivo
☐ Rollback plan pronto

Approvato da: ________________
Data: _______________________
Firma: ______________________
```

---

## 🔄 Post-Deployment Verification (entro 1 ora)

```bash
# 1. Application risponde
curl https://yourdomain.com

# 2. Database queries funzionano
# Login admin e verifica dashboard

# 3. Cron eseguito almeno una volta
tail -f storage/logs/scheduler.log

# 4. No errori nei logs
tail -100 storage/logs/error.log

# 5. Monitoring attivo
# Verifica dashboard monitoring
```

---

## 📞 In Caso di Problemi

1. **Controlla logs:** `tail -100 storage/logs/error.log`
2. **Verifica health:** `php tools/health-check.php --verbose`
3. **Database status:** `php cli.php db:status`
4. **Rollback se necessario:** Vedi `DEPLOYMENT_GUIDE.md` sezione Recovery

---

**🎉 Se tutti i check sono ✅ → DEPLOY CON FIDUCIA!**
