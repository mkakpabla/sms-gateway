<?php

namespace SmsGateway\Tests;

use SmsGateway\Contracts\SmsDriverInterface;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\SmsGateway;
use SmsGateway\SmsMessage;
use Mockery;
use PHPUnit\Framework\TestCase;

class SmsGatewayTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_sends_via_default_driver(): void
    {
        $driver = Mockery::mock(SmsDriverInterface::class);
        $driver->shouldReceive('send')
            ->once()
            ->withArgs(fn (string $to, SmsMessage $msg) => $to === '+22890001234' && $msg->getContent() === 'Hello');

        $gateway = new SmsGateway();
        $gateway->registerDriver('test', $driver);
        $gateway->setDefaultDriver('test');

        $gateway->send('+22890001234', SmsMessage::create('Hello'));
    }

    public function test_it_uses_first_registered_driver_if_no_default(): void
    {
        $driver = Mockery::mock(SmsDriverInterface::class);
        $driver->shouldReceive('send')->once();

        $gateway = new SmsGateway();
        $gateway->registerDriver('first', $driver);

        $gateway->send('+22890001234', SmsMessage::create('Test'));
    }

    public function test_it_throws_when_no_driver_registered(): void
    {
        $gateway = new SmsGateway();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No SMS driver registered');

        $gateway->send('+22890001234', SmsMessage::create('Test'));
    }

    public function test_it_throws_when_driver_not_found(): void
    {
        $gateway = new SmsGateway();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SMS driver [unknown] is not registered');

        $gateway->getDriver('unknown');
    }

    public function test_it_falls_back_to_next_driver_on_failure(): void
    {
        $failingDriver = Mockery::mock(SmsDriverInterface::class);
        $failingDriver->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Provider down'));

        $succeedingDriver = Mockery::mock(SmsDriverInterface::class);
        $succeedingDriver->shouldReceive('send')->once();

        $gateway = new SmsGateway();
        $gateway->registerDriver('driver-a', $failingDriver);
        $gateway->registerDriver('driver-b', $succeedingDriver);
        $gateway->setFallbackOrder(['driver-a', 'driver-b']);

        $gateway->sendWithFallback('+22890001234', SmsMessage::create('Test fallback'));
    }

    public function test_it_throws_when_all_drivers_fail(): void
    {
        $failingA = Mockery::mock(SmsDriverInterface::class);
        $failingA->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Provider A down'));

        $failingB = Mockery::mock(SmsDriverInterface::class);
        $failingB->shouldReceive('send')
            ->once()
            ->andThrow(new \RuntimeException('Provider B down'));

        $gateway = new SmsGateway();
        $gateway->registerDriver('driver-a', $failingA);
        $gateway->registerDriver('driver-b', $failingB);
        $gateway->setFallbackOrder(['driver-a', 'driver-b']);

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('All SMS drivers failed');

        $gateway->sendWithFallback('+22890001234', SmsMessage::create('Test'));
    }

    public function test_send_with_fallback_uses_default_when_no_fallback_order(): void
    {
        $driver = Mockery::mock(SmsDriverInterface::class);
        $driver->shouldReceive('send')->once();

        $gateway = new SmsGateway();
        $gateway->registerDriver('default', $driver);
        $gateway->setDefaultDriver('default');

        $gateway->sendWithFallback('+22890001234', SmsMessage::create('Test'));
    }
}
