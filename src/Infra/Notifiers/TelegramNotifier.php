<?php

declare(strict_types=1);

namespace FP\DMS\Infra\Notifiers;

use FP\DMS\Infra\Logger;
use FP\DMS\Support\Wp;

class TelegramNotifier implements BaseNotifier
{
    public function __construct(private string $botToken, private string $chatId)
    {
    }

    public function send(array $payload): bool
    {
        if ($this->botToken === '' || $this->chatId === '') {
            return false;
        }

        $text = (string) ($payload['text'] ?? '');
        if ($text === '') {
            return false;
        }

        $url = sprintf('https://api.telegram.org/bot%s/sendMessage', $this->botToken);
        $response = Wp::remotePost($url, [
            'body' => [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
            ],
            'timeout' => 5,
        ]);

        if (Wp::isWpError($response) || Wp::remoteRetrieveResponseCode($response) >= 300) {
            Logger::logChannel('ANOM', sprintf('telegram_failed chat=%s', $this->chatId));

            return false;
        }

        return true;
    }
}
