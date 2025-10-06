<?php

declare(strict_types=1);

namespace FP\DMS\App;

use FP\DMS\Infra\Scheduler;
use FP\DMS\Infra\Queue;
use FP\DMS\Infra\Retention;
use FP\DMS\Services\Anomalies\AnomalyScanner;

/**
 * Registra tutti i task schedulati
 */
class ScheduleProvider
{
    /**
     * Registra tutti i task nell'applicazione
     */
    public static function register(Scheduler $scheduler): void
    {
        // ===== QUEUE PROCESSING =====
        
        // Queue tick ogni 5 minuti
        $scheduler->schedule('queue:tick', function() {
            Queue::tick();
        })->everyFiveMinutes();

        // ===== MAINTENANCE =====
        
        // Cleanup retention giornaliero alle 3:00 AM
        $scheduler->schedule('retention:cleanup', function() {
            Retention::cleanup();
        })->dailyAt('03:00');

        // Pulizia locks vecchi ogni ora
        $scheduler->schedule('locks:cleanup', function() {
            global $wpdb;
            $table = DB::table('locks');
            $wpdb->query(
                "DELETE FROM {$table} 
                 WHERE acquired_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"
            );
        })->hourly();

        // ===== ANOMALY DETECTION =====
        
        // Scan anomalie ogni ora
        $scheduler->schedule('anomalies:scan', function() {
            try {
                $scanner = new AnomalyScanner();
                $scanner->scanAll();
            } catch (\Exception $e) {
                Logger::error('Anomaly scan failed', [
                    'error' => $e->getMessage()
                ]);
            }
        })->hourly();

        // ===== REPORTING =====
        
        // Report giornalieri alle 8:00 AM
        $scheduler->schedule('reports:daily', function() {
            // Logica per report giornalieri automatici
            // (se configurati nei clients)
        })->dailyAt('08:00');

        // Report settimanali ogni lunedì alle 9:00 AM
        $scheduler->schedule('reports:weekly', function() {
            // Logica per report settimanali automatici
        })->weeklyOn(1, '09:00');

        // Report mensili il primo giorno del mese alle 10:00 AM
        $scheduler->schedule('reports:monthly', function() {
            // Logica per report mensili automatici
        })->monthlyOn(1, '10:00');

        // ===== HEALTH CHECKS =====
        
        // Health check ogni 15 minuti
        $scheduler->schedule('health:check', function() {
            // Verifica connessioni database
            // Verifica spazio disco
            // Verifica servizi esterni
        })->everyFifteenMinutes();

        // ===== NOTIFICATIONS =====
        
        // Digest notifiche (se configurato) ogni mattina alle 7:00
        $scheduler->schedule('notifications:digest', function() {
            // Invia digest giornaliero anomalie
        })->dailyAt('07:00');

        // ===== OPTIONAL TASKS =====
        
        // Backup database (se configurato) ogni notte alle 2:00 AM
        if (getenv('ENABLE_DB_BACKUP') === 'true') {
            $scheduler->schedule('backup:database', function() {
                // Logica backup database
            })->dailyAt('02:00');
        }

        // Stats aggregation ogni giorno alle 23:00
        $scheduler->schedule('stats:aggregate', function() {
            // Aggrega statistiche giornaliere
        })->dailyAt('23:00');
    }
}
