<?php

declare(strict_types=1);

namespace FP\DMS\Audit {

use DateTimeImmutable;
use DateTimeZone;

final class RuntimeEnv
{
    public static string $root;
    /** @var array<string,mixed> */
    public static array $options = [];
    /** @var array<string,array{value:mixed,expires:?int}> */
    public static array $transients = [];
    /** @var array<int,array<string,mixed>> */
    public static array $remoteRequests = [];
    /** @var array<int,array<string,mixed>> */
    public static array $mailLog = [];
    /** @var array<string,mixed>|null */
    private static ?array $uploadDir = null;

    public static function init(string $root): void
    {
        self::$root = $root;
        date_default_timezone_set('UTC');
    }

    /**
     * @return array{path:string,url:string,subdir:string,basedir:string,baseurl:string,error:false}
     */
    public static function uploadDir(): array
    {
        if (self::$uploadDir !== null) {
            return self::$uploadDir;
        }

        $baseDir = self::$root . '/tests/audit/.runtime/uploads';
        if (! is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        self::$uploadDir = [
            'path' => $baseDir,
            'url' => 'https://example.test/uploads',
            'subdir' => '',
            'basedir' => $baseDir,
            'baseurl' => 'https://example.test/uploads',
            'error' => false,
        ];

        return self::$uploadDir;
    }

    public static function recordRemote(array $entry): void
    {
        self::$remoteRequests[] = $entry;
    }

    public static function recordMail(array $entry): void
    {
        self::$mailLog[] = $entry;
    }

    public static function purgeExpiredTransients(): void
    {
        $now = time();
        foreach (self::$transients as $key => $item) {
            if ($item['expires'] !== null && $item['expires'] <= $now) {
                unset(self::$transients[$key]);
            }
        }
    }
}

class RuntimeWpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;

    /** @var array<string,array<int,array<string,mixed>>>> */
    private array $tables = [];

    public function __construct()
    {
        $baseTables = ['clients', 'datasources', 'schedules', 'reports', 'anomalies', 'templates', 'locks'];
        foreach ($baseTables as $table) {
            $this->tables[$this->tableName($table)] = [];
        }
    }

    private function tableName(string $name): string
    {
        return $this->prefix . 'fpdms_' . $name;
    }

    /**
     * @param string $query
     * @param mixed ...$args
     */
    public function prepare(string $query, ...$args): string
    {
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }

        $offset = 0;
        $result = '';
        $length = strlen($query);
        $argIndex = 0;

        while ($offset < $length) {
            $pos = strpos($query, '%', $offset);
            if ($pos === false || $pos === $length - 1) {
                $result .= substr($query, $offset);
                break;
            }

            $result .= substr($query, $offset, $pos - $offset);
            $specifier = $query[$pos + 1];
            $value = $args[$argIndex] ?? '';
            $argIndex++;

            if ($specifier === 'd' || $specifier === 'f') {
                $result .= (string) (is_numeric($value) ? $value + 0 : 0);
            } else {
                $escaped = addslashes((string) $value);
                $result .= "'{$escaped}'";
            }

            $offset = $pos + 2;
        }

        return $result;
    }

