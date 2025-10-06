# Scheduler Standalone - Guida Rapida

## ✅ Cosa è stato implementato

Ho creato un sistema di scheduling completo per la versione standalone:

### File Creati

1. **`src/Infra/Scheduler.php`** - Classe scheduler principale
2. **`src/App/ScheduleProvider.php`** - Registrazione task
3. **`src/App/Commands/ScheduleRunCommand.php`** - Comando per eseguire scheduler
4. **`src/App/Commands/ScheduleListCommand.php`** - Comando per listare task
5. **`bin/cron-runner.sh`** - Script wrapper per cron con lock

### Task Pre-configurati

✅ **Queue tick** - Ogni 5 minuti  
✅ **Retention cleanup** - Giornaliero alle 3:00 AM  
✅ **Lock cleanup** - Ogni ora  
✅ **Anomaly scan** - Ogni ora  
✅ **Daily reports** - Alle 8:00 AM  
✅ **Weekly reports** - Lunedì alle 9:00 AM  
✅ **Monthly reports** - Primo del mese alle 10:00 AM  
✅ **Health checks** - Ogni 15 minuti  
✅ **Notification digest** - Alle 7:00 AM  
✅ **Stats aggregation** - Alle 23:00  

## 🚀 Setup Rapido

### 1. Installa Dipendenze

```bash
composer install
# oppure aggiorna
composer update
```

Questo installerà `dragonmantank/cron-expression` necessario per il parsing delle espressioni cron.

### 2. Testa lo Scheduler

```bash
# Lista tutti i task schedulati
php cli.php schedule:list

# Output:
# Scheduled Tasks:
# 
# +-----------------------+--------------+---------------------+------------------+
# | Task                  | Schedule     | Next Run            | Time Until       |
# +-----------------------+--------------+---------------------+------------------+
# | queue:tick            | */5 * * * *  | 2024-01-15 14:35:00 | 2 minute(s)      |
# | retention:cleanup     | 0 3 * * *    | 2024-01-16 03:00:00 | 12 hour(s)       |
# | anomalies:scan        | 0 * * * *    | 2024-01-15 15:00:00 | 27 minute(s)     |
# +-----------------------+--------------+---------------------+------------------+
```

### 3. Esecuzione Manuale (Test)

```bash
# Esegui lo scheduler manualmente
php cli.php schedule:run

# Con output verbose
php cli.php schedule:run -v

# Output:
# Starting scheduler...
# Running scheduled task: queue:tick
# Task completed: queue:tick (duration: 234.5ms)
# Scheduler completed. Executed 1 task(s).
```

### 4. Setup Cron (Produzione)

#### Linux/Unix/macOS

```bash
# Opzione A: Cron semplice (raccomandato per iniziare)
crontab -e

# Aggiungi:
* * * * * cd /var/www/fpdms-standalone && php cli.php schedule:run >> storage/logs/scheduler.log 2>&1
```

```bash
# Opzione B: Con script wrapper (più robusto)
crontab -e

# Aggiungi:
* * * * * /var/www/fpdms-standalone/bin/cron-runner.sh
```

**Nota:** Lo scheduler esegue **ogni minuto** ma decide internamente quali task eseguire in base allo schedule.

#### Verifica Cron

```bash
# Verifica che il cron sia attivo
crontab -l

# Monitora i log
tail -f storage/logs/scheduler.log
```

### 5. Test Completo

```bash
# 1. Testa esecuzione
php cli.php schedule:run -v

# 2. Verifica log
cat storage/logs/scheduler.log

# 3. Aspetta 1 minuto e testa di nuovo
sleep 60
php cli.php schedule:run -v

# 4. Controlla che non ci siano duplicati (grazie al check isDue)
```

## 📝 Aggiungere Task Personalizzati

### Esempio: Backup Database

Modifica `src/App/ScheduleProvider.php`:

