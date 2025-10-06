# Guida Implementazione Architettura Dual-Version

## 🎯 Obiettivo

Riorganizzare il codebase esistente per supportare **due versioni simultanee**:
- **WordPress Plugin** (per utenti WordPress)
- **Standalone Application** (per utenti non-WordPress)

## 📋 Step-by-Step Implementation

### Phase 1: Preparazione (30 minuti)

#### 1.1 Backup
```bash
# Crea backup completo
git add -A
git commit -m "backup: before dual-version refactoring"
git tag backup-before-dual-version

# Crea branch per dual-version
git checkout -b feature/dual-version
```

#### 1.2 Crea Struttura Directory
```bash
# Esegui script setup
./build/sync-core.sh           # Estrae business logic in core/
./build/setup-wordpress.sh     # Setup WordPress version
./build/setup-standalone.sh    # Setup Standalone version
```

Dopo questi comandi avrai:
```
.
├── core/                  # Business logic condivisa
├── wordpress/             # Plugin WordPress
├── standalone/            # App standalone
└── build/                 # Script di build
```

### Phase 2: Core Interfaces (1 ora)

#### 2.1 Crea Interfacce Database

```php
// core/Contracts/DatabaseInterface.php
<?php

namespace FP\DMS\Core\Contracts;

interface DatabaseInterface
{
    public function table(string $name): string;
    public function get_results(string $query, array $params = []): array;
    public function get_row(string $query, array $params = []): ?object;
    public function get_var(string $query, array $params = []): mixed;
    public function insert(string $table, array $data): int;
    public function update(string $table, array $data, array $where): int;
    public function delete(string $table, array $where): int;
    public function query(string $query, array $params = []): bool;
    public function prepare(string $query, ...$args): string;
}
```

#### 2.2 Crea Interfacce Config

```php
// core/Contracts/ConfigInterface.php
<?php

namespace FP\DMS\Core\Contracts;

interface ConfigInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function delete(string $key): void;
}
```

#### 2.3 Crea Interfacce Auth

```php
// core/Contracts/AuthInterface.php
<?php

namespace FP\DMS\Core\Contracts;

interface AuthInterface
{
    public function getCurrentUserId(): ?int;
    public function getCurrentUserEmail(): ?string;
    public function isLoggedIn(): bool;
    public function can(string $capability): bool;
}
```

### Phase 3: WordPress Adapters (1 ora)

#### 3.1 WordPress Database Adapter

```php
// wordpress/src/Adapters/WordPressDatabase.php
<?php

namespace FP\DMS\WordPress\Adapters;

use FP\DMS\Core\Contracts\DatabaseInterface;

class WordPressDatabase implements DatabaseInterface
{
    public function table(string $name): string
    {
        global $wpdb;
        return $wpdb->prefix . 'fpdms_' . $name;
    }

    public function get_results(string $query, array $params = []): array
    {
        global $wpdb;
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, ...$params);
        }
        
        return $wpdb->get_results($query) ?: [];
    }

    public function get_row(string $query, array $params = []): ?object
    {
        global $wpdb;
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, ...$params);
        }
        
        return $wpdb->get_row($query);
    }

    public function get_var(string $query, array $params = []): mixed
    {
        global $wpdb;
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, ...$params);
        }
        
        return $wpdb->get_var($query);
    }

    public function insert(string $table, array $data): int
    {
        global $wpdb;
        $wpdb->insert($table, $data);
        return $wpdb->insert_id;
    }

    public function update(string $table, array $data, array $where): int
    {
        global $wpdb;
        return $wpdb->update($table, $data, $where);
    }

    public function delete(string $table, array $where): int
    {
        global $wpdb;
        return $wpdb->delete($table, $where);
    }

    public function query(string $query, array $params = []): bool
    {
        global $wpdb;
        
        if (!empty($params)) {
            $query = $wpdb->prepare($query, ...$params);
        }
        
        return $wpdb->query($query) !== false;
    }

    public function prepare(string $query, ...$args): string
    {
        global $wpdb;
        return $wpdb->prepare($query, ...$args);
    }
}
```

#### 3.2 WordPress Config Adapter

