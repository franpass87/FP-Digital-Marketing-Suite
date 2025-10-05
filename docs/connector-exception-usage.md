# ConnectorException - Usage Guide

## Overview

`ConnectorException` è una nuova classe di eccezione che fornisce una gestione strutturata degli errori nei connettori, con supporto per informazioni di contesto dettagliate e metodi factory per scenari comuni.

## Benefici

✅ **Gestione errori strutturata**: Ogni eccezione include contesto debuggabile  
✅ **Logging migliorato**: Context array facilita il logging strutturato  
✅ **Codici HTTP semantici**: Usa codici standard (401, 429, 500, etc.)  
✅ **Factory methods**: Metodi dedicati per scenari comuni  
✅ **Exception chaining**: Supporto nativo per exception wrapping  

---

## Esempi di Utilizzo

### 1. Autenticazione Fallita

```php
// Prima (senza ConnectorException)
if (!$json || !$propertyId) {
    return ConnectionResult::failure(__('Missing service account or property ID.', 'fp-dms'));
}

// Dopo (con ConnectorException)
use FP\DMS\Services\Connectors\ConnectorException;

if (!$json || !$propertyId) {
    throw ConnectorException::authenticationFailed(
        'ga4',
        'Missing service account or property ID',
        [
            'has_json' => !empty($json),
            'has_property_id' => !empty($propertyId),
            'client_id' => $this->config['client_id'] ?? 'none',
        ]
    );
}
```

### 2. Chiamata API Fallita

```php
// Prima
$response = Wp::remotePost($url, $args);
if (Wp::isWpError($response)) {
    Logger::error('API call failed: ' . Wp::wpErrorMessage($response));
    return [];
}

// Dopo
try {
    $response = Wp::remotePost($url, $args);
    if (Wp::isWpError($response)) {
        throw ConnectorException::apiCallFailed(
            'meta_ads',
            $url,
            0,
            Wp::wpErrorMessage($response),
            [
                'method' => 'POST',
                'account_id' => $this->config['account_id'],
            ]
        );
    }
    
    $status = Wp::remoteRetrieveResponseCode($response);
    if ($status >= 400) {
        throw ConnectorException::apiCallFailed(
            'meta_ads',
            $url,
            $status,
            Wp::remoteRetrieveBody($response),
            ['response_headers' => wp_remote_retrieve_headers($response)]
        );
    }
} catch (ConnectorException $e) {
    Logger::error('Meta Ads API error', $e->getContext());
    throw $e;
}
```

### 3. Configurazione Non Valida

```php
// Prima
$customerId = trim((string) ($this->config['customer_id'] ?? ''));
if ($customerId === '' || !preg_match('/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/', $customerId)) {
    return ConnectionResult::failure(__('Enter a valid Google Ads customer ID', 'fp-dms'));
}

// Dopo
$customerId = trim((string) ($this->config['customer_id'] ?? ''));
if ($customerId === '') {
    throw ConnectorException::invalidConfiguration(
        'google_ads',
        'Customer ID is required',
        ['provided_fields' => array_keys($this->config)]
    );
}

if (!preg_match('/^[0-9]{3}-[0-9]{3}-[0-9]{4}$/', $customerId)) {
    throw ConnectorException::validationFailed(
        'google_ads',
        'customer_id',
        'Must be in format 000-000-0000',
        ['provided_value' => $customerId]
    );
}
```

### 4. Rate Limiting

```php
// Nuovo pattern (prima non implementato)
use FP\DMS\Infra\RateLimiter;

$limiter = new RateLimiter('ga4', 100, 60); // 100 req/min

if (!$limiter->attempt()) {
    throw ConnectorException::rateLimitExceeded(
        'ga4',
        $limiter->waitTime(),
        [
            'property_id' => $this->config['property_id'],
            'requests_made' => $limiter->getRequestCount(),
        ]
    );
}
```

### 5. Validazione Dati

```php
// Prima
$date = self::normalizeDate($row['date'] ?? '');
if (!$date) {
    continue; // Silently skip invalid rows
}

// Dopo
$date = self::normalizeDate($row['date'] ?? '');
if (!$date) {
    throw ConnectorException::validationFailed(
        'csv_generic',
        'date',
        'Invalid date format',
        [
            'provided_value' => $row['date'] ?? null,
            'row_index' => $index,
            'expected_format' => 'Y-m-d or parseable date string',
        ]
    );
}
```

### 6. Wrapping Exception Esistenti

```php
// Cattura e wrappa eccezioni di terze parti
try {
    $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
} catch (\JsonException $e) {
    throw new ConnectorException(
        'Failed to parse JSON response from Meta Ads API',
        [
            'provider' => 'meta_ads',
            'error' => $e->getMessage(),
            'json_preview' => substr($json, 0, 200),
        ],
        500,
        $e // Exception chaining
    );
}
```

---

## Pattern Consigliati per Provider

### Pattern 1: Validazione nel Constructor

```php
class GA4Provider implements DataSourceProviderInterface
{
    public function __construct(private array $auth, private array $config)
    {
        $this->validateConfiguration();
    }

    private function validateConfiguration(): void
    {
        $propertyId = $this->config['property_id'] ?? '';
        if (empty($propertyId)) {
            throw ConnectorException::invalidConfiguration(
                'ga4',
                'Property ID is required',
                ['provided_config' => array_keys($this->config)]
            );
        }

        if (!is_numeric($propertyId)) {
            throw ConnectorException::validationFailed(
                'ga4',
                'property_id',
                'Must be numeric',
                ['provided_value' => $propertyId]
            );
        }
    }
}
```

### Pattern 2: Try-Catch in fetchMetrics

