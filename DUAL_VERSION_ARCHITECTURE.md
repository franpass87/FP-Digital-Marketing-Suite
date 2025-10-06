# Architettura Dual-Version: Plugin WordPress + Applicazione Standalone

## 🎯 Obiettivo

Mantenere **due versioni separate ma sincronizzate**:

1. **Plugin WordPress** - Per chi usa WordPress
2. **Applicazione Standalone** - Per chi non usa WordPress

Condividendo il 95% del codice business logic.

## 📁 Struttura Proposta

```
FP Digital Marketing Suite/
│
├── core/                           # CODICE CONDIVISO (95%)
│   ├── Domain/                     # Entità e logica dominio
│   │   ├── Entities/
│   │   ├── Repos/
│   │   └── Templates/
│   ├── Services/                   # Servizi business
│   │   ├── Connectors/
│   │   ├── Reports/
│   │   ├── Anomalies/
│   │   └── Overview/
│   ├── Infra/                      # Infrastruttura condivisa
│   │   ├── Logger.php
│   │   ├── Mailer.php
│   │   ├── Queue.php
│   │   ├── PdfRenderer.php
│   │   ├── Retention.php
│   │   └── Notifiers/
│   └── Support/                    # Utilità
│       ├── Arr.php
│       ├── Dates.php
│       ├── Period.php
│       ├── Security.php
│       └── Validation.php
│
├── wordpress/                      # PLUGIN WORDPRESS (5%)
│   ├── fp-digital-marketing-suite.php
│   ├── src/
│   │   ├── Adapters/              # Adapter WordPress-specifici
│   │   │   ├── WordPressDatabase.php
│   │   │   ├── WordPressConfig.php
│   │   │   └── WordPressAuth.php
│   │   ├── Admin/                 # UI WordPress Admin
│   │   │   ├── Menu.php
│   │   │   └── Pages/
│   │   ├── Cli/                   # WP-CLI commands
│   │   │   └── Commands.php
│   │   └── Http/                  # WordPress REST routes
│   │       └── Routes.php
│   ├── assets/                    # Asset WordPress
│   ├── readme.txt                 # WordPress plugin readme
│   └── composer.json              # Dipendenze WordPress
│
├── standalone/                     # APP STANDALONE (5%)
│   ├── public/
│   │   └── index.php
│   ├── cli.php
│   ├── src/
│   │   ├── Adapters/              # Adapter standalone-specifici
│   │   │   ├── PDODatabase.php
│   │   │   ├── EnvConfig.php
│   │   │   └── SessionAuth.php
│   │   ├── App/                   # Bootstrap standalone
│   │   │   ├── Bootstrap.php
│   │   │   ├── Router.php
│   │   │   └── CommandRegistry.php
│   │   ├── Controllers/           # HTTP controllers
│   │   ├── Commands/              # Symfony Console commands
│   │   └── Middleware/            # HTTP middleware
│   ├── storage/
│   ├── .env.example
│   └── composer.json              # Dipendenze standalone
│
├── shared/                         # CONFIGURAZIONE CONDIVISA
│   ├── composer-core.json         # Dipendenze core
│   └── tests/                     # Test condivisi
│
├── build/                          # SCRIPT DI BUILD
│   ├── build-wordpress.sh         # Genera plugin WordPress
│   ├── build-standalone.sh        # Genera app standalone
│   └── sync.sh                    # Sincronizza core
│
├── dist/                           # OUTPUT BUILD
│   ├── wordpress/                 # Plugin pronto per distribuzione
│   └── standalone/                # App pronta per distribuzione
│
└── docs/                           # DOCUMENTAZIONE
    ├── WORDPRESS_README.md
    ├── STANDALONE_README.md
    └── DEVELOPMENT.md
```

## 🔑 Principi Chiave

### 1. Separation of Concerns

**Core (Condiviso)**
- Business logic pura
- Nessuna dipendenza da WordPress o framework specifici
- Usa interfacce per dipendenze esterne

**Adapters (Specifici)**
- Implementano le interfacce del core
- Gestiscono integrazione con WordPress/Standalone
- Mappano funzionalità piattaforma-specifiche

### 2. Dependency Injection

