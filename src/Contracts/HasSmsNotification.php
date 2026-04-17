<?php

namespace SmsGateway\Contracts;

use SmsGateway\SmsMessage;

interface HasSmsNotification
{
    public function toSms(object $notifiable): SmsMessage;
}
