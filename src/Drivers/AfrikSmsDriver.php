<?php

namespace SmsGateway\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SmsGateway\Contracts\SmsDriverInterface;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\SmsMessage;

class AfrikSmsDriver implements SmsDriverInterface
{
    private const SEND_SMS = '%s/outbounds/send';

    private Client $client;

    public function __construct(
        private readonly string $clientId,
        private readonly string $apiKey,
        private readonly string $senderId,
        private readonly string $apiUrl = 'https://api.afriksms.com/api/web/web_v1',
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client([
            'timeout' => 10.0,
            'connect_timeout' => 5.0,
        ]);
    }

    public function send(string $to, SmsMessage $message): void
    {
        $url = sprintf(self::SEND_SMS, $this->apiUrl);

        try {
            $response = $this->client->post($url, [
                'query' => [
                    'ClientId' => $this->clientId,
                    'ApiKey' => $this->apiKey,
                    'SenderId' => $message->getFrom() ?? $this->senderId,
                    'Message' => $message->getContent(),
                    'MobileNumbers' => $to,
                ],
            ]);
        } catch (GuzzleException $e) {
            throw CouldNotSendNotification::serviceRespondedWithAnError(
                driver: 'afriksms',
                statusCode: $e->getCode(),
                body: $e->getMessage(),
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode >= 400) {
            throw CouldNotSendNotification::serviceRespondedWithAnError(
                driver: 'afriksms',
                statusCode: $statusCode,
                body: $body,
            );
        }

        $decoded = json_decode($body, true);

        if (is_array($decoded) && isset($decoded['code']) && (int) $decoded['code'] !== 100) {
            throw CouldNotSendNotification::serviceRespondedWithAnError(
                driver: 'afriksms',
                statusCode: (int) $decoded['code'],
                body: $decoded['message'] ?? $body,
            );
        }
    }
}
