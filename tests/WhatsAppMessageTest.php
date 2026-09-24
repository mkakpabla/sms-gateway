<?php

namespace SmsGateway\Tests;

use PHPUnit\Framework\TestCase;
use SmsGateway\WhatsAppMessage;

class WhatsAppMessageTest extends TestCase
{
    public function testAFreeFormMessageIsNotATemplate(): void
    {
        $message = WhatsAppMessage::create('Bonjour')->to('22890001234')->from('14155238886');

        $this->assertSame('Bonjour', $message->getBody());
        $this->assertSame('22890001234', $message->getTo());
        $this->assertSame('14155238886', $message->getFrom());
        $this->assertFalse($message->isTemplate());
    }

    public function testListVariablesAreNumberedFromOne(): void
    {
        $message = WhatsAppMessage::template('HX123', ['Lomé', '24-09-2026']);

        $this->assertTrue($message->isTemplate());
        $this->assertSame('HX123', $message->getTemplate());
        $this->assertSame(['1' => 'Lomé', '2' => '24-09-2026'], $message->getTemplateVariables());
    }

    public function testKeyedVariablesAreKeptAsIs(): void
    {
        $message = WhatsAppMessage::template('HX123', [2 => 'b', 1 => 'a']);

        $this->assertSame(['2' => 'b', '1' => 'a'], $message->getTemplateVariables());
    }

    public function testATemplateWithoutVariablesHasNone(): void
    {
        $this->assertSame([], WhatsAppMessage::template('HX123')->getTemplateVariables());
    }

    public function testMediaUrlsAccumulate(): void
    {
        $message = WhatsAppMessage::create()->mediaUrl('https://a.test/1.pdf')->mediaUrl('https://a.test/2.pdf');

        $this->assertSame(['https://a.test/1.pdf', 'https://a.test/2.pdf'], $message->getMediaUrls());
    }

    public function testATemplateCanCarryALanguageAndBePinnedToADriver(): void
    {
        $message = WhatsAppMessage::template('daily_stock_report', ['24-09-2026'], 'fr')->driver('meta');

        $this->assertSame('fr', $message->getLanguage());
        $this->assertSame('meta', $message->getDriver());
    }
}
