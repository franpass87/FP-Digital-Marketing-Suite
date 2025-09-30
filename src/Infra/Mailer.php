<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\Domain\Entities\Client;
use FP\DMS\Domain\Entities\ReportJob;
use FP\DMS\Support\I18n;
use FP\DMS\Support\Period;
use PHPMailer\PHPMailer\PHPMailer;
use function is_email;
use function sanitize_email;

class Mailer
{
    public static function bootstrap(): void
    {
        add_action('phpmailer_init', [self::class, 'configureMailer']);
    }

    public function sendReport(Client $client, ReportJob $report, Period $period): bool
    {
        $settings = Options::getGlobalSettings();
        $primary = $this->sanitizeEmails($client->emailTo);
        $cc = $this->sanitizeEmails($client->emailCc);

        $owner = isset($settings['owner_email']) ? sanitize_email((string) $settings['owner_email']) : '';
        if ($owner !== '' && ! is_email($owner)) {
            $owner = '';
        }

        if (empty($primary) && $owner !== '') {
            $primary[] = $owner;
        }

        if (empty($primary)) {
            return false;
        }

        $cc = array_values(array_filter(
            $cc,
            static fn(string $email): bool => ! in_array($email, $primary, true)
        ));

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        if (! empty($cc)) {
            $headers[] = 'Cc: ' . implode(', ', $cc);
        }

        /* translators: 1: client name, 2: period start date, 3: period end date */
        $subject = sprintf(I18n::__('[Report] %1$s – %2$s to %3$s'), $client->name, $period->start->format('Y-m-d'), $period->end->format('Y-m-d'));
        $body = '<p>' . esc_html(sprintf(I18n::__('Attached you will find the latest marketing report for %s.'), $client->name)) . '</p>';

        if ($owner !== '' && ! in_array($owner, $primary, true) && ! in_array($owner, $cc, true)) {
            $headers[] = 'Bcc: ' . $owner;
        }

        if (empty($report->storagePath)) {
            return false;
        }

        $upload = wp_upload_dir();
        $attachment = trailingslashit($upload['basedir']) . $report->storagePath;

        if (! file_exists($attachment)) {
            return false;
        }

        return $this->sendWithRetry($primary, $subject, $body, $headers, [$attachment]);
    }

    /**
     * @param string[]|string $to
     * @param string[] $headers
     * @param string[] $attachments
     */
    public function sendWithRetry(array|string $to, string $subject, string $bodyHtml, array $headers = [], array $attachments = [], int $maxAttempts = 3): bool
    {
        $delays = [0, 30, 120];
        $attempt = 0;
        $startedAt = microtime(true);

        while ($attempt < $maxAttempts) {
            $attempt++;
            $success = wp_mail($to, $subject, $bodyHtml, $headers, $attachments);
            if ($success) {
                Logger::log(sprintf('MAIL_SENT attempt=%d subject="%s"', $attempt, $subject));

                return true;
            }

            $error = $this->describeLastError();
            Logger::log(sprintf('MAIL_RETRY_FAIL attempt=%d subject="%s" error="%s"', $attempt, $subject, $error));

            if ($attempt >= $maxAttempts) {
                break;
            }

            $delay = (int) ($delays[$attempt] ?? end($delays));
            if ($delay <= 0) {
                continue;
            }

            $remaining = $this->remainingExecutionTime($startedAt);
            if ($remaining <= 0) {
                Logger::log(sprintf('MAIL_BACKOFF_SKIPPED attempt=%d subject="%s"', $attempt, $subject));
                continue;
            }

            $this->sleepSeconds((int) min($delay, $remaining));
        }

        Logger::log(sprintf('MAIL_FAILED subject="%s"', $subject));

        return false;
    }