```php
// wordpress/src/Adapters/WordPressConfig.php
<?php

namespace FP\DMS\WordPress\Adapters;

use FP\DMS\Core\Contracts\ConfigInterface;

class WordPressConfig implements ConfigInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return get_option($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        update_option($key, $value);
    }

    public function has(string $key): bool
    {
        return get_option($key) !== false;
    }

    public function delete(string $key): void
    {
        delete_option($key);
    }
}
```

#### 3.3 WordPress Auth Adapter

```php
// wordpress/src/Adapters/WordPressAuth.php
<?php

namespace FP\DMS\WordPress\Adapters;

use FP\DMS\Core\Contracts\AuthInterface;

class WordPressAuth implements AuthInterface
{
    public function getCurrentUserId(): ?int
    {
        $userId = get_current_user_id();
        return $userId > 0 ? $userId : null;
    }

    public function getCurrentUserEmail(): ?string
    {
        $user = wp_get_current_user();
        return $user->exists() ? $user->user_email : null;
    }

    public function isLoggedIn(): bool
    {
        return is_user_logged_in();
    }

    public function can(string $capability): bool
    {
        return current_user_can($capability);
    }
}
```

### Phase 4: Standalone Adapters (1 ora)

#### 4.1 Standalone Database Adapter

```php
// standalone/src/Adapters/PDODatabase.php
<?php

namespace FP\DMS\Standalone\Adapters;

use FP\DMS\Core\Contracts\DatabaseInterface;
use PDO;

class PDODatabase implements DatabaseInterface
{
    private PDO $pdo;
    private string $prefix;

    public function __construct(PDO $pdo, string $prefix = 'fpdms_')
    {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
    }

    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    public function get_results(string $query, array $params = []): array
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function get_row(string $query, array $params = []): ?object
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        return $result !== false ? $result : null;
    }

    public function get_var(string $query, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public function insert(string $table, array $data): int
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ":$f", $fields);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $table,
            implode(', ', $fields),
            implode(', ', $placeholders)
        );
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $field => $value) {
            $stmt->bindValue(":$field", $value);
        }
        $stmt->execute();
        
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): int
    {
        $setClause = implode(', ', array_map(fn($f) => "$f = :data_$f", array_keys($data)));
        $whereClause = implode(' AND ', array_map(fn($f) => "$f = :where_$f", array_keys($where)));
        
        $sql = "UPDATE $table SET $setClause WHERE $whereClause";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($data as $field => $value) {
            $stmt->bindValue(":data_$field", $value);
        }
        foreach ($where as $field => $value) {
            $stmt->bindValue(":where_$field", $value);
        }
        
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function delete(string $table, array $where): int
    {
        $whereClause = implode(' AND ', array_map(fn($f) => "$f = :$f", array_keys($where)));
        
        $sql = "DELETE FROM $table WHERE $whereClause";
        
        $stmt = $this->pdo->prepare($sql);
        foreach ($where as $field => $value) {
            $stmt->bindValue(":$field", $value);
        }
        
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function query(string $query, array $params = []): bool
    {
        $stmt = $this->pdo->prepare($query);
        return $stmt->execute($params);
    }

    public function prepare(string $query, ...$args): string
    {
        // Simple sprintf-based prepare for compatibility
        return vsprintf(str_replace('%s', "'%s'", $query), $args);
    }
}
```

#### 4.2 Standalone Config Adapter

