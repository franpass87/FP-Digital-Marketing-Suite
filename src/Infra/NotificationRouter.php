<?php

declare(strict_types=1);

namespace FP\DMS\Infra;

use DateTimeImmutable;
use FP\DMS\Domain\Entities\Client;
use FP\DMS\Infra\Notifiers\EmailNotifier;
use FP\DMS\Infra\Notifiers\SlackNotifier;
use FP\DMS\Infra\Notifiers\TeamsNotifier;
use FP\DMS\Infra\Notifiers\TelegramNotifier;
use FP\DMS\Infra\Notifiers\TwilioNotifierStub;
use FP\DMS\Infra\Notifiers\WebhookNotifier;
use FP\DMS\Infra\Options;
use FP\DMS\Support\Period;
use function is_email;
use function sanitize_email;

class NotificationRouter
{
    /**
     * @param array<int,array<string,mixed>> $anomalies
     * @return array<string,mixed>
     */
    public function route(array $anomalies, array $policy, Client $client, Period $period): array
    {
        if (empty($anomalies)) {
            return ['channels' => []];
        }

        if ($this->isMuted($policy['mute'] ?? [], $client)) {
            return ['channels' => [], 'muted' => true];
        }

        $routing = is_array($policy['routing'] ?? null) ? $policy['routing'] : [];
        $digest = (int) ($routing['email']['digest_window_min'] ?? 15);
        $cooldown = (int) ($policy['cooldown_min'] ?? 30);
        $maxPerWindow = (int) ($policy['max_per_window'] ?? 5);

        $eligible = [];
        foreach ($anomalies as $anomaly) {
            if (! is_array($anomaly)) {
                continue;
            }
            $metric = isset($anomaly['metric']) ? (string) $anomaly['metric'] : '';
            $severity = isset($anomaly['severity']) ? (string) $anomaly['severity'] : 'warn';
            if ($metric === '') {
                continue;
            }
            if ($this->isDuplicate($client->id ?? 0, $metric, $severity, $digest)) {
                continue;
            }
            if ($this->isCoolingDown($client->id ?? 0, $metric, $severity, $cooldown)) {
                continue;
            }
            $eligible[] = $anomaly;
            $this->rememberDuplicate($client->id ?? 0, $metric, $severity, $digest);
        }

        if (empty($eligible)) {
            return ['channels' => [], 'skipped' => 'dedup'];
        }

        if ($maxPerWindow > 0 && $this->isWindowCapped($client->id ?? 0, $maxPerWindow, max($cooldown, $digest))) {
            return ['channels' => [], 'skipped' => 'window_limit'];
        }

        $summaryText = $this->buildSummary($eligible, $client, $period);
        $webhookBody = [
            'client' => ['id' => $client->id, 'name' => $client->name],
            'period' => [
                'start' => $period->start->format('Y-m-d'),
                'end' => $period->end->format('Y-m-d'),
            ],
            'anomalies' => $eligible,
        ];

        $results = ['channels' => []];
        foreach ($routing as $channel => $config) {
            if (empty($config['enabled'])) {
                continue;
            }

            $success = false;
            switch ($channel) {
                case 'email':
                    $recipients = $this->emailRecipients($client);
                    if (! empty($recipients)) {
                        $success = (new EmailNotifier())->send([
                            'client' => $client,
                            'period' => $period,
                            'anomalies' => $eligible,
                            'recipients' => $recipients,
                        ]);
                    }
                    break;
                case 'slack':
                    $success = (new SlackNotifier((string) ($config['webhook_url'] ?? '')))->send([
                        'text' => $summaryText,
                    ]);
                    break;
                case 'teams':
                    $success = (new TeamsNotifier((string) ($config['webhook_url'] ?? '')))->send([
                        'title' => sprintf('Anomaly alert – %s', $client->name),
                        'text' => nl2br(esc_html($summaryText)),
                    ]);
                    break;
                case 'telegram':
                    $success = (new TelegramNotifier(
                        (string) ($config['bot_token'] ?? ''),
                        (string) ($config['chat_id'] ?? '')
                    ))->send(['text' => $summaryText]);
                    break;
                case 'webhook':
                    $success = (new WebhookNotifier(
                        (string) ($config['url'] ?? ''),
                        isset($config['hmac_secret']) && $config['hmac_secret'] !== '' ? (string) $config['hmac_secret'] : null
                    ))->send(['body' => $webhookBody]);
                    break;
                case 'sms_twilio':
                    $success = (new TwilioNotifierStub((array) $config))->send([
                        'text' => $summaryText,
                    ]);
                    break;
            }

            if ($success) {
                $results['channels'][$channel] = true;
            }
        }

        if (! empty($results['channels'])) {
            $this->rememberWindowSend($client->id ?? 0, $maxPerWindow, max($cooldown, $digest));
            foreach ($eligible as $anomaly) {
                $metric = isset($anomaly['metric']) ? (string) $anomaly['metric'] : '';
                $severity = isset($anomaly['severity']) ? (string) $anomaly['severity'] : 'warn';
                if ($metric === '') {
                    continue;
                }
                $this->rememberCooldown($client->id ?? 0, $metric, $severity, $cooldown);
            }
        }

        return $results;
    }

