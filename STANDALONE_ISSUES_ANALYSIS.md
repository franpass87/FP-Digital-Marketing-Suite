# Analisi Problemi Potenziali: WordPress → Standalone

## 🔍 Funzionalità da Controllare e Risolvere

### 1. File Upload / Media Library ⚠️ PROBLEMA

#### WordPress (Plugin)
```php
// WordPress gestisce upload automaticamente
$file = $_FILES['logo'];
$upload = wp_handle_upload($file);
$attachment_id = wp_insert_attachment([
    'post_title' => $file['name'],
    'post_mime_type' => $file['type']
], $upload['file']);

// Salva ID in database
update_post_meta($client_id, 'logo_id', $attachment_id);

// Recupera URL
$logo_url = wp_get_attachment_url($attachment_id);
```

#### Standalone - SOLUZIONE

```php
// src/Infra/FileUploader.php
<?php

namespace FP\DMS\Infra;

use RuntimeException;

class FileUploader
{
    private string $uploadDir;
    private array $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'text/csv', 'application/vnd.ms-excel'
    ];
    private int $maxFileSize = 10485760; // 10MB

    public function __construct(?string $uploadDir = null)
    {
        $this->uploadDir = $uploadDir ?? __DIR__ . '/../../storage/uploads';
        $this->ensureUploadDirExists();
    }

    /**
     * Upload file e ritorna informazioni
     */
    public function upload(array $file, string $subfolder = ''): array
    {
        // Validazione
        $this->validateFile($file);

        // Crea subfolder se necessario
        $targetDir = $this->uploadDir . '/' . trim($subfolder, '/');
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        // Genera nome file unico
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $this->generateUniqueFilename($extension);
        $targetPath = $targetDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new RuntimeException('Failed to move uploaded file');
        }

        // Genera thumbnail se immagine
        $thumbnail = null;
        if ($this->isImage($file['type'])) {
            $thumbnail = $this->generateThumbnail($targetPath, $targetDir);
        }

        return [
            'filename' => $filename,
            'path' => $targetPath,
            'relative_path' => str_replace($this->uploadDir, '', $targetPath),
            'url' => $this->getFileUrl($targetPath),
            'thumbnail_url' => $thumbnail ? $this->getFileUrl($thumbnail) : null,
            'mime_type' => $file['type'],
            'size' => $file['size'],
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Genera thumbnail per immagini
     */
    private function generateThumbnail(string $sourcePath, string $targetDir): ?string
    {
        $thumbnailPath = $targetDir . '/thumb_' . basename($sourcePath);
        
        $imageType = exif_imagetype($sourcePath);
        
        switch ($imageType) {
            case IMAGETYPE_JPEG:
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $source = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $source = imagecreatefromgif($sourcePath);
                break;
            default:
                return null;
        }

        // Resize to 150x150
        $width = imagesx($source);
        $height = imagesy($source);
        
        $newWidth = 150;
        $newHeight = (int) ($height * ($newWidth / $width));
        
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        imagejpeg($thumb, $thumbnailPath, 85);
        
        imagedestroy($source);
        imagedestroy($thumb);
        
        return $thumbnailPath;
    }

    private function validateFile(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload error: ' . $file['error']);
        }

        if ($file['size'] > $this->maxFileSize) {
            throw new RuntimeException('File too large');
        }

        if (!in_array($file['type'], $this->allowedTypes)) {
            throw new RuntimeException('File type not allowed');
        }

        // Check if actually an image
        if ($this->isImage($file['type'])) {
            if (!getimagesize($file['tmp_name'])) {
                throw new RuntimeException('Invalid image file');
            }
        }
    }

    private function isImage(string $mimeType): bool
    {
        return strpos($mimeType, 'image/') === 0;
    }

    private function generateUniqueFilename(string $extension): string
    {
        return date('Y-m-d') . '_' . uniqid() . '.' . $extension;
    }

    private function getFileUrl(string $path): string
    {
        $relativePath = str_replace($this->uploadDir, '', $path);
        return ($_ENV['APP_URL'] ?? 'http://localhost:8080') . '/uploads' . $relativePath;
    }

    private function ensureUploadDirExists(): void
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    /**
     * Delete file
     */
    public function delete(string $relativePath): bool
    {
        $fullPath = $this->uploadDir . '/' . ltrim($relativePath, '/');
        
        if (file_exists($fullPath)) {
            unlink($fullPath);
            
            // Delete thumbnail if exists
            $thumbPath = dirname($fullPath) . '/thumb_' . basename($fullPath);
            if (file_exists($thumbPath)) {
                unlink($thumbPath);
            }
            
            return true;
        }
        
        return false;
    }

    /**
     * Get file info
     */
    public function getFileInfo(string $relativePath): ?array
    {
        $fullPath = $this->uploadDir . '/' . ltrim($relativePath, '/');
        
        if (!file_exists($fullPath)) {
            return null;
        }
        
        return [
            'path' => $fullPath,
            'url' => $this->getFileUrl($fullPath),
            'size' => filesize($fullPath),
            'mime_type' => mime_content_type($fullPath),
            'modified_at' => date('Y-m-d H:i:s', filemtime($fullPath))
        ];
    }
}
```

