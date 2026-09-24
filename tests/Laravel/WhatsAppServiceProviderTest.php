<?php

namespace SmsGateway\Tests\Laravel;

use Illuminate\Notifications\ChannelManager;
use InvalidArgumentException;
use Orchestra\Testbench\TestCase;
use SmsGateway\Drivers\TwilioWhatsAppDriver;
use SmsGateway\Laravel\SmsGatewayServiceProvider;
use SmsGateway\Laravel\WhatsAppChannel;
use SmsGateway\WhatsAppGateway;
use SmsGateway\Tests\Laravel\Fixtures\FakeAcmeWhatsAppDriver;

class WhatsAppServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [SmsGatewayServiceProvider::class];
    }

    private function gateway(): WhatsAppGateway
    {
        return $this->app->make(WhatsAppGateway::class);
    }

    public function testTwilioIsTheDefaultDriver(): void
    {
        $this->assertSame('twilio', $this->gateway()->getDefaultDriver());
        $this->assertInstanceOf(TwilioWhatsAppDriver::class, $this->gateway()->getDriver('twilio'));
    }

    public function testTheWhatsAppNotificationChannelIsRegistered(): void
    {
        $channel = $this->app->make(ChannelManager::class)->driver('whatsapp');

        $this->assertInstanceOf(WhatsAppChannel::class, $channel);
    }

    public function testACustomProviderIsPluggedFromConfig(): void
    {
        $this->app['config']->set('sms-gateway.whatsapp.default', 'acme');
        $this->app['config']->set('sms-gateway.whatsapp.drivers.acme', [
            'class' => FakeAcmeWhatsAppDriver::class,
            'api_token' => 'secret',
            'sender_id' => 'VONAMAWU',
        ]);

        $driver = $this->gateway()->getDriver('acme');

        $this->assertInstanceOf(FakeAcmeWhatsAppDriver::class, $driver);
        $this->assertSame('secret', $driver->apiToken);
        $this->assertSame('VONAMAWU', $driver->senderId);
        $this->assertSame('acme', $this->gateway()->getDefaultDriver());
    }

    public function testAnEntryWithoutAValidClassIsRejected(): void
    {
        $this->app['config']->set('sms-gateway.whatsapp.drivers.acme', ['class' => \stdClass::class]);

        $this->expectException(InvalidArgumentException::class);

        $this->gateway()->getDriver('acme');
    }
}