```php
public function fetchMetrics(Period $period): array
{
    try {
        $json = $this->resolveServiceAccount();
        $client = ServiceAccountHttpClient::fromJson($json);
        
        if (!$client) {
            throw ConnectorException::authenticationFailed(
                'ga4',
                'Failed to initialize HTTP client',
                ['has_json' => !empty($json)]
            );
        }

        $response = $client->postJson($url, $body, $scopes);
        
        if (!$response['ok']) {
            throw ConnectorException::apiCallFailed(
                'ga4',
                $url,
                $response['status'],
                $response['message'] ?? '',
                ['response_body' => $response['body']]
            );
        }

        return $this->parseResponse($response['json']);
        
    } catch (ConnectorException $e) {
        // Log with full context
        Logger::error('GA4 metrics fetch failed', array_merge(
            $e->getContext(),
            [
                'period_start' => $period->start->format('Y-m-d'),
                'period_end' => $period->end->format('Y-m-d'),
            ]
        ));
        
        // Re-throw per gestione upstream
        throw $e;
    } catch (\Exception $e) {
        // Wrap unexpected exceptions
        throw new ConnectorException(
            'Unexpected error fetching GA4 metrics',
            [
                'provider' => 'ga4',
                'error_class' => get_class($e),
                'error_message' => $e->getMessage(),
            ],
            500,
            $e
        );
    }
}
```

### Pattern 3: Gestione Graceful con Fallback

```php
public function fetchMetrics(Period $period): array
{
    try {
        return $this->fetchMetricsFromApi($period);
    } catch (ConnectorException $e) {
        Logger::warning('Failed to fetch from API, using cached data', $e->getContext());
        
        // Fallback su dati cache
        $cached = $this->getCachedMetrics($period);
        if ($cached !== null) {
            return $cached;
        }
        
        // Se neanche cache disponibile, rilancia
        throw $e;
    }
}
```

---

## Integrazione con Logger

```php
// In un catch block
catch (ConnectorException $e) {
    Logger::error($e->getMessage(), array_merge(
        $e->getContext(),
        [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]
    ));
}

// Nel logger, il context array viene formattato come JSON
// Output log:
// [2024-10-05 10:30:00] ERROR: API call to meta_ads failed (HTTP 429): Rate limit exceeded
// Context: {"provider":"meta_ads","endpoint":"/insights","status_code":429,"account_id":"act_123"}
```

---

## Migration Checklist

Per migrare un provider esistente:

- [ ] Aggiungere `use FP\DMS\Services\Connectors\ConnectorException;`
- [ ] Identificare tutti i punti di errore
- [ ] Sostituire `return ConnectionResult::failure()` con throw in `testConnection()`
- [ ] Wrappare API calls con try-catch
- [ ] Aggiungere validazione nel constructor
- [ ] Aggiungere context utile in ogni exception
- [ ] Aggiungere test per scenari di errore
- [ ] Verificare che logging funzioni correttamente

---

## Factory Methods Reference

### `authenticationFailed()`
**Quando usare**: Credenziali mancanti, non valide, o scadute  
**HTTP Code**: 401  
**Context suggerito**: provider, has_credentials, account_info

### `apiCallFailed()`
**Quando usare**: Chiamata API fallisce (network, 4xx, 5xx)  
**HTTP Code**: Quello dell'API (o 0 per errori network)  
**Context suggerito**: provider, endpoint, status_code, response_body

### `invalidConfiguration()`
**Quando usare**: Configurazione mancante o mal formata  
**HTTP Code**: 400  
**Context suggerito**: provider, required_fields, provided_fields

### `rateLimitExceeded()`
**Quando usare**: Limite di rate API superato  
**HTTP Code**: 429  
**Context suggerito**: provider, retry_after, requests_made

### `validationFailed()`
**Quando usare**: Validazione input fallisce  
**HTTP Code**: 422  
**Context suggerito**: provider, field, provided_value, expected_format

---

## Testing

```php
// Test che l'exception viene lanciata
public function testFetchMetricsThrowsWhenAuthFails(): void
{
    $this->expectException(ConnectorException::class);
    $this->expectExceptionCode(401);
    
    $provider = new GA4Provider([], ['property_id' => '123']);
    $period = Period::fromStrings('2024-01-01', '2024-01-31', 'UTC');
    
    $provider->fetchMetrics($period);
}

// Test che il context sia corretto
public function testExceptionIncludesProperContext(): void
{
    try {
        $provider = new GA4Provider([], []);
        $period = Period::fromStrings('2024-01-01', '2024-01-31', 'UTC');
        $provider->fetchMetrics($period);
        
        $this->fail('Expected ConnectorException to be thrown');
    } catch (ConnectorException $e) {
        $context = $e->getContext();
        $this->assertSame('ga4', $context['provider']);
        $this->assertArrayHasKey('has_json', $context);
    }
}
```

---

## Best Practices

1. ✅ **Includi sempre context rilevante** - Facilita il debugging
2. ✅ **Usa factory methods quando possibile** - Consistency
3. ✅ **Logga prima di ri-lanciare** - Traccia completa
4. ✅ **Wrappa exception di terze parti** - Context uniforme
5. ✅ **Testa scenari di errore** - Affidabilità
6. ❌ **Non catchare e ignorare** - Gestisci esplicitamente
7. ❌ **Non usare exception per control flow** - Solo per errori
8. ❌ **Non esporre dati sensibili nel message** - Usa context privato

---

## Prossimi Passi

1. Creare test per ConnectorException ✅
2. Migrare GA4Provider per usare ConnectorException
3. Migrare GSCProvider per usare ConnectorException
4. Aggiungere logging strutturato in tutti i provider
5. Creare dashboard per monitorare errori connettori

---

**Documento creato**: 2025-10-05  
**Versione**: 1.0