```php
public static function register(Scheduler $scheduler): void
{
    // ... task esistenti ...
    
    // Backup database ogni notte alle 2:00 AM
    $scheduler->schedule('backup:database', function() {
        $backupDir = __DIR__ . '/../../storage/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $date = date('Y-m-d_H-i-s');
        $filename = "backup_{$date}.sql";
        
        $command = sprintf(
            'mysqldump -u %s -p%s %s > %s/%s',
            $_ENV['DB_USERNAME'],
            $_ENV['DB_PASSWORD'],
            $_ENV['DB_DATABASE'],
            $backupDir,
            $filename
        );
        
        exec($command);
        
        Logger::info("Database backup created: {$filename}");
    })->dailyAt('02:00');
}
```

### Esempio: Invio Email Settimanale

```php
// Email riepilogo settimanale ogni venerdì alle 17:00
$scheduler->schedule('email:weekly-summary', function() {
    $clients = ClientsRepo::all();
    
    foreach ($clients as $client) {
        if (empty($client->email_to)) {
            continue;
        }
        
        // Genera riepilogo
        $summary = generateWeeklySummary($client);
        
        // Invia email
        Mailer::send(
            $client->email_to,
            'Weekly Summary',
            $summary
        );
    }
})->weeklyOn(5, '17:00'); // 5 = Venerdì
```

### Tutte le Opzioni di Scheduling

```php
// Frequenza
$task->everyMinute();                    // * * * * *
$task->everyFiveMinutes();               // */5 * * * *
$task->everyTenMinutes();                // */10 * * * *
$task->everyFifteenMinutes();            // */15 * * * *
$task->everyThirtyMinutes();             // */30 * * * *
$task->hourly();                         // 0 * * * *
$task->hourlyAt(15);                     // 15 * * * *
$task->daily();                          // 0 0 * * *
$task->dailyAt('13:30');                 // 30 13 * * *
$task->weekly();                         // 0 0 * * 0
$task->weeklyOn(1, '08:00');             // 0 8 * * 1 (lunedì)
$task->monthly();                        // 0 0 1 * *
$task->monthlyOn(15, '12:00');           // 0 12 15 * *

// Custom cron expression
$task->cron('0 */4 * * *');              // Ogni 4 ore
```

## 🔍 Monitoring

### Check Salute Scheduler

```bash
# Lista task e prossime esecuzioni
php cli.php schedule:list

# Check ultimo run
php -r "echo date('Y-m-d H:i:s', filemtime('storage/logs/scheduler.log'));"
```

### Log Monitoring

```bash
# Real-time monitoring
tail -f storage/logs/scheduler.log

# Cerca errori
grep ERROR storage/logs/scheduler.log

# Conta task eseguiti oggi
grep "$(date +%Y-%m-%d)" storage/logs/scheduler.log | grep "Running scheduled task" | wc -l
```

### Health Check Endpoint (Opzionale)

Aggiungi al tuo `Router.php`:

```php
$app->get('/health/scheduler', function($request, $response) {
    // Check ultimo run
    $lastRunFile = __DIR__ . '/../../storage/logs/scheduler.log';
    
    if (!file_exists($lastRunFile)) {
        return $response->withStatus(503)->write(json_encode([
            'status' => 'unhealthy',
            'message' => 'Scheduler never run'
        ]));
    }
    
    $lastModified = filemtime($lastRunFile);
    $minutesAgo = (time() - $lastModified) / 60;
    
    $healthy = $minutesAgo < 5; // Deve essere eseguito entro 5 minuti
    
    return $response->withStatus($healthy ? 200 : 503)->write(json_encode([
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'last_run' => date('Y-m-d H:i:s', $lastModified),
        'minutes_ago' => round($minutesAgo, 1)
    ]));
});
```

## ⚙️ Configurazione Avanzata

### Disabilitare Task Specifici

In `.env`:

```env
# Disabilita backup automatico
ENABLE_DB_BACKUP=false

# Disabilita report automatici
ENABLE_AUTO_REPORTS=false
```

In `ScheduleProvider.php`:

```php
// Backup condizionale
if (getenv('ENABLE_DB_BACKUP') === 'true') {
    $scheduler->schedule('backup:database', function() {
        // ...
    })->dailyAt('02:00');
}
```

### Retry Logic

