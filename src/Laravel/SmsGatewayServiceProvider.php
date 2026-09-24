<?php

namespace SmsGateway\Laravel;

use InvalidArgumentException;
use SmsGateway\Contracts\WhatsAppDriverInterface;
use SmsGateway\Drivers\AfrikSmsDriver;
use SmsGateway\Drivers\FasterMessageDriver;
use SmsGateway\Drivers\NatyabipDriver;
use SmsGateway\Drivers\TwilioWhatsAppDriver;
use SmsGateway\SmsGateway;
use SmsGateway\WhatsAppGateway;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class SmsGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/sms-gateway.php', 'sms-gateway');

        $this->app->singleton(SmsGateway::class, function ($app) {
            $gateway = new SmsGateway();
            $config = $app['config']['sms-gateway'];

            $this->registerDrivers($gateway, $config['drivers'] ?? []);

            $gateway->setDefaultDriver($config['default'] ?? 'faster-message');
            $gateway->setFallbackOrder($config['fallback'] ?? []);

            return $gateway;
        });

        $this->app->singleton(SmsChannel::class, function ($app) {
            return new SmsChannel($app->make(SmsGateway::class));
        });

        $this->app->singleton(WhatsAppGateway::class, function ($app) {
            $gateway = new WhatsAppGateway();
            $config = $app['config']['sms-gateway.whatsapp'] ?? [];

            $this->registerWhatsAppDrivers($gateway, $config['drivers'] ?? []);

            $gateway->setDefaultDriver($config['default'] ?? 'twilio');
            $gateway->setFallbackOrder($config['fallback'] ?? []);

            return $gateway;
        });

        $this->app->singleton(WhatsAppChannel::class, function ($app) {
            return new WhatsAppChannel($app->make(WhatsAppGateway::class));
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/sms-gateway.php' => config_path('sms-gateway.php'),
            ], 'sms-gateway-config');
        }

        Notification::resolved(function (ChannelManager $service) {
            $service->extend('sms-gateway', function ($app) {
                return $app->make(SmsChannel::class);
            });

            $service->extend('whatsapp', function ($app) {
                return $app->make(WhatsAppChannel::class);
            });
        });
    }

    /**
     * @param array<string, array<string, string>> $drivers
     */
    private function registerDrivers(SmsGateway $gateway, array $drivers): void
    {
        if (isset($drivers['faster-message'])) {
            $config = $drivers['faster-message'];
            $gateway->registerDriver('faster-message', new FasterMessageDriver(
                from: $config['from'] ?? '',
                apiUrl: $config['api_url'] ?? '',
                username: $config['username'] ?? '',
                password: $config['password'] ?? '',
            ));
        }

        if (isset($drivers['afriksms'])) {
            $config = $drivers['afriksms'];
            $gateway->registerDriver('afriksms', new AfrikSmsDriver(
                clientId: $config['client_id'] ?? '',
                apiKey: $config['api_key'] ?? '',
                senderId: $config['sender_id'] ?? '',
                apiUrl: $config['api_url'] ?? '',
            ));
        }

        if (isset($drivers['natyabip'])) {
            $config = $drivers['natyabip'];
            $gateway->registerDriver('natyabip', new NatyabipDriver(
                username: $config['username'] ?? '',
                password: $config['password'] ?? '',
                from: $config['from'] ?? '',
                apiUrl: $config['api_url'] ?? '',
            ));
        }
    }

    /**
     * Built-in drivers are keyed by name; any other entry must name its
     * class, whose constructor receives the entry keys as camelCase
     * named arguments (e.g. api_token -> $apiToken).
     *
     * @param array<string, array<string, mixed>> $drivers
     */
    private function registerWhatsAppDrivers(WhatsAppGateway $gateway, array $drivers): void
    {
        foreach ($drivers as $name => $config) {
            $gateway->extend($name, fn () => $this->makeWhatsAppDriver($name, $config));
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function makeWhatsAppDriver(string $name, array $config): WhatsAppDriverInterface
    {
        $class = $config['class'] ?? null;

        if ($class === null && $name === 'twilio') {
            return new TwilioWhatsAppDriver(
                accountSid: $config['account_sid'] ?? '',
                authToken: $config['auth_token'] ?? '',
                from: $config['from'] ?? '',
                messagingServiceSid: $config['messaging_service_sid'] ?? '',
                statusCallback: $config['status_callback'] ?? '',
            );
        }

        if (! is_string($class) || ! is_subclass_of($class, WhatsAppDriverInterface::class)) {
            throw new InvalidArgumentException(
                "WhatsApp driver [{$name}] must define a 'class' implementing " . WhatsAppDriverInterface::class . '.'
            );
        }

        $parameters = [];

        foreach ($config as $key => $value) {
            if ($key !== 'class') {
                $parameters[Str::camel($key)] = $value;
            }
        }

        return $this->app->make($class, $parameters);
    }
}
