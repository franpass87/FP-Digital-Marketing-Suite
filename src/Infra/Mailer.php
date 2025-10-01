<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use FP\DMS\Domain\Entities\Client;
use FP\DMS\Domain\Entities\ReportJob;
use FP\DMS\Support\I18n;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;
use PHPMailer\PHPMailer\PHPMailer;
use function ltrim;
use function str_starts_with;
use function file_exists;

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

        $owner = isset($settings['owner_email']) ? Wp::sanitizeEmail($settings['owner_email']) : '';
        if ($owner !== '' && ! Wp::isEmail($owner)) {
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
        $body = '<p>' . Wp::escHtml(sprintf(I18n::__('Attached you will find the latest marketing report for %s.'), $client->name)) . '</p>';

        if ($owner !== '' && ! in_array($owner, $primary, true) && ! in_array($owner, $cc, true)) {
            $headers[] = 'Bcc: ' . $owner;
        }

        if (empty($report->storagePath)) {
            Logger::log('MAIL_ATTACHMENT_MISSING empty_storage_path');
            return false;
        }

        $upload = Wp::uploadDir();
        if (! empty($upload['error']) || empty($upload['basedir'])) {
            Logger::log('MAIL_ATTACHMENT_DIR_MISSING ' . (string) ($upload['error'] ?? 'unknown'));

            return false;
        }
        $baseDir = Wp::trailingSlashIt(Wp::normalizePath($upload['basedir']));
        $relative = Wp::normalizePath(ltrim((string) $report->storagePath, '/\\'));
        if ($relative === '') {
            Logger::log('MAIL_ATTACHMENT_INVALID_PATH empty_relative');

            return false;
        }

        $attachment = Wp::normalizePath($baseDir . $relative);
        if (! str_starts_with($attachment, $baseDir)) {
            Logger::log(sprintf('MAIL_ATTACHMENT_INVALID_PATH path="%s"', $report->storagePath));

            return false;
        }

        if (! file_exists($attachment)) {
            Logger::log(sprintf('MAIL_ATTACHMENT_NOT_FOUND path="%s"', $attachment));
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
        $owner = Wp::sanitizeEmail($settings['owner_email'] ?? '');
        if ($owner === '' || ! Wp::isEmail($owner)) {
            return false;
        }

        $first = $anomalies[0] ?? [];
        $metric = is_array($first) && isset($first['metric']) ? (string) $first['metric'] : 'metric';
        $severity = is_array($first) && isset($first['severity']) ? (string) $first['severity'] : 'warn';
        $subject = self::buildAnomalySubject($client, $metric, $severity);
        $body = '<p>' . Wp::escHtml(sprintf(I18n::__('The anomaly detector flagged the following metrics for %s.'), $client->name)) . '</p><ul>';
        foreach ($anomalies as $anomaly) {
            if (! is_array($anomaly)) {
                continue;
            }
            $metric = Wp::escHtml((string) ($anomaly['metric'] ?? I18n::__('metric')));
            $delta = isset($anomaly['delta_percent'])
                ? Wp::numberFormatI18n((float) $anomaly['delta_percent'], 1) . '%'
                : I18n::__('n/a');
            $severity = Wp::escHtml((string) ($anomaly['severity'] ?? 'warn'));
            $body .= '<li><strong>' . $metric . '</strong> – ' . Wp::escHtml(sprintf(I18n::__('Δ %s (%s)'), $delta, $severity)) . '</li>';
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
            $sanitized = Wp::sanitizeEmail($email);
            if ($sanitized === '' || ! Wp::isEmail($sanitized)) {
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
