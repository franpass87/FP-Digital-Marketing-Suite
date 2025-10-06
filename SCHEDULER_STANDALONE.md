# Scheduler per Versione Standalone

## 🎯 Il Problema

WordPress usa **WP-Cron** che:
- Si attiva ad ogni visita della pagina
- È gestito automaticamente da WordPress
- Non è affidabile al 100%

Nella versione standalone dobbiamo implementare un **vero scheduler**.

## 💡 Soluzioni Disponibili

### Opzione 1: System Cron (Linux/Unix) ⭐ RACCOMANDATO

**Vantaggi:**
- Nativo, affidabile, zero dipendenze
- Funziona anche con traffico zero
- Garantisce l'esecuzione agli orari esatti

**Implementazione:**

#### 1.1 Setup Base

```bash
# Modifica crontab
crontab -e

# Aggiungi queste righe:
# Esegui queue tick ogni 5 minuti
*/5 * * * * cd /var/www/fpdms-standalone && php cli.php queue:tick >> storage/logs/cron.log 2>&1

# Cleanup giornaliero alle 3:00 AM
0 3 * * * cd /var/www/fpdms-standalone && php cli.php maintenance:cleanup >> storage/logs/maintenance.log 2>&1

# Check anomalie ogni ora
0 * * * * cd /var/www/fpdms-standalone && php cli.php anomalies:check-all >> storage/logs/anomalies.log 2>&1
```

#### 1.2 Script Wrapper (più robusto)

```bash
# Crea: bin/cron-runner.sh
#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
APP_DIR="$(dirname "$SCRIPT_DIR")"
LOG_DIR="$APP_DIR/storage/logs"

# Assicurati che log directory esista
mkdir -p "$LOG_DIR"

# Lock file per evitare sovrapposizioni
LOCK_FILE="/tmp/fpdms-cron.lock"

# Funzione di cleanup
cleanup() {
    rm -f "$LOCK_FILE"
}
trap cleanup EXIT

# Check se già in esecuzione
if [ -f "$LOCK_FILE" ]; then
    # Check se il processo è davvero attivo
    PID=$(cat "$LOCK_FILE")
    if ps -p "$PID" > /dev/null 2>&1; then
        echo "$(date): Cron già in esecuzione (PID: $PID), skip" >> "$LOG_DIR/cron.log"
        exit 0
    fi
fi

# Crea lock file
echo $$ > "$LOCK_FILE"

# Esegui comando
cd "$APP_DIR"
php cli.php queue:tick >> "$LOG_DIR/cron.log" 2>&1

# Cleanup
cleanup
```

```bash
# Rendi eseguibile
chmod +x bin/cron-runner.sh

# Crontab
*/5 * * * * /var/www/fpdms-standalone/bin/cron-runner.sh
```

### Opzione 2: PHP Scheduler Interno ⭐ FLESSIBILE

Implementare uno scheduler interno simile a Laravel Task Scheduler.

#### 2.1 Crea Scheduler Class

```php
// src/Infra/Scheduler.php
<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use Cron\CronExpression;

class Scheduler
{
    private array $tasks = [];
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Registra un task
     */
    public function schedule(string $name, callable $callback): Task
    {
        $task = new Task($name, $callback);
        $this->tasks[] = $task;
        return $task;
    }

    /**
     * Esegui tutti i task dovuti
     */
    public function run(): void
    {
        $now = new \DateTime();
        
        foreach ($this->tasks as $task) {
            if ($task->isDue($now)) {
                try {
                    $this->logger->info("Running task: {$task->getName()}");
                    $task->run();
                    $this->logger->info("Task completed: {$task->getName()}");
                } catch (\Exception $e) {
                    $this->logger->error("Task failed: {$task->getName()}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }
    }

    /**
     * Lista tutti i task
     */
    public function listTasks(): array
    {
        return array_map(fn($task) => [
            'name' => $task->getName(),
            'expression' => $task->getExpression(),
            'next_run' => $task->getNextRunDate()?->format('Y-m-d H:i:s'),
        ], $this->tasks);
    }
}

class Task
{
    private string $name;
    private $callback;
    private ?string $cronExpression = null;
    private ?CronExpression $cron = null;

    public function __construct(string $name, callable $callback)
    {
        $this->name = $name;
        $this->callback = $callback;
    }

    /**
     * Ogni 5 minuti
     */
    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    /**
     * Ogni ora
     */
    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    /**
     * Giornaliero ad orario specifico
     */
    public function dailyAt(string $time): self
    {
        [$hour, $minute] = explode(':', $time);
        return $this->cron("$minute $hour * * *");
    }

    /**
     * Settimanale
     */
    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    /**
     * Custom cron expression
     */
    public function cron(string $expression): self
    {
        $this->cronExpression = $expression;
        $this->cron = new CronExpression($expression);
        return $this;
    }

    /**
     * Check se il task deve essere eseguito ora
     */
    public function isDue(\DateTime $now): bool
    {
        if ($this->cron === null) {
            return false;
        }

        return $this->cron->isDue($now);
    }

    /**
     * Esegui il task
     */
    public function run(): void
    {
        call_user_func($this->callback);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getExpression(): ?string
    {
        return $this->cronExpression;
    }

    public function getNextRunDate(): ?\DateTime
    {
        if ($this->cron === null) {
            return null;
        }

        return $this->cron->getNextRunDate();
    }
}
```

