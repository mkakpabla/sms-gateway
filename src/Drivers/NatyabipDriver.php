<?php

namespace SmsGateway\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SmsGateway\Contracts\SmsDriverInterface;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\SmsMessage;

class NatyabipDriver implements SmsDriverInterface
{
    private Client $client;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $username,
        private readonly string $password,
        private readonly string $from,
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client([
            'timeout' => 10.0,
            'connect_timeout' => 5.0,
        ]);
    }

    public function send(string $to, SmsMessage $message): void
    {
        try {
            $response = $this->client->post($this->apiUrl, [
                'auth' => [$this->username, $this->password],
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'from' => $message->getFrom() ?? $this->from,
                    'to' => $to,
                    'text' => $message->getContent(),
                ],
            ]);
        } catch (GuzzleException $e) {
            throw CouldNotSendNotification::serviceRespondedWithAnError(
                driver: 'natyabip',
                statusCode: (int) $e->getCode(),
                body: $e->getMessage(),
            );
        }

        $statusCode = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($statusCode >= 400) {
            throw CouldNotSendNotification::serviceRespondedWithAnError(
                driver: 'natyabip',
                statusCode: $statusCode,
                body: $body,
            );
        }

        $this->assertResponseSuccessful($statusCode, $body);
    }

    private function assertResponseSuccessful(int $statusCode, string $body): void
    {
        $decoded = json_decode($body, true);

        if (is_array($decoded)) {
            $this->assertJsonResponseSuccessful($statusCode, $body, $decoded);

            return;
        }

        $this->assertPlainTextResponseSuccessful($statusCode, $body);
    }

    /**
     * @param array<mixed> $decoded
     */
    private function assertJsonResponseSuccessful(int $statusCode, string $body, array $decoded): void
    {
        $messageResponse = isset($decoded['messages'][0]) && is_array($decoded['messages'][0])
            ? $decoded['messages'][0]
            : null;

        if ($messageResponse === null) {
            return;
        }

        $status = is_array($messageResponse['status'] ?? null) ? $messageResponse['status'] : [];
        $groupId = isset($status['groupId']) ? (int) $status['groupId'] : null;
        $groupName = strtoupper((string) ($status['groupName'] ?? ''));

        if ($groupId === 5 || $groupName === 'REJECTED') {
            throw CouldNotSendNotification::serviceRespondedWithAnError(
                driver: 'natyabip',
                statusCode: $statusCode,
                body: (string) ($status['description'] ?? $body),
            );
        }
    }

    private function assertPlainTextResponseSuccessful(int $statusCode, string $body): void
    {
        $normalizedBody = trim($body);

        if (str_starts_with($normalizedBody, 'OK:')) {
            return;
        }

        $errorBody = str_starts_with($normalizedBody, 'ERROR:')
            ? trim(substr($normalizedBody, 6))
            : $body;

        throw CouldNotSendNotification::serviceRespondedWithAnError(
            driver: 'natyabip',
            statusCode: $statusCode,
            body: $errorBody,
        );
    }
}