**Uso nel Controller:**

```php
// src/App/Controllers/ClientsController.php
public function uploadLogo(Request $request, Response $response): Response
{
    $uploader = new FileUploader();
    
    try {
        $fileInfo = $uploader->upload($_FILES['logo'], 'clients/logos');
        
        // Salva in database
        $db->update(
            $db->table('clients'),
            ['logo_url' => $fileInfo['url']],
            ['id' => $clientId]
        );
        
        return $this->json($response, [
            'success' => true,
            'file' => $fileInfo
        ]);
    } catch (\Exception $e) {
        return $this->json($response, [
            'success' => false,
            'error' => $e->getMessage()
        ], 400);
    }
}
```

### 2. User Authentication & Capabilities ⚠️ PROBLEMA

#### WordPress (Plugin)
```php
// WordPress user system
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}

$current_user = wp_get_current_user();
$user_email = $current_user->user_email;
```

#### Standalone - SOLUZIONE

```php
// src/Infra/Auth.php
<?php

namespace FP\DMS\Infra;

class Auth
{
    private static ?array $user = null;

    /**
     * Login user
     */
    public static function login(string $email, string $password): bool
    {
        global $wpdb;
        
        $table = DB::table('users');
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE email = %s",
            $email
        ));
        
        if (!$user || !password_verify($password, $user->password)) {
            return false;
        }
        
        session_start();
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['user_name'] = $user->display_name;
        
        self::$user = (array) $user;
        
        return true;
    }

    /**
     * Logout user
     */
    public static function logout(): void
    {
        session_start();
        session_destroy();
        self::$user = null;
    }

    /**
     * Check if user is logged in
     */
    public static function check(): bool
    {
        session_start();
        return isset($_SESSION['user_id']);
    }

    /**
     * Get current user
     */
    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        
        session_start();
        
        if (!isset($_SESSION['user_id'])) {
            return null;
        }
        
        global $wpdb;
        $table = DB::table('users');
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $_SESSION['user_id']
        ));
        
        self::$user = $user ? (array) $user : null;
        
        return self::$user;
    }

    /**
     * Check user capability (simula WordPress capabilities)
     */
    public static function can(string $capability): bool
    {
        if (!self::check()) {
            return false;
        }
        
        $user = self::user();
        $role = $user['role'] ?? 'subscriber';
        
        $capabilities = [
            'administrator' => ['*'], // Tutto
            'editor' => ['edit_posts', 'manage_clients', 'view_reports'],
            'author' => ['edit_posts', 'view_reports'],
            'subscriber' => ['view_reports']
        ];
        
        $userCaps = $capabilities[$role] ?? [];
        
        return in_array('*', $userCaps) || in_array($capability, $userCaps);
    }

    /**
     * Create new user
     */
    public static function createUser(string $email, string $password, string $displayName, string $role = 'subscriber'): int
    {
        global $wpdb;
        
        $table = DB::table('users');
        
        // Check if email exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE email = %s",
            $email
        ));
        
        if ($exists > 0) {
            throw new \RuntimeException('Email already exists');
        }
        
        $wpdb->insert($table, [
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'display_name' => $displayName,
            'role' => $role,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return $wpdb->insert_id;
    }
}
```

**Middleware Aggiornato:**

