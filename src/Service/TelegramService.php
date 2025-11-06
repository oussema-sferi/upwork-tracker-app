<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramService
{
    private HttpClientInterface $httpClient;
    private string $botToken;
    private string $chatId;

    public function __construct(string $telegramBotToken, string $telegramChatId)
    {
        $this->httpClient = HttpClient::create();
        $this->botToken = $telegramBotToken;
        $this->chatId = $telegramChatId;
    }

    public function sendMessage(string $message): bool
    {
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
            
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'chat_id' => $this->chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode === 200) {
                $data = $response->toArray();
                return isset($data['ok']) && $data['ok'] === true;
            }
            
            error_log('Telegram API error (HTTP ' . $statusCode . '): ' . $response->getContent(false));
            return false;
        } catch (\Exception $e) {
            error_log('Telegram API exception: ' . $e->getMessage());
            return false;
        }
    }
}

