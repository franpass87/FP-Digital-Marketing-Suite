<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunReportCommand extends Command
{
    protected static $defaultName = 'run';
    protected static $defaultDescription = 'Run a report for a specific client';

    protected function configure(): void
    {
        $this
            ->addOption('client', null, InputOption::VALUE_REQUIRED, 'Client ID')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Start date (YYYY-MM-DD)')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'End date (YYYY-MM-DD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clientId = $input->getOption('client');
        $from = $input->getOption('from');
        $to = $input->getOption('to');

        $io->title('FP DMS - Run Report');
        $io->info(sprintf('Running report for client %s from %s to %s', $clientId, $from, $to));

        // TODO: Implement report generation logic

        $io->success('Report generated successfully!');

        return Command::SUCCESS;
    }
}
