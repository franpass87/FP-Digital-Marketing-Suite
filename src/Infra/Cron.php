<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

class Cron
{
    /**
     * Chiave transient per rate limiting
     */
    private const RATE_LIMIT_KEY = 'fpdms_cron_rate_limit_';

    public static function bootstrap(): void
    {
        add_filter('cron_schedules', [self::class, 'registerInterval']);
        add_action('fpdms_retention_cleanup', [self::class, 'rateLimitedCleanup']);
    }

    /**
     * Wrapper per cleanup con rate limiting
     */
    public static function rateLimitedCleanup(): void
    {
        // Verifica se possiamo eseguire il cleanup
        if (! self::canRunTask('retention_cleanup')) {
            return;
        }

        // Esegui cleanup
        Retention::cleanup();

        // Imposta il rate limit
        self::setRateLimit('retention_cleanup');
    }

    /**
     * Verifica se un task può essere eseguito (rate limiting)
     * 
     * @param string $taskName Nome del task
     * @return bool True se il task può essere eseguito
     */
    public static function canRunTask(string $taskName): bool
    {
        $key = self::RATE_LIMIT_KEY . $taskName;
        $lastRun = get_transient($key);

        // Se non c'è un last run, può essere eseguito
        if ($lastRun === false) {
            return true;
        }

        return false;
    }

    /**
     * Imposta il rate limit per un task
     * 
     * @param string $taskName Nome del task
     * @param int|null $duration Durata in secondi (null = auto in base all'hosting)
     */
    public static function setRateLimit(string $taskName, ?int $duration = null): void
    {
        if ($duration === null) {
            // Su shared hosting: 6 ore, altrimenti 1 ora
            $duration = HostingDetector::isSharedHosting() ? (6 * HOUR_IN_SECONDS) : HOUR_IN_SECONDS;
        }

        $key = self::RATE_LIMIT_KEY . $taskName;
        set_transient($key, time(), $duration);
    }

    /**
     * Registra intervalli custom ottimizzati per il tipo di hosting
     */
    public static function registerInterval(array $schedules): array
    {
        // Intervallo base 5 minuti
        $baseInterval = 300;
        
        // Su shared hosting, usa intervalli più lunghi
        if (HostingDetector::isSharedHosting()) {
            $baseInterval = 900; // 15 minuti
        }

        if (! isset($schedules['fpdms_5min'])) {
            $schedules['fpdms_5min'] = [
                'interval' => $baseInterval,
                'display' => __('FP-DMS Optimized Interval', 'fp-dms'),
            ];
        }

        // Intervallo per task pesanti (1 ora su shared, 30 min altrimenti)
        $heavyInterval = HostingDetector::isSharedHosting() ? HOUR_IN_SECONDS : (30 * MINUTE_IN_SECONDS);
        
        if (! isset($schedules['fpdms_heavy'])) {
            $schedules['fpdms_heavy'] = [
                'interval' => $heavyInterval,
                'display' => __('FP-DMS Heavy Tasks', 'fp-dms'),
            ];
        }

        return $schedules;
    }
}
