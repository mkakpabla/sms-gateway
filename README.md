# SMS Gateway

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Framework-agnostic SMS gateway with multi-provider fallback support for PHP 8.3+.

## Features

- **Multi-driver support** — register multiple SMS providers and switch between them
- **Automatic fallback** — if one provider fails, the next one in the chain is used
- **Laravel integration** — service provider with auto-discovery, notification channel, and publishable config
- **Extensible** — implement `SmsDriverInterface` to add your own providers

## Supported Providers

| Provider | Driver | Status |
|---|---|---|
| FasterMessage | `faster-message` | ✅ Available |
| AfrikSMS | `afrik-sms` | Planned |
| Twilio | `twilio` | Planned |

## Installation

```bash
composer require mkakpabla/sms-gateway
```

### Laravel

The service provider is auto-discovered. Publish the configuration file:

```bash
php artisan vendor:publish --tag=sms-gateway-config
```

Add your credentials to `.env`:

```env
SMS_DRIVER=faster-message

FASTER_MESSAGE_FROM=MyApp
FASTER_MESSAGE_API_URL=https://api.fastermessage.com
FASTER_MESSAGE_USERNAME=your-username
FASTER_MESSAGE_PASSWORD=your-password
```

## Usage

### Standalone

```php
use SmsGateway\SmsGateway;
use SmsGateway\SmsMessage;
use SmsGateway\Drivers\FasterMessageDriver;

$gateway = new SmsGateway();

$gateway->registerDriver('faster-message', new FasterMessageDriver(
    from: 'MyApp',
    apiUrl: 'https://api.fastermessage.com',
    username: 'your-username',
    password: 'your-password',
));

$gateway->setDefaultDriver('faster-message');

$gateway->send('+22890001234', SmsMessage::create('Hello!'));
```

### With fallback

```php
$gateway->registerDriver('driver-a', $driverA);
$gateway->registerDriver('driver-b', $driverB);

$gateway->setFallbackOrder(['driver-a', 'driver-b']);

// Tries driver-a first, falls back to driver-b on failure
$gateway->sendWithFallback('+22890001234', SmsMessage::create('Hello!'));
```

### Laravel Notification

Implement the `HasSmsNotification` contract on your notification:

```php
use Illuminate\Notifications\Notification;
use SmsGateway\Contracts\HasSmsNotification;
use SmsGateway\SmsMessage;

class OrderShipped extends Notification implements HasSmsNotification
{
    public function via($notifiable): array
    {
        return ['sms-gateway'];
    }

    public function toSms(object $notifiable): SmsMessage
    {
        return SmsMessage::create('Your order has been shipped!');
    }
}
```

Make sure your notifiable model provides a phone number:

```php
public function routeNotificationForSms(): string
{
    return $this->phone;
}
```

## Creating a Custom Driver

Implement `SmsDriverInterface`:

```php
use SmsGateway\Contracts\SmsDriverInterface;
use SmsGateway\SmsMessage;

class MyCustomDriver implements SmsDriverInterface
{
    public function send(string $to, SmsMessage $message): void
    {
        // Your implementation here
    }
}
```

Then register it:

```php
$gateway->registerDriver('my-driver', new MyCustomDriver());
```

## Configuration

The config file (`config/sms-gateway.php`) supports the following options:

| Key | Description |
|---|---|
| `default` | The default SMS driver to use |
| `fallback` | Ordered list of drivers for the fallback chain |
| `drivers` | Per-driver configuration (credentials, API URLs, etc.) |

## Testing

```bash
composer test
```

### Quality tools

```bash
composer phpstan   # Static analysis
composer phpmd     # Mess detector
composer phpcs     # Code style
composer quality   # Run all checks
```

## Contributing

Contributions are welcome! See [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

MIT