    /**
     * @param string $query
     * @param string $output
     * @return array<int,array<string,mixed>>|null
     */
    public function get_results(string $query, string $output = 'OBJECT')
    {
        $parsed = $this->parseSelect($query);
        if ($parsed === null) {
            return [];
        }

        [$table, $columns, $conditions, $order, $direction, $limit] = $parsed;
        $rows = array_values($this->tables[$table] ?? []);

        $rows = array_values(array_filter($rows, function (array $row) use ($conditions): bool {
            foreach ($conditions as $condition) {
                [$field, $operator, $value] = $condition;
                $field = strtolower($field);
                $rowValue = $row[$field] ?? null;

                if ($operator === 'IN') {
                    if (! in_array($rowValue, $value, true)) {
                        return false;
                    }
                    continue;
                }

                if ($operator === '!=') {
                    if ($rowValue == $value) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
                        return false;
                    }
                    continue;
                }

                if ($rowValue != $value) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
                    return false;
                }
            }

            return true;
        }));

        if ($order !== null) {
            usort($rows, static function (array $a, array $b) use ($order, $direction): int {
                $av = $a[$order] ?? null;
                $bv = $b[$order] ?? null;
                if ($av == $bv) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
                    return 0;
                }

                if ($direction === 'DESC') {
                    return ($av < $bv) ? 1 : -1;
                }

                return ($av < $bv) ? -1 : 1;
            });
        }

        if ($limit !== null) {
            $rows = array_slice($rows, 0, $limit);
        }

        if ($columns !== '*') {
            $rows = array_map(static function (array $row) use ($columns): array {
                return [$columns => $row[$columns] ?? null];
            }, $rows);
        }

        return $rows;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function get_row(string $query, string $output = 'OBJECT')
    {
        $results = $this->get_results($query, $output);

        return $results[0] ?? null;
    }

    /**
     * @return int|string|null
     */
    public function get_var(string $query)
    {
        $parsed = $this->parseSelect($query);
        if ($parsed === null) {
            return null;
        }

        [$table, $columns] = $parsed;
        $rows = $this->get_results($query, 'ARRAY_A') ?? [];

        if ($columns === 'COUNT(*)') {
            return count($rows);
        }

        if ($rows === []) {
            return null;
        }

        $first = $rows[0];
        $keys = array_keys($first);
        $key = $keys[0] ?? null;

        return $key ? $first[$key] : null;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,string> $format
     */
    public function insert(string $table, array $data, array $format = []): int|false
    {
        $table = strtolower($table);
        if (! isset($this->tables[$table])) {
            $this->tables[$table] = [];
        }

        $row = [];
        foreach ($data as $key => $value) {
            $row[strtolower((string) $key)] = $value;
        }

        if (! array_key_exists('id', $row)) {
            $row['id'] = $this->nextId($table);
        } else {
            $row['id'] = (int) $row['id'];
        }

        $this->insert_id = (int) $row['id'];
        $this->tables[$table][] = $row;

        return 1;
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     * @param array<int,string> $format
     * @param array<int,string> $whereFormat
     */
    public function update(string $table, array $data, array $where, array $format = [], array $whereFormat = []): int|false
    {
        $table = strtolower($table);
        $updated = 0;
        if (! isset($this->tables[$table])) {
            return 0;
        }

        foreach ($this->tables[$table] as &$row) {
            $matches = true;
            foreach ($where as $key => $value) {
                $key = strtolower((string) $key);
                if (($row[$key] ?? null) != $value) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
                    $matches = false;
                    break;
                }
            }

            if (! $matches) {
                continue;
            }

            foreach ($data as $key => $value) {
                $row[strtolower((string) $key)] = $value;
            }
            $updated++;
        }
        unset($row);

        return $updated;
    }

    /**
     * @param array<string,mixed> $where
     * @param array<int,string> $whereFormat
     */
    public function delete(string $table, array $where, array $whereFormat = []): int|false
    {
        $table = strtolower($table);
        if (! isset($this->tables[$table])) {
            return 0;
        }

        $remaining = [];
        $deleted = 0;
        foreach ($this->tables[$table] as $row) {
            $matches = true;
            foreach ($where as $key => $value) {
                $key = strtolower((string) $key);
                if (($row[$key] ?? null) != $value) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $deleted++;
                continue;
            }

            $remaining[] = $row;
        }

        $this->tables[$table] = $remaining;

        return $deleted;
    }

    public function query(string $sql): int|false
    {
        $sql = trim($sql);
        if ($sql === '') {
            return 0;
        }

        $normalized = strtoupper($sql);
        if (str_starts_with($normalized, 'START TRANSACTION') || $normalized === 'COMMIT' || $normalized === 'ROLLBACK') {
            return 1;
        }

        if (preg_match('/^UPDATE\s+(\S+)\s+SET\s+IS_DEFAULT\s*=\s*0\s+WHERE\s+ID\s+!=\s*(\d+)/i', $sql, $m)) {
            $table = strtolower($m[1]);
            $keepId = (int) $m[2];
            foreach ($this->tables[$table] ?? [] as &$row) {
                if (($row['id'] ?? null) !== $keepId) {
                    $row['is_default'] = 0;
                }
            }
            unset($row);

            return 1;
        }

        if (preg_match('/^INSERT\s+INTO\s+(\S+)\s*\(([^)]+)\)\s*VALUES\s*\((.+)\)$/i', $sql, $m)) {
            $table = strtolower($m[1]);
            $columns = array_map(static fn($col) => strtolower(trim($col, " `")), explode(',', $m[2]));
            $values = str_getcsv($m[3], ',', "'", '\\');
            $row = [];
            foreach ($columns as $index => $column) {
                $value = $values[$index] ?? '';
                $row[$column] = $this->unquote($value);
            }

            if ($table === $this->tableName('locks')) {
                foreach ($this->tables[$table] as $existing) {
                    if (($existing['lock_key'] ?? null) === ($row['lock_key'] ?? null)) {
                        return 0;
                    }
                }
            }

            $this->insert($table, $row);

            return 1;
        }

        return 0;
    }

    public function get_charset_collate(): string
    {
        return 'DEFAULT CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    private function nextId(string $table): int
    {
        $max = 0;
        foreach ($this->tables[$table] ?? [] as $row) {
            $max = max($max, (int) ($row['id'] ?? 0));
        }

        return $max + 1;
    }

    private function unquote(string $value): string
    {
        $value = trim($value);
        if ($value === "''" || $value === '""') {
            return '';
        }

        if ((str_starts_with($value, "'") && str_ends_with($value, "'")) || (str_starts_with($value, '"') && str_ends_with($value, '"'))) {
            $value = substr($value, 1, -1);
        }

        return stripcslashes($value);
    }

    /**
     * @return array{0:string,1:string,2:array<int,array{0:string,1:string,2:mixed}>,3:?string,4:string,5:?int}|null
     */
    private function parseSelect(string $query): ?array
    {
        if (! preg_match('/^SELECT\s+(?<columns>\*|COUNT\(\*\))\s+FROM\s+(?<table>\S+)\s*(?<rest>.*)$/i', trim($query), $m)) {
            return null;
        }

        $table = strtolower($m['table']);
        $columns = strtoupper($m['columns']);
        $rest = $m['rest'];
        $conditions = [];
        $order = null;
        $direction = 'ASC';
        $limit = null;

        if (preg_match('/WHERE\s+(?<where>.+?)(ORDER BY|LIMIT|$)/i', $rest, $whereMatch)) {
            $clauses = preg_split('/\s+AND\s+/i', trim($whereMatch['where']));
            if ($clauses !== false) {
                foreach ($clauses as $clause) {
                    $clause = trim($clause, ' ()');
                    if ($clause === '') {
                        continue;
                    }

                    if (preg_match('/^(?<field>[a-z0-9_]+)\s+IN\s*\((?<values>[^)]+)\)$/i', $clause, $inMatch)) {
                        $values = array_map(static function ($part): string {
                            $part = trim($part);
                            if ($part === '') {
                                return '';
                            }
                            if (($part[0] ?? '') === "'" || ($part[0] ?? '') === '"') {
                                return trim($part, "'\"");
                            }

                            return $part;
                        }, explode(',', $inMatch['values']));
                        $conditions[] = [strtolower($inMatch['field']), 'IN', $values];
                        continue;
                    }

                    if (preg_match('/^(?<field>[a-z0-9_]+)\s*!=\s*(?<value>.+)$/i', $clause, $neqMatch)) {
                        $value = trim($neqMatch['value']);
                        $conditions[] = [strtolower($neqMatch['field']), '!=', trim($value, "'\"")];
                        continue;
                    }

                    if (preg_match('/^(?<field>[a-z0-9_]+)\s*=\s*(?<value>.+)$/i', $clause, $eqMatch)) {
                        $value = trim($eqMatch['value']);
                        $conditions[] = [strtolower($eqMatch['field']), '=', trim($value, "'\"")];
                    }
                }
            }
        }

        if (preg_match('/ORDER BY\s+(?<field>[a-z0-9_]+)\s*(?<dir>ASC|DESC)?/i', $rest, $orderMatch)) {
            $order = strtolower($orderMatch['field']);
            if (! empty($orderMatch['dir'])) {
                $direction = strtoupper($orderMatch['dir']);
            }
        }

        if (preg_match('/LIMIT\s+(?<limit>\d+)/i', $rest, $limitMatch)) {
            $limit = (int) $limitMatch['limit'];
        }

        return [$table, $columns, $conditions, $order, $direction, $limit];
    }
}

RuntimeEnv::init(dirname(__DIR__, 2));
}

