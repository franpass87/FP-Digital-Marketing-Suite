<?php
/**
 * Script per risolvere il problema dei dati che rimangono sempre a 0
 */

echo "<h1>🔧 Risoluzione Problema Dati a 0</h1>\n";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    .step { background: #f9f9f9; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba; }
    .code { background: #f5f5f5; padding: 10px; border-radius: 3px; font-family: monospace; }
</style>\n";

echo "<div class='debug-section'>";
echo "<h2>🎯 SOLUZIONE: Dati sempre a 0</h2>";

echo "<div class='step'>";
echo "<h3>📋 Passo 1: Verifica Configurazione Data Sources</h3>";
echo "<p>Il problema più comune è che non ci sono data source configurati o attivi.</p>";
echo "<ol>";
echo "<li><strong>Vai alla pagina di amministrazione del plugin</strong></li>";
echo "<li><strong>Naviga alla sezione 'Data Sources' o 'Connettori'</strong></li>";
echo "<li><strong>Verifica che ci siano data source configurati</strong></li>";
echo "<li><strong>Se non ce ne sono, aggiungi almeno uno:</strong></li>";
echo "<ul>";
echo "<li>Google Analytics 4 (GA4)</li>";
echo "<li>Google Search Console (GSC)</li>";
echo "<li>Google Ads</li>";
echo "<li>Meta Ads (Facebook/Instagram)</li>";
echo "</ul>";
echo "</ol>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>🔑 Passo 2: Configurazione Credenziali</h3>";
echo "<p>Per ogni data source, devi configurare le credenziali di autenticazione:</p>";

echo "<h4>Google Analytics 4 (GA4):</h4>";
echo "<ul>";
echo "<li>Service Account JSON (scarica da Google Cloud Console)</li>";
echo "<li>Property ID (formato: 123456789)</li>";
echo "<li>Verifica che il Service Account abbia accesso alla proprietà GA4</li>";
echo "</ul>";

echo "<h4>Google Search Console (GSC):</h4>";
echo "<ul>";
echo "<li>Service Account JSON (stesso del GA4)</li>";
echo "<li>Site URL (es: https://example.com)</li>";
echo "<li>Verifica che il sito sia verificato in GSC</li>";
echo "</ul>";

echo "<h4>Google Ads:</h4>";
echo "<ul>";
echo "<li>Developer Token</li>";
echo "<li>Client ID</li>";
echo "<li>Client Secret</li>";
echo "<li>Refresh Token</li>";
echo "</ul>";

echo "<h4>Meta Ads:</h4>";
echo "<ul>";
echo "<li>App ID</li>";
echo "<li>App Secret</li>";
echo "<li>Access Token</li>";
echo "<li>Ad Account ID</li>";
echo "</ul>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>✅ Passo 3: Test Connessioni</h3>";
echo "<p>Per ogni data source configurato:</p>";
echo "<ol>";
echo "<li><strong>Testa la connessione</strong> usando il pulsante 'Test Connection'</li>";
echo "<li><strong>Verifica che il test sia positivo</strong></li>";
echo "<li><strong>Se il test fallisce, controlla:</strong></li>";
echo "<ul>";
echo "<li>Credenziali corrette</li>";
echo "<li>Permessi sufficienti</li>";
echo "<li>Connessione internet</li>";
echo "<li>Rate limiting delle API</li>";
echo "</ul>";
echo "</ol>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>🔄 Passo 4: Attivazione e Sincronizzazione</h3>";
echo "<p>Dopo aver configurato e testato le connessioni:</p>";
echo "<ol>";
echo "<li><strong>Attiva tutti i data source</strong> configurati</li>";
echo "<li><strong>Esegui una sincronizzazione manuale</strong></li>";
echo "<li><strong>Verifica che i dati vengano recuperati</strong></li>";
echo "<li><strong>Controlla i log per eventuali errori</strong></li>";
echo "</ol>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>🛠️ Passo 5: Debug Avanzato</h3>";
echo "<p>Se i dati rimangono ancora a 0, usa questi strumenti di debug:</p>";

echo "<h4>Strumenti di Debug Disponibili:</h4>";
echo "<ul>";
echo "<li><strong>Pagina Debug:</strong> Vai alla pagina di debug del plugin</li>";
echo "<li><strong>Test Provider:</strong> Usa la funzione di test per ogni provider</li>";
echo "<li><strong>Log Sistema:</strong> Controlla i log per errori specifici</li>";
echo "<li><strong>Sincronizzazione Manuale:</strong> Forza una sincronizzazione</li>";
echo "</ul>";

echo "<h4>Comandi CLI (se disponibili):</h4>";
echo "<div class='code'>";
echo "# Test data sources<br>";
echo "php cli.php debug:data-sources<br><br>";
echo "# Sincronizzazione forzata<br>";
echo "php cli.php sync:all<br><br>";
echo "# Test provider specifico<br>";
echo "php cli.php test:provider ga4<br>";
echo "</div>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>📊 Passo 6: Verifica Frontend</h3>";
echo "<p>Se i data source funzionano ma i dati rimangono a 0 nel frontend:</p>";
echo "<ol>";
echo "<li><strong>Verifica endpoint API:</strong> Controlla che il frontend chiami l'endpoint corretto</li>";
echo "<li><strong>Controlla console browser:</strong> Cerca errori JavaScript</li>";
echo "<li><strong>Verifica permessi utente:</strong> Assicurati che l'utente abbia accesso ai dati</li>";
echo "<li><strong>Pulisci cache:</strong> Svuota la cache del browser e del plugin</li>";
echo "</ol>";
echo "</div>";

echo "<div class='step'>";
echo "<h3>🚨 Problemi Comuni e Soluzioni</h3>";

echo "<h4>Problema: 'Nessun data source configurato'</h4>";
echo "<p><strong>Soluzione:</strong> Aggiungi almeno un data source e configuralo correttamente.</p>";

echo "<h4>Problema: 'Data source disattivati'</h4>";
echo "<p><strong>Soluzione:</strong> Attiva tutti i data source configurati.</p>";

echo "<h4>Problema: 'Test di connessione fallito'</h4>";
echo "<p><strong>Soluzione:</strong> Verifica credenziali e permessi.</p>";

echo "<h4>Problema: 'Dati sincronizzati ma frontend mostra 0'</h4>";
echo "<p><strong>Soluzione:</strong> Controlla endpoint API e permessi frontend.</p>";

echo "<h4>Problema: 'Rate limiting API'</h4>";
echo "<p><strong>Soluzione:</strong> Aspetta e riprova, o controlla i limiti delle API.</p>";
echo "</div>";

echo "</div>";

echo "<div class='debug-section'>";
echo "<h2>📋 Checklist Finale</h2>";

echo "<h3>Verifica Configurazione:</h3>";
echo "<ul>";
echo "<li>□ Almeno un data source configurato</li>";
echo "<li>□ Data source attivato</li>";
echo "<li>□ Credenziali di autenticazione valide</li>";
echo "<li>□ Test di connessione positivo</li>";
echo "<li>□ Sincronizzazione eseguita</li>";
echo "<li>□ Dati presenti nel summary</li>";
echo "<li>□ Periodo di dati corretto</li>";
echo "<li>□ Permessi API sufficienti</li>";
echo "</ul>";

echo "<h3>Verifica Frontend:</h3>";
echo "<ul>";
echo "<li>□ Endpoint API corretti</li>";
echo "<li>□ Nessun errore JavaScript</li>";
echo "<li>□ Permessi utente corretti</li>";
echo "<li>□ Cache aggiornata</li>";
echo "</ul>";

echo "<h3>Se tutto è configurato correttamente:</h3>";
echo "<p>Il problema potrebbe essere nel frontend. Controlla:</p>";
echo "<ul>";
echo "<li>Console del browser per errori</li>";
echo "<li>Network tab per richieste API fallite</li>";
echo "<li>Permessi utente per l'accesso ai dati</li>";
echo "<li>Configurazione del periodo di dati</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><em>Guida di risoluzione completata il " . date('Y-m-d H:i:s') . "</em></p>";
?>