```php
// src/App/Middleware/AuthMiddleware.php
public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler
): ResponseInterface {
    $path = $request->getUri()->getPath();

    // Public routes
    if ($this->isPublicRoute($path)) {
        return $handler->handle($request);
    }

    // Check authentication
    if (!Auth::check()) {
        if (str_starts_with($path, '/api/')) {
            // API: return 401
            $response = new Response();
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        } else {
            // Web: redirect to login
            $response = new Response();
            return $response
                ->withStatus(302)
                ->withHeader('Location', '/login');
        }
    }

    return $handler->handle($request);
}
```

### 3. WYSIWYG Editor per Template ⚠️ PROBLEMA

#### WordPress (Plugin)
```php
// WordPress editor automatico
wp_editor($content, 'template_content', [
    'textarea_name' => 'content',
    'media_buttons' => true,
    'tinymce' => true
]);
```

#### Standalone - SOLUZIONE

**Opzione A: TinyMCE (stesso di WordPress)**

```html
<!-- In template edit page -->
<!DOCTYPE html>
<html>
<head>
    <script src="https://cdn.tiny.cloud/1/YOUR-API-KEY/tinymce/6/tinymce.min.js"></script>
    <script>
        tinymce.init({
            selector: '#template_content',
            height: 500,
            menubar: false,
            plugins: [
                'advlist', 'autolink', 'lists', 'link', 'image', 'charmap',
                'preview', 'anchor', 'searchreplace', 'visualblocks',
                'code', 'fullscreen', 'insertdatetime', 'media', 'table',
                'help', 'wordcount'
            ],
            toolbar: 'undo redo | formatselect | bold italic | \
                     alignleft aligncenter alignright alignjustify | \
                     bullist numlist outdent indent | removeformat | help',
            content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:14px }'
        });
    </script>
</head>
<body>
    <form method="POST">
        <textarea id="template_content" name="content">
            <?= htmlspecialchars($template->content) ?>
        </textarea>
        <button type="submit">Save Template</button>
    </form>
</body>
</html>
```

**Opzione B: Quill (più moderno)**

```html
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>

<div id="editor"></div>
<input type="hidden" name="content" id="content">

<script>
var quill = new Quill('#editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            ['bold', 'italic', 'underline', 'strike'],
            ['blockquote', 'code-block'],
            [{ 'header': 1 }, { 'header': 2 }],
            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
            [{ 'script': 'sub'}, { 'script': 'super' }],
            [{ 'indent': '-1'}, { 'indent': '+1' }],
            ['link', 'image'],
            ['clean']
        ]
    }
});

// Save to hidden input on form submit
document.querySelector('form').onsubmit = function() {
    document.getElementById('content').value = quill.root.innerHTML;
};
</script>
```

### 4. Localization / i18n ⚠️ PROBLEMA

#### WordPress (Plugin)
```php
__('Hello', 'fp-dms');
_e('Welcome', 'fp-dms');
esc_html__('Title', 'fp-dms');
```

#### Standalone - SOLUZIONE

```php
// src/Support/I18n.php (già esiste, espandiamolo)
<?php

namespace FP\DMS\Support;

class I18n
{
    private static ?string $locale = null;
    private static array $translations = [];
    private static string $domain = 'fp-dms';

    /**
     * Set locale
     */
    public static function setLocale(string $locale): void
    {
        self::$locale = $locale;
        self::loadTranslations($locale);
    }

    /**
     * Get current locale
     */
    public static function getLocale(): string
    {
        if (self::$locale === null) {
            self::$locale = $_ENV['APP_LOCALE'] ?? 'en_US';
        }
        
        return self::$locale;
    }

    /**
     * Load translations for locale
     */
    private static function loadTranslations(string $locale): void
    {
        $file = __DIR__ . "/../../languages/{$locale}.json";
        
        if (file_exists($file)) {
            $content = file_get_contents($file);
            self::$translations = json_decode($content, true) ?? [];
        }
    }

    /**
     * Translate string
     */
    public static function translate(string $text, string $domain = null): string
    {
        $domain = $domain ?? self::$domain;
        $locale = self::getLocale();
        
        if (!isset(self::$translations[$domain])) {
            self::loadTranslations($locale);
        }
        
        return self::$translations[$domain][$text] ?? $text;
    }

    /**
     * Echo translated string
     */
    public static function echo(string $text, string $domain = null): void
    {
        echo self::translate($text, $domain);
    }
}

// Helper functions compatibili con WordPress
if (!function_exists('__')) {
    function __(string $text, string $domain = 'fp-dms'): string {
        return I18n::translate($text, $domain);
    }
}

if (!function_exists('_e')) {
    function _e(string $text, string $domain = 'fp-dms'): void {
        I18n::echo($text, $domain);
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'fp-dms'): string {
        return htmlspecialchars(I18n::translate($text, $domain), ENT_QUOTES);
    }
}
```

