<?php

namespace SmsGateway\Tests\Drivers;

use PHPUnit\Framework\TestCase;
use SmsGateway\Drivers\TwilioWhatsAppDriver;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\WhatsAppMessage;
use Twilio\AuthStrategy\AuthStrategy;
use Twilio\Exceptions\HttpException;
use Twilio\Http\Client as HttpClient;
use Twilio\Http\Response;
use Twilio\Rest\Client;

class TwilioWhatsAppDriverTest extends TestCase
{
    /** @var array<int, array{method: string, url: string, data: array<string, mixed>, user: ?string}> */
    private array $requests = [];

    private function makeDriver(Response|HttpException $reply, string $messagingServiceSid = ''): TwilioWhatsAppDriver
    {
        $httpClient = new class ($reply, $this->requests) implements HttpClient
        {
            /**
             * @param array<int, array<string, mixed>> $requests
             */
            public function __construct(private Response|HttpException $reply, private array &$requests)
            {
            }

            public function request(
                string $method,
                string $url,
                array $params = [],
                array $data = [],
                array $headers = [],
                ?string $user = null,
                ?string $password = null,
                ?int $timeout = null,
                ?AuthStrategy $authStrategy = null,
            ): Response {
                $this->requests[] = ['method' => $method, 'url' => $url, 'data' => $data, 'user' => $user];

                if ($this->reply instanceof HttpException) {
                    throw $this->reply;
                }

                return $this->reply;
            }
        };

        return new TwilioWhatsAppDriver(
            accountSid: 'AC123',
            authToken: 'secret-token',
            from: '+14155238886',
            messagingServiceSid: $messagingServiceSid,
            client: new Client('AC123', 'secret-token', httpClient: $httpClient),
        );
    }

    private function queued(): Response
    {
        return new Response(201, json_encode(['sid' => 'SM123', 'account_sid' => 'AC123', 'status' => 'queued']));
    }

    /**
     * @return array<string, mixed>
     */
    private function sentData(): array
    {
        $this->assertCount(1, $this->requests);

        return $this->requests[0]['data'];
    }

    public function testItSendsATemplateMessage(): void
    {
        $driver = $this->makeDriver($this->queued());

        $driver->send('22890001234', WhatsAppMessage::template('HX123', ['Lomé', '24-09-2026']));

        $request = $this->requests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertStringEndsWith('/2010-04-01/Accounts/AC123/Messages.json', $request['url']);
        $this->assertSame('AC123', $request['user']);

        $data = $this->sentData();
        $this->assertSame('whatsapp:+22890001234', $data['To']);
        $this->assertSame('whatsapp:+14155238886', $data['From']);
        $this->assertSame('HX123', $data['ContentSid']);
        $this->assertSame('{"1":"Lomé","2":"24-09-2026"}', $data['ContentVariables']);
        $this->assertArrayNotHasKey('Body', $data);
    }

    public function testItSendsAFreeFormMessageWithSeveralMedia(): void
    {
        $driver = $this->makeDriver($this->queued());

        $driver->send(
            '+228 90 00 12 34',
            WhatsAppMessage::create('Rapport du jour')
                ->mediaUrl('https://example.com/a.pdf')
                ->mediaUrl('https://example.com/b.pdf'),
        );

        $data = $this->sentData();
        $this->assertSame('whatsapp:+22890001234', $data['To']);
        $this->assertSame('Rapport du jour', $data['Body']);
        $this->assertSame(['https://example.com/a.pdf', 'https://example.com/b.pdf'], $data['MediaUrl']);
    }

    public function testItUsesTheMessagingServiceInsteadOfTheSenderNumber(): void
    {
        $driver = $this->makeDriver($this->queued(), messagingServiceSid: 'MG123');

        $driver->send('whatsapp:+22890001234', WhatsAppMessage::create('Bonjour'));

        $data = $this->sentData();
        $this->assertSame('whatsapp:+22890001234', $data['To']);
        $this->assertSame('MG123', $data['MessagingServiceSid']);
        $this->assertArrayNotHasKey('From', $data);
    }

    public function testMessageFromOverridesTheDefaultSender(): void
    {
        $driver = $this->makeDriver($this->queued(), messagingServiceSid: 'MG123');

        $driver->send('22890001234', WhatsAppMessage::create('Bonjour')->from('0022899999999'));

        $data = $this->sentData();
        $this->assertSame('whatsapp:+22899999999', $data['From']);
        $this->assertArrayNotHasKey('MessagingServiceSid', $data);
    }

    public function testItRejectsAnEmptyMessage(): void
    {
        $driver = $this->makeDriver($this->queued());

        try {
            $driver->send('22890001234', WhatsAppMessage::create());
            $this->fail('An empty message must be rejected.');
        } catch (CouldNotSendNotification) {
            $this->assertSame([], $this->requests);
        }
    }

    public function testItThrowsWithTwilioErrorDetails(): void
    {
        $driver = $this->makeDriver(new Response(400, json_encode([
            'code' => 63016,
            'message' => 'Failed to send freeform message because you are outside the allowed window.',
            'status' => 400,
        ])));

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('[twilio] WhatsApp service responded with status 400: 63016');

        $driver->send('22890001234', WhatsAppMessage::create('Bonjour'));
    }

    public function testItThrowsWhenTwilioReportsAFailedMessage(): void
    {
        $driver = $this->makeDriver(new Response(201, json_encode([
            'sid' => 'SM123',
            'account_sid' => 'AC123',
            'status' => 'failed',
            'error_code' => 63003,
            'error_message' => 'Invalid destination',
        ])));

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('63003 Invalid destination');

        $driver->send('22890001234', WhatsAppMessage::create('Bonjour'));
    }

    public function testItThrowsOnNetworkError(): void
    {
        $driver = $this->makeDriver(new HttpException('Connection refused'));

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('[twilio]');

        $driver->send('22890001234', WhatsAppMessage::create('Bonjour'));
    }
}
