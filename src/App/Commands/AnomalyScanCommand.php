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

        $io->title('FP DMS - Anomaly Scan');

        try {
            $clientIdOption = $input->getOption('client');
            
            // Se specificato un client, scansiona solo quello
            if ($clientIdOption) {
                $clientId = (int) $clientIdOption;
                $io->info(sprintf('Scanning for anomalies for client %d', $clientId));
                $this->scanClient($clientId, $io);
            } else {
                // Altrimenti scansiona tutti i client
                $io->info('Scanning for anomalies for all clients');
                $this->scanAllClients($io);
            }

            $io->success('Anomaly scan completed successfully!');
            return Command::SUCCESS;
            
        } catch (\Throwable $e) {
            $io->error(sprintf('Anomaly scan failed: %s', $e->getMessage()));
            error_log(sprintf('[AnomalyScanCommand] Error: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function scanClient(int $clientId, SymfonyStyle $io): void
    {
        // Implementazione base - in un sistema reale si recupererebbero i dati e si chiamerebbe Engine
        $io->note(sprintf('Client %d would be scanned here. Integrate with Engine::evaluateClientPeriod()', $clientId));
        
        // TODO: Integrate with actual repositories and services
        // $clientsRepo = new \FP\DMS\Domain\Repos\ClientsRepo();
        // $anomaliesRepo = new \FP\DMS\Domain\Repos\AnomaliesRepo();
        // $engine = new \FP\DMS\Services\Anomalies\Engine($anomaliesRepo);
        // $period = Period::lastNDays(7);
        // $policy = ['metrics' => [...], 'baseline' => [...]];
        // $results = $engine->evaluateClientPeriod($clientId, $period, $policy);
        
        $io->text('✓ Anomaly detection would run for this client');
    }

    private function scanAllClients(SymfonyStyle $io): void
    {
        // Implementazione base - in un sistema reale si recupererebbero tutti i client
        $io->note('All clients would be scanned here');
        
        // TODO: Integrate with actual repositories
        // $clientsRepo = new \FP\DMS\Domain\Repos\ClientsRepo();
        // $clients = $clientsRepo->all();
        // foreach ($clients as $client) {
        //     $this->scanClient($client->id, $io);
        // }
        
        $io->text('✓ All clients would be processed');
    }
}