```php
// Core business logic usa interfacce
namespace FP\DMS\Core\Services;

interface DatabaseInterface {
    public function get_results(string $query): array;
    public function insert(string $table, array $data): int;
}

interface ConfigInterface {
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
}

class ReportGenerator {
    public function __construct(
        private DatabaseInterface $db,
        private ConfigInterface $config
    ) {}
    
    // Business logic non sa se è WordPress o Standalone!
}
```

**WordPress Adapter**
```php
namespace FP\DMS\WordPress\Adapters;

class WordPressDatabase implements DatabaseInterface {
    public function get_results(string $query): array {
        global $wpdb;
        return $wpdb->get_results($query);
    }
}

class WordPressConfig implements ConfigInterface {
    public function get(string $key, mixed $default = null): mixed {
        return get_option($key, $default);
    }
}
```

**Standalone Adapter**
```php
namespace FP\DMS\Standalone\Adapters;

class PDODatabase implements DatabaseInterface {
    private PDO $pdo;
    
    public function get_results(string $query): array {
        $stmt = $this->pdo->query($query);
        return $stmt->fetchAll();
    }
}

class EnvConfig implements ConfigInterface {
    public function get(string $key, mixed $default = null): mixed {
        return $_ENV[$key] ?? $default;
    }
}
```

## 🔧 Sistema di Build

### Script di Sincronizzazione

```bash
# build/sync.sh
#!/bin/bash

# Sincronizza core in entrambe le versioni
rsync -av --delete core/ wordpress/vendor/fp/dms-core/
rsync -av --delete core/ standalone/vendor/fp/dms-core/

echo "Core synchronized!"
```

### Build Plugin WordPress

```bash
# build/build-wordpress.sh
#!/bin/bash

echo "Building WordPress plugin..."

# Crea directory dist
mkdir -p dist/wordpress

# Copia core
cp -r core dist/wordpress/src/Core

# Copia WordPress specifico
cp -r wordpress/* dist/wordpress/

# Installa dipendenze WordPress
cd dist/wordpress
composer install --no-dev --optimize-autoloader

# Crea ZIP per distribuzione
cd ..
zip -r fp-digital-marketing-suite.zip wordpress/

echo "WordPress plugin built: dist/fp-digital-marketing-suite.zip"
```

### Build Standalone

```bash
# build/build-standalone.sh
#!/bin/bash

echo "Building Standalone application..."

# Crea directory dist
mkdir -p dist/standalone

# Copia core
cp -r core dist/standalone/src/Core

# Copia standalone specifico
cp -r standalone/* dist/standalone/

# Installa dipendenze standalone
cd dist/standalone
composer install --no-dev --optimize-autoloader

echo "Standalone app built: dist/standalone/"
```

## 📦 Composer Configuration

### core/composer.json (Core condiviso)

```json
{
  "name": "fp/dms-core",
  "description": "FP Digital Marketing Suite - Core Business Logic",
  "type": "library",
  "require": {
    "php": ">=8.1",
    "mpdf/mpdf": "^8.2",
    "phpmailer/phpmailer": "^6.9",
    "guzzlehttp/guzzle": "^7.8"
  },
  "autoload": {
    "psr-4": {
      "FP\\DMS\\Core\\": "."
    }
  }
}
```

### wordpress/composer.json

```json
{
  "name": "fp/digital-marketing-suite-wordpress",
  "description": "FP Digital Marketing Suite - WordPress Plugin",
  "type": "wordpress-plugin",
  "require": {
    "php": ">=8.1",
    "fp/dms-core": "@dev"
  },
  "repositories": [
    {
      "type": "path",
      "url": "../core"
    }
  ],
  "autoload": {
    "psr-4": {
      "FP\\DMS\\WordPress\\": "src/"
    }
  }
}
```

### standalone/composer.json

```json
{
  "name": "fp/digital-marketing-suite-standalone",
  "description": "FP Digital Marketing Suite - Standalone Application",
  "type": "project",
  "require": {
    "php": ">=8.1",
    "fp/dms-core": "@dev",
    "slim/slim": "^4.12",
    "slim/psr7": "^1.6",
    "symfony/console": "^6.3",
    "vlucas/phpdotenv": "^5.5"
  },
  "repositories": [
    {
      "type": "path",
      "url": "../core"
    }
  ],
  "autoload": {
    "psr-4": {
      "FP\\DMS\\Standalone\\": "src/"
    }
  }
}
```

