<?php

namespace SmsGateway\Contracts;

use SmsGateway\WhatsAppMessage;

interface WhatsAppDriverInterface
{
    /**
     * Send a WhatsApp message.
     *
     * @throws \SmsGateway\Exceptions\CouldNotSendNotification
     */
    public function send(string $to, WhatsAppMessage $message): void;
}
