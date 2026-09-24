<?php

namespace SmsGateway\Contracts;

use SmsGateway\WhatsAppMessage;

interface HasWhatsAppNotification
{
    public function toWhatsApp(object $notifiable): WhatsAppMessage;
}
