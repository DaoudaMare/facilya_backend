<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->string('payment_status')->default('pending');
            $table->string('service_status')->default('pending');
            $table->decimal('amount', 12, 2);
            $table->decimal('network_fee', 12, 2)->default(0);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->char('currency', 3)->default('XOF');
            $table->string('description')->nullable();

            $table->foreignId('payment_network_id')
                ->nullable()
                ->constrained('transfer_networks')
                ->restrictOnDelete();
            $table->string('payment_reference')->nullable();
            $table->string('service_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->text('payment_failure_reason')->nullable();
            $table->text('service_failure_reason')->nullable();

            $table->foreignId('travel_company_trip_id')
                ->nullable()
                ->constrained('travel_company_trips')
                ->restrictOnDelete();
            $table->date('travel_date')->nullable();
            $table->string('passenger_name')->nullable();
            $table->string('passenger_phone')->nullable();
            $table->unsignedTinyInteger('passenger_count')->nullable();

            $table->foreignId('source_network_id')
                ->nullable()
                ->constrained('transfer_networks')
                ->restrictOnDelete();
            $table->foreignId('destination_network_id')
                ->nullable()
                ->constrained('transfer_networks')
                ->restrictOnDelete();
            $table->string('sender_phone')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_name')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'type', 'payment_status']);
            $table->index(['user_id', 'type', 'service_status']);
            $table->index(['type', 'created_at']);
            $table->index('payment_reference');
            $table->index('service_reference');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_network_fee_positive CHECK (network_fee >= 0)');
        DB::statement('ALTER TABLE transactions ADD CONSTRAINT transactions_platform_fee_positive CHECK (platform_fee >= 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE transactions ADD CONSTRAINT transactions_service_requires_payment CHECK (
                service_status <> 'delivered'
                OR payment_status = 'received'
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE transactions ADD CONSTRAINT transactions_type_payload CHECK (
                (
                    type = 'ticket_purchase'
                    AND travel_company_trip_id IS NOT NULL
                    AND travel_date IS NOT NULL
                    AND source_network_id IS NULL
                    AND destination_network_id IS NULL
                )
                OR
                (
                    type = 'network_transfer'
                    AND source_network_id IS NOT NULL
                    AND destination_network_id IS NOT NULL
                    AND source_network_id <> destination_network_id
                    AND sender_phone IS NOT NULL
                    AND recipient_phone IS NOT NULL
                    AND travel_company_trip_id IS NULL
                    AND travel_date IS NULL
                )
            )
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
