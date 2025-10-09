# Contributing to FP Digital Marketing Suite

Grazie per l'interesse nel contribuire a FP Digital Marketing Suite! 🎉

---

## 📋 Indice

1. [Come Contribuire](#come-contribuire)
2. [Setup Ambiente di Sviluppo](#setup-ambiente-di-sviluppo)
3. [Standards di Codice](#standards-di-codice)
4. [Testing](#testing)
5. [Pull Request Process](#pull-request-process)
6. [Bug Report](#bug-report)
7. [Feature Request](#feature-request)

---

## Come Contribuire

Ci sono molti modi per contribuire:

- 🐛 **Bug Reports** - Segnala bug o problemi
- ✨ **Feature Requests** - Proponi nuove funzionalità
- 📝 **Documentazione** - Migliora o traduci la documentazione
- 💻 **Code** - Contribuisci con codice
- 🧪 **Testing** - Aiuta a testare nuove features
- 🌐 **Traduzioni** - Traduci in altre lingue

---

## Setup Ambiente di Sviluppo

### Prerequisiti

- PHP 8.1+ (raccomandato 8.2+)
- Composer
- MySQL 5.7+ o MariaDB 10.3+
- Git

### Clone Repository

```bash
git clone https://github.com/franpass87/FP-Digital-Marketing-Suite.git
cd FP-Digital-Marketing-Suite
```

### Installa Dipendenze

```bash
# Dipendenze PHP
composer install

# Dipendenze dev (include PHPUnit, PHPStan)
composer install --dev

# Setup environment
cp env.example .env
# Modifica .env con le tue configurazioni
```

### Crea Database

```bash
# MySQL
mysql -u root -p
CREATE DATABASE fpdms_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fpdms_dev'@'localhost' IDENTIFIED BY 'password';
GRANT ALL PRIVILEGES ON fpdms_dev.* TO 'fpdms_dev'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Esegui migrations
php cli.php db:migrate
```

### Verifica Setup

```bash
# Health check
php tools/health-check.php --verbose

# Run tests
./vendor/bin/phpunit tests

# Static analysis
./vendor/bin/phpstan analyse src --level=5
```

---

## Standards di Codice

### PHP Standards

Seguiamo **PSR-12** con alcune eccezioni:

```php
<?php

declare(strict_types=1);

namespace FP\DMS\Example;

use FP\DMS\Support\Wp;

class ExampleClass
{
    private const CONSTANT_NAME = 'value';

    public function __construct(
        private string $property,
        private int $count
    ) {
    }

    public function exampleMethod(): bool
    {
        // Always use strict types
        $value = (int) $this->property;
        
        // Always validate input
        if ($value < 0) {
            return false;
        }
        
        // Use early returns
        if (!$condition) {
            return false;
        }
        
        return true;
    }
}
```

### Best Practices

1. **Type Safety**
   ```php
   // ✅ GOOD
   public function calculate(int $value): float
   
   // ❌ BAD
   public function calculate($value)
   ```

2. **Error Handling**
   ```php
   // ✅ GOOD
   try {
       $result = $this->riskyOperation();
   } catch (SpecificException $e) {
       Logger::error('Operation failed', ['error' => $e->getMessage()]);
       return null;
   }
   
   // ❌ BAD
   $result = @$this->riskyOperation(); // Suppress errors
   ```

3. **Input Validation**
   ```php
   // ✅ GOOD
   $userId = Wp::absInt($_GET['user_id'] ?? 0);
   if ($userId <= 0) {
       return new WP_Error('invalid_id', 'Invalid user ID');
   }
   
   // ❌ BAD
   $userId = $_GET['user_id'];
   ```

4. **SQL Safety**
   ```php
   // ✅ GOOD
   $sql = $wpdb->prepare(
       "SELECT * FROM {$table} WHERE id = %d AND status = %s",
       $id,
       $status
   );
   if ($sql !== false) {
       $wpdb->query($sql);
   }
   
   // ❌ BAD
   $wpdb->query("SELECT * FROM {$table} WHERE id = {$id}");
   ```

5. **Reference Cleanup**
   ```php
   // ✅ GOOD
   foreach ($array as $key => &$value) {
       $value = transform($value);
   }
   unset($value); // IMPORTANT!
   
   // ❌ BAD
   foreach ($array as $key => &$value) {
       $value = transform($value);
   } // Missing unset()
   ```

### Code Style Check

```bash
# Check code style
./vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix code style
./vendor/bin/php-cs-fixer fix
```

---

## Testing

### Run Tests

```bash
# All tests
./vendor/bin/phpunit tests

# Specific test file
./vendor/bin/phpunit tests/Unit/SecurityTest.php

# With coverage
./vendor/bin/phpunit tests --coverage-html coverage/
```

### Writing Tests

```php
<?php

namespace FP\DMS\Tests\Unit;

use PHPUnit\Framework\TestCase;
use FP\DMS\Support\Security;

class SecurityTest extends TestCase
{
    public function testEncryptionRoundtrip(): void
    {
        $plain = 'sensitive data';
        
        $encrypted = Security::encrypt($plain);
        $this->assertNotEquals($plain, $encrypted);
        
        $failed = false;
        $decrypted = Security::decrypt($encrypted, $failed);
        
        $this->assertFalse($failed);
        $this->assertEquals($plain, $decrypted);
    }
}
```

### Test Coverage

Target: **80%+** coverage

```bash
./vendor/bin/phpunit tests --coverage-text
```

---

## Pull Request Process

### 1. Fork & Branch

```bash
# Fork repository su GitHub
# Clone your fork
git clone https://github.com/YOUR_USERNAME/FP-Digital-Marketing-Suite.git

# Create feature branch
git checkout -b feature/amazing-feature

# O bug fix branch
git checkout -b fix/bug-description
```

### 2. Develop

- Scrivi codice seguendo gli standards
- Aggiungi test per nuove features
- Aggiorna documentazione se necessario
- Esegui tests e static analysis

### 3. Commit

```bash
# Good commit messages
git commit -m "feat: Add anomaly detection z-score algorithm"
git commit -m "fix: Correct SQL injection in search query"
git commit -m "docs: Update deployment guide for Docker"
git commit -m "test: Add unit tests for encryption"

# Bad commit messages
git commit -m "fix stuff"
git commit -m "wip"
git commit -m "updates"
```

**Commit Message Format:**
```
<type>: <description>

[optional body]

[optional footer]
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `style`: Code style (formatting, missing semicolons, etc)
- `refactor`: Code refactoring
- `test`: Adding tests
- `chore`: Maintenance

### 4. Push & Create PR

```bash
git push origin feature/amazing-feature
```

Vai su GitHub e crea Pull Request con:
- **Titolo chiaro**: "feat: Add anomaly detection z-score"
- **Descrizione dettagliata**:
  ```markdown
  ## Summary
  Implements z-score algorithm for anomaly detection
  
  ## Changes
  - Add ZScore class in Services/Anomalies
  - Add unit tests
  - Update documentation
  
  ## Testing
  - [x] Unit tests pass
  - [x] Manual testing on sample data
  - [x] No regression
  
  ## Screenshots (if applicable)
  [screenshots here]
  ```

### 5. Code Review

- Rispondi ai commenti
- Fai modifiche richieste
- Mantieni branch aggiornato con main

```bash
git checkout main
git pull upstream main
git checkout feature/amazing-feature
git rebase main
git push origin feature/amazing-feature --force-with-lease
```

### 6. Checklist PR

- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ] Comments added for complex code
- [ ] Documentation updated
- [ ] Tests added/updated
- [ ] All tests pass
- [ ] No breaking changes (or documented)
- [ ] Commit messages are clear

---

## Bug Report

### Prima di Segnalare

1. Cerca negli [Issues esistenti](https://github.com/franpass87/FP-Digital-Marketing-Suite/issues)
2. Verifica sia l'ultima versione
3. Esegui health check: `php tools/health-check.php`

### Template Bug Report

```markdown
**Descrizione Bug**
Descrizione chiara e concisa del bug.

**Come Riprodurre**
Steps per riprodurre:
1. Vai a '...'
2. Click su '...'
3. Scroll down to '...'
4. Vedi errore

**Comportamento Atteso**
Cosa dovrebbe succedere.

**Comportamento Attuale**
Cosa succede invece.

**Screenshots**
Se applicabile, aggiungi screenshots.

**Ambiente:**
 - OS: [es. Ubuntu 22.04]
 - PHP Version: [es. 8.2.0]
 - MySQL Version: [es. 8.0.33]
 - WordPress Version (se applicabile): [es. 6.4]

**Logs**
```
[Paste relevant logs from storage/logs/error.log]
```

**Health Check Output**
```bash
php tools/health-check.php
[Paste output]
```

**Informazioni Aggiuntive**
Qualsiasi altra informazione utile.
```

---

## Feature Request

### Template Feature Request

```markdown
**Problema da Risolvere**
Descrivi il problema che questa feature risolverebbe.

**Soluzione Proposta**
Descrivi come vorresti che funzionasse.

**Alternative Considerate**
Altre soluzioni che hai valutato.

**Benefit**
- Chi beneficia di questa feature?
- Quali problemi risolve?
- Impatto sul sistema esistente?

**Implementation Ideas**
Se hai idee su come implementarla.

**Esempi**
Screenshots, mockups, o esempi da altri sistemi.

**Priorità**
Low / Medium / High

**Disponibilità a Contribuire**
Saresti disponibile a implementarla?
```

---

## Security Issues

**⚠️ NON creare issue pubblici per vulnerabilità di sicurezza!**

Invece:
1. Email a: security@francescopasseri.com
2. Includi:
   - Descrizione vulnerabilità
   - Steps per riprodurre
   - Impatto potenziale
   - Eventuale PoC

Risponderemo entro 48 ore.

---

## Code of Conduct

### Our Pledge

Ci impegniamo a rendere questo progetto accogliente per tutti.

### Our Standards

**✅ Comportamenti Positivi:**
- Usare linguaggio inclusivo
- Rispettare opinioni diverse
- Accettare critiche costruttive
- Focalizzarsi su ciò che è meglio per la community

**❌ Comportamenti Inaccettabili:**
- Linguaggio o immagini sessualizzati
- Trolling, insulti, o commenti offensivi
- Molestie pubbliche o private
- Publishing others' private information

### Enforcement

Violazioni possono essere segnalate a: info@francescopasseri.com

---

## Development Workflow

### Feature Development

```bash
# 1. Sync with main
git checkout main
git pull origin main

# 2. Create feature branch
git checkout -b feature/my-feature

# 3. Develop
# ... write code ...

# 4. Test
./vendor/bin/phpunit tests
./vendor/bin/phpstan analyse src --level=5
php tools/health-check.php

# 5. Commit
git add .
git commit -m "feat: Add my amazing feature"

# 6. Push
git push origin feature/my-feature

# 7. Create PR on GitHub
```

### Bug Fix Workflow

```bash
# 1. Create fix branch
git checkout -b fix/bug-description

# 2. Write failing test
./vendor/bin/phpunit tests/Unit/MyTest.php
# Test should fail

# 3. Fix bug
# ... fix code ...

# 4. Verify test passes
./vendor/bin/phpunit tests/Unit/MyTest.php
# Test should pass

# 5. Commit & push
git commit -m "fix: Correct issue with X"
git push origin fix/bug-description
```

---

## Resources

### Documentation
- [README.md](./README.md) - Project overview
- [DEPLOYMENT_GUIDE.md](./DEPLOYMENT_GUIDE.md) - Deployment instructions
- [SECURITY_AUDIT_FINAL_2025-10-08.md](./SECURITY_AUDIT_FINAL_2025-10-08.md) - Security audit

### Tools
- [PHPUnit](https://phpunit.de/) - Testing framework
- [PHPStan](https://phpstan.org/) - Static analysis
- [PHP-CS-Fixer](https://github.com/FriendsOfPHP/PHP-CS-Fixer) - Code style

### Communication
- GitHub Issues: Technical discussions
- Email: info@francescopasseri.com

---

## Recognition

Contributors will be recognized in:
- README.md contributors section
- CHANGELOG.md for releases
- GitHub contributors page

---

## License

By contributing, you agree that your contributions will be licensed under the GPL-2.0-or-later License.

---

## Questions?

Non esitare a:
- Aprire una [Discussion](https://github.com/franpass87/FP-Digital-Marketing-Suite/discussions)
- Chiedere nell'issue esistente
- Email a: info@francescopasseri.com

---

**Grazie per contribuire a FP Digital Marketing Suite! 🚀**
