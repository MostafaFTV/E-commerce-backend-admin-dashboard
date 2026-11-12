<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FaqQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'faq_group_id',
        'question',
        'question_en',
        'answer',
        'answer_en',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function faqGroup(): BelongsTo
    {
        return $this->belongsTo(
            FaqGroup::class
        );
    }
}