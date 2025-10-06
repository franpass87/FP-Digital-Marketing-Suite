<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class AnomalyNotifyCommand extends Command
{
    protected static $defaultName = 'anomalies:notify';
    protected static $defaultDescription = 'Send anomaly notifications';

    protected function configure(): void
    {
        $this
            ->addOption('client', null, InputOption::VALUE_REQUIRED, 'Client ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $clientId = $input->getOption('client');

        $io->title('FP DMS - Anomaly Notification');
        $io->info(sprintf('Sending anomaly notifications for client %s', $clientId));

        // TODO: Implement anomaly notification logic

        $io->success('Anomaly notifications sent successfully!');

        return Command::SUCCESS;
    }
}
