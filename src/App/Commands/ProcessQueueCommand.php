<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use FP\DMS\Infra\Queue;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessQueueCommand extends Command
{
    protected static $defaultName = 'queue:process';
    protected static $defaultDescription = 'Process pending jobs in the queue';

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of jobs to process',
                10
            )
            ->addOption(
                'timeout',
                't',
                InputOption::VALUE_OPTIONAL,
                'Maximum execution time in seconds',
                300
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $timeout = (int) $input->getOption('timeout');

        $output->writeln('<info>Processing queue...</info>');

        $startTime = time();
        $processed = 0;

        while ($processed < $limit && (time() - $startTime) < $timeout) {
            $jobsProcessed = Queue::tick();
            
            if ($jobsProcessed === 0) {
                $output->writeln('<info>No more jobs to process</info>');
                break;
            }
            
            $processed += $jobsProcessed;
            $output->writeln(sprintf('<info>Processed %d jobs</info>', $jobsProcessed));
            
            // Piccola pausa per evitare sovraccarico
            sleep(1);
        }

        $output->writeln(sprintf('<info>Queue processing completed. Total processed: %d jobs</info>', $processed));

        return Command::SUCCESS;
    }
}
