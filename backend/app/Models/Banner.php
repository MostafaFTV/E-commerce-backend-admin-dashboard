<?php

namespace App\Models;

use App\Casts\LocalizedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'subtitle',
        'image',
        'mobile_image',
        'link',
        'button_text',
        'sort_order',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'title' => LocalizedText::class,
            'subtitle' => LocalizedText::class,
            'button_text' => LocalizedText::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function isCurrentlyActive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $now = now();

        if (
            $this->starts_at &&
            $now->lt($this->starts_at)
        ) {
            return false;
        }

        if (
            $this->ends_at &&
            $now->gt($this->ends_at)
        ) {
            return false;
        }

        return true;
    }
}