#### 2.2 Registra Tasks

```php
// src/App/ScheduleProvider.php
<?php

namespace FP\DMS\App;

use FP\DMS\Infra\Scheduler;
use FP\DMS\Infra\Queue;
use FP\DMS\Infra\Retention;
use FP\DMS\Services\Anomalies\AnomalyScanner;

class ScheduleProvider
{
    public static function register(Scheduler $scheduler): void
    {
        // Queue tick ogni 5 minuti
        $scheduler->schedule('queue:tick', function() {
            Queue::tick();
        })->everyFiveMinutes();

        // Cleanup retention giornaliero alle 3:00
        $scheduler->schedule('retention:cleanup', function() {
            Retention::cleanup();
        })->dailyAt('03:00');

        // Scan anomalie ogni ora
        $scheduler->schedule('anomalies:scan', function() {
            $scanner = new AnomalyScanner();
            $scanner->scanAll();
        })->hourly();

        // Report settimanali ogni lunedì alle 9:00
        $scheduler->schedule('reports:weekly', function() {
            // Logica report settimanali
        })->cron('0 9 * * 1');

        // Health check ogni 15 minuti
        $scheduler->schedule('health:check', function() {
            // Verifica salute sistema
        })->cron('*/15 * * * *');
    }
}
```

#### 2.3 Comando CLI Scheduler

```php
// src/App/Commands/ScheduleRunCommand.php
<?php

namespace FP\DMS\App\Commands;

use FP\DMS\App\ScheduleProvider;
use FP\DMS\Infra\Scheduler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

class ScheduleRunCommand extends Command
{
    protected static $defaultName = 'schedule:run';
    protected static $defaultDescription = 'Run scheduled tasks';

    private Scheduler $scheduler;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->scheduler = new Scheduler($logger);
        ScheduleProvider::register($this->scheduler);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Running scheduled tasks...</info>');
        
        $this->scheduler->run();
        
        $output->writeln('<info>Scheduled tasks completed.</info>');
        
        return Command::SUCCESS;
    }
}

class ScheduleListCommand extends Command
{
    protected static $defaultName = 'schedule:list';
    protected static $defaultDescription = 'List all scheduled tasks';

    private Scheduler $scheduler;

    public function __construct(LoggerInterface $logger)
    {
        parent::__construct();
        $this->scheduler = new Scheduler($logger);
        ScheduleProvider::register($this->scheduler);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $tasks = $this->scheduler->listTasks();
        
        $output->writeln('<info>Scheduled Tasks:</info>');
        $output->writeln('');
        
        foreach ($tasks as $task) {
            $output->writeln(sprintf(
                '  <comment>%s</comment> (%s) - Next run: %s',
                $task['name'],
                $task['expression'],
                $task['next_run'] ?? 'N/A'
            ));
        }
        
        return Command::SUCCESS;
    }
}
```

#### 2.4 Crontab Semplice

```bash
# Una sola riga in crontab che esegue lo scheduler
* * * * * cd /var/www/fpdms-standalone && php cli.php schedule:run >> storage/logs/scheduler.log 2>&1
```

