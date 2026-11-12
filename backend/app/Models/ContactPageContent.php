<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactPageContent extends Model
{
    use HasFactory;

    public const KEY = 'contact';

    protected $fillable = [
        'content_key',
        'eyebrow',
        'eyebrow_en',
        'title',
        'title_en',

        'instagram_label',
        'instagram_label_en',
        'instagram_value',
        'instagram_url',

        'telegram_label',
        'telegram_label_en',
        'telegram_value',
        'telegram_url',

        'whatsapp_label',
        'whatsapp_label_en',
        'whatsapp_value',
        'whatsapp_url',

        'phone_label',
        'phone_label_en',
        'phone_value',
        'phone_url',

        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}