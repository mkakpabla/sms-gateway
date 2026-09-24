# SMS Gateway

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

Framework-agnostic SMS gateway with multi-provider fallback support for PHP 8.3+.

## Features

- **Multi-driver support** — register multiple SMS providers and switch between them
- **Automatic fallback** — if one provider fails, the next one in the chain is used
- **Laravel integration** — service provider with auto-discovery, notification channel, and publishable config
- **Extensible** — implement `SmsDriverInterface` to add your own providers
- **WhatsApp** — provider-agnostic `whatsapp` notification channel (templates, media, fallback); Twilio built in via its official SDK, any other provider pluggable

## Supported Providers

| Provider | Driver | Status |
|---|---|---|
| FasterMessage | `faster-message` | ✅ Available |
| AfrikSMS | `afriksms` | ✅ Available |
| NATYABIP | `natyabip` | ✅ Available |

| WhatsApp provider | Driver | Status |
|---|---|---|
| Twilio | `twilio` | ✅ Available |

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

# FasterMessage
FASTER_MESSAGE_FROM=MyApp
FASTER_MESSAGE_API_URL=https://api.fastermessage.com
FASTER_MESSAGE_USERNAME=your-username
FASTER_MESSAGE_PASSWORD=your-password

# AfrikSMS
AFRIKSMS_CLIENT_ID=your-client-id
AFRIKSMS_API_KEY=your-api-key
AFRIKSMS_SENDER_ID=AFRIKSMS

# NATYABIP
NATYABIP_USERNAME=your-username
NATYABIP_PASSWORD=your-password
NATYABIP_FROM=EASYSERVICE
NATYABIP_API_URL=https://api.natyabip.com/smsapiprod_web/FR/api.awp

# WhatsApp (Twilio)
WHATSAPP_DRIVER=twilio
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your-auth-token
TWILIO_WHATSAPP_FROM=+14155238886
# Optional: send through a Messaging Service instead of a single number
TWILIO_MESSAGING_SERVICE_SID=
TWILIO_STATUS_CALLBACK=
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

#### AfrikSMS

```php
use SmsGateway\SmsGateway;
use SmsGateway\SmsMessage;
use SmsGateway\Drivers\AfrikSmsDriver;

$gateway = new SmsGateway();

$gateway->registerDriver('afriksms', new AfrikSmsDriver(
    clientId: 'your-client-id',
    apiKey: 'your-api-key',
    senderId: 'AFRIKSMS',
));

$gateway->setDefaultDriver('afriksms');

$gateway->send('22890001234', SmsMessage::create('Hello!'));
```

#### NATYABIP

```php
use SmsGateway\SmsGateway;
use SmsGateway\SmsMessage;
use SmsGateway\Drivers\NatyabipDriver;

$gateway = new SmsGateway();

$gateway->registerDriver('natyabip', new NatyabipDriver(
    apiUrl: 'https://your-natyabip-api-url',
    username: 'your-username',
    password: 'your-password',
    from: 'EASYSERVICE',
));

$gateway->setDefaultDriver('natyabip');

$gateway->send('22890001234', SmsMessage::create('Hello!'));
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

## WhatsApp

WhatsApp is a dedicated channel, **not** part of the SMS fallback chain: outside the
24-hour customer service window, Meta only accepts pre-approved templates, so an SMS
body cannot simply be replayed on WhatsApp.

The channel is named `whatsapp` whatever the provider: notifications never depend on Twilio.
Providers are drivers of a `WhatsAppGateway`, exactly like SMS drivers of `SmsGateway`.

### Standalone

```php
use SmsGateway\Drivers\TwilioWhatsAppDriver;
use SmsGateway\WhatsAppGateway;
use SmsGateway\WhatsAppMessage;

$gateway = (new WhatsAppGateway())
    ->registerDriver('twilio', new TwilioWhatsAppDriver(
        accountSid: 'ACxxxxxxxx',
        authToken: 'your-auth-token',
        from: '+14155238886',
    ))
    ->setDefaultDriver('twilio');

// Approved template with positional variables {{1}}, {{2}}...
$gateway->send('22890001234', WhatsAppMessage::template('HXxxxxxxxx', ['Lomé', '24-09-2026']));

// Free-form message with a document: only inside the 24-hour window
$gateway->send('22890001234', WhatsAppMessage::create('Daily report')->mediaUrl('https://example.com/report.pdf'));
```

The template identifier is provider specific (a Content SID for Twilio, a template name for the
Meta Cloud API...). `WhatsAppMessage::template($id, $variables, $language)` carries all three;
each driver uses what its provider needs. Pin a message to a provider with `->driver('meta')`:
a pinned message bypasses the fallback chain.

The Twilio driver sends numbers as `whatsapp:+<E.164>`: spaces are stripped and `+` is added
when missing, but the country code must already be present.

Media must be reachable by Twilio through a public HTTPS URL. For a template whose header is
a document, the URL is declared in the template itself (usually as a variable), not with `mediaUrl()`.

### Laravel Notification

```php
use Illuminate\Notifications\Notification;
use SmsGateway\Contracts\HasWhatsAppNotification;
use SmsGateway\WhatsAppMessage;

class DailyStockReport extends Notification implements HasWhatsAppNotification
{
    public function via($notifiable): array
    {
        return ['whatsapp'];
    }

    public function toWhatsApp(object $notifiable): WhatsAppMessage
    {
        return WhatsAppMessage::template('HXxxxxxxxx', [now()->format('d-m-Y')]);
    }
}
```

The notifiable provides the number through `routeNotificationForWhatsapp()`, or use an on-demand
notification: `Notification::route('whatsapp', '22890001234')->notify(...)`.

### Adding a WhatsApp provider

Implement `WhatsAppDriverInterface`:

```php
use SmsGateway\Contracts\WhatsAppDriverInterface;
use SmsGateway\WhatsAppMessage;

class MetaCloudDriver implements WhatsAppDriverInterface
{
    public function __construct(private string $phoneNumberId, private string $accessToken)
    {
    }

    public function send(string $to, WhatsAppMessage $message): void
    {
        // Call the provider API, throw CouldNotSendNotification on failure
    }
}
```

Then declare it in `config/sms-gateway.php`. Keys other than `class` are passed to the
constructor as camelCase named arguments; the driver is only built when first used:

```php
'whatsapp' => [
    'default' => env('WHATSAPP_DRIVER', 'twilio'),
    'fallback' => [],
    'drivers' => [
        'twilio' => [/* ... */],
        'meta' => [
            'class' => App\WhatsApp\MetaCloudDriver::class,
            'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
            'access_token' => env('META_WHATSAPP_TOKEN'),
        ],
    ],
],
```

Or register it in code: `app(WhatsAppGateway::class)->extend('meta', fn () => new MetaCloudDriver(...))`.

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
| `whatsapp.default` | The default WhatsApp driver (`twilio`) |
| `whatsapp.fallback` | Ordered WhatsApp drivers for the fallback chain |
| `whatsapp.drivers` | Per-driver WhatsApp configuration; custom providers declare a `class` |

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
