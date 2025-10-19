<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Domain\Repos\SchedulesRepo;
use FP\DMS\Domain\Repos\TemplatesRepo;
use FP\DMS\Infra\Queue;
use FP\DMS\Services\Connectors\ProviderFactory;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SyncDataCommand extends Command
{
    protected static $defaultName = 'data:sync';
    protected static $defaultDescription = 'Synchronize data from all active data sources';

    protected function configure(): void
    {
        $this
            ->addOption(
                'client-id',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Sync data for specific client ID'
            )
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Number of days to sync (default: 7)',
                7
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force sync even if data already exists'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $clientId = $input->getOption('client-id');
        $days = (int) $input->getOption('days');
        $force = $input->getOption('force');

        $output->writeln('<info>Starting data synchronization...</info>');

        $clientsRepo = new ClientsRepo();
        $dataSourcesRepo = new DataSourcesRepo();
        $schedulesRepo = new SchedulesRepo();
        $templatesRepo = new TemplatesRepo();

        // Se non specificato client-id, usa tutti i client
        if ($clientId) {
            $client = $clientsRepo->find((int) $clientId);
            $clients = $client ? [$client] : [];
        } else {
            $clients = $clientsRepo->all();
        }

        if (empty($clients)) {
            $output->writeln('<error>No clients found</error>');
            return Command::FAILURE;
        }

        $totalSynced = 0;

        foreach ($clients as $client) {
            $output->writeln(sprintf('<info>Processing client: %s (ID: %d)</info>', $client->name ?? 'Unknown', $client->id ?? 0));

            // Verifica se il client ha uno schedule attivo
            $schedules = $schedulesRepo->forClient($client->id ?? 0);
            $activeSchedule = null;
            
            foreach ($schedules as $schedule) {
                if ($schedule->active) {
                    $activeSchedule = $schedule;
                    break;
                }
            }

            // Se non ha schedule, creane uno di default
            if (!$activeSchedule) {
                $output->writeln('<comment>No active schedule found, creating default daily schedule...</comment>');
                
                $template = $templatesRepo->findDefault();
                $nextRun = Wp::currentTime('mysql');
                
                $activeSchedule = $schedulesRepo->create([
                    'client_id' => $client->id ?? 0,
                    'frequency' => 'daily',
                    'next_run_at' => $nextRun,
                    'active' => 1,
                    'template_id' => $template?->id,
                ]);

                if ($activeSchedule) {
                    $output->writeln('<info>Default schedule created successfully</info>');
                }
            }

            // Verifica se il client ha data sources attive
            $dataSources = $dataSourcesRepo->forClient($client->id ?? 0);
            $activeDataSources = array_filter($dataSources, fn($ds) => $ds->active);

            if (empty($activeDataSources)) {
                $output->writeln('<comment>No active data sources found for this client</comment>');
                continue;
            }

            $output->writeln(sprintf('<info>Found %d active data sources</info>', count($activeDataSources)));

            // Crea job per ogni giorno degli ultimi N giorni
            for ($i = $days; $i >= 1; $i--) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                
                $job = Queue::enqueue(
                    $client->id ?? 0,
                    $date,
                    $date,
                    $activeSchedule->templateId,
                    $activeSchedule->id,
                    [
                        'origin' => 'manual_sync',
                        'force' => $force,
                    ]
                );

                if ($job) {
                    $totalSynced++;
                    $output->writeln(sprintf('<info>Queued job for date: %s</info>', $date));
                } else {
                    $output->writeln(sprintf('<error>Failed to queue job for date: %s</error>', $date));
                }
            }
        }

        $output->writeln(sprintf('<info>Synchronization completed. %d jobs queued.</info>', $totalSynced));
        $output->writeln('<comment>Jobs will be processed by the queue system. Run "php cli.php queue:process" to process them immediately.</comment>');

        return Command::SUCCESS;
    }
}