    /**
     * @param array<int,array<string,mixed>> $anomalies
     */
    public function sendAnomalyAlert(Client $client, array $anomalies, Period $period): bool
    {
        $settings = Options::getGlobalSettings();
        $owner = $settings['owner_email'] ?? '';
        if (! is_string($owner) || $owner === '') {
            return false;
        }

        $first = $anomalies[0] ?? [];
        $metric = is_array($first) && isset($first['metric']) ? (string) $first['metric'] : 'metric';
        $severity = is_array($first) && isset($first['severity']) ? (string) $first['severity'] : 'warn';
        $subject = self::buildAnomalySubject($client, $metric, $severity);
        $body = '<p>' . esc_html(sprintf(I18n::__('The anomaly detector flagged the following metrics for %s.'), $client->name)) . '</p><ul>';
        foreach ($anomalies as $anomaly) {
            if (! is_array($anomaly)) {
                continue;
            }
            $metric = esc_html((string) ($anomaly['metric'] ?? I18n::__('metric')));
            $delta = isset($anomaly['delta_percent']) ? number_format_i18n((float) $anomaly['delta_percent'], 1) . '%' : I18n::__('n/a');
            $severity = esc_html((string) ($anomaly['severity'] ?? 'warn'));
            $body .= '<li><strong>' . $metric . '</strong> – ' . esc_html(sprintf(I18n::__('Δ %s (%s)'), $delta, $severity)) . '</li>';
        }
        $body .= '</ul>';

        return $this->sendWithRetry($owner, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    public static function buildAnomalySubject(Client $client, string $metric, string $severity): string
    {
        /* translators: 1: client name, 2: metric, 3: severity */
        return sprintf(I18n::__('[Anomaly] %1$s — %2$s (%3$s)'), $client->name, ucfirst($metric), ucfirst($severity));
    }

    /**
     * @param string[] $emails
     * @return string[]
     */
    private function sanitizeEmails(array $emails): array
    {
        $clean = [];
        foreach ($emails as $email) {
            $sanitized = sanitize_email((string) $email);
            if ($sanitized === '' || ! is_email($sanitized)) {
                continue;
            }
            if (! in_array($sanitized, $clean, true)) {
                $clean[] = $sanitized;
            }
        }

        return $clean;
    }

    private function describeLastError(): string
    {
        global $phpmailer;
        if (isset($phpmailer) && $phpmailer instanceof PHPMailer) {
            $info = trim((string) $phpmailer->ErrorInfo);
            if ($info !== '') {
                return $info;
            }
        }

        $last = error_get_last();
        if (is_array($last) && isset($last['message'])) {
            return (string) $last['message'];
        }

        return 'unknown_error';
    }

    private function remainingExecutionTime(float $startedAt): int
    {
        $limit = (int) ini_get('max_execution_time');
        if ($limit <= 0) {
            return PHP_INT_MAX;
        }

        $elapsed = (int) floor(microtime(true) - $startedAt);

        return max($limit - $elapsed - 1, 0);
    }

    private function sleepSeconds(int $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        $previousState = null;
        if (function_exists('wp_suspend_cache_invalidation')) {
            $previousState = wp_suspend_cache_invalidation(true);
        }

        if (function_exists('wp_sleep')) {
            wp_sleep($seconds);
        } elseif ($seconds === 1) {
            usleep(1_000_000);
        } else {
            sleep($seconds);
        }

        if ($previousState !== null) {
            wp_suspend_cache_invalidation((bool) $previousState);
        }
    }

    public static function configureMailer(PHPMailer $phpmailer): void
    {
        $settings = Options::getGlobalSettings();
        $smtp = $settings['mail']['smtp'] ?? [];

        $host = isset($smtp['host']) ? trim((string) $smtp['host']) : '';
        $port = isset($smtp['port']) ? (int) $smtp['port'] : 0;

        if ($host === '' || $port <= 0) {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = $port;

        $secure = isset($smtp['secure']) ? strtolower((string) $smtp['secure']) : 'none';
        if ($secure === 'ssl' || $secure === 'tls') {
            $phpmailer->SMTPSecure = $secure;
        } else {
            $phpmailer->SMTPSecure = '';
        }

        $username = isset($smtp['user']) ? trim((string) $smtp['user']) : '';
        $password = isset($smtp['pass']) ? (string) $smtp['pass'] : '';

        $phpmailer->SMTPAuth = $username !== '';
        if ($username !== '') {
            $phpmailer->Username = $username;
        }

        if ($password !== '') {
            $phpmailer->Password = $password;
        }
    }
}
