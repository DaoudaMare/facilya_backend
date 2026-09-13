<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trusted_payments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();
            $table->foreignId('buyer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('merchant_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('status')->default('pending_payment');
            $table->string('payout_status')->default('pending');
            $table->decimal('merchandise_amount', 12, 2);
            $table->decimal('network_fee', 12, 2)->default(0);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('payout_amount', 12, 2)->default(0);
            $table->char('currency', 3)->default('XOF');
            $table->string('buyer_deposit_phone');
            $table->string('merchant_payout_phone');
            $table->foreignId('payment_network_id')->nullable()->constrained('transfer_networks')->nullOnDelete();
            $table->foreignId('payout_network_id')->nullable()->constrained('transfer_networks')->nullOnDelete();
            $table->string('product_description');
            $table->decimal('declared_value', 12, 2)->nullable();
            $table->string('pickup_address')->nullable();
            $table->string('pickup_district')->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('delivery_district')->nullable();
            $table->string('payout_reference')->nullable();
            $table->unsignedBigInteger('payout_relay_job_id')->nullable();
            $table->timestamp('funds_held_at')->nullable();
            $table->timestamp('expedition_requested_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('payout_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['buyer_user_id', 'status']);
            $table->index(['merchant_user_id', 'status']);
        });

        Schema::create('trusted_payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trusted_payment_id')->constrained('trusted_payments')->cascadeOnDelete();
            $table->string('status');
            $table->string('note')->nullable();
            $table->string('actor_type')->default('system');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trusted_payment_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_payload');
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
                    OR
                    (
                        type = 'parcel_shipment'
                        AND source_network_id IS NULL
                        AND destination_network_id IS NULL
                        AND travel_company_trip_id IS NULL
                        AND travel_date IS NULL
                    )
                    OR
                    (
                        type = 'trusted_payment'
                        AND source_network_id IS NULL
                        AND destination_network_id IS NULL
                        AND travel_company_trip_id IS NULL
                        AND travel_date IS NULL
                        AND sender_phone IS NOT NULL
                        AND recipient_phone IS NOT NULL
                    )
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_payment_events');
        Schema::dropIfExists('trusted_payments');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_payload');
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
                    OR
                    (
                        type = 'parcel_shipment'
                        AND source_network_id IS NULL
                        AND destination_network_id IS NULL
                        AND travel_company_trip_id IS NULL
                        AND travel_date IS NULL
                    )
                )
            SQL);
        }
    }
};
