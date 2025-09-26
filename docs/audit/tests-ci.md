# Phase 8 – Tests & Continuous Integration

## PHPUnit Test Harness
- Updated `phpunit.xml` to exclude long running integration suites by default while still allowing them to run on demand with `composer test:integration`.
- Stabilised the bootstrap by muting third-party deprecation noise and allowing CI to parameterise the simulated WordPress version through the `FP_DMS_WP_VERSION` environment variable.
- Documented a new Composer script family so developers can run unit (`composer test`), coverage (`composer test-coverage`), and integration (`composer test:integration`) checks consistently.

## Continuous Integration
- Expanded the GitHub Actions pipeline to execute the PHPUnit suite across PHP 7.4, 8.1, and 8.2 while toggling WordPress targets 6.5 and 6.6, ensuring cross-version confidence.
- Reused Composer caching between matrix jobs and kept existing linting and security checks intact.

## Coverage Reporting
- Generated a text coverage report via `phpdbg` and stored it at `docs/coverage/coverage.txt` for auditability. The current snapshot shows ~22% line coverage from the fast unit suite, highlighting the next optimisation area.

## Running Tests Locally
```bash
composer install
vendor/bin/phpunit              # fast unit suite (default)
composer test:integration       # optional integration suite
phpdbg -qrr vendor/bin/phpunit --group integration --coverage-text
```
