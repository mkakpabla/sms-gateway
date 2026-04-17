<?php

namespace SmsGateway\Laravel;

use SmsGateway\Drivers\AfrikSmsDriver;
use SmsGateway\Drivers\FasterMessageDriver;
use SmsGateway\SmsGateway;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\ServiceProvider;

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
    }
}