#### 2.5 Composer Dependency

```bash
# Installa libreria cron expression
composer require dragonmantank/cron-expression
```

### Opzione 3: Supervisor (Process Manager) 🔄 LONG-RUNNING

Per processi long-running che ascoltano continuamente.

#### 3.1 Worker Daemon

```php
// src/App/Commands/WorkerCommand.php
<?php

namespace FP\DMS\App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommand extends Command
{
    protected static $defaultName = 'worker:run';
    
    private bool $shouldQuit = false;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Gestione graceful shutdown
        pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
        pcntl_signal(SIGINT, [$this, 'handleShutdown']);

        $output->writeln('<info>Worker started. Press Ctrl+C to stop.</info>');

        while (!$this->shouldQuit) {
            try {
                // Esegui task
                Queue::tick();
                
                // Sleep 5 minuti
                sleep(300);
                
                // Check per segnali
                pcntl_signal_dispatch();
            } catch (\Exception $e) {
                $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
                sleep(60); // Sleep più lungo in caso di errore
            }
        }

        $output->writeln('<info>Worker stopped gracefully.</info>');
        return Command::SUCCESS;
    }

    public function handleShutdown(): void
    {
        $this->shouldQuit = true;
    }
}
```

#### 3.2 Supervisor Configuration

```ini
; /etc/supervisor/conf.d/fpdms-worker.conf

[program:fpdms-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/fpdms-standalone/cli.php worker:run
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/fpdms-standalone/storage/logs/worker.log
stopwaitsecs=60
```

```bash
# Comandi Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start fpdms-worker:*
sudo supervisorctl status
```

### Opzione 4: Systemd Service (Linux) 🐧

#### 4.1 Service File

```ini
# /etc/systemd/system/fpdms-scheduler.service

[Unit]
Description=FP DMS Scheduler Service
After=network.target mysql.service

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/fpdms-standalone
ExecStart=/usr/bin/php /var/www/fpdms-standalone/cli.php worker:run
Restart=always
RestartSec=10
StandardOutput=append:/var/www/fpdms-standalone/storage/logs/scheduler.log
StandardError=append:/var/www/fpdms-standalone/storage/logs/scheduler-error.log

[Install]
WantedBy=multi-user.target
```

```bash
# Comandi systemd
sudo systemctl daemon-reload
sudo systemctl enable fpdms-scheduler
sudo systemctl start fpdms-scheduler
sudo systemctl status fpdms-scheduler
sudo journalctl -u fpdms-scheduler -f
```

#### 4.2 Timer (alternativa a cron)

```ini
# /etc/systemd/system/fpdms-scheduler.timer

[Unit]
Description=FP DMS Scheduler Timer
Requires=fpdms-scheduler.service

[Timer]
OnCalendar=*:0/5
Unit=fpdms-scheduler.service

[Install]
WantedBy=timers.target
```

```bash
sudo systemctl enable fpdms-scheduler.timer
sudo systemctl start fpdms-scheduler.timer
sudo systemctl list-timers
```

### Opzione 5: Windows Task Scheduler 🪟

Per server Windows.

#### 5.1 Script PowerShell

```powershell
# bin/run-scheduler.ps1

$AppDir = "C:\inetpub\wwwroot\fpdms-standalone"
$PhpPath = "C:\php\php.exe"
$LogFile = "$AppDir\storage\logs\scheduler.log"

Set-Location $AppDir

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
"[$timestamp] Running scheduler..." | Out-File -Append $LogFile

& $PhpPath cli.php schedule:run 2>&1 | Out-File -Append $LogFile

$timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
"[$timestamp] Scheduler completed." | Out-File -Append $LogFile
```

#### 5.2 Task Scheduler Setup

```powershell
# Crea task con PowerShell
$action = New-ScheduledTaskAction -Execute "PowerShell.exe" -Argument "-File C:\inetpub\wwwroot\fpdms-standalone\bin\run-scheduler.ps1"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration ([TimeSpan]::MaxValue)
$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -LogonType ServiceAccount -RunLevel Highest
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable
Register-ScheduledTask -TaskName "FP DMS Scheduler" -Action $action -Trigger $trigger -Principal $principal -Settings $settings
```