```php
// standalone/src/Adapters/EnvConfig.php
<?php

namespace FP\DMS\Standalone\Adapters;

use FP\DMS\Core\Contracts\ConfigInterface;
use FP\DMS\Core\Contracts\DatabaseInterface;

class EnvConfig implements ConfigInterface
{
    private array $cache = [];
    private ?DatabaseInterface $db = null;

    public function __construct(?DatabaseInterface $db = null)
    {
        $this->db = $db;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        // Check cache first
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        // Check environment variables
        $envKey = 'FPDMS_' . strtoupper(str_replace('.', '_', $key));
        if (isset($_ENV[$envKey])) {
            $this->cache[$key] = $_ENV[$envKey];
            return $_ENV[$envKey];
        }

        // Check database if available
        if ($this->db !== null) {
            $table = $this->db->table('options');
            $value = $this->db->get_var(
                "SELECT option_value FROM {$table} WHERE option_name = ?",
                [$key]
            );
            
            if ($value !== null && $value !== false) {
                $unserialized = $this->maybeUnserialize($value);
                $this->cache[$key] = $unserialized;
                return $unserialized;
            }
        }

        return $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->cache[$key] = $value;

        if ($this->db === null) {
            return;
        }

        $table = $this->db->table('options');
        $serialized = $this->maybeSerialize($value);
        
        // Try update first
        $updated = $this->db->update(
            $table,
            ['option_value' => $serialized],
            ['option_name' => $key]
        );
        
        // If no rows updated, insert
        if ($updated === 0) {
            $this->db->insert($table, [
                'option_name' => $key,
                'option_value' => $serialized,
                'autoload' => 'yes'
            ]);
        }
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function delete(string $key): void
    {
        unset($this->cache[$key]);

        if ($this->db !== null) {
            $table = $this->db->table('options');
            $this->db->delete($table, ['option_name' => $key]);
        }
    }

    private function maybeSerialize(mixed $data): string
    {
        if (is_array($data) || is_object($data)) {
            return serialize($data);
        }
        return (string) $data;
    }

    private function maybeUnserialize(mixed $data): mixed
    {
        if (!is_string($data)) {
            return $data;
        }

        $data = trim($data);
        if (strlen($data) < 4 || $data[1] !== ':') {
            return $data;
        }

        $unserialized = @unserialize($data);
        return $unserialized !== false ? $unserialized : $data;
    }
}
```

#### 4.3 Standalone Auth Adapter

```php
// standalone/src/Adapters/SessionAuth.php
<?php

namespace FP\DMS\Standalone\Adapters;

use FP\DMS\Core\Contracts\AuthInterface;

class SessionAuth implements AuthInterface
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function getCurrentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function getCurrentUserEmail(): ?string
    {
        return $_SESSION['user_email'] ?? null;
    }

    public function isLoggedIn(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function can(string $capability): bool
    {
        // Simple role-based check
        $role = $_SESSION['user_role'] ?? 'guest';
        
        return match($capability) {
            'manage_options' => $role === 'admin',
            'edit_posts' => in_array($role, ['admin', 'editor']),
            default => false
        };
    }

    public function login(int $userId, string $email, string $role = 'user'): void
    {
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['user_role'] = $role;
    }

    public function logout(): void
    {
        session_destroy();
        session_start();
    }
}
```

### Phase 5: Aggiorna Core per Usare Interfacce (2 ore)

#### 5.1 Aggiorna Repository Base

```php
// core/Domain/Repos/BaseRepo.php
<?php

namespace FP\DMS\Core\Domain\Repos;

use FP\DMS\Core\Contracts\DatabaseInterface;

abstract class BaseRepo
{
    protected DatabaseInterface $db;

    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    protected function table(string $name): string
    {
        return $this->db->table($name);
    }
}
```

#### 5.2 Aggiorna ClientsRepo

```php
// core/Domain/Repos/ClientsRepo.php
<?php

namespace FP\DMS\Core\Domain\Repos;

use FP\DMS\Core\Domain\Entities\Client;

class ClientsRepo extends BaseRepo
{
    public function all(): array
    {
        $table = $this->table('clients');
        $results = $this->db->get_results("SELECT * FROM {$table} ORDER BY name ASC");
        
        return array_map(fn($row) => Client::fromArray((array)$row), $results);
    }

    public function find(int $id): ?Client
    {
        $table = $this->table('clients');
        $query = "SELECT * FROM {$table} WHERE id = %d";
        $prepared = $this->db->prepare($query, $id);
        
        $row = $this->db->get_row($prepared);
        
        return $row ? Client::fromArray((array)$row) : null;
    }

    // ... altri metodi
}
```

### Phase 6: Dependency Injection Setup

#### 6.1 WordPress Container

