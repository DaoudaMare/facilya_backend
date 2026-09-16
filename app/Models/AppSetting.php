<?php

namespace App\Models;

use App\Support\Phone;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    protected $fillable = [
        'support_whatsapp_phone',
    ];

    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function supportWhatsAppChatId(): ?string
    {
        $phone = trim((string) $this->support_whatsapp_phone);

        if ($phone === '' || ! Phone::isValid($phone)) {
            return null;
        }

        return Phone::toWhatsAppChatId($phone);
    }

    public function supportWhatsAppUrl(): ?string
    {
        $chatId = $this->supportWhatsAppChatId();

        return $chatId ? 'https://wa.me/'.$chatId : null;
    }
}
