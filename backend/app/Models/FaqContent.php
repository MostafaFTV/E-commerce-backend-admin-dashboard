<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FaqContent extends Model
{
    use HasFactory;

    public const KEY = 'faq';

    protected $fillable = [
        'content_key',
        'title',
        'title_en',
        'intro',
        'intro_en',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(
            FaqGroup::class
        );
    }
}