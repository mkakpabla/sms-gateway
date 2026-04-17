<?php

namespace SmsGateway\Contracts;

use SmsGateway\SmsMessage;

interface SmsDriverInterface
{
    /**
     * Send an SMS message.
     *
     * @throws \SmsGateway\Exceptions\CouldNotSendNotification
     */
    public function send(string $to, SmsMessage $message): void;
}