## 🔄 Workflow di Sviluppo

### 1. Sviluppo Feature Nuova

```bash
# 1. Implementa logica in core/
vim core/Services/NewFeature.php

# 2. Testa in entrambe le versioni
cd wordpress && composer update fp/dms-core
cd ../standalone && composer update fp/dms-core

# 3. Adatta UI per ogni versione
# WordPress:
vim wordpress/src/Admin/Pages/NewFeaturePage.php

# Standalone:
vim standalone/src/Controllers/NewFeatureController.php

# 4. Build per distribuzione
./build/build-wordpress.sh
./build/build-standalone.sh
```

### 2. Fix Bug nel Core

```bash
# 1. Fix nel core
vim core/Services/Reports/ReportGenerator.php

# 2. Sincronizza
./build/sync.sh

# 3. Test in entrambe le versioni
cd wordpress && ./test.sh
cd ../standalone && ./test.sh

# 4. Commit
git add core/
git commit -m "fix: report generation bug"
```

### 3. Aggiornamento WordPress-Specifico

```bash
# 1. Modifica solo WordPress
vim wordpress/src/Admin/Pages/DashboardPage.php

# 2. Test WordPress
cd wordpress && ./test.sh

# 3. Commit
git add wordpress/
git commit -m "feat(wordpress): improve dashboard UI"
```

## 🎯 Esempio Completo: ClientsRepo

### Core (Condiviso)

```php
// core/Domain/Repos/ClientsRepo.php
namespace FP\DMS\Core\Domain\Repos;

use FP\DMS\Core\Domain\Entities\Client;

class ClientsRepo {
    public function __construct(
        private DatabaseInterface $db
    ) {}
    
    public function all(): array {
        $table = $this->db->table('clients');
        $results = $this->db->get_results("SELECT * FROM {$table}");
        
        return array_map(fn($row) => Client::fromArray((array)$row), $results);
    }
    
    public function find(int $id): ?Client {
        $table = $this->db->table('clients');
        $query = $this->db->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $id
        );
        
        $row = $this->db->get_row($query);
        
        return $row ? Client::fromArray((array)$row) : null;
    }
    
    public function save(Client $client): int {
        $table = $this->db->table('clients');
        $data = $client->toArray();
        
        if ($client->id > 0) {
            $this->db->update($table, $data, ['id' => $client->id]);
            return $client->id;
        }
        
        return $this->db->insert($table, $data);
    }
}
```

### WordPress Adapter

```php
// wordpress/src/Adapters/WordPressDatabase.php
namespace FP\DMS\WordPress\Adapters;

use FP\DMS\Core\Services\DatabaseInterface;

class WordPressDatabase implements DatabaseInterface {
    public function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'fpdms_' . $name;
    }
    
    public function get_results(string $query): array {
        global $wpdb;
        return $wpdb->get_results($query) ?: [];
    }
    
    public function get_row(string $query): ?object {
        global $wpdb;
        return $wpdb->get_row($query);
    }
    
    public function insert(string $table, array $data): int {
        global $wpdb;
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }
    
    public function update(string $table, array $data, array $where): int {
        global $wpdb;
        return $wpdb->update($table, $data, $where);
    }
    
    public function prepare(string $query, ...$args): string {
        global $wpdb;
        return $wpdb->prepare($query, ...$args);
    }
}
```

### Standalone Adapter

