<?php

namespace SmsGateway\Exceptions;

use Exception;

class CouldNotSendNotification extends Exception
{
    public static function serviceRespondedWithAnError(string $driver, int $statusCode, string $body): self
    {
        return new self("[{$driver}] SMS service responded with status {$statusCode}: {$body}");
    }

    public static function allDriversFailed(?\Throwable $lastException = null): self
    {
        return new self(
            'All SMS drivers failed to send the notification.',
            previous: $lastException,
        );
    }

    public static function missingRecipient(): self
    {
        return new self('No recipient was provided for the SMS notification.');
    }
}