namespace PHPMailer\PHPMailer {
    if (! class_exists(PHPMailer::class, false)) {
        class PHPMailer
        {
            public string $ErrorInfo = '';
            public bool $SMTPAuth = false;
            public string $SMTPSecure = '';
            public string $Host = '';
            public int $Port = 0;
            public string $Username = '';
            public string $Password = '';

            public function isSMTP(): void
            {
            }
        }
    }
}

namespace {

use FP\DMS\Audit\RuntimeEnv;
use FP\DMS\Audit\RuntimeWpdb;
use PHPMailer\PHPMailer\PHPMailer;

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
    define('ARRAY_N', 'ARRAY_N');
    define('OBJECT', 'OBJECT');
    define('OBJECT_K', 'OBJECT_K');
}

if (! defined('MINUTE_IN_SECONDS')) {
    define('SECOND_IN_SECONDS', 1);
    define('MINUTE_IN_SECONDS', 60);
    define('HOUR_IN_SECONDS', 3600);
    define('DAY_IN_SECONDS', 86400);
    define('WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS);
    define('MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS);
}

if (! class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(public string $code = '', public string $message = '')
        {
        }
    }
}

if (! class_exists('wpdb')) {
    class wpdb extends RuntimeWpdb
    {
    }
}

global $wpdb;
$wpdb = new wpdb();

if (! function_exists('add_action')) {
    function add_action(string $hook, callable $callback): void
    {
        $GLOBALS['__fpdms_actions'][$hook][] = $callback;
    }
}

if (! function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        foreach ($GLOBALS['__fpdms_actions'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}

if (! function_exists('apply_filters')) {
    function apply_filters(string $hook, $value)
    {
        foreach ($GLOBALS['__fpdms_filters'][$hook] ?? [] as $callback) {
            $value = $callback($value);
        }

        return $value;
    }
}

if (! function_exists('get_option')) {
    function get_option(string $name, $default = false)
    {
        RuntimeEnv::purgeExpiredTransients();

        return RuntimeEnv::$options[$name] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $name, $value, bool $autoload = false): void
    {
        RuntimeEnv::$options[$name] = $value;
    }
}

if (! function_exists('delete_option')) {
    function delete_option(string $name): void
    {
        unset(RuntimeEnv::$options[$name]);
    }
}

if (! function_exists('get_transient')) {
    function get_transient(string $name)
    {
        RuntimeEnv::purgeExpiredTransients();

        return RuntimeEnv::$transients[$name]['value'] ?? false;
    }
}

if (! function_exists('set_transient')) {
    function set_transient(string $name, $value, int $expiration): void
    {
        $expires = $expiration > 0 ? time() + $expiration : null;
        RuntimeEnv::$transients[$name] = ['value' => $value, 'expires' => $expires];
    }
}

if (! function_exists('delete_transient')) {
    function delete_transient(string $name): void
    {
        unset(RuntimeEnv::$transients[$name]);
    }
}

if (! function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($special_chars) {
            $chars .= '!@#$%^&*()';
        }
        if ($extra_special_chars) {
            $chars .= '-_=+[]{}';
        }
        $password = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, $max)];
        }

        return $password;
    }
}

