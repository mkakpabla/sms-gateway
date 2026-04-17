<?php

namespace SmsGateway\Tests\Laravel;

use SmsGateway\Contracts\HasSmsNotification;
use SmsGateway\Laravel\SmsChannel;
use SmsGateway\SmsGateway;
use SmsGateway\SmsMessage;
use Illuminate\Notifications\Notification;
use Mockery;
use PHPUnit\Framework\TestCase;

class SmsChannelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_can_send_a_notification(): void
    {
        $gateway = Mockery::mock(SmsGateway::class);
        $gateway->shouldReceive('sendWithFallback')
            ->once()
            ->withArgs(fn (string $to, SmsMessage $msg) => $to === '+22890001234' && $msg->getContent() === 'Test message');

        $channel = new SmsChannel($gateway);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel): string
            {
                return '+22890001234';
            }
        };

        $notification = new class extends Notification implements HasSmsNotification
        {
            public function toSms(object $notifiable): SmsMessage
            {
                return SmsMessage::create('Test message');
            }
        };

        $channel->send($notifiable, $notification);
    }

    public function test_it_skips_if_no_recipient(): void
    {
        $gateway = Mockery::mock(SmsGateway::class);
        $gateway->shouldNotReceive('sendWithFallback');

        $channel = new SmsChannel($gateway);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel): ?string
            {
                return null;
            }
        };

        $notification = new class extends Notification implements HasSmsNotification
        {
            public function toSms(object $notifiable): SmsMessage
            {
                return SmsMessage::create('Test');
            }
        };

        $channel->send($notifiable, $notification);
    }

    public function test_it_skips_if_notification_does_not_implement_interface(): void
    {
        $gateway = Mockery::mock(SmsGateway::class);
        $gateway->shouldNotReceive('sendWithFallback');

        $channel = new SmsChannel($gateway);

        $notifiable = new class
        {
            public function routeNotificationFor(string $channel): string
            {
                return '+22890001234';
            }
        };

        $notification = new class extends Notification {};

        $channel->send($notifiable, $notification);
    }
}
