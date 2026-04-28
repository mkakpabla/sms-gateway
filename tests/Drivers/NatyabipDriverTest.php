<?php

namespace SmsGateway\Tests\Drivers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SmsGateway\Drivers\NatyabipDriver;
use SmsGateway\Exceptions\CouldNotSendNotification;
use SmsGateway\SmsMessage;

class NatyabipDriverTest extends TestCase
{
    /** @var array<int, array<string, mixed>> */
    private array $history = [];

    private function makeDriver(MockHandler $mock): NatyabipDriver
    {
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(Middleware::history($this->history));

        $client = new Client(['handler' => $handlerStack]);

        return new NatyabipDriver(
            username: 'test-user',
            password: 'test-password',
            from: 'EASYSERVICE',
            apiUrl: 'https://api.natyabip.com/smsapiprod_web/FR/api.awp',
            client: $client,
        );
    }

    public function testItSendsAnSmsSuccessfully(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [[
                    'receiver' => '22898384141',
                    'status' => [
                        'id' => 0,
                        'groupId' => 1,
                        'groupName' => 'DELIVERED',
                        'name' => 'DELIVERED',
                        'description' => 'DELIVERED',
                    ],
                    'smsCount' => 1,
                    'messageId' => 'cfa14ffa-13fe-4c8c-8940-ae917c11d678',
                ]],
            ])),
        ]);

        $driver = $this->makeDriver($mock);

        $driver->send('22898384141', SmsMessage::create('Bonjour'));

        $this->assertCount(1, $this->history);

        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/smsapiprod_web/FR/api.awp', $request->getUri()->getPath());
        $this->assertSame(
            'Basic ' . base64_encode('test-user:test-password'),
            $request->getHeaderLine('Authorization'),
        );

        $payload = json_decode((string) $request->getBody(), true);
        $this->assertSame('EASYSERVICE', $payload['from']);
        $this->assertSame('22898384141', $payload['to']);
        $this->assertSame('Bonjour', $payload['text']);
    }

    public function testItUsesMessageFromOverridesDefaultSender(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [[
                    'status' => [
                        'groupId' => 1,
                        'groupName' => 'DELIVERED',
                        'description' => 'DELIVERED',
                    ],
                    'messageId' => 'cfa14ffa-13fe-4c8c-8940-ae917c11d678',
                ]],
            ])),
        ]);

        $driver = $this->makeDriver($mock);

        $driver->send('22898384141', SmsMessage::create('Test')->from('CUSTOM'));

        $request = $this->history[0]['request'];
        $payload = json_decode((string) $request->getBody(), true);
        $this->assertSame('CUSTOM', $payload['from']);
    }

    public function testItThrowsOnRejectedStatus(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [[
                    'status' => [
                        'groupId' => 5,
                        'groupName' => 'REJECTED',
                        'description' => 'User and password incorrect',
                    ],
                ]],
            ])),
        ]);

        $driver = $this->makeDriver($mock);

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('User and password incorrect');

        $driver->send('22898384141', SmsMessage::create('Test'));
    }

    public function testItThrowsOnPlainTextErrorResponse(): void
    {
        $mock = new MockHandler([
            new Response(200, [], 'ERROR: User and password incorrect'),
        ]);

        $driver = $this->makeDriver($mock);

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('User and password incorrect');

        $driver->send('22898384141', SmsMessage::create('Test'));
    }

    public function testItThrowsOnHttpError(): void
    {
        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error'),
        ]);

        $driver = $this->makeDriver($mock);

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('[natyabip]');

        $driver->send('22898384141', SmsMessage::create('Test'));
    }

    public function testItThrowsOnNetworkError(): void
    {
        $mock = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'https://api.natyabip.com')),
        ]);

        $driver = $this->makeDriver($mock);

        $this->expectException(CouldNotSendNotification::class);
        $this->expectExceptionMessage('[natyabip]');

        $driver->send('22898384141', SmsMessage::create('Test'));
    }
}