```php
// wordpress/src/Container.php
<?php

namespace FP\DMS\WordPress;

use FP\DMS\WordPress\Adapters\WordPressDatabase;
use FP\DMS\WordPress\Adapters\WordPressConfig;
use FP\DMS\WordPress\Adapters\WordPressAuth;
use FP\DMS\Core\Domain\Repos\ClientsRepo;
use FP\DMS\Core\Contracts\DatabaseInterface;
use FP\DMS\Core\Contracts\ConfigInterface;
use FP\DMS\Core\Contracts\AuthInterface;

class Container
{
    private static array $instances = [];

    public static function get(string $class): mixed
    {
        if (isset(self::$instances[$class])) {
            return self::$instances[$class];
        }

        return self::$instances[$class] = self::make($class);
    }

    private static function make(string $class): mixed
    {
        return match($class) {
            DatabaseInterface::class => new WordPressDatabase(),
            ConfigInterface::class => new WordPressConfig(),
            AuthInterface::class => new WordPressAuth(),
            ClientsRepo::class => new ClientsRepo(self::get(DatabaseInterface::class)),
            default => new $class()
        };
    }
}
```

#### 6.2 Standalone Container (PHP-DI)

```php
// standalone/src/App/Container.php
<?php

namespace FP\DMS\Standalone\App;

use DI\Container as DIContainer;
use FP\DMS\Standalone\Adapters\PDODatabase;
use FP\DMS\Standalone\Adapters\EnvConfig;
use FP\DMS\Standalone\Adapters\SessionAuth;
use FP\DMS\Core\Contracts\DatabaseInterface;
use FP\DMS\Core\Contracts\ConfigInterface;
use FP\DMS\Core\Contracts\AuthInterface;
use PDO;

class Container
{
    public static function create(): DIContainer
    {
        $container = new DIContainer();

        // Database
        $container->set(DatabaseInterface::class, function() {
            $pdo = new PDO(
                sprintf(
                    '%s:host=%s;port=%d;dbname=%s;charset=%s',
                    $_ENV['DB_CONNECTION'] ?? 'mysql',
                    $_ENV['DB_HOST'] ?? 'localhost',
                    $_ENV['DB_PORT'] ?? 3306,
                    $_ENV['DB_DATABASE'] ?? 'fpdms',
                    $_ENV['DB_CHARSET'] ?? 'utf8mb4'
                ),
                $_ENV['DB_USERNAME'] ?? 'root',
                $_ENV['DB_PASSWORD'] ?? '',
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                ]
            );
            
            return new PDODatabase($pdo, $_ENV['DB_PREFIX'] ?? 'fpdms_');
        });

        // Config
        $container->set(ConfigInterface::class, function(DatabaseInterface $db) {
            return new EnvConfig($db);
        });

        // Auth
        $container->set(AuthInterface::class, function() {
            return new SessionAuth();
        });

        return $container;
    }
}
```

### Phase 7: Testing (1 ora)

#### 7.1 Test WordPress Version

```bash
cd wordpress
composer install

# Test in ambiente WordPress
# Attiva plugin e verifica funzionamento
```

#### 7.2 Test Standalone Version

```bash
cd standalone
composer install
cp .env.example .env
nano .env  # Configura database

# Testa migrazione
php cli.php db:migrate

# Testa server
composer serve
```

## 📊 Checklist Implementazione

- [ ] Phase 1: Preparazione e backup
- [ ] Phase 2: Creazione interfacce core
- [ ] Phase 3: Adapter WordPress
- [ ] Phase 4: Adapter Standalone
- [ ] Phase 5: Aggiornamento core repositories
- [ ] Phase 6: Setup dependency injection
- [ ] Phase 7: Testing entrambe le versioni

## 🎯 Risultato Finale

Dopo l'implementazione avrai:

```
Repository Structure:
├── core/                          # 95% codice condiviso
│   ├── Contracts/                 # Interfacce
│   ├── Domain/                    # Entities & Repos
│   ├── Services/                  # Business logic
│   └── Support/                   # Utilities
│
├── wordpress/                     # 5% WordPress-specific
│   ├── src/Adapters/              # WordPress adapters
│   ├── src/Admin/                 # WordPress UI
│   └── fp-digital-marketing-suite.php
│
└── standalone/                    # 5% Standalone-specific
    ├── src/Adapters/              # Standalone adapters
    ├── src/App/                   # Slim application
    └── public/index.php
```

## 🚀 Workflow Futuro

```bash
# Sviluppo feature
vim core/Services/NewFeature.php    # Implementa in core

# Build entrambe le versioni
./build/build-all.sh

# Deploy
# - WordPress: Carica su WordPress.org
# - Standalone: Release su GitHub
```

Vuoi che proceda con l'implementazione pratica?
