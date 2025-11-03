<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Migrations;

use FP\DMS\Infra\DB;

/**
 * Migrazione: Aggiunge la colonna 'description' alla tabella clients
 * 
 * Questa migrazione aggiunge il campo per la descrizione business del cliente
 * che verrà utilizzato dall'AI per generare report contestualizzati.
 */
class AddClientDescriptionColumn
{
    public static function run(): bool
    {
        global $wpdb;
        
        $table = DB::table('clients');
        
        // Verifica se la colonna esiste già
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $column = $wpdb->get_results(
            "SHOW COLUMNS FROM {$table} LIKE 'description'"
        );
        
        // Se la colonna esiste già, non fare nulla
        if (!empty($column)) {
            return true;
        }
        
        // Aggiungi la colonna dopo 'notes'
        $sql = "ALTER TABLE {$table} ADD COLUMN description LONGTEXT NULL AFTER notes";
        
        $result = $wpdb->query($sql);
        
        return $result !== false;
    }
}

