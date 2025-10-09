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

        $io->title('FP DMS - Anomaly Evaluation');

        try {
            $clientIdOption = $input->getOption('client');
            if (!$clientIdOption) {
                $io->error('Client ID is required. Use --client=<id>');
                return Command::FAILURE;
            }

            $clientId = (int) $clientIdOption;
            $from = $input->getOption('from');
            $to = $input->getOption('to');

            $io->info(sprintf('Evaluating anomalies for client %d', $clientId));

            if ($from && $to) {
                $io->text(sprintf('Period: %s to %s', $from, $to));
            } else {
                $io->text('Using default period (last 7 days)');
            }

            $this->evaluateAnomalies($clientId, $from, $to, $io);

            $io->success('Anomaly evaluation completed successfully!');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error(sprintf('Anomaly evaluation failed: %s', $e->getMessage()));
            error_log(sprintf('[AnomalyEvaluateCommand] Error: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function evaluateAnomalies(int $clientId, ?string $from, ?string $to, SymfonyStyle $io): void
    {
        // Validazione delle date
        if ($from && !$this->isValidDate($from)) {
            throw new \InvalidArgumentException(sprintf('Invalid from date: %s. Use YYYY-MM-DD format.', $from));
        }

        if ($to && !$this->isValidDate($to)) {
            throw new \InvalidArgumentException(sprintf('Invalid to date: %s. Use YYYY-MM-DD format.', $to));
        }

        $io->section('Anomaly Evaluation Details');
        $io->note(sprintf('Client: %d', $clientId));

        // TODO: Integrate with actual Engine
        // $anomaliesRepo = new \FP\DMS\Domain\Repos\AnomaliesRepo();
        // $engine = new \FP\DMS\Services\Anomalies\Engine($anomaliesRepo);
        //
        // $startDate = $from ? new \DateTimeImmutable($from) : (new \DateTimeImmutable())->modify('-7 days');
        // $endDate = $to ? new \DateTimeImmutable($to) : new \DateTimeImmutable();
        // $period = new \FP\DMS\Support\Period($startDate, $endDate);
        //
        // $policy = [
        //     'metrics' => [
        //         'sessions' => ['warn_pct' => 20, 'crit_pct' => 40],
        //         'revenue' => ['warn_pct' => 15, 'crit_pct' => 30],
        //         'clicks' => ['warn_pct' => 25, 'crit_pct' => 50],
        //     ],
        //     'baseline' => [
        //         'window_days' => 28,
        //         'seasonality' => 'dow',
        //     ],
        // ];
        //
        // $results = $engine->evaluateClientPeriod($clientId, $period, $policy);
        //
        // $io->text(sprintf('Found %d anomalies', count($results)));

        $io->text('✓ Anomaly evaluation logic would execute here');
        $io->text('✓ Results would be stored in database');
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}
