<?php

namespace SmsGateway\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SmsGateway\Contracts\SmsDriverInterface;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\SmsMessage;

class FasterMessageDriver implements SmsDriverInterface
{
    private const SEND_SMS = '%s/send';

    private Client $client;

    public function __construct(
        private readonly string $from,
        private readonly string $apiUrl,
        private readonly string $username,
        private readonly string $password,
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client();
    }

    public function send(string $to, SmsMessage $message): void
    {
        $url = sprintf(self::SEND_SMS, $this->apiUrl);

        try {
            $response = $this->client->post($url, [
                'auth' => [$this->username, $this->password],
                'json' => [
                    'from' => $message->getFrom() ?? $this->from,
                    'to' => $to,
                    'text' => $message->getContent(),
                ],
            ]);
        } catch (GuzzleException $e) {
            throw CouldNotSendNotification::serviceRespondedWithAnError(
                driver: 'faster-message',
                statusCode: $e->getCode(),
                body: $e->getMessage(),
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            throw CouldNotSendNotification::serviceRespondedWithAnError(
                driver: 'faster-message',
                statusCode: $statusCode,
                body: (string) $response->getBody(),
            );
        }
    }
}
