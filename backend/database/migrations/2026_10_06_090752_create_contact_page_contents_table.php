<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'contact_page_contents',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->string(
                        'content_key',
                        50
                    )
                    ->unique();

                /*
                |--------------------------------------------------------------------------
                | Contact Card Headings
                |--------------------------------------------------------------------------
                */

                $table
                    ->string(
                        'eyebrow',
                        150
                    );

                $table
                    ->string(
                        'eyebrow_en',
                        150
                    )
                    ->nullable();

                $table
                    ->string(
                        'title',
                        255
                    );

                $table
                    ->string(
                        'title_en',
                        255
                    )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Instagram
                |--------------------------------------------------------------------------
                */

                $table
                    ->string(
                        'instagram_label',
                        100
                    );

                $table
                    ->string(
                        'instagram_label_en',
                        100
                    )
                    ->nullable();

                $table
                    ->string(
                        'instagram_value',
                        255
                    )
                    ->nullable();

                $table
                    ->string(
                        'instagram_url',
                        2048
                    )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Telegram
                |--------------------------------------------------------------------------
                */

                $table
                    ->string(
                        'telegram_label',
                        100
                    );

                $table
                    ->string(
                        'telegram_label_en',
                        100
                    )
                    ->nullable();

                $table
                    ->string(
                        'telegram_value',
                        255
                    )
                    ->nullable();

                $table
                    ->string(
                        'telegram_url',
                        2048
                    )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | WhatsApp
                |--------------------------------------------------------------------------
                */

                $table
                    ->string(
                        'whatsapp_label',
                        100
                    );

                $table
                    ->string(
                        'whatsapp_label_en',
                        100
                    )
                    ->nullable();

                $table
                    ->string(
                        'whatsapp_value',
                        255
                    )
                    ->nullable();

                $table
                    ->string(
                        'whatsapp_url',
                        2048
                    )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Phone
                |--------------------------------------------------------------------------
                */

                $table
                    ->string(
                        'phone_label',
                        100
                    );

                $table
                    ->string(
                        'phone_label_en',
                        100
                    )
                    ->nullable();

                $table
                    ->string(
                        'phone_value',
                        255
                    )
                    ->nullable();

                $table
                    ->string(
                        'phone_url',
                        255
                    )
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Management
                |--------------------------------------------------------------------------
                */

                $table
                    ->boolean(
                        'is_active'
                    )
                    ->default(true);

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'contact_page_contents'
        );
    }
};