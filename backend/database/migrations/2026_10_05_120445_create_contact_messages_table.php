<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'contact_messages',
            function (Blueprint $table) {
                $table->id();

                $table->string(
                    'full_name',
                    150
                );

                $table->string(
                    'contact',
                    255
                );

                $table->string(
                    'subject',
                    50
                );

                $table->text(
                    'message'
                );

                $table->string(
                    'locale',
                    2
                );

                $table->string(
                    'status',
                    30
                )->default('new');

                $table->timestamps();

                $table->index(
                    'status'
                );

                $table->index(
                    'created_at'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'contact_messages'
        );
    }
};