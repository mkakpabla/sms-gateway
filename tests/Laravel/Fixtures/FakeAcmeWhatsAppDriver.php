<?php

namespace SmsGateway\Tests\Laravel\Fixtures;

use SmsGateway\Contracts\WhatsAppDriverInterface;
use SmsGateway\WhatsAppMessage;

class FakeAcmeWhatsAppDriver implements WhatsAppDriverInterface
{
    public function __construct(public readonly string $apiToken, public readonly string $senderId)
    {
    }

    public function send(string $to, WhatsAppMessage $message): void
    {
    }
}
