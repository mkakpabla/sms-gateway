<?php

namespace SmsGateway\Tests;

use SmsGateway\SmsMessage;
use PHPUnit\Framework\TestCase;

class SmsMessageTest extends TestCase
{
    public function test_it_can_create_a_message(): void
    {
        $message = SmsMessage::create('Bonjour, votre plainte a été reçue.');

        $this->assertSame('Bonjour, votre plainte a été reçue.', $message->getContent());
    }

    public function test_it_can_set_recipient(): void
    {
        $message = SmsMessage::create('Test')->to('+22890001234');

        $this->assertSame('+22890001234', $message->getTo());
    }

    public function test_it_can_set_sender(): void
    {
        $message = SmsMessage::create('Test')->from('DGT');

        $this->assertSame('DGT', $message->getFrom());
    }

    public function test_it_is_fluent(): void
    {
        $message = SmsMessage::create('Contenu')
            ->to('+22890001234')
            ->from('DGT');

        $this->assertSame('Contenu', $message->getContent());
        $this->assertSame('+22890001234', $message->getTo());
        $this->assertSame('DGT', $message->getFrom());
    }
}
