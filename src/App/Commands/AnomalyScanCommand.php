<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AnomalyScanCommand extends Command
{
    protected static $defaultName = 'anomalies:scan';
    protected static $defaultDescription = 'Scan for anomalies';

    protected function configure(): void
    {
        $this
            ->addOption('client', null, InputOption::VALUE_REQUIRED, 'Client ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clientId = $input->getOption('client');

        $io->title('FP DMS - Anomaly Scan');
        $io->info(sprintf('Scanning for anomalies for client %s', $clientId));

        // TODO: Implement anomaly scanning logic

        $io->success('Anomaly scan completed successfully!');

        return Command::SUCCESS;
    }
}