```php
$scheduler->schedule('important-task', function() {
    $maxRetries = 3;
    $attempt = 0;
    
    while ($attempt < $maxRetries) {
        try {
            // Esegui task
            performImportantTask();
            break; // Successo
        } catch (\Exception $e) {
            $attempt++;
            if ($attempt >= $maxRetries) {
                throw $e; // Riporta errore dopo tentativi
            }
            sleep(5); // Aspetta prima di retry
        }
    }
})->hourly();
```

### Task con Timeout

```php
$scheduler->schedule('long-running-task', function() {
    $timeout = 300; // 5 minuti
    $start = time();
    
    set_time_limit($timeout);
    
    // Esegui task
    while (hasMoreWork()) {
        if (time() - $start > $timeout - 10) {
            Logger::warning('Task timeout approaching, stopping gracefully');
            break;
        }
        
        doWork();
    }
})->hourly();
```

## 🐛 Troubleshooting

### Scheduler non si esegue

```bash
# 1. Verifica crontab
crontab -l

# 2. Verifica log cron di sistema
sudo tail -f /var/log/syslog | grep CRON

# 3. Testa esecuzione manuale
php cli.php schedule:run -v

# 4. Verifica permessi
ls -la storage/logs/
chmod 755 storage/logs
```

### Task eseguiti multiple volte

**Causa:** Cron sovrapposto

**Soluzione:** Usa `bin/cron-runner.sh` che ha lock file:

```bash
crontab -e
# Cambia da:
* * * * * cd /path && php cli.php schedule:run
# A:
* * * * * /path/bin/cron-runner.sh
```

### Task non si eseguono all'orario giusto

```bash
# Verifica timezone server
date
php -r "echo date_default_timezone_get();"

# Imposta timezone in .env
echo "APP_TIMEZONE=Europe/Rome" >> .env

# Imposta timezone in PHP
# In cli.php aggiungi:
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');
```

## 📊 Esempi Completi

### Task con Notifica su Errore

```php
$scheduler->schedule('critical-task', function() {
    try {
        performCriticalOperation();
    } catch (\Exception $e) {
        // Log errore
        Logger::error('Critical task failed', [
            'error' => $e->getMessage()
        ]);
        
        // Notifica admin
        Mailer::send(
            getenv('ADMIN_EMAIL'),
            'Critical Task Failed',
            "Error: " . $e->getMessage()
        );
        
        // Webhook
        if ($webhookUrl = getenv('ERROR_WEBHOOK_URL')) {
            file_get_contents($webhookUrl . '?error=' . urlencode($e->getMessage()));
        }
    }
})->hourly();
```

### Task Condizionale (solo giorni lavorativi)

```php
$scheduler->schedule('business-hours-task', function() {
    $now = new \DateTime();
    $dayOfWeek = (int) $now->format('N'); // 1=lunedì, 7=domenica
    
    // Solo lun-ven
    if ($dayOfWeek >= 6) {
        return;
    }
    
    // Esegui task
    performBusinessTask();
})->hourly();
```

## ✅ Checklist Setup Produzione

- [ ] `composer install` eseguito
- [ ] `php cli.php schedule:list` funziona
- [ ] Test manuale: `php cli.php schedule:run -v`
- [ ] Crontab configurato
- [ ] Log directory writable: `chmod 755 storage/logs`
- [ ] Monitoring attivo: `tail -f storage/logs/scheduler.log`
- [ ] Health check endpoint funzionante (opzionale)
- [ ] Notifiche errori configurate
- [ ] Backup scheduler logs impostato
- [ ] Documentazione team aggiornata

## 🎯 Conclusione

Ora hai uno scheduler completo che:

✅ Esegue task in background automaticamente  
✅ Gestisce lock per evitare sovrapposizioni  
✅ Supporta cron expressions standard  
✅ Ha logging completo  
✅ È facile da estendere con nuovi task  
✅ Funziona sia su Linux che Windows  

**Prossimi passi:**
1. Installa dipendenze: `composer install`
2. Testa: `php cli.php schedule:list`
3. Configura cron: `crontab -e`
4. Monitora: `tail -f storage/logs/scheduler.log`

Buon scheduling! 🚀