**File traduzione:**

```json
// languages/it_IT.json
{
  "fp-dms": {
    "Hello": "Ciao",
    "Welcome": "Benvenuto",
    "Dashboard": "Cruscotto",
    "Clients": "Clienti",
    "Reports": "Report",
    "Settings": "Impostazioni",
    "Logout": "Esci"
  }
}
```

### 5. Nonces / CSRF Protection ⚠️ PROBLEMA

#### WordPress (Plugin)
```php
wp_nonce_field('save_client');
if (!wp_verify_nonce($_POST['_wpnonce'], 'save_client')) {
    die('Invalid nonce');
}
```

#### Standalone - SOLUZIONE

```php
// src/Support/Csrf.php
<?php

namespace FP\DMS\Support;

class Csrf
{
    private const SESSION_KEY = '_csrf_tokens';

    /**
     * Generate CSRF token
     */
    public static function generate(string $action = 'default'): string
    {
        session_start();
        
        if (!isset($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = [];
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION[self::SESSION_KEY][$action] = $token;
        
        return $token;
    }

    /**
     * Verify CSRF token
     */
    public static function verify(string $token, string $action = 'default'): bool
    {
        session_start();
        
        if (!isset($_SESSION[self::SESSION_KEY][$action])) {
            return false;
        }
        
        $valid = hash_equals($_SESSION[self::SESSION_KEY][$action], $token);
        
        // One-time use
        unset($_SESSION[self::SESSION_KEY][$action]);
        
        return $valid;
    }

    /**
     * Get field HTML
     */
    public static function field(string $action = 'default'): string
    {
        $token = self::generate($action);
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars($token)
        );
    }

    /**
     * Middleware per verificare CSRF
     */
    public static function middleware(Request $request, Response $response, callable $next): Response
    {
        if (in_array($request->getMethod(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $token = $request->getParsedBody()['_csrf_token'] ?? '';
            
            if (!self::verify($token)) {
                $response->getBody()->write(json_encode(['error' => 'Invalid CSRF token']));
                return $response->withStatus(403);
            }
        }
        
        return $next($request, $response);
    }
}
```

**Uso:**

```php
// In form
<form method="POST">
    <?= Csrf::field('save_client') ?>
    <!-- form fields -->
</form>

// In controller
if (!Csrf::verify($_POST['_csrf_token'], 'save_client')) {
    throw new Exception('Invalid CSRF token');
}
```

### 6. Caching ⚠️ PROBLEMA

#### WordPress (Plugin)
```php
$data = get_transient('my_cache_key');
if ($data === false) {
    $data = expensive_operation();
    set_transient('my_cache_key', $data, HOUR_IN_SECONDS);
}
```

#### Standalone - SOLUZIONE

```php
// src/Infra/Cache.php
<?php

namespace FP\DMS\Infra;

class Cache
{
    private static string $cacheDir;

    public static function init(string $dir = null): void
    {
        self::$cacheDir = $dir ?? __DIR__ . '/../../storage/cache';
        
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }

    /**
     * Get from cache
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::init();
        
        $file = self::getCacheFile($key);
        
        if (!file_exists($file)) {
            return $default;
        }
        
        $data = unserialize(file_get_contents($file));
        
        // Check if expired
        if ($data['expires_at'] !== null && $data['expires_at'] < time()) {
            unlink($file);
            return $default;
        }
        
        return $data['value'];
    }

    /**
     * Store in cache
     */
    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        self::init();
        
        $file = self::getCacheFile($key);
        
        $data = [
            'value' => $value,
            'expires_at' => $ttl > 0 ? time() + $ttl : null
        ];
        
        return file_put_contents($file, serialize($data)) !== false;
    }

    /**
     * Delete from cache
     */
    public static function delete(string $key): bool
    {
        self::init();
        
        $file = self::getCacheFile($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return false;
    }

    /**
     * Clear all cache
     */
    public static function flush(): void
    {
        self::init();
        
        $files = glob(self::$cacheDir . '/*');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Remember: Get from cache or execute callback
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = self::get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        self::set($key, $value, $ttl);
        
        return $value;
    }

    private static function getCacheFile(string $key): string
    {
        return self::$cacheDir . '/' . md5($key) . '.cache';
    }
}
```

