<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    public function test_whatsapp_chat_id_keeps_local_zero_and_country_code(): void
    {
        $this->assertSame('22607684843', Phone::toWhatsAppChatId('07684843'));
        $this->assertSame('22607684843', Phone::toWhatsAppChatId('07 68 48 43'));
        $this->assertSame('22607684843', Phone::toWhatsAppChatId('+226 07 68 48 43'));
        $this->assertSame('22607684843', Phone::toWhatsAppChatId('22607684843'));
        $this->assertSame('22670111111', Phone::toWhatsAppChatId('70111111'));
        $this->assertSame('22670111111', Phone::toWhatsAppChatId('70 11 11 11'));
    }

    public function test_matches_orange_sms_number_without_local_zero(): void
    {
        $this->assertTrue(Phone::matches('07684843', '7684843'));
        $this->assertTrue(Phone::matches('07684843', '2267684843'));
        $this->assertTrue(Phone::matches('07684843', '22607684843'));
        $this->assertTrue(Phone::matches('07684843', '07684843'));
        $this->assertFalse(Phone::matches('07684843', '07111111'));
    }
}
