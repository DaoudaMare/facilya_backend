<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('relay_jobs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('relay_device_id')->nullable()->constrained('relay_devices')->nullOnDelete();
            $table->string('type', 32)->default('transfer');
            $table->string('status', 32)->default('pending');
            $table->string('network', 32);
            $table->string('recipient_phone', 32);
            $table->string('recipient_name')->nullable();
            $table->decimal('amount', 12, 2);
            $table->char('currency', 3)->default('XOF');
            $table->string('provider_reference')->nullable();
            $table->string('failure_reason')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'network']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('relay_jobs');
    }
};