if (! function_exists('current_time')) {
    function current_time(string $type, bool $gmt = false)
    {
        $timestamp = time();
        if ($type === 'mysql') {
            return gmdate('Y-m-d H:i:s', $timestamp);
        }

        if ($type === 'timestamp') {
            return $timestamp;
        }

        return $timestamp;
    }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($data, int $options = 0, int $depth = 512): string
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | $options, $depth);

        return $encoded === false ? 'null' : $encoded;
    }
}

if (! function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array
    {
        return RuntimeEnv::uploadDir();
    }
}

if (! function_exists('wp_mkdir_p')) {
    function wp_mkdir_p(string $path): bool
    {
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, 0777, true);
    }
}

if (! function_exists('trailingslashit')) {
    function trailingslashit(string $path): string
    {
        return rtrim($path, '/\\') . '/';
    }
}

if (! function_exists('wp_date')) {
    function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
    {
        $timestamp = $timestamp ?? time();
        $timezone = $timezone ?? new DateTimeZone(date_default_timezone_get());
        $date = (new DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);

        return $date->format($format);
    }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return esc_html($text);
    }
}

if (! function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}

if (! function_exists('sanitize_hex_color')) {
    function sanitize_hex_color(?string $color): ?string
    {
        if (! is_string($color)) {
            return null;
        }

        $color = trim($color);
        if ($color === '') {
            return null;
        }

        if ($color[0] !== '#') {
            $color = '#' . $color;
        }

        if (preg_match('/^#([0-9a-fA-F]{3}){1,2}$/', $color)) {
            return strtolower($color);
        }

        return null;
    }
}

