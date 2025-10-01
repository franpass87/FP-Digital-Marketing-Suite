# Plugin Audit Report — FP Digital Marketing Suite — 2025-10-01

## Summary
- Files scanned: 103/103
- Issues found: 3 (Critical: 0 | High: 2 | Medium: 1 | Low: 0)
- Key risks:
  - CSV-backed connectors ignore the requested reporting window, so overview totals and PDF reports aggregate stale data from previous periods.
  - Cron fallback URL exposed in the UI calls a REST route that only accepts POST, so common GET-based cron pings will never fire the queue.
  - Sensitive connector credentials and routing secrets are stored in plaintext whenever libsodium is missing, which is common on shared hosts.
- Recommended priorities: 1) ISSUE-003 2) ISSUE-001 3) ISSUE-002

## Manifest mismatch
- Stored manifest hash `5d05c7b9cde94d6cfed7a478f7d24cc0c098686fbd334b2184d08a53764d6c04` differed from the current tree hash `c3c866f78cc7011cd260e1e54f62882ce15a76a7e505966b5e86c001d8d5ef4a` (generated via `git ls-files`).
- Regenerated the manifest hash to continue the audit; no unexpected source files were discovered beyond the tracked repository contents.

## Issues
### [High] Cron fallback URL uses GET but REST route requires POST
- ID: ISSUE-001
- File: src/Http/Routes.php:42
- Snippet:
  ```php
  register_rest_route('fpdms/v1', '/tick', [
      'methods' => 'POST',
      'callback' => [self::class, 'handleTick'],
      'permission_callback' => '__return_true',
  ]);
  ```
  ```php
  $tickUrl = esc_url_raw(rest_url('fpdms/v1/tick?key=' . $settings['tick_key']));
  echo '<p><strong>' . esc_html__('Cron Fallback Endpoint:', 'fp-dms') . '</strong> <code>' . esc_html($tickUrl) . '</code></p>';
  ```

Diagnosis: The REST route that processes the fallback cron tick only whitelists the POST verb, yet the Settings page prints a GET URL (with the key as a query string) for operators to call. Remote cron services and browser tests that follow the guidance will issue GET requests and always receive a 404/405, so the queue never runs.

Impact: Functional — remote cron integrations silently fail, preventing scheduled report generation and notification dispatch on sites that rely on the documented fallback URL.

Repro steps (se applicabile):
1. Copy the "Cron Fallback Endpoint" value from the settings page.
2. Execute a GET request to that URL (the default behaviour of most cron services).
3. Observe the REST API returns "No route was found" and the queue never ticks.

Proposed fix (concise):

Allow the tick route to accept safe GET requests in addition to POST (while keeping the key check) or update the generated URL to explicitly require POST. For example:
```php
register_rest_route('fpdms/v1', '/tick', [
    'methods' => ['GET', 'POST'],
    'callback' => [self::class, 'handleTick'],
    'permission_callback' => '__return_true',
]);
```

Side effects / Regression risk: Low — the handler already validates the shared key and rate-limits calls; accepting GET simply aligns with the documented usage.

Est. effort: S

Tags: #cron #rest #scheduling #ux

### [Medium] Secrets stored in plaintext when libsodium is unavailable
- ID: ISSUE-002
- File: src/Support/Security.php:16
- Snippet:
  ```php
  public static function encrypt(string $plain): string
  {
      if ($plain === '') {
          return $plain;
      }

      if (! self::isEncryptionAvailable()) {
          return $plain;
      }

      $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
      $cipher = sodium_crypto_secretbox($plain, $nonce, self::getKey());

      return base64_encode($nonce . $cipher);
  }
  ```
  ```php
  $payload = [
      'auth' => Security::encrypt($auth),
      // ...
  ];
  ```

Diagnosis: `Security::encrypt()` returns the plaintext as-is whenever libsodium is missing. On many shared hosts sodium is disabled, so connector credentials and anomaly routing secrets are stored unencrypted in the database, despite the code assuming they are protected.

Impact: Security — database snapshots, backups, or low-privilege admins can read third-party API tokens and SMTP passwords in clear text, breaking the expectation of secure storage.

Repro steps (se applicabile):
1. Install the plugin on a host without the sodium extension.
2. Save a connector credential or routing secret.
3. Inspect the `wp_options` or `wp_fpdms_datasources` table — the values are stored verbatim.

Proposed fix (concise):