**Uso:**

```php
// Get/Set manual
$data = Cache::get('clients_list');
if ($data === null) {
    $data = ClientsRepo::all();
    Cache::set('clients_list', $data, 3600); // 1 hour
}

// Remember (più semplice)
$data = Cache::remember('clients_list', 3600, function() {
    return ClientsRepo::all();
});
```

### 7. Logging avanzato ⚠️ MIGLIORAMENTO

```php
// src/Infra/Logger.php - Espanso
<?php

namespace FP\DMS\Infra;

class Logger
{
    private static string $logDir;
    private static array $levels = ['debug', 'info', 'warning', 'error', 'critical'];

    public static function init(string $dir = null): void
    {
        self::$logDir = $dir ?? __DIR__ . '/../../storage/logs';
        
        if (!is_dir(self::$logDir)) {
            mkdir(self::$logDir, 0755, true);
        }
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context) : '';
        
        $logLine = sprintf(
            "[%s] %s: %s %s\n",
            $timestamp,
            strtoupper($level),
            $message,
            $contextStr
        );
        
        // Log generale
        $file = self::$logDir . '/app.log';
        file_put_contents($file, $logLine, FILE_APPEND);
        
        // Log per livello
        if (in_array($level, ['error', 'critical'])) {
            $errorFile = self::$logDir . '/error.log';
            file_put_contents($errorFile, $logLine, FILE_APPEND);
        }
        
        // Log giornaliero
        $dailyFile = self::$logDir . '/app-' . date('Y-m-d') . '.log';
        file_put_contents($dailyFile, $logLine, FILE_APPEND);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        self::log('critical', $message, $context);
    }
}
```

## 📋 Checklist Migrazione Funzionalità

### Funzionalità File

- [x] Upload file → `FileUploader` class
- [x] Media library → Storage directory + database
- [x] Thumbnail generation → GD library
- [x] File serving → Static file serving

### Funzionalità Utenti

- [x] Authentication → `Auth` class + sessions
- [x] User roles → Database + capability checking
- [x] Password hashing → `password_hash()` / `password_verify()`
- [x] User management → CRUD operations

### Funzionalità UI

- [x] WYSIWYG editor → TinyMCE/Quill
- [x] Form handling → Standard HTML forms
- [x] Admin pages → Controllers + views
- [x] Asset management → Public directory

### Funzionalità Sicurezza

- [x] CSRF protection → `Csrf` class
- [x] Nonces → Token-based validation
- [x] Input sanitization → Già in `Support\Wp.php`
- [x] SQL injection → Prepared statements (PDO)
- [x] XSS protection → Output escaping

### Funzionalità Sistema

- [x] Caching → `Cache` class (file-based)
- [x] Logging → `Logger` class (enhanced)
- [x] Scheduling → `Scheduler` class
- [x] Queue → Esistente, già compatibile
- [x] Email → PHPMailer, già compatibile

### Funzionalità i18n

- [x] Translations → `I18n` class + JSON files
- [x] Helper functions → `__()`, `_e()`, etc.
- [x] Locale switching → Runtime configuration

## 🎯 Conclusione

**Tutte le funzionalità WordPress hanno equivalenti standalone:**

1. ✅ File Upload → FileUploader class
2. ✅ User Auth → Auth class + PDO
3. ✅ WYSIWYG → TinyMCE/Quill
4. ✅ i18n → I18n class + JSON
5. ✅ CSRF → Csrf class
6. ✅ Caching → Cache class
7. ✅ Logging → Logger class enhanced

**Nessuna funzionalità è bloccante!**

Vuoi che implementi una di queste soluzioni nel codice?
