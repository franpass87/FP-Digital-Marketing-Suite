<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

/**
 * Rileva il tipo di hosting per ottimizzare le performance
 */
class HostingDetector
{
    /**
     * Cache della rilevazione hosting
     */
    private static ?string $hostingType = null;

    /**
     * Rileva se siamo su shared hosting
     */
    public static function isSharedHosting(): bool
    {
        return self::getHostingType() === 'shared';
    }

    /**
     * Rileva il tipo di hosting
     * 
     * @return string 'shared', 'vps', 'dedicated', o 'unknown'
     */
    public static function getHostingType(): string
    {
        if (self::$hostingType !== null) {
            return self::$hostingType;
        }

        // Check per variabili d'ambiente comuni su shared hosting
        $sharedIndicators = [
            // Memoria limitata (< 256MB indica shared hosting)
            self::hasLimitedMemory(),
            // Presenza di suhosin o altre restrizioni
            extension_loaded('suhosin'),
            // Max execution time molto basso (< 60s)
            self::hasLowExecutionTime(),
            // Disable functions attivo
            self::hasFunctionRestrictions(),
        ];

        $sharedCount = count(array_filter($sharedIndicators));

        // Se 2 o più indicatori sono presenti, consideriamo shared hosting
        if ($sharedCount >= 2) {
            self::$hostingType = 'shared';
        } elseif ($sharedCount === 1) {
            self::$hostingType = 'vps';
        } else {
            self::$hostingType = 'dedicated';
        }

        return self::$hostingType;
    }

    /**
     * Controlla se la memoria è limitata
     */
    private static function hasLimitedMemory(): bool
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return false; // Illimitata
        }

        $bytes = self::convertToBytes($memoryLimit);
        // Meno di 256MB indica shared hosting
        return $bytes < (256 * 1024 * 1024);
    }

    /**
     * Controlla se il tempo di esecuzione è basso
     */
    private static function hasLowExecutionTime(): bool
    {
        $maxExecution = (int) ini_get('max_execution_time');
        // 0 significa illimitato
        if ($maxExecution === 0) {
            return false;
        }
        // Meno di 60 secondi indica shared hosting
        return $maxExecution < 60;
    }

    /**
     * Controlla se ci sono restrizioni sulle funzioni
     */
    private static function hasFunctionRestrictions(): bool
    {
        $disabled = ini_get('disable_functions');
        if (empty($disabled)) {
            return false;
        }

        // Funzioni critiche comunemente disabilitate su shared hosting
        $criticalFunctions = ['exec', 'shell_exec', 'system', 'proc_open'];
        $disabledArray = array_map('trim', explode(',', $disabled));

        foreach ($criticalFunctions as $func) {
            if (in_array($func, $disabledArray, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Converte stringa memoria in bytes
     */
    private static function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
                // no break
            case 'm':
                $value *= 1024;
                // no break
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Ottieni intervallo cron raccomandato in secondi
     * 
     * @param int $defaultInterval Intervallo di default
     * @return int Intervallo ottimizzato
     */
    public static function getRecommendedCronInterval(int $defaultInterval): int
    {
        if (self::isSharedHosting()) {
            // Su shared hosting, rallenta i cron di almeno 4x
            return max($defaultInterval * 4, 3600); // Minimo 1 ora
        }

        return $defaultInterval;
    }
}

