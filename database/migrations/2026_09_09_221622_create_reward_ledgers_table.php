<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reward_ledgers')) {
            return;
        }

        Schema::create('reward_ledgers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('referral_commission_id')->nullable()->constrained('referral_commissions')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_ledgers');
    }
};
