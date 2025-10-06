<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use FP\DMS\App\ScheduleProvider;
use FP\DMS\Infra\Scheduler;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Psr\Log\LoggerInterface;

class ScheduleRunCommand extends Command
{
    protected static $defaultName = 'schedule:run';
    protected static $defaultDescription = 'Run all scheduled tasks that are due';

    private ?LoggerInterface $logger;

    public function __construct(?LoggerInterface $logger = null)
    {
        parent::__construct();
        $this->logger = $logger;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'verbose',
                'v',
                InputOption::VALUE_NONE,
                'Increase verbosity of messages'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $verbose = $input->getOption('verbose');
        
        if ($verbose) {
            $output->writeln('<info>Starting scheduler...</info>');
        }

        // Crea scheduler e registra task
        $scheduler = new Scheduler($this->logger);
        ScheduleProvider::register($scheduler);

        // Esegui task dovuti
        $executedCount = $scheduler->run();

        if ($verbose) {
            $output->writeln(sprintf(
                '<info>Scheduler completed. Executed %d task(s).</info>',
                $executedCount
            ));
        }

        // Aggiorna timestamp ultimo run
        if (function_exists('update_option')) {
            update_option('fpdms_scheduler_last_run', date('Y-m-d H:i:s'));
        } elseif (class_exists('\FP\DMS\Infra\Config')) {
            \FP\DMS\Infra\Config::set('scheduler_last_run', date('Y-m-d H:i:s'));
        }

        return Command::SUCCESS;
    }
}