## 🎯 Architettura Completa Raccomandata

### Setup Multi-Layer

```
┌─────────────────────────────────────┐
│   System Cron (ogni minuto)         │
│   * * * * * php cli.php schedule:run│
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│   Scheduler (PHP interno)            │
│   - Check quali task eseguire        │
│   - Gestisce lock                    │
│   - Log e monitoring                 │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│   Queue System                       │
│   - Background jobs                  │
│   - Retry logic                      │
│   - Priority handling                │
└────────────────┬────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────┐
│   Business Logic                     │
│   - Report generation                │
│   - Anomaly detection                │
│   - Notifications                    │
└─────────────────────────────────────┘
```

### Implementazione Completa

```php
// composer.json
{
    "require": {
        "dragonmantank/cron-expression": "^3.3"
    }
}
```

```bash
# Struttura file
standalone/
├── bin/
│   ├── cron-runner.sh          # Wrapper script
│   └── run-scheduler.ps1       # Windows script
├── src/
│   ├── App/
│   │   ├── Commands/
│   │   │   ├── ScheduleRunCommand.php
│   │   │   ├── ScheduleListCommand.php
│   │   │   └── WorkerCommand.php
│   │   └── ScheduleProvider.php
│   └── Infra/
│       └── Scheduler.php
└── storage/
    └── logs/
        ├── cron.log
        ├── scheduler.log
        └── worker.log
```

## 📋 Checklist Setup Scheduler

### Linux/Unix (Raccomandato)

- [ ] Crea `src/Infra/Scheduler.php`
- [ ] Crea `src/App/ScheduleProvider.php`
- [ ] Crea comandi CLI `schedule:run` e `schedule:list`
- [ ] Installa `composer require dragonmantank/cron-expression`
- [ ] Crea `bin/cron-runner.sh` con lock file
- [ ] Configura crontab: `*/5 * * * * /path/to/cron-runner.sh`
- [ ] Test: `php cli.php schedule:list`
- [ ] Monitora: `tail -f storage/logs/scheduler.log`

### Windows

- [ ] Crea `bin/run-scheduler.ps1`
- [ ] Configura Windows Task Scheduler
- [ ] Test esecuzione manuale
- [ ] Verifica log

### Docker/Container

```yaml
# docker-compose.yml
services:
  app:
    # ... app service
  
  scheduler:
    build: .
    command: php cli.php worker:run
    volumes:
      - ./:/var/www/html
    depends_on:
      - app
      - db
    restart: unless-stopped
```

## 🔍 Monitoring e Debug

### Check Scheduler Status

```php
// src/App/Commands/ScheduleStatusCommand.php
class ScheduleStatusCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check ultimo run
        $lastRun = Config::get('scheduler_last_run');
        $output->writeln("Last run: " . ($lastRun ?? 'Never'));
        
        // Check prossimo run
        $tasks = $scheduler->listTasks();
        foreach ($tasks as $task) {
            $output->writeln(sprintf(
                "%s: next in %s",
                $task['name'],
                $this->timeUntil($task['next_run'])
            ));
        }
        
        return Command::SUCCESS;
    }
}
```

### Health Check Endpoint

```php
// src/App/Controllers/HealthController.php
public function scheduler(Request $request, Response $response): Response
{
    $lastRun = Config::get('scheduler_last_run');
    $now = time();
    $diff = $now - strtotime($lastRun);
    
    $healthy = $diff < 600; // Meno di 10 minuti
    
    return $this->json($response, [
        'status' => $healthy ? 'healthy' : 'unhealthy',
        'last_run' => $lastRun,
        'seconds_ago' => $diff
    ], $healthy ? 200 : 503);
}
```

## 🚀 Conclusione

**Setup Raccomandato:**

1. **Sviluppo**: PHP Scheduler interno + cron ogni minuto
2. **Produzione**: PHP Scheduler interno + Supervisor/Systemd
3. **Windows**: Task Scheduler + PowerShell script

**Comando da registrare:**

```php
// src/App/CommandRegistry.php
$console->add(new ScheduleRunCommand($logger));
$console->add(new ScheduleListCommand($logger));
$console->add(new WorkerCommand());
```

Vuoi che implementi una di queste soluzioni nel codice?