Add a hardened fallback encryption path (e.g. AES-256 via OpenSSL with a key derived from `Wp::salt()`) and refuse to return plaintext. For example:
```php
if (! self::isEncryptionAvailable()) {
    if (function_exists('openssl_encrypt')) {
        $key = hash('sha256', self::getKey(), true);
        $iv  = random_bytes(16);
        $cipher = openssl_encrypt($plain, 'aes-256-ctr', $key, OPENSSL_RAW_DATA, $iv);
        if ($cipher !== false) {
            return base64_encode($iv . $cipher);
        }
    }
    throw new RuntimeException('Secure credential storage requires libsodium or OpenSSL.');
}
```
Update `decrypt()` accordingly and surface an admin error when neither library is available.

Side effects / Regression risk: Medium — requires thorough testing of credential saves/migrations and appropriate user messaging when encryption cannot be provided.

Est. effort: M

Tags: #security #encryption #credentials

### [High] CSV connectors ignore requested period and inflate overview totals
- ID: ISSUE-003
- File: src/Services/Connectors/GoogleAdsProvider.php:40
- Snippet:
  ```php
  foreach ($summary['daily'] as $date => $metrics) {
      if (! is_array($metrics)) {
          continue;
      }
      $dateString = (string) $date;
      if ($dateString === 'total') {
          $dateString = $period->end->format('Y-m-d');
      }
      $rows[] = Normalizer::ensureKeys(array_merge(
          ['source' => 'google_ads', 'date' => $dateString],
          self::mapMetrics($metrics)
      ));
  }
  ```
  ```php
  $normalizedDate = $this->safeDate($date, $period->end);
  if (! $normalizedDate) {
      continue;
  }

  if (! isset($daily[$normalizedDate])) {
      $daily[$normalizedDate] = array_fill_keys(array_keys(self::KPI_MAP), 0.0);
  }

  foreach ($metrics as $metric => $value) {
      $daily[$normalizedDate][$metric] = ($daily[$normalizedDate][$metric] ?? 0.0) + (float) $value;
      $totals[$metric] = ($totals[$metric] ?? 0.0) + (float) $value;
  }
  ```

Diagnosis: All CSV-based connectors (`GoogleAdsProvider`, `MetaAdsProvider`, `ClarityCsvProvider`, `CsvGenericProvider`) iterate every cached `summary['daily']` row without checking whether the date falls inside the requested `Period`. The overview assembler then sums whatever rows it receives, so cached metrics from previous months bleed into the current reporting window. This causes the KPI totals and anomalies to be computed against inflated historical data rather than the selected range.

Impact: Functional — dashboards and generated reports display incorrect totals and anomalies for the chosen date range, undermining trust in the plugin’s output and masking real regressions.

Repro steps (se applicabile):
1. Import a CSV summary that contains several weeks of data (e.g. July 1–31).
2. Request an overview/report for a narrow window (e.g. July 15–21).
3. Observe that the totals include days outside the requested range.

Proposed fix (concise):

Filter connector rows (and/or guard in `Assembler::collectSeries`) so only dates within `$period->start` and `$period->end` are merged. For example:
```php
if ($dateString < $period->start->format('Y-m-d') || $dateString > $period->end->format('Y-m-d')) {
    continue;
}
```
Apply the same window check across CSV providers before appending to `$rows`, and keep the assembler’s totals in sync by skipping out-of-range dates as a second line of defence.

Side effects / Regression risk: Medium — trimming rows by period changes aggregation logic and requires verifying reports still include fallback zero rows for empty days.

Est. effort: M

Tags: #reports #overview #data-integrity #csv

## Conflicts & Duplicates
_None observed in this batch._

## Deprecated & Compatibility
- Plugin header still lists "Requires at least: 6.4" and "Requires PHP: 8.1" (fp-digital-marketing-suite.php). Update to match the 6.6+/PHP 8.2–8.3 support policy.

## Performance Hotspots
_None observed in this batch._

## i18n & A11y
- Text domain usage appears consistent in the reviewed files.

## Test Coverage (se presente)
- No automated coverage found for REST cron fallback or credential storage paths.

## Next Steps (per fase di FIX)
- Ordine consigliato: ISSUE-003, ISSUE-001, ISSUE-002
- Safe-fix batch plan:
  - Batch 1: Ensure CSV connectors and overview aggregation respect the requested period (ISSUE-003).
  - Batch 2: Align REST tick route methods with documented cron URL (ISSUE-001).
  - Batch 3: Implement secure encryption fallback or blocking behaviour for secrets (ISSUE-002).
  - Batch 4: Add regression tests around the adjusted data windowing and cron/encryption behaviour.
