<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use FP\DMS\App\ScheduleProvider;
use FP\DMS\Infra\Scheduler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class ScheduleListCommand extends Command
{
    protected static $defaultName = 'schedule:list';
    protected static $defaultDescription = 'List all scheduled tasks';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Scheduled Tasks:</info>');
        $output->writeln('');

        // Crea scheduler e registra task
        $scheduler = new Scheduler();
        ScheduleProvider::register($scheduler);

        $tasks = $scheduler->listTasks();

        if (empty($tasks)) {
            $output->writeln('  <comment>No tasks scheduled.</comment>');
            return Command::SUCCESS;
        }

        // Crea tabella
        $table = new Table($output);
        $table->setHeaders(['Task', 'Schedule', 'Next Run', 'Time Until']);

        foreach ($tasks as $task) {
            $nextRun = $task['next_run'] ?? 'N/A';
            $timeUntil = 'N/A';

            if ($nextRun !== 'N/A') {
                $next = new \DateTime($nextRun);
                $now = new \DateTime();
                $diff = $now->diff($next);

                $timeUntil = $this->formatDiff($diff);
            }

            $table->addRow([
                $task['name'],
                $task['expression'] ?? 'N/A',
                $nextRun,
                $timeUntil
            ]);
        }

        $table->render();

        $output->writeln('');
        $output->writeln(sprintf('<info>Total: %d task(s)</info>', count($tasks)));

        // Mostra ultimo run
        $lastRun = 'Never';
        if (function_exists('get_option')) {
            $lastRun = get_option('fpdms_scheduler_last_run', 'Never');
        } elseif (class_exists('\FP\DMS\Infra\Config')) {
            $lastRun = \FP\DMS\Infra\Config::get('scheduler_last_run', 'Never');
        }

        $output->writeln(sprintf('<comment>Last run: %s</comment>', $lastRun));

        return Command::SUCCESS;
    }

    private function formatDiff(\DateInterval $diff): string
    {
        if ($diff->invert) {
            return 'Overdue!';
        }

        if ($diff->d > 0) {
            return sprintf('%d day(s)', $diff->d);
        }

        if ($diff->h > 0) {
            return sprintf('%d hour(s)', $diff->h);
        }

        if ($diff->i > 0) {
            return sprintf('%d minute(s)', $diff->i);
        }

        return 'Less than 1 minute';
    }
}