    private function emailRecipients(Client $client): array
    {
        $primary = $this->sanitizeEmails($client->emailTo);
        $cc = $this->sanitizeEmails($client->emailCc);

        $recipients = $primary;
        foreach ($cc as $email) {
            if (! in_array($email, $recipients, true)) {
                $recipients[] = $email;
            }
        }

        $settings = Options::getGlobalSettings();
        $owner = isset($settings['owner_email']) ? sanitize_email((string) $settings['owner_email']) : '';
        if ($owner !== '' && is_email($owner) && ! in_array($owner, $recipients, true)) {
            $recipients[] = $owner;
        }

        return $recipients;
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
            if ($sanitized === '' || ! is_email($sanitized) || in_array($sanitized, $clean, true)) {
                continue;
            }
            $clean[] = $sanitized;
        }

        return $clean;
    }

    private function buildSummary(array $anomalies, Client $client, Period $period): string
    {
        $lines = [];
        $lines[] = sprintf('*%s* %s → %s', $client->name, $period->start->format('Y-m-d'), $period->end->format('Y-m-d'));
        foreach ($anomalies as $anomaly) {
            $metric = (string) ($anomaly['metric'] ?? 'metric');
            $severity = strtoupper((string) ($anomaly['severity'] ?? 'warn'));
            $delta = isset($anomaly['delta_percent']) && $anomaly['delta_percent'] !== null
                ? number_format_i18n((float) $anomaly['delta_percent'], 1) . '%'
                : 'n/a';
            $z = isset($anomaly['z_score']) && $anomaly['z_score'] !== null
                ? number_format_i18n((float) $anomaly['z_score'], 2)
                : 'n/a';
            $lines[] = sprintf('%s %s Δ %s z=%s', $severity, $metric, $delta, $z);
        }

        return implode("\n", $lines);
    }

    private function isDuplicate(int $clientId, string $metric, string $severity, int $minutes): bool
    {
        if ($minutes <= 0) {
            return false;
        }
        $key = 'fpdms_anom_digest_' . md5($clientId . $metric . $severity);
        return (bool) get_transient($key);
    }

    private function rememberDuplicate(int $clientId, string $metric, string $severity, int $minutes): void
    {
        if ($minutes <= 0) {
            return;
        }
        $key = 'fpdms_anom_digest_' . md5($clientId . $metric . $severity);
        set_transient($key, 1, $minutes * MINUTE_IN_SECONDS);
    }

    private function isCoolingDown(int $clientId, string $metric, string $severity, int $minutes): bool
    {
        if ($minutes <= 0) {
            return false;
        }
        $key = 'fpdms_anom_cool_' . md5($clientId . $metric . $severity);

        return (bool) get_transient($key);
    }

    private function rememberCooldown(int $clientId, string $metric, string $severity, int $minutes): void
    {
        if ($minutes <= 0) {
            return;
        }
        $key = 'fpdms_anom_cool_' . md5($clientId . $metric . $severity);
        set_transient($key, 1, $minutes * MINUTE_IN_SECONDS);
    }

    private function isWindowCapped(int $clientId, int $limit, int $windowMinutes): bool
    {
        if ($limit <= 0 || $windowMinutes <= 0) {
            return false;
        }
        $key = 'fpdms_anom_window_' . $clientId;
        $data = get_transient($key);
        if (! is_array($data) || empty($data['count'])) {
            return false;
        }

        return (int) $data['count'] >= $limit;
    }

    private function rememberWindowSend(int $clientId, int $limit, int $windowMinutes): void
    {
        if ($limit <= 0 || $windowMinutes <= 0) {
            return;
        }
        $key = 'fpdms_anom_window_' . $clientId;
        $data = get_transient($key);
        $count = is_array($data) ? (int) ($data['count'] ?? 0) : 0;
        $count++;
        set_transient($key, ['count' => $count], $windowMinutes * MINUTE_IN_SECONDS);
    }

    private function isMuted(array $mute, Client $client): bool
    {
        $start = isset($mute['start']) ? (string) $mute['start'] : '';
        $end = isset($mute['end']) ? (string) $mute['end'] : '';
        if ($start === '' || $end === '') {
            return false;
        }
        $tzString = isset($mute['tz']) ? (string) $mute['tz'] : $client->timezone;
        try {
            $tz = new \DateTimeZone($tzString);
        } catch (\Exception) {
            $tz = new \DateTimeZone('UTC');
        }
        $now = new DateTimeImmutable('now', $tz);
        [$startH, $startM] = array_pad(explode(':', $start), 2, '0');
        [$endH, $endM] = array_pad(explode(':', $end), 2, '0');
        $startMinutes = ((int) $startH) * 60 + (int) $startM;
        $endMinutes = ((int) $endH) * 60 + (int) $endM;
        $currentMinutes = ((int) $now->format('H')) * 60 + (int) $now->format('i');

        if ($startMinutes <= $endMinutes) {
            return $currentMinutes >= $startMinutes && $currentMinutes < $endMinutes;
        }

        return $currentMinutes >= $startMinutes || $currentMinutes < $endMinutes;
    }
}
