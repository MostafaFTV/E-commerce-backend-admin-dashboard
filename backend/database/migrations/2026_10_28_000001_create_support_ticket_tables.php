<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('subject', 50);
            $table->string('locale', 2)->default('fa');
            $table->string('status', 10)->default('open');
            $table->unsignedBigInteger('customer_last_read_message_id')->nullable();
            $table->unsignedBigInteger('admin_last_read_message_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status', 'updated_at'], 'support_tickets_customer_status_updated_idx');
            $table->index(['status', 'updated_at'], 'support_tickets_status_updated_idx');
            $table->index(['customer_last_read_message_id'], 'support_tickets_customer_read_idx');
            $table->index(['admin_last_read_message_id'], 'support_tickets_admin_read_idx');
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();
            $table->foreignId('sender_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('sender_role', 10);
            $table->string('sender_name', 255);
            $table->uuid('client_message_id')->unique();
            $table->text('message');
            $table->timestamps();

            $table->index(['support_ticket_id', 'id'], 'support_ticket_messages_ticket_id_idx');
            $table->index(['support_ticket_id', 'sender_role', 'created_at'], 'support_ticket_messages_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
    }
};
