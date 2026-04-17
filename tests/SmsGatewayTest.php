<?php

namespace SmsGateway\Tests;

use SmsGateway\Contracts\SmsDriverInterface;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\SmsGateway;
use SmsGateway\SmsMessage;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

class SmsGatewayTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function testItSendsViaDefaultDriver(): void
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

    public function testItUsesFirstRegisteredDriverIfNoDefault(): void
    {
        $driver = Mockery::mock(SmsDriverInterface::class);
        $driver->shouldReceive('send')->once();

        $gateway = new SmsGateway();
        $gateway->registerDriver('first', $driver);

        $gateway->send('+22890001234', SmsMessage::create('Test'));
    }

    public function testItThrowsWhenNoDriverRegistered(): void
    {
        $gateway = new SmsGateway();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No SMS driver registered');

        $gateway->send('+22890001234', SmsMessage::create('Test'));
    }

    public function testItThrowsWhenDriverNotFound(): void
    {
        $gateway = new SmsGateway();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SMS driver [unknown] is not registered');

        $gateway->getDriver('unknown');
    }

    public function testItFallsBackToNextDriverOnFailure(): void
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

    public function testItThrowsWhenAllDriversFail(): void
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

    public function testSendWithFallbackUsesDefaultWhenNoFallbackOrder(): void
    {
        $driver = Mockery::mock(SmsDriverInterface::class);
        $driver->shouldReceive('send')->once();

        $gateway = new SmsGateway();
        $gateway->registerDriver('default', $driver);
        $gateway->setDefaultDriver('default');

        $gateway->sendWithFallback('+22890001234', SmsMessage::create('Test'));
    }
}
