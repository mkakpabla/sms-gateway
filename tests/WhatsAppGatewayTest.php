<?php

namespace SmsGateway\Tests;

use InvalidArgumentException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SmsGateway\Contracts\WhatsAppDriverInterface;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\WhatsAppGateway;
use SmsGateway\WhatsAppMessage;

class WhatsAppGatewayTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testItSendsWithTheDefaultDriver(): void
    {
        $message = WhatsAppMessage::create('Bonjour');

        $twilio = Mockery::mock(WhatsAppDriverInterface::class);
        $meta = Mockery::mock(WhatsAppDriverInterface::class);
        $meta->shouldReceive('send')->once()->with('22890001234', $message);
        $twilio->shouldNotReceive('send');

        (new WhatsAppGateway())
            ->registerDriver('twilio', $twilio)
            ->registerDriver('meta', $meta)
            ->setDefaultDriver('meta')
            ->send('22890001234', $message);
    }

    public function testAPinnedMessageUsesItsDriver(): void
    {
        $message = WhatsAppMessage::create('Bonjour')->driver('meta');

        $twilio = Mockery::mock(WhatsAppDriverInterface::class);
        $twilio->shouldNotReceive('send');
        $meta = Mockery::mock(WhatsAppDriverInterface::class);
        $meta->shouldReceive('send')->once();

        (new WhatsAppGateway())
            ->registerDriver('twilio', $twilio)
            ->registerDriver('meta', $meta)
            ->setDefaultDriver('twilio')
            ->setFallbackOrder(['twilio', 'meta'])
            ->sendWithFallback('22890001234', $message);
    }

    public function testExtendedDriversAreBuiltOnlyWhenUsed(): void
    {
        $built = [];

        $gateway = (new WhatsAppGateway())
            ->extend('twilio', function () use (&$built) {
                $built[] = 'twilio';

                return Mockery::mock(WhatsAppDriverInterface::class)->shouldReceive('send')->twice()->getMock();
            })
            ->extend('meta', function () use (&$built) {
                $built[] = 'meta';

                return Mockery::mock(WhatsAppDriverInterface::class);
            })
            ->setDefaultDriver('twilio');

        $this->assertTrue($gateway->hasDriver('meta'));

        $gateway->send('22890001234', WhatsAppMessage::create('Bonjour'));
        $gateway->send('22890001234', WhatsAppMessage::create('Encore'));

        $this->assertSame(['twilio'], $built);
    }

    public function testItFallsBackToTheNextDriver(): void
    {
        $failing = Mockery::mock(WhatsAppDriverInterface::class);
        $failing->shouldReceive('send')->once()->andThrow(new CouldNotSendNotification('down'));
        $working = Mockery::mock(WhatsAppDriverInterface::class);
        $working->shouldReceive('send')->once();

        (new WhatsAppGateway())
            ->registerDriver('twilio', $failing)
            ->registerDriver('meta', $working)
            ->setFallbackOrder(['twilio', 'meta'])
            ->sendWithFallback('22890001234', WhatsAppMessage::create('Bonjour'));
    }

    public function testItThrowsWhenAllDriversFail(): void
    {
        $failing = Mockery::mock(WhatsAppDriverInterface::class);
        $failing->shouldReceive('send')->andThrow(new CouldNotSendNotification('down'));

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('All WhatsApp drivers failed');

        (new WhatsAppGateway())
            ->registerDriver('twilio', $failing)
            ->registerDriver('meta', $failing)
            ->setFallbackOrder(['twilio', 'meta'])
            ->sendWithFallback('22890001234', WhatsAppMessage::create('Bonjour'));
    }

    public function testItRejectsAnUnknownDriver(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new WhatsAppGateway())->getDriver('unknown');
    }

    public function testItRequiresAtLeastOneDriver(): void
    {
        $this->expectException(RuntimeException::class);

        (new WhatsAppGateway())->send('22890001234', WhatsAppMessage::create('Bonjour'));
    }
}
