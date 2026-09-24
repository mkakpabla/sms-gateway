<?php

namespace SmsGateway\Laravel;

use Illuminate\Notifications\Notification;
use SmsGateway\Contracts\HasWhatsAppNotification;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\WhatsAppGateway;

class WhatsAppChannel
{
    public function __construct(private readonly WhatsAppGateway $gateway)
    {
    }

    /**
     * Send the given notification.
     *
     * @throws CouldNotSendNotification
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof HasWhatsAppNotification) {
            return;
        }

        $message = $notification->toWhatsApp($notifiable);

        $to = $message->getTo() ?: $notifiable->routeNotificationFor('whatsapp', $notification);

        if (! $to) {
            return;
        }

        $this->gateway->sendWithFallback($to, $message);
    }
}
