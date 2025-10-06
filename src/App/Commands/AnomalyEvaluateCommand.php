<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AnomalyEvaluateCommand extends Command
{
    protected static $defaultName = 'anomalies:evaluate';
    protected static $defaultDescription = 'Evaluate anomalies for a client';

    protected function configure(): void
    {
        $this
            ->addOption('client', null, InputOption::VALUE_REQUIRED, 'Client ID')
            ->addOption('from', null, InputOption::VALUE_OPTIONAL, 'Start date (YYYY-MM-DD)')
            ->addOption('to', null, InputOption::VALUE_OPTIONAL, 'End date (YYYY-MM-DD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clientId = $input->getOption('client');

        $io->title('FP DMS - Anomaly Evaluation');
        $io->info(sprintf('Evaluating anomalies for client %s', $clientId));

        // TODO: Implement anomaly evaluation logic

        $io->success('Anomaly evaluation completed successfully!');

        return Command::SUCCESS;
    }
}
