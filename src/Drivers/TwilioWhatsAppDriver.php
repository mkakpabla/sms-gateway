<?php

namespace SmsGateway\Drivers;

use SmsGateway\Contracts\WhatsAppDriverInterface;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\WhatsAppMessage;
use Twilio\Exceptions\RestException;
use Twilio\Exceptions\TwilioException;
use Twilio\Rest\Client;

class TwilioWhatsAppDriver implements WhatsAppDriverInterface
{
    private const DRIVER = 'twilio';

    private const ADDRESS_PREFIX = 'whatsapp:';

    private Client $client;

    public function __construct(
        string $accountSid,
        string $authToken,
        private readonly string $from = '',
        private readonly string $messagingServiceSid = '',
        private readonly string $statusCallback = '',
        ?Client $client = null,
    ) {
        $this->client = $client ?? new Client($accountSid, $authToken);
    }

    public function send(string $to, WhatsAppMessage $message): void
    {
        $options = $this->buildOptions($message);

        try {
            $sent = $this->client->messages->create($this->toAddress($to), $options);
        } catch (RestException $e) {
            throw CouldNotSendNotification::whatsAppServiceRespondedWithAnError(
                driver: self::DRIVER,
                statusCode: $e->getStatusCode(),
                body: trim($e->getCode() . ' ' . $e->getMessage()),
            );
        } catch (TwilioException $e) {
            throw CouldNotSendNotification::whatsAppServiceRespondedWithAnError(
                driver: self::DRIVER,
                statusCode: (int) $e->getCode(),
                body: $e->getMessage(),
            );
        }

        if (in_array($sent->status, ['failed', 'undelivered'], true)) {
            throw CouldNotSendNotification::whatsAppServiceRespondedWithAnError(
                driver: self::DRIVER,
                statusCode: 201,
                body: trim($sent->errorCode . ' ' . ($sent->errorMessage ?? $sent->status)),
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOptions(WhatsAppMessage $message): array
    {
        if (! $message->isTemplate() && $message->getBody() === '' && $message->getMediaUrls() === []) {
            throw CouldNotSendNotification::emptyWhatsAppMessage();
        }

        $options = [...$this->senderOptions($message), ...$this->contentOptions($message)];

        if ($message->getMediaUrls() !== []) {
            $options['mediaUrl'] = $message->getMediaUrls();
        }

        if ($this->statusCallback !== '') {
            $options['statusCallback'] = $this->statusCallback;
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private function senderOptions(WhatsAppMessage $message): array
    {
        if ($this->messagingServiceSid !== '' && $message->getFrom() === null) {
            return ['messagingServiceSid' => $this->messagingServiceSid];
        }

        return ['from' => $this->toAddress($message->getFrom() ?? $this->from)];
    }

    /**
     * @return array<string, string>
     */
    private function contentOptions(WhatsAppMessage $message): array
    {
        if (! $message->isTemplate()) {
            return $message->getBody() !== '' ? ['body' => $message->getBody()] : [];
        }

        // Twilio identifies approved templates by their Content SID; the
        // language is part of the Content resource, not of the request.
        $options = ['contentSid' => (string) $message->getTemplate()];

        if ($message->getTemplateVariables() !== []) {
            $options['contentVariables'] = (string) json_encode(
                $message->getTemplateVariables(),
                JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE,
            );
        }

        return $options;
    }

    private function toAddress(string $number): string
    {
        $number = preg_replace('/[\s().-]/', '', $number) ?? $number;

        if (str_starts_with($number, self::ADDRESS_PREFIX)) {
            return $number;
        }

        if (str_starts_with($number, '00')) {
            $number = substr($number, 2);
        }

        return self::ADDRESS_PREFIX . (str_starts_with($number, '+') ? $number : '+' . $number);
    }
}