```php
// standalone/src/Adapters/PDODatabase.php
namespace FP\DMS\Standalone\Adapters;

use FP\DMS\Core\Services\DatabaseInterface;
use PDO;

class PDODatabase implements DatabaseInterface {
    private PDO $pdo;
    private string $prefix;
    
    public function __construct(PDO $pdo, string $prefix = 'fpdms_') {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
    }
    
    public function table(string $name): string {
        return $this->prefix . $name;
    }
    
    public function get_results(string $query): array {
        return $this->pdo->query($query)->fetchAll(PDO::FETCH_OBJ);
    }
    
    public function get_row(string $query): ?object {
        $result = $this->pdo->query($query)->fetch(PDO::FETCH_OBJ);
        return $result ?: null;
    }
    
    public function insert(string $table, array $data): int {
        $fields = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $query = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($data);
        
        return (int) $this->pdo->lastInsertId();
    }
    
    public function update(string $table, array $data, array $where): int {
        $set = implode(', ', array_map(fn($k) => "$k = :data_$k", array_keys($data)));
        $whereSql = implode(' AND ', array_map(fn($k) => "$k = :where_$k", array_keys($where)));
        
        $query = "UPDATE {$table} SET {$set} WHERE {$whereSql}";
        $stmt = $this->pdo->prepare($query);
        
        foreach ($data as $k => $v) $stmt->bindValue(":data_$k", $v);
        foreach ($where as $k => $v) $stmt->bindValue(":where_$k", $v);
        
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    public function prepare(string $query, ...$args): string {
        // Simple sprintf-based prepare
        return sprintf($query, ...$args);
    }
}
```

### Uso in WordPress

```php
// wordpress/fp-digital-marketing-suite.php
use FP\DMS\WordPress\Adapters\WordPressDatabase;
use FP\DMS\Core\Domain\Repos\ClientsRepo;

$db = new WordPressDatabase();
$repo = new ClientsRepo($db);

$clients = $repo->all(); // Funziona con WordPress!
```

### Uso in Standalone

```php
// standalone/public/index.php
use FP\DMS\Standalone\Adapters\PDODatabase;
use FP\DMS\Core\Domain\Repos\ClientsRepo;

$pdo = new PDO("mysql:host=localhost;dbname=fpdms", "user", "pass");
$db = new PDODatabase($pdo);
$repo = new ClientsRepo($db);

$clients = $repo->all(); // Funziona standalone!
```

## 📋 Vantaggi di Questo Approccio

### ✅ Pro

1. **DRY (Don't Repeat Yourself)**
   - Business logic scritta una sola volta
   - Bug fix applicati a entrambe le versioni
   - Feature nuove disponibili ovunque

2. **Flessibilità**
   - Ogni versione può avere UI personalizzata
   - Performance ottimizzate per piattaforma
   - Deploy indipendenti

3. **Manutenibilità**
   - Core testato una volta
   - Separazione chiara responsabilità
   - Upgrade facilitati

4. **Scelta per l'Utente**
   - Chi ha WordPress usa il plugin
   - Chi no usa standalone
   - Stesse funzionalità in entrambe

### ⚠️ Considerazioni

1. **Complessità Build**
   - Serve processo build
   - Test su entrambe le piattaforme

2. **Sincronizzazione**
   - Core deve rimanere sincronizzato
   - Versioning coordinato

3. **Testing**
   - Suite test per core
   - Test specifici per adapters
   - Integration test per entrambe

## 🚀 Release Strategy

### Versioning

```
Core:     v1.2.3
WordPress: v1.2.3-wp
Standalone: v1.2.3-sa
```

### Release Process

```bash
# 1. Tag core version
git tag -a core-v1.2.3 -m "Core version 1.2.3"

# 2. Build WordPress
./build/build-wordpress.sh
git tag -a wp-v1.2.3 -m "WordPress plugin version 1.2.3"

# 3. Build Standalone
./build/build-standalone.sh
git tag -a sa-v1.2.3 -m "Standalone app version 1.2.3"

# 4. Publish
# - WordPress.org plugin repository
# - GitHub releases (standalone)
# - Packagist (composer)
```

## 📝 Prossimi Passi

1. **Riorganizza codebase esistente**
   - Sposta business logic in `core/`
   - Crea adapter WordPress
   - Mantieni adapter standalone

2. **Setup build system**
   - Script sincronizzazione
   - Script build
   - CI/CD pipeline

3. **Aggiorna documentazione**
   - Guida sviluppo dual-version
   - Contributing guidelines
   - Release process

4. **Testing**
   - Test suite core
   - Test WordPress integration
   - Test standalone integration

Vuoi che proceda con la riorganizzazione del codice esistente in questa struttura dual-version?
