<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedTinyInteger('discount_percentage')
                ->nullable()
                ->after('price');
        });

        Schema::create('customer_discount_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 64)->unique();
            $table->unsignedTinyInteger('percentage');
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->enum('scope', ['all', 'category']);
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('categories')
                ->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(
                ['customer_id', 'is_active', 'expires_at'],
                'customer_discount_codes_customer_status_expiry_index'
            );
            $table->index(
                ['is_active', 'expires_at'],
                'customer_discount_codes_status_expiry_index'
            );
            $table->index(
                ['scope', 'category_id'],
                'customer_discount_codes_scope_category_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_discount_codes');

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('discount_percentage');
        });
    }
};