if (! function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('_x')) {
    function _x(string $text, string $context, string $domain = 'default'): string
    {
        return $text;
    }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string
    {
        return esc_html(__($text, $domain));
    }
}

if (! function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return esc_url($url);
    }
}

if (! function_exists('esc_html_x')) {
    function esc_html_x(string $text, string $context, string $domain = 'default'): string
    {
        return esc_html(_x($text, $context, $domain));
    }
}

if (! function_exists('number_format_i18n')) {
    function number_format_i18n(float $number, int $decimals = 0): string
    {
        return number_format($number, $decimals, '.', ',');
    }
}

if (! function_exists('sanitize_title')) {
    function sanitize_title(string $title): string
    {
        $title = strtolower($title);
        $title = preg_replace('/[^a-z0-9]+/', '-', $title) ?? '';

        return trim($title, '-');
    }
}

if (! function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        return filter_var($email, FILTER_SANITIZE_EMAIL) ?: '';
    }
}

if (! function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        $key = strtolower($key);

        return preg_replace('/[^a-z0-9_]/', '', $key) ?? '';
    }
}

if (! function_exists('is_email')) {
    function is_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (! function_exists('wp_mail')) {
    function wp_mail($to, string $subject, string $message, array $headers = [], array $attachments = []): bool
    {
        $recipients = is_array($to) ? $to : [$to];
        RuntimeEnv::recordMail([
            'to' => array_values($recipients),
            'subject' => $subject,
            'headers' => $headers,
            'attachments' => $attachments,
            'message' => $message,
        ]);
        $GLOBALS['phpmailer'] = new PHPMailer();

        return true;
    }
}

if (! function_exists('wp_remote_post')) {
    function wp_remote_post(string $url, array $args = [])
    {
        $body = $args['body'] ?? '';
        if (is_array($body)) {
            $body = wp_json_encode($body);
        }
        RuntimeEnv::recordRemote([
            'url' => $url,
            'args' => $args,
            'timestamp' => time(),
        ]);

        return [
            'body' => $body,
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => [],
        ];
    }
}

if (! function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(array $response): int
    {
        return (int) ($response['response']['code'] ?? 0);
    }
}

if (! function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (! function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(array $response): string
    {
        return (string) ($response['body'] ?? '');
    }
}

if (! function_exists('wp_remote_retrieve_response_message')) {
    function wp_remote_retrieve_response_message(array $response): string
    {
        return (string) ($response['response']['message'] ?? '');
    }
}

if (! function_exists('wp_suspend_cache_invalidation')) {
    function wp_suspend_cache_invalidation(bool $suspend = true): bool
    {
        $previous = $GLOBALS['__fpdms_cache_suspended'] ?? false;
        $GLOBALS['__fpdms_cache_suspended'] = $suspend;

        return (bool) $previous;
    }
}

if (! function_exists('wp_sleep')) {
    function wp_sleep(int $seconds): void
    {
        if ($seconds > 0) {
            usleep($seconds * 1_000_000);
        }
    }
}

if (! function_exists('wp_timezone')) {
    function wp_timezone(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get());
    }
}

if (! function_exists('esc_js')) {
    function esc_js(string $text): string
    {
        return addslashes($text);
    }
}

if (! function_exists('esc_textarea')) {
    function esc_textarea(string $text): string
    {
        return esc_html($text);
    }
}

if (! function_exists('checked')) {
    function checked($checked, $current = true, bool $echo = true): string
    {
        $result = $checked == $current ? 'checked="checked"' : ''; // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
        if ($echo) {
            echo $result; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        return $result;
    }
}

if (! function_exists('esc_attr__')) {
    function esc_attr__(string $text, string $domain = 'default'): string
    {
        return esc_attr(__($text, $domain));
    }
}

if (! defined('ABSPATH')) {
    define('ABSPATH', RuntimeEnv::$root . '/wp/');
}
}

namespace FP\DMS\Audit {

use FP\DMS\Services\Qa\Automation;

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'FP\\DMS\\')) {
        $relative = substr($class, strlen('FP\\DMS\\'));
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        $path = RuntimeEnv::$root . '/src/' . $relative . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

$expectedKeys = [
    'seed' => ['qa', 'client_id', 'datasources', 'schedule', 'status'],
    'run' => ['qa', 'client_id', 'report_id', 'pdf', 'email', 'locks', 'warnings', 'status'],
    'anomalies' => ['qa', 'client_id', 'anomalies', 'severities', 'status'],
    'status' => ['qa', 'client_id', 'schedules', 'last_report', 'anomalies_count', 'last_tick', 'mail_last_result', 'warnings', 'status'],
];

$automation = new Automation();
$seed = $automation->seed();
$run = $automation->run(false);
$anomalies = $automation->anomalies(false);
$status = $automation->status();

$results = compact('seed', 'run', 'anomalies', 'status');
$missing = [];
foreach ($expectedKeys as $stage => $keys) {
    $missing[$stage] = [];
    $payload = $results[$stage];
    foreach ($keys as $key) {
        if (! array_key_exists($key, $payload)) {
            $missing[$stage][] = $key;
        }
    }
}

$notes = [];
$mpdfWarning = false;
if (($run['status'] ?? '') !== 'PASS') {
    $warnings = array_map('strval', $run['warnings'] ?? []);
    foreach ($warnings as $warning) {
        $lower = strtolower($warning);
        if (str_contains($lower, 'mpdf') || str_contains($lower, 'pdf renderer missing')) {
            $mpdfWarning = true;
            break;
        }
    }

    if ($mpdfWarning) {
        $notes[] = 'PDF renderer missing – recorded as WARN.';
    }
}

$report = [
    'seed' => $seed,
    'run' => $run,
    'anomalies' => $anomalies,
    'status' => $status,
    'http_requests' => count(RuntimeEnv::$remoteRequests),
    'mail_events' => count(RuntimeEnv::$mailLog),
    'remote_requests' => RuntimeEnv::$remoteRequests,
    'mail_log' => RuntimeEnv::$mailLog,
    'missing_keys' => $missing,
    'notes' => $notes,
];

file_put_contents(__DIR__ . '/runtime.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$summary = [
    'seed' => $seed['status'] ?? 'UNKNOWN',
    'run' => $run['status'] ?? 'UNKNOWN',
    'anomalies' => $anomalies['status'] ?? 'UNKNOWN',
    'status' => $status['status'] ?? 'UNKNOWN',
    'http_requests' => $report['http_requests'],
    'mail_events' => $report['mail_events'],
    'mpdf_warning' => $mpdfWarning,
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
