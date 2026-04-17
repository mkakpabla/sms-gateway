<?php

namespace SmsGateway\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use SmsGateway\Drivers\AfrikSmsDriver;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\SmsMessage;
use PHPUnit\Framework\TestCase;

class AfrikSmsDriverTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    private function makeDriver(MockHandler $mock): AfrikSmsDriver
    {
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($this->history));

        $client = new Client(['handler' => $handlerStack]);

        return new AfrikSmsDriver(
            clientId: 'test-client-id',
            apiKey: 'test-api-key',
            senderId: 'TESTSMS',
            client: $client,
        );
    }

    public function testItSendsAnSmsSuccessfully(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'code' => 100,
                'message' => 'Success operation',
                'resourceId' => 'P7COiDNjFvZ_gkYo7lGKVIDMemINjrgx',
            ])),
        ]);

        $driver = $this->makeDriver($mock);

        $driver->send('22890909090', SmsMessage::create('Bonjour'));

        $this->assertCount(1, $this->history);

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('api.afriksms.com', (string) $request->getUri());

        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('test-client-id', $query['ClientId']);
        $this->assertSame('test-api-key', $query['ApiKey']);
        $this->assertSame('TESTSMS', $query['SenderId']);
        $this->assertSame('Bonjour', $query['Message']);
        $this->assertSame('22890909090', $query['MobileNumbers']);
    }

    public function testItUsesMessageFromOverridesDefaultSenderId(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['code' => 100, 'message' => 'Success operation'])),
        ]);

        $driver = $this->makeDriver($mock);

        $message = SmsMessage::create('Test')->from('CUSTOM');
        $driver->send('22890909090', $message);

        $request = $this->history[0]['request'];
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertSame('CUSTOM', $query['SenderId']);
    }

    public function testItThrowsOnHttpError(): void
    {
        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);

        $driver = $this->makeDriver($mock);

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('[afriksms]');

        $driver->send('22890909090', SmsMessage::create('Test'));
    }

    public function testItThrowsOnApiErrorCode(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'code' => 400,
                'message' => 'Invalid credentials',
            ])),
        ]);

        $driver = $this->makeDriver($mock);

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('Invalid credentials');

        $driver->send('22890909090', SmsMessage::create('Test'));
    }

    public function testItThrowsOnNetworkError(): void
    {
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'https://api.afriksms.com')),
        ]);

        $driver = $this->makeDriver($mock);

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('[afriksms]');

        $driver->send('22890909090', SmsMessage::create('Test'));
    }
}
