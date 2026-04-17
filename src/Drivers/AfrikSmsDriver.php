<?php

namespace SmsGateway\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SmsGateway\Contracts\SmsDriverInterface;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\SmsMessage;

class AfrikSmsDriver implements SmsDriverInterface
{
    private const ENDPOINT = 'https://api.afriksms.com/api/web/web_v1/outbounds/send';

    private Client $client;

    public function __construct(
        private readonly string $clientId,
        private readonly string $apiKey,
        private readonly string $senderId,
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client();
    }

    public function send(string $to, SmsMessage $message): void
    {
        try {
            $response = $this->client->post(self::ENDPOINT, [
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
