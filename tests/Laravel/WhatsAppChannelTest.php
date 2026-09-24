<?php

namespace SmsGateway\Tests\Laravel;

use Illuminate\Notifications\Notification;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use SmsGateway\Contracts\HasWhatsAppNotification;
use SmsGateway\Laravel\WhatsAppChannel;
use SmsGateway\WhatsAppGateway;
use SmsGateway\WhatsAppMessage;

class WhatsAppChannelTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function notification(WhatsAppMessage $message): Notification
    {
        return new class ($message) extends Notification implements HasWhatsAppNotification
        {
            public function __construct(private readonly WhatsAppMessage $message)
            {
            }

            public function toWhatsApp(object $notifiable): WhatsAppMessage
            {
                return $this->message;
            }
        };
    }

    private function notifiable(?string $number): object
    {
        return new class ($number)
        {
            public function __construct(private readonly ?string $number)
            {
            }

            public function routeNotificationFor(string $channel): ?string
            {
                return $channel === 'whatsapp' ? $this->number : null;
            }
        };
    }

    public function testItSendsToTheRoutedNumber(): void
    {
        $message = WhatsAppMessage::template('HX123', ['a']);

        $gateway = Mockery::mock(WhatsAppGateway::class);
        $gateway->shouldReceive('sendWithFallback')->once()->with('22890001234', $message);

        (new WhatsAppChannel($gateway))->send($this->notifiable('22890001234'), $this->notification($message));
    }

    public function testTheMessageRecipientOverridesTheRoute(): void
    {
        $message = WhatsAppMessage::create('Bonjour')->to('22899999999');

        $gateway = Mockery::mock(WhatsAppGateway::class);
        $gateway->shouldReceive('sendWithFallback')->once()->with('22899999999', $message);

        (new WhatsAppChannel($gateway))->send($this->notifiable('22890001234'), $this->notification($message));
    }

    public function testItSkipsIfNoRecipient(): void
    {
        $gateway = Mockery::mock(WhatsAppGateway::class);
        $gateway->shouldNotReceive('sendWithFallback');

        (new WhatsAppChannel($gateway))->send(
            $this->notifiable(null),
            $this->notification(WhatsAppMessage::create('Bonjour')),
        );
    }

    public function testItSkipsIfNotificationDoesNotImplementInterface(): void
    {
        $gateway = Mockery::mock(WhatsAppGateway::class);
        $gateway->shouldNotReceive('sendWithFallback');

        (new WhatsAppChannel($gateway))->send($this->notifiable('22890001234'), new class extends Notification {
        });
    }
}
