<?php

declare(strict_types=1);

namespace FP\DMS\Support;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use RuntimeException;

if (! \defined('SECOND_IN_SECONDS')) {
    \define('SECOND_IN_SECONDS', 1);
}

if (! \defined('MINUTE_IN_SECONDS')) {
    \define('MINUTE_IN_SECONDS', 60 * SECOND_IN_SECONDS);
}

if (! \defined('HOUR_IN_SECONDS')) {
    \define('HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS);
}

if (! \defined('DAY_IN_SECONDS')) {
    \define('DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS);
}

if (! \defined('WEEK_IN_SECONDS')) {
    \define('WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS);
}

if (! \defined('MONTH_IN_SECONDS')) {
    \define('MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS);
}

if (! \defined('YEAR_IN_SECONDS')) {
    \define('YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS);
}

final class Wp
{
    public static function absInt(mixed $value): int
    {
        if (\function_exists('absint')) {
            /** @var int $result */
            $result = \absint($value);

            return $result;
        }

        if (\is_int($value)) {
            return $value < 0 ? -$value : $value;
        }

        if (\is_float($value)) {
            return (int) \abs($value);
        }

        if (\is_numeric($value)) {
            return (int) \abs((float) $value);
        }

        return 0;
    }

    public static function sanitizeTextField(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('sanitize_text_field')) {
            return \sanitize_text_field($string);
        }

        $stripped = \strip_tags($string);
        $normalized = \preg_replace('/[\r\n\t]+/', ' ', $stripped);

        return \trim($normalized ?? $stripped);
    }

    public static function sanitizeTitle(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('sanitize_title')) {
            return (string) \sanitize_title($string);
        }

        $lower = \strtolower(\strip_tags($string));
        $slug = \preg_replace('/[^a-z0-9]+/', '-', $lower);

        return \trim($slug ?? '', '-');
    }

    public static function sanitizeKey(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('sanitize_key')) {
            return \sanitize_key($string);
        }

        $lower = \strtolower($string);
        $sanitized = \preg_replace('/[^a-z0-9_\-]/', '', $lower);

        return $sanitized ?? '';
    }

    public static function sanitizeEmail(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('sanitize_email')) {
            return \sanitize_email($string);
        }

        $sanitized = \filter_var($string, FILTER_SANITIZE_EMAIL);

        if (! \is_string($sanitized)) {
            return '';
        }

        return \trim($sanitized);
    }

    public static function isEmail(mixed $value): bool
    {
        if (\function_exists('is_email')) {
            return \is_email($value) !== false;
        }

        if ($value instanceof \Stringable) {
            $candidate = (string) $value;
        } elseif (\is_string($value)) {
            $candidate = $value;
        } elseif (\is_scalar($value)) {
            $candidate = (string) $value;
        } else {
            return false;
        }

        $candidate = \trim($candidate);
        if ($candidate === '') {
            return false;
        }

        return \filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function unslash(mixed $value): mixed
    {
        if (\function_exists('wp_unslash')) {
            return \wp_unslash($value);
        }

        if (\is_array($value)) {
            return \array_map([self::class, 'unslash'], $value);
        }

        if (\is_string($value)) {
            return \stripslashes($value);
        }

        return $value;
    }

    public static function ksesPost(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('wp_kses_post')) {
            return \wp_kses_post($string);
        }

        return \strip_tags($string, '<a><strong><em><br><p><ul><ol><li>');
    }

    public static function escHtml(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('esc_html')) {
            return \esc_html($string);
        }

        return \htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function escAttr(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('esc_attr')) {
            return \esc_attr($string);
        }

        return \htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function escTextarea(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('esc_textarea')) {
            return \esc_textarea($string);
        }

        return \htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function escJs(mixed $value): string
    {
        $string = (string) $value;

        if (\function_exists('esc_js')) {
            return \esc_js($string);
        }

        $escaped = \addcslashes($string, "\"'\\\n\r\t\0\x0B");

        return \str_replace('</', '<\/', $escaped);
    }

    public static function escUrl(mixed $value): string
    {
        $url = (string) $value;

        if (\function_exists('esc_url')) {
            return \esc_url($url);
        }

        return self::sanitizeUrl($url, true);
    }

    public static function escUrlRaw(mixed $value): string
    {
        $url = (string) $value;

        if (\function_exists('esc_url_raw')) {
            return \esc_url_raw($url);
        }

        return self::sanitizeUrl($url, false);
    }

    public static function sanitizeHexColor(mixed $value): string
    {
        $color = (string) $value;

        if (\function_exists('sanitize_hex_color')) {
            $sanitized = \sanitize_hex_color($color);

            return $sanitized === null ? '' : $sanitized;
        }

        $color = \trim($color);

        if ($color === '') {
            return '';
        }

        if (! \preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $color)) {
            return '';
        }

        return \strtolower($color);
    }

    public static function restSanitizeBoolean(mixed $value): bool
    {
        if (\function_exists('rest_sanitize_boolean')) {
            /** @var bool $result */
            $result = \rest_sanitize_boolean($value);

            return $result;
        }

        $filtered = \filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($filtered !== null) {
            return $filtered;
        }

        return (bool) $value;
    }

    public static function numberFormatI18n(float $number, int $decimals = 0): string
    {
        if (\function_exists('number_format_i18n')) {
            return \number_format_i18n($number, $decimals);
        }

        $locale = \localeconv();
        $decimalPoint = '.';
        $thousandsSep = ',';

        if (\is_array($locale)) {
            if (isset($locale['decimal_point']) && \is_string($locale['decimal_point']) && $locale['decimal_point'] !== '') {
                $decimalPoint = $locale['decimal_point'];
            }

            if (isset($locale['thousands_sep']) && \is_string($locale['thousands_sep']) && $locale['thousands_sep'] !== '') {
                $thousandsSep = $locale['thousands_sep'];
            }
        }

        return \number_format($number, max(0, $decimals), $decimalPoint, $thousandsSep);
    }

    public static function jsonEncode(mixed $data, int $options = 0, int $depth = 512): string|false
    {
        if (\function_exists('wp_json_encode')) {
            return \wp_json_encode($data, $options, $depth);
        }

        $defaultOptions = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_APOS;
        $encodeOptions = $options === 0 ? $defaultOptions : $options;

        return \json_encode($data, $encodeOptions, $depth);
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function remotePost(string $url, array $args = []): mixed
    {
        if (\function_exists('wp_remote_post')) {
            return \wp_remote_post($url, $args);
        }

        return self::remoteRequest('POST', $url, $args);
    }

    public static function isWpError(mixed $value): bool
    {
        if (\function_exists('is_wp_error')) {
            return \is_wp_error($value);
        }

        return \is_array($value) && isset($value['error']);
    }

    public static function remoteRetrieveResponseCode(mixed $response): int
    {
        if (\function_exists('wp_remote_retrieve_response_code')) {
            return (int) \wp_remote_retrieve_response_code($response);
        }

        if (\is_array($response) && isset($response['response']) && \is_array($response['response'])) {
            $code = $response['response']['code'] ?? 0;

            if (\is_numeric($code)) {
                return (int) $code;
            }
        }

        return 0;
    }

    public static function remoteRetrieveBody(mixed $response): string
    {
        if (\function_exists('wp_remote_retrieve_body')) {
            $body = \wp_remote_retrieve_body($response);

            return \is_string($body) ? $body : '';
        }

        if (\is_array($response) && isset($response['body'])) {
            $body = $response['body'];

            return \is_string($body) ? $body : '';
        }

        return '';
    }

    public static function wpErrorMessage(mixed $value): string
    {
        if (\function_exists('is_wp_error') && \is_wp_error($value)) {
            $message = $value->get_error_message();

            return \is_string($message) ? \trim($message) : '';
        }

        if (! \is_array($value)) {
            return '';
        }

        if (isset($value['error_message']) && \is_string($value['error_message'])) {
            return \trim($value['error_message']);
        }

        if (isset($value['error']) && \is_string($value['error'])) {
            return \trim($value['error']);
        }

        return '';
    }

    public static function generatePassword(int $length = 12, bool $specialChars = true, bool $extraSpecialChars = false): string
    {
        if (\function_exists('wp_generate_password')) {
            return (string) \wp_generate_password($length, $specialChars, $extraSpecialChars);
        }

        if ($length <= 0) {
            return '';
        }

        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        if ($specialChars) {
            $chars .= '!@#$%^&*()';
        }

        if ($extraSpecialChars) {
            $chars .= '-_[]{}<>~`+=,.;:/?|';
        }

        $password = '';
        $maxIndex = \strlen($chars) - 1;

        for ($i = 0; $i < $length; $i++) {
            try {
                $index = \random_int(0, $maxIndex);
            } catch (Exception) {
                $index = $maxIndex > 0 ? \mt_rand(0, $maxIndex) : 0;
            }

            $password .= $chars[$index] ?? '';
        }

        return $password;
    }

    public static function normalizePath(string $path): string
    {
        if (\function_exists('wp_normalize_path')) {
            return (string) \wp_normalize_path($path);
        }

        $normalized = \str_replace('\\', '/', $path);
        $normalized = \preg_replace('|/+|', '/', $normalized);

        return $normalized ?? $path;
    }

    public static function trailingSlashIt(string $path): string
    {
        if (\function_exists('trailingslashit')) {
            return (string) \trailingslashit($path);
        }

        return \rtrim($path, '/\\') . '/';
    }

    /**
     * @return array{path:string,url:string,subdir:string,basedir:string,baseurl:string,error:false|string}
     */
    public static function uploadDir(): array
    {
        if (\function_exists('wp_upload_dir')) {
            $dir = \wp_upload_dir();

            if (\is_array($dir)) {
                return $dir;
            }
        }

        $base = self::normalizePath(\rtrim((string) \sys_get_temp_dir(), '\\/') . '/fp-dms-uploads');

        try {
            self::mkdirP($base);
            $error = false;
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        }

        return [
            'path' => $base,
            'url' => '',
            'subdir' => '',
            'basedir' => $base,
            'baseurl' => '',
            'error' => $error,
        ];
    }

    public static function mkdirP(string $path): bool
    {
        if (\function_exists('wp_mkdir_p')) {
            return (bool) \wp_mkdir_p($path);
        }

        if ($path === '') {
            return false;
        }

        if (\is_dir($path)) {
            return true;
        }

        if (@\mkdir($path, 0777, true)) {
            return true;
        }

        if (\is_dir($path)) {
            return true;
        }

        throw new RuntimeException('Unable to create directory: ' . $path);
    }

    public static function secondInSeconds(): int
    {
        return self::durationConstant('SECOND_IN_SECONDS', 1);
    }

    public static function minuteInSeconds(): int
    {
        return self::durationConstant('MINUTE_IN_SECONDS', 60);
    }

    public static function hourInSeconds(): int
    {
        return self::durationConstant('HOUR_IN_SECONDS', 60 * self::minuteInSeconds());
    }

    public static function dayInSeconds(): int
    {
        return self::durationConstant('DAY_IN_SECONDS', 24 * self::hourInSeconds());
    }

    public static function weekInSeconds(): int
    {
        return self::durationConstant('WEEK_IN_SECONDS', 7 * self::dayInSeconds());
    }

    public static function monthInSeconds(): int
    {
        return self::durationConstant('MONTH_IN_SECONDS', 30 * self::dayInSeconds());
    }

    public static function yearInSeconds(): int
    {
        return self::durationConstant('YEAR_IN_SECONDS', 365 * self::dayInSeconds());
    }

    public static function timezoneString(): string
    {
        if (\function_exists('wp_timezone_string')) {
            return (string) \wp_timezone_string();
        }

        $iniTz = \ini_get('date.timezone');
        if (\is_string($iniTz) && $iniTz !== '') {
            return $iniTz;
        }

        $default = \date_default_timezone_get();

        return \is_string($default) && $default !== '' ? $default : 'UTC';
    }

    public static function timezone(): DateTimeZone
    {
        if (\function_exists('wp_timezone')) {
            $wpTz = \wp_timezone();
            if ($wpTz instanceof DateTimeZone) {
                return $wpTz;
            }
        }

        $identifier = self::timezoneString();

        try {
            return new DateTimeZone($identifier);
        } catch (Exception) {
            return new DateTimeZone('UTC');
        }
    }

    public static function date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
    {
        if (\function_exists('wp_date')) {
            return (string) \wp_date($format, $timestamp, $timezone);
        }

        $ts = $timestamp ?? \time();
        $tz = $timezone ?? self::timezone();

        try {
            $date = new DateTimeImmutable('@' . $ts);

            return $date->setTimezone($tz)->format($format);
        } catch (Exception) {
            return \date($format, $ts);
        }
    }

    public static function currentTime(string $type, bool $gmt = false): int|string
    {
        if (\function_exists('current_time')) {
            /** @var int|string $result */
            $result = \current_time($type, $gmt);

            return $result;
        }

        $siteTimezone = self::timezone();

        try {
            $utcNow = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        } catch (Exception) {
            $timestampFallback = \time();
            $fallbackUtc = DateTimeImmutable::createFromFormat('U', (string) $timestampFallback, new DateTimeZone('UTC'));

            if ($fallbackUtc === false) {
                $offset = 0;
                try {
                    $offset = $siteTimezone->getOffset(new DateTimeImmutable('@' . $timestampFallback, new DateTimeZone('UTC')));
                } catch (Exception) {
                    $offset = 0;
                }

                if ($type === 'timestamp' || $type === 'U') {
                    return $gmt ? $timestampFallback : $timestampFallback + $offset;
                }

                $format = $type === 'mysql' ? 'Y-m-d H:i:s' : $type;

                return $gmt
                    ? \gmdate($format, $timestampFallback)
                    : \date($format, $timestampFallback + $offset);
            }

            $utcNow = $fallbackUtc;
        }

        $offset = $siteTimezone->getOffset($utcNow);

        if ($type === 'timestamp' || $type === 'U') {
            return $utcNow->getTimestamp() + ($gmt ? 0 : $offset);
        }

        $format = $type === 'mysql' ? 'Y-m-d H:i:s' : $type;
        $target = $gmt ? $utcNow : $utcNow->setTimezone($siteTimezone);

        return $target->format($format);
    }

    public static function salt(string $scheme = 'auth'): string
    {
        if (\function_exists('wp_salt')) {
            return (string) \wp_salt($scheme);
        }

        $candidates = [];

        $env = \getenv('FP_DMS_SALT');
        if (\is_string($env) && $env !== '') {
            $candidates[] = $env;
        }

        $constants = [
            'AUTH_KEY',
            'SECURE_AUTH_KEY',
            'LOGGED_IN_KEY',
            'NONCE_KEY',
            'AUTH_SALT',
            'SECURE_AUTH_SALT',
            'LOGGED_IN_SALT',
            'NONCE_SALT',
        ];

        foreach ($constants as $constant) {
            if (\defined($constant)) {
                $value = \constant($constant);
                if (\is_string($value) && $value !== '') {
                    $candidates[] = $value;
                }
            }
        }

        $hostname = \gethostname();
        if (\is_string($hostname) && $hostname !== '') {
            $candidates[] = $hostname;
        }

        $candidates[] = PHP_OS_FAMILY;
        $candidates[] = __FILE__;

        $seed = $scheme . '|' . \implode('|', $candidates);

        return \hash('sha256', $seed);
    }

    private static function durationConstant(string $name, int $fallback): int
    {
        if (\defined($name)) {
            $value = \constant($name);

            if (\is_int($value)) {
                return $value;
            }

            if (\is_numeric($value)) {
                return (int) $value;
            }
        }

        return $fallback;
    }

    private static function sanitizeUrl(string $url, bool $forDisplay): string
    {
        $trimmed = \trim($url);
        if ($trimmed === '') {
            return '';
        }

        $sanitized = \filter_var($trimmed, FILTER_SANITIZE_URL);
        if (! \is_string($sanitized)) {
            return '';
        }

        $sanitized = \trim($sanitized);
        if ($sanitized === '') {
            return '';
        }

        $parts = \parse_url($sanitized);
        if ($parts === false) {
            return '';
        }

        if (isset($parts['scheme'])) {
            $scheme = \strtolower($parts['scheme']);
            $allowed = ['http', 'https', 'mailto', 'ftp', 'tel'];
            if (! \in_array($scheme, $allowed, true)) {
                return '';
            }
        }

        if ($forDisplay) {
            return \htmlspecialchars($sanitized, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return $sanitized;
    }

    /**
     * @param array<string,mixed> $args
     * @return array<string,mixed>
     */
    private static function remoteRequest(string $method, string $url, array $args): array
    {
        $timeout = 5.0;
        if (isset($args['timeout']) && \is_numeric($args['timeout'])) {
            $timeout = (float) $args['timeout'];
        }

        $headers = [];
        if (isset($args['headers'])) {
            $headers = self::normalizeHeaders($args['headers']);
        }

        $body = '';
        if (isset($args['body'])) {
            $body = self::normalizeBody($args['body']);
        }

        $contextOptions = [
            'http' => [
                'method' => $method,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ];

        if ($headers !== []) {
            $contextOptions['http']['header'] = \implode("\r\n", $headers);
        }

        if ($body !== '') {
            $contextOptions['http']['content'] = $body;
        }

        if (isset($args['sslverify']) && $args['sslverify'] === false) {
            $contextOptions['ssl'] = ['verify_peer' => false, 'verify_peer_name' => false];
        }

        $context = \stream_context_create($contextOptions);

        try {
            $stream = @\fopen($url, 'r', false, $context);
        } catch (Exception) {
            $stream = false;
        }

        if ($stream === false) {
            return [
                'error' => [
                    'code' => 'http_request_failed',
                    'message' => 'Unable to connect to remote URL.',
                ],
            ];
        }

        $contents = \stream_get_contents($stream);
        $meta = \stream_get_meta_data($stream);
        \fclose($stream);

        $statusLine = '';
        if (isset($meta['wrapper_data']) && \is_array($meta['wrapper_data'])) {
            foreach ($meta['wrapper_data'] as $headerLine) {
                if (! \is_string($headerLine)) {
                    continue;
                }

                if (\str_starts_with($headerLine, 'HTTP/')) {
                    $statusLine = $headerLine;
                }
            }
        }

        $code = 0;
        $message = '';
        if ($statusLine !== '' && \preg_match('/HTTP\/\d\.\d\s+(\d{3})\s*(.*)/', $statusLine, $matches) === 1) {
            $code = (int) ($matches[1] ?? 0);
            $message = \trim((string) ($matches[2] ?? ''));
        }

        return [
            'response' => [
                'code' => $code,
                'message' => $message,
            ],
            'body' => \is_string($contents) ? $contents : '',
        ];
    }

    private static function normalizeBody(mixed $body): string
    {
        if (\is_string($body)) {
            return $body;
        }

        if (\is_array($body)) {
            return \http_build_query($body);
        }

        if (\is_scalar($body)) {
            return (string) $body;
        }

        return '';
    }

    /**
     * @param mixed $headers
     * @return array<int,string>
     */
    private static function normalizeHeaders(mixed $headers): array
    {
        if (\is_string($headers)) {
            $lines = \preg_split('/\r?\n/', $headers) ?: [];

            return array_values(array_filter(array_map('trim', $lines), static fn(string $line): bool => $line !== ''));
        }

        if (! \is_array($headers)) {
            return [];
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            if (! \is_string($name) && ! \is_int($name)) {
                continue;
            }

            if (\is_array($value)) {
                $value = \implode(', ', array_map(static fn($item): string => (string) $item, $value));
            }

            if (! \is_scalar($value)) {
                continue;
            }

            $normalized[] = \sprintf('%s: %s', $name, (string) $value);
        }

        return $normalized;
    }
}
