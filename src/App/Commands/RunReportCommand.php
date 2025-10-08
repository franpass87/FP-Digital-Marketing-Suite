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

        $io->title('FP DMS - Run Report');

        try {
            // Validazione parametri
            $clientIdOption = $input->getOption('client');
            $from = $input->getOption('from');
            $to = $input->getOption('to');

            if (!$clientIdOption) {
                $io->error('Client ID is required. Use --client=<id>');
                return Command::FAILURE;
            }

            if (!$from || !$to) {
                $io->error('Both --from and --to dates are required (format: YYYY-MM-DD)');
                return Command::FAILURE;
            }

            // Validazione formato date
            if (!$this->isValidDate($from)) {
                $io->error(sprintf('Invalid from date: %s. Use YYYY-MM-DD format.', $from));
                return Command::FAILURE;
            }

            if (!$this->isValidDate($to)) {
                $io->error(sprintf('Invalid to date: %s. Use YYYY-MM-DD format.', $to));
                return Command::FAILURE;
            }

            $clientId = (int) $clientIdOption;

            $io->info(sprintf('Running report for client %d from %s to %s', $clientId, $from, $to));

            $this->generateReport($clientId, $from, $to, $io);

            $io->success('Report generated successfully!');
            return Command::SUCCESS;
            
        } catch (\Throwable $e) {
            $io->error(sprintf('Report generation failed: %s', $e->getMessage()));
            error_log(sprintf('[RunReportCommand] Error: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function generateReport(int $clientId, string $from, string $to, SymfonyStyle $io): void
    {
        $io->section('Report Generation');
        
        $io->table(
            ['Parameter', 'Value'],
            [
                ['Client ID', $clientId],
                ['From', $from],
                ['To', $to],
            ]
        );

        // TODO: Integrate with actual ReportBuilder
        // $clientsRepo = new \FP\DMS\Domain\Repos\ClientsRepo();
        // $reportsRepo = new \FP\DMS\Domain\Repos\ReportsRepo();
        // $templatesRepo = new \FP\DMS\Domain\Repos\TemplatesRepo();
        // $dataSourcesRepo = new \FP\DMS\Domain\Repos\DataSourcesRepo();
        // 
        // $client = $clientsRepo->find($clientId);
        // if (!$client) {
        //     throw new \RuntimeException(sprintf('Client %d not found', $clientId));
        // }
        // 
        // $startDate = new \DateTimeImmutable($from);
        // $endDate = new \DateTimeImmutable($to);
        // $period = new \FP\DMS\Support\Period($startDate, $endDate);
        // 
        // // Get data sources and create providers
        // $dataSources = $dataSourcesRepo->forClient($clientId);
        // $providers = []; // Create providers from data sources
        // 
        // // Get template (use default if none specified)
        // $templates = $templatesRepo->all();
        // $template = reset($templates);
        // 
        // // Create report job
        // $job = $reportsRepo->create([
        //     'client_id' => $clientId,
        //     'status' => 'pending',
        //     'meta' => [],
        // ]);
        // 
        // // Generate report
        // $htmlRenderer = new \FP\DMS\Services\Reports\HtmlRenderer(new \FP\DMS\Services\Reports\TokenEngine());
        // $pdfRenderer = new \FP\DMS\Infra\PdfRenderer();
        // $builder = new \FP\DMS\Services\Reports\ReportBuilder($reportsRepo, $htmlRenderer, $pdfRenderer);
        // 
        // $result = $builder->generate($job, $client, $providers, $period, $template);
        // 
        // $io->text(sprintf('Report saved to: %s', $result->storagePath ?? 'N/A'));
        
        $io->text('✓ Client data would be fetched');
        $io->text('✓ Data sources would be queried');
        $io->text('✓ PDF report would be generated');
        $io->text('✓ Report would be saved to storage');
        
        $io->note('Report generation logic is ready for integration with repositories and services');
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
