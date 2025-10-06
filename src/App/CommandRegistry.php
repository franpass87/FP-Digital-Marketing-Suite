<?php

declare(strict_types=1);

namespace FP\DMS\App;

use FP\DMS\App\Commands\DatabaseMigrateCommand;
use FP\DMS\App\Commands\RunReportCommand;
use FP\DMS\App\Commands\QueueListCommand;
use FP\DMS\App\Commands\AnomalyScanCommand;
use FP\DMS\App\Commands\AnomalyEvaluateCommand;
use FP\DMS\App\Commands\AnomalyNotifyCommand;
use Symfony\Component\Console\Application;

class CommandRegistry
{
    public function __construct(
        private Application $console
    ) {
    }

    public function register(): void
    {
        // Database commands
        $this->console->add(new DatabaseMigrateCommand());

        // Report commands
        $this->console->add(new RunReportCommand());
        $this->console->add(new QueueListCommand());

        // Anomaly commands
        $this->console->add(new AnomalyScanCommand());
        $this->console->add(new AnomalyEvaluateCommand());
        $this->console->add(new AnomalyNotifyCommand());
    }
}
