<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use Cron\CronExpression;
use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Task Scheduler per versione standalone
 * Simile a Laravel Task Scheduler
 */
class Scheduler
{
    /** @var Task[] */
    private array $tasks = [];
    
    private LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Registra un nuovo task
     */
    public function schedule(string $name, callable $callback): Task
    {
        $task = new Task($name, $callback, $this->logger);
        $this->tasks[] = $task;
        
        return $task;
    }

    /**
     * Esegui tutti i task che sono dovuti
     */
    public function run(): int
    {
        $now = new DateTime();
        $executed = 0;
        
        foreach ($this->tasks as $task) {
            if ($task->isDue($now)) {
                $executed++;
                $task->run();
            }
        }
        
        return $executed;
    }

    /**
     * Lista tutti i task registrati
     * 
     * @return array<int, array{name: string, expression: string|null, next_run: string|null}>
     */
    public function listTasks(): array
    {
        return array_map(fn($task) => [
            'name' => $task->getName(),
            'expression' => $task->getExpression(),
            'next_run' => $task->getNextRunDate()?->format('Y-m-d H:i:s'),
        ], $this->tasks);
    }

    /**
     * Get all registered tasks
     * 
     * @return Task[]
     */
    public function getTasks(): array
    {
        return $this->tasks;
    }
}

/**
 * Singolo task schedulato
 */
class Task
{
    private string $name;
    
    /** @var callable */
    private $callback;
    
    private ?string $cronExpression = null;
    private ?CronExpression $cron = null;
    private LoggerInterface $logger;

    public function __construct(string $name, callable $callback, ?LoggerInterface $logger = null)
    {
        $this->name = $name;
        $this->callback = $callback;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Ogni minuto
     */
    public function everyMinute(): self
    {
        return $this->cron('* * * * *');
    }

    /**
     * Ogni 5 minuti
     */
    public function everyFiveMinutes(): self
    {
        return $this->cron('*/5 * * * *');
    }

    /**
     * Ogni 10 minuti
     */
    public function everyTenMinutes(): self
    {
        return $this->cron('*/10 * * * *');
    }

    /**
     * Ogni 15 minuti
     */
    public function everyFifteenMinutes(): self
    {
        return $this->cron('*/15 * * * *');
    }

    /**
     * Ogni 30 minuti
     */
    public function everyThirtyMinutes(): self
    {
        return $this->cron('*/30 * * * *');
    }

    /**
     * Ogni ora
     */
    public function hourly(): self
    {
        return $this->cron('0 * * * *');
    }

    /**
     * Ogni ora ad un minuto specifico
     */
    public function hourlyAt(int $minute): self
    {
        return $this->cron("$minute * * * *");
    }

    /**
     * Giornaliero a mezzanotte
     */
    public function daily(): self
    {
        return $this->cron('0 0 * * *');
    }

    /**
     * Giornaliero ad orario specifico
     */
    public function dailyAt(string $time): self
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');
        
        return $this->cron("$minute $hour * * *");
    }

    /**
     * Settimanale domenica a mezzanotte
     */
    public function weekly(): self
    {
        return $this->cron('0 0 * * 0');
    }

    /**
     * Settimanale in un giorno specifico
     */
    public function weeklyOn(int $day, string $time = '00:00'): self
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');
        
        return $this->cron("$minute $hour * * $day");
    }

    /**
     * Mensile il primo giorno del mese
     */
    public function monthly(): self
    {
        return $this->cron('0 0 1 * *');
    }

    /**
     * Mensile in un giorno specifico
     */
    public function monthlyOn(int $day, string $time = '00:00'): self
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');
        
        return $this->cron("$minute $hour $day * *");
    }

    /**
     * Custom cron expression
     */
    public function cron(string $expression): self
    {
        $this->cronExpression = $expression;
        
        try {
            $this->cron = new CronExpression($expression);
        } catch (Exception $e) {
            $this->logger->error("Invalid cron expression for task {$this->name}: {$expression}", [
                'error' => $e->getMessage()
            ]);
        }
        
        return $this;
    }

    /**
     * Check se il task deve essere eseguito ora
     */
    public function isDue(DateTime $now): bool
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
        $this->logger->info("Running scheduled task: {$this->name}");
        
        $startTime = microtime(true);
        
        try {
            call_user_func($this->callback);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->logger->info("Task completed: {$this->name}", [
                'duration_ms' => $duration
            ]);
        } catch (Exception $e) {
            $this->logger->error("Task failed: {$this->name}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getExpression(): ?string
    {
        return $this->cronExpression;
    }

    public function getNextRunDate(): ?DateTime
    {
        if ($this->cron === null) {
            return null;
        }

        return $this->cron->getNextRunDate();
    }

    public function getPreviousRunDate(): ?DateTime
    {
        if ($this->cron === null) {
            return null;
        }

        return $this->cron->getPreviousRunDate();
    }
}
