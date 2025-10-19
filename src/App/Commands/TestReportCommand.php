<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use FP\DMS\Domain\Repos\ClientsRepo;
use FP\DMS\Domain\Repos\DataSourcesRepo;
use FP\DMS\Domain\Repos\ReportsRepo;
use FP\DMS\Domain\Repos\SchedulesRepo;
use FP\DMS\Domain\Repos\TemplatesRepo;
use FP\DMS\Infra\Queue;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TestReportCommand extends Command
{
    protected static $defaultName = 'report:test';
    protected static $defaultDescription = 'Test report generation for a specific client';

    protected function configure(): void
    {
        $this
            ->addOption(
                'client-id',
                'c',
                InputOption::VALUE_REQUIRED,
                'Client ID to test report generation for'
            )
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Number of days to test (default: 1)',
                1
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $clientId = (int) $input->getOption('client-id');
        $days = (int) $input->getOption('days');

        if (!$clientId) {
            $output->writeln('<error>Client ID is required</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Testing report generation for client %d...</info>', $clientId));

        $clientsRepo = new ClientsRepo();
        $dataSourcesRepo = new DataSourcesRepo();
        $schedulesRepo = new SchedulesRepo();
        $templatesRepo = new TemplatesRepo();
        $reportsRepo = new ReportsRepo();

        // Find client
        $client = $clientsRepo->find($clientId);
        if (!$client) {
            $output->writeln(sprintf('<error>Client %d not found</error>', $clientId));
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Client found: %s</info>', $client->name ?? 'Unknown'));

        // Check data sources
        $dataSources = $dataSourcesRepo->forClient($clientId);
        $activeDataSources = array_filter($dataSources, fn($ds) => $ds->active);

        $output->writeln(sprintf('<info>Active data sources: %d</info>', count($activeDataSources)));
        foreach ($activeDataSources as $ds) {
            $output->writeln(sprintf('  - %s (ID: %d)', $ds->type, $ds->id ?? 0));
        }

        if (empty($activeDataSources)) {
            $output->writeln('<error>No active data sources found for this client</error>');
            return Command::FAILURE;
        }

        // Check schedules
        $schedules = $schedulesRepo->forClient($clientId);
        $activeSchedules = array_filter($schedules, fn($s) => $s->active);

        $output->writeln(sprintf('<info>Active schedules: %d</info>', count($activeSchedules)));

        // Find or create template
        $template = $templatesRepo->findDefault();
        if (!$template) {
            $output->writeln('<comment>No default template found, creating one...</comment>');
            // Create a simple default template
            $template = $templatesRepo->create([
                'name' => 'Default Template',
                'description' => 'Default report template',
                'content' => '<h1>Report for {{client.name}}</h1><p>Period: {{period.start}} to {{period.end}}</p>',
                'is_default' => 1,
            ]);
        }

        if (!$template) {
            $output->writeln('<error>Failed to create or find template</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Using template: %s (ID: %d)</info>', $template->name, $template->id ?? 0));

        // Create a test job
        $date = date('Y-m-d', strtotime("-{$days} days"));
        $output->writeln(sprintf('<info>Creating test job for date: %s</info>', $date));

        $job = Queue::enqueue(
            $clientId,
            $date,
            $date,
            $template->id,
            $activeSchedules[0]->id ?? null,
            [
                'origin' => 'test_command',
                'test' => true,
            ]
        );

        if (!$job) {
            $output->writeln('<error>Failed to create test job</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Test job created with ID: %d</info>', $job->id ?? 0));

        // Process the job immediately
        $output->writeln('<info>Processing job...</info>');
        
        try {
            Queue::tick();
            
            // Check the result
            $result = $reportsRepo->find($job->id ?? 0);
            if ($result) {
                $output->writeln(sprintf('<info>Job status: %s</info>', $result->status ?? 'unknown'));
                
                if ($result->status === 'failed' && isset($result->meta['error'])) {
                    $output->writeln(sprintf('<error>Error: %s</error>', $result->meta['error']));
                }
                
                if ($result->status === 'success') {
                    $output->writeln(sprintf('<info>Report generated successfully! Storage path: %s</info>', $result->storagePath ?? 'unknown'));
                }
            } else {
                $output->writeln('<error>Job result not found</error>');
            }
        } catch (\Exception $e) {
            $output->writeln(sprintf('<error>Exception during job processing: %s</error>', $e->getMessage()));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
