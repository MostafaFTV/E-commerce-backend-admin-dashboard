<?php

namespace App\Models;

use App\Casts\LocalizedText;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'name' => LocalizedText::class,
        ];
    }

    public function values(): HasMany
    {
        return $this->hasMany(
            AttributeValue::class
        );
    }
}