<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use DateTime;

/**
 * Task Scheduler per versione standalone
 * Simile a Laravel Task Scheduler
 */
class Scheduler
{
    /** @var Task[] */
    private array $tasks = [];

    private \Psr\Log\LoggerInterface $logger;

    public function __construct(?\Psr\Log\LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? new \Psr\Log\NullLogger();
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
     * Uses locking to prevent concurrent executions
     */
    public function run(): int
    {
        $now = new DateTime();
        $executed = 0;

        foreach ($this->tasks as $task) {
            if ($task->isDue($now)) {
                // Use locking to prevent concurrent execution of same task
                if ($task->tryRun($now)) {
                    $executed++;
                }
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
