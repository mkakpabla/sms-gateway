<?php

namespace SmsGateway\Laravel;

use SmsGateway\Contracts\HasSmsNotification;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\SmsGateway;
use Illuminate\Notifications\Notification;

class SmsChannel
{
    public function __construct(private readonly SmsGateway $gateway)
    {
    }

    /**
     * Send the given notification.
     *
     * @throws CouldNotSendNotification
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof HasSmsNotification) {
            return;
        }

        $message = $notification->toSms($notifiable);

        $to = $message->getTo() ?: $notifiable->routeNotificationFor('sms', $notification);

        if (! $to) {
            return;
        }

        $this->gateway->sendWithFallback($to, $message);
    }
}
