<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relay_deposits', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('relay_device_id')->constrained('relay_devices')->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('network', 32);
            $table->decimal('amount', 12, 2);
            $table->string('provider_transaction_id')->unique();
            $table->string('sender_phone', 32);
            $table->string('sender_name')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('raw_body')->nullable();
            $table->timestamp('matched_at')->nullable();
            $table->timestamps();

            $table->index(['network', 'sender_phone', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relay_deposits');
    }
};
