<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Notifiers;

use FP\DMS\Domain\Entities\Client;
use FP\DMS\Infra\Logger;
use FP\DMS\Infra\Mailer;
use FP\DMS\Support\I18n;
use FP\DMS\Support\Period;
use FP\DMS\Support\Wp;

class EmailNotifier implements BaseNotifier
{
    public function __construct(private Mailer $mailer = new Mailer())
    {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function send(array $payload): bool
    {
        $client = $payload['client'] ?? null;
        $period = $payload['period'] ?? null;
        $anomalies = is_array($payload['anomalies'] ?? null) ? $payload['anomalies'] : [];
        $recipients = array_filter(array_map('strval', $payload['recipients'] ?? []));
        if (! $client instanceof Client || ! $period instanceof Period || empty($anomalies) || empty($recipients)) {
            return false;
        }

        $first = $anomalies[0];
        $metric = isset($first['metric']) ? (string) $first['metric'] : I18n::__('metric');
        $severity = isset($first['severity']) ? (string) $first['severity'] : 'warn';
        $subject = Mailer::buildAnomalySubject($client, $metric, $severity);
        $body = $this->renderBody($client, $period, $anomalies);

        $success = $this->mailer->sendWithRetry($recipients, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
        if (! $success) {
            Logger::logChannel('ANOM', sprintf('email_failed client=%d metric=%s', $client->id ?? 0, $metric));
        }

        return $success;
    }

    /**
     * @param array<int,array<string,mixed>> $anomalies
     */
    private function renderBody(Client $client, Period $period, array $anomalies): string
    {
        $items = '';
        foreach ($anomalies as $anomaly) {
            if (! is_array($anomaly)) {
                continue;
            }
            $metric = Wp::escHtml((string) ($anomaly['metric'] ?? I18n::__('metric')));
            $severity = Wp::escHtml((string) ($anomaly['severity'] ?? 'warn'));
            $delta = isset($anomaly['delta_percent']) && $anomaly['delta_percent'] !== null
                ? Wp::numberFormatI18n((float) $anomaly['delta_percent'], 2) . '%'
                : I18n::__('n/a');
            $items .= '<li><strong>' . $metric . '</strong> – ' . Wp::escHtml(sprintf(I18n::__('%s (%s)'), $delta, $severity)) . '</li>';
        }

        return sprintf(
            '<p>%s</p><ul>%s</ul><p><small>%s</small></p>',
            Wp::escHtml(sprintf(
                I18n::__('The anomaly detector flagged the following metrics for %s (%s → %s).'),
                $client->name,
                $period->start->format('Y-m-d'),
                $period->end->format('Y-m-d')
            )),
            $items,
            Wp::escHtml(I18n::__('You can adjust thresholds from the FP Suite dashboard.'))
        );
    }
}
