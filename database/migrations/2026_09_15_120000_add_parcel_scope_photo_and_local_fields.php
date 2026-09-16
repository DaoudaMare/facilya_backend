<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('parcel_shipments', 'scope')) {
                $table->string('scope')->default('intercity');
            }
            if (! Schema::hasColumn('parcel_shipments', 'origin_city')) {
                $table->string('origin_city')->nullable();
            }
            if (! Schema::hasColumn('parcel_shipments', 'destination_city')) {
                $table->string('destination_city')->nullable();
            }
            if (! Schema::hasColumn('parcel_shipments', 'parcel_photo')) {
                $table->string('parcel_photo')->nullable();
            }
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE parcel_shipments MODIFY travel_company_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE parcel_shipments MODIFY travel_company_route_id BIGINT UNSIGNED NULL');

            return;
        }

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE parcel_shipments ALTER COLUMN travel_company_id DROP NOT NULL');
            DB::statement('ALTER TABLE parcel_shipments ALTER COLUMN travel_company_route_id DROP NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('parcel_shipments', function (Blueprint $table) {
            if (Schema::hasColumn('parcel_shipments', 'parcel_photo')) {
                $table->dropColumn('parcel_photo');
            }
            if (Schema::hasColumn('parcel_shipments', 'destination_city')) {
                $table->dropColumn('destination_city');
            }
            if (Schema::hasColumn('parcel_shipments', 'origin_city')) {
                $table->dropColumn('origin_city');
            }
            if (Schema::hasColumn('parcel_shipments', 'scope')) {
                $table->dropColumn('scope');
            }
        });
    }
};
