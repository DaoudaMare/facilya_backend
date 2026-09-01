<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('travel_company_route_id')
                ->nullable()
                ->after('travel_company_trip_id')
                ->constrained('travel_company_routes')
                ->restrictOnDelete();
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE transactions AS t
            SET travel_company_route_id = trips.travel_company_route_id
            FROM travel_company_trips AS trips
            WHERE t.travel_company_trip_id = trips.id
              AND t.travel_company_route_id IS NULL
        SQL);

        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_payload');
        DB::statement(<<<'SQL'
            ALTER TABLE transactions ADD CONSTRAINT transactions_type_payload CHECK (
                (
                    type = 'ticket_purchase'
                    AND travel_company_route_id IS NOT NULL
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
                    AND travel_company_route_id IS NULL
                    AND travel_company_trip_id IS NULL
                    AND travel_date IS NULL
                )
            )
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_type_payload');
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('travel_company_route_id');
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

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
};
