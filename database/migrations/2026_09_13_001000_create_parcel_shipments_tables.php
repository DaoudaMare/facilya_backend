<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_shipments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained('transactions')
                ->nullOnDelete();
            $table->foreignId('travel_company_id')->nullable()->constrained('travel_companies')->nullOnDelete();
            $table->foreignId('travel_company_route_id')->nullable()->constrained('travel_company_routes')->nullOnDelete();

            $table->string('delivery_mode');
            $table->string('status')->default('pending_payment');

            $table->string('sender_name');
            $table->string('sender_phone');
            $table->string('pickup_address');
            $table->string('pickup_district')->nullable();

            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->string('recipient_address')->nullable();
            $table->string('recipient_district')->nullable();

            $table->string('parcel_description')->nullable();
            $table->decimal('estimated_weight_kg', 8, 2)->nullable();
            $table->decimal('declared_value', 12, 2)->nullable();

            $table->decimal('base_amount', 12, 2);
            $table->decimal('pickup_fee', 12, 2)->default(0);
            $table->decimal('delivery_fee', 12, 2)->default(0);
            $table->decimal('network_fee', 12, 2)->default(0);
            $table->decimal('platform_fee', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->char('currency', 3)->default('XOF');

            $table->string('pickup_code', 12)->nullable();
            $table->string('delivery_code', 12)->nullable();
            $table->string('station_pickup_code', 12)->nullable();

            $table->timestamp('collected_at')->nullable();
            $table->timestamp('departed_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('delivery_mode');
        });

        Schema::create('parcel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_shipment_id')->constrained('parcel_shipments')->cascadeOnDelete();
            $table->string('status');
            $table->string('note')->nullable();
            $table->string('actor_type')->default('system');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['parcel_shipment_id', 'created_at']);
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
                )
            SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_events');
        Schema::dropIfExists('parcel_shipments');

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
                )
            SQL);
        }
    }
};
