<?php

declare(strict_types=1);

namespace FP\DMS\App\Commands;

use FP\DMS\App\Database\Database;
use FP\DMS\App\Database\DatabaseAdapter;
use FP\DMS\Infra\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DatabaseMigrateCommand extends Command
{
    protected static $defaultName = 'db:migrate';
    protected static $defaultDescription = 'Run database migrations';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('FP Digital Marketing Suite - Database Migration');

        try {
            // Initialize database
            $db = new Database([
                'driver' => $_ENV['DB_CONNECTION'] ?? 'mysql',
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int) ($_ENV['DB_PORT'] ?? 3306),
                'database' => $_ENV['DB_DATABASE'] ?? 'fpdms',
                'username' => $_ENV['DB_USERNAME'] ?? 'root',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
                'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
                'prefix' => $_ENV['DB_PREFIX'] ?? 'fpdms_',
            ]);

            DatabaseAdapter::setInstance($db);

            $io->info('Creating database tables...');

            // Create options table first
            $this->createOptionsTable($db);
            $io->success('Options table created');

            // Run main migrations using DB::migrate()
            $this->runMigrations($db);
            $io->success('All tables created successfully');

            // Run anomalies v2 migration
            DB::migrateAnomaliesV2();
            $io->success('Anomalies table updated to v2');

            $io->success('Database migration completed successfully!');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Migration failed: ' . $e->getMessage());
            $io->error($e->getTraceAsString());

            return Command::FAILURE;
        }
    }

    private function createOptionsTable(Database $db): void
    {
        $table = $db->table('options');
        $charset = $db->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            option_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            option_name VARCHAR(191) NOT NULL,
            option_value LONGTEXT NOT NULL,
            autoload VARCHAR(20) NOT NULL DEFAULT 'yes',
            PRIMARY KEY (option_id),
            UNIQUE KEY option_name (option_name)
        ) {$charset}";

        $db->query($sql);
    }

    private function runMigrations(Database $db): void
    {
        $schema = $this->getSchema($db);

        foreach ($schema as $sql) {
            $db->query($sql);
        }
    }

    private function getSchema(Database $db): array
    {
        $charset = $db->get_charset_collate();
        $prefix = $db->getPrefix();

        return [
            "CREATE TABLE IF NOT EXISTS {$prefix}clients (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                email_to LONGTEXT NULL,
                email_cc LONGTEXT NULL,
                logo_id BIGINT UNSIGNED NULL,
                timezone VARCHAR(64) NOT NULL DEFAULT 'UTC',
                notes LONGTEXT NULL,
                ga4_property_id VARCHAR(32) NULL,
                ga4_stream_id VARCHAR(32) NULL,
                ga4_measurement_id VARCHAR(32) NULL,
                gsc_site_property VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id)
            ) $charset",
            
            "CREATE TABLE IF NOT EXISTS {$prefix}datasources (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(32) NOT NULL,
                auth LONGTEXT NULL,
                config LONGTEXT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY client_id (client_id)
            ) $charset",
            
            "CREATE TABLE IF NOT EXISTS {$prefix}schedules (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                cron_key VARCHAR(64) NOT NULL,
                frequency VARCHAR(16) NOT NULL,
                next_run_at DATETIME NULL,
                last_run_at DATETIME NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                template_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY cron_key (cron_key)
            ) $charset",
            
            "CREATE TABLE IF NOT EXISTS {$prefix}reports (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                status VARCHAR(16) NOT NULL,
                storage_path VARCHAR(255) NULL,
                meta LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY client_id (client_id)
            ) $charset",
            
            "CREATE TABLE IF NOT EXISTS {$prefix}anomalies (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                client_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(64) NOT NULL,
                severity VARCHAR(16) NOT NULL,
                payload LONGTEXT NULL,
                detected_at DATETIME NOT NULL,
                notified TINYINT(1) NOT NULL DEFAULT 0,
                algo VARCHAR(32) NULL,
                score DOUBLE NULL,
                expected DOUBLE NULL,
                actual DOUBLE NULL,
                baseline DOUBLE NULL,
                z DOUBLE NULL,
                p_value DOUBLE NULL,
                window INT NULL,
                PRIMARY KEY  (id),
                KEY client_id (client_id)
            ) $charset",
            
            "CREATE TABLE IF NOT EXISTS {$prefix}templates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(190) NOT NULL,
                description VARCHAR(255) NULL,
                content LONGTEXT NOT NULL,
                is_default TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id)
            ) $charset",
            
            "CREATE TABLE IF NOT EXISTS {$prefix}locks (
                lock_key VARCHAR(100) NOT NULL,
                owner VARCHAR(64) NOT NULL,
                acquired_at DATETIME NOT NULL,
                PRIMARY KEY (lock_key)
            ) $charset",
            
            "CREATE TABLE IF NOT EXISTS {$prefix}users (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                username VARCHAR(60) NOT NULL,
                email VARCHAR(100) NOT NULL,
                password VARCHAR(255) NOT NULL,
                display_name VARCHAR(250) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY username (username),
                UNIQUE KEY email (email)
            ) $charset",
        ];
    }
}
