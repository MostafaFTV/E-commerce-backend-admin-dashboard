<?php

namespace App\Enums;

enum Locale: string
{
    case EN = 'en';
    case FA = 'fa';

    public function isRtl(): bool
    {
        return $this === self::FA;
    }

    public function direction(): string
    {
        return $this->isRtl()
            ? 'rtl'
            : 'ltr';
    }

    public function label(): string
    {
        return match ($this) {
            self::EN => 'English',
            self::FA => 'فارسی',
        };
    }
}