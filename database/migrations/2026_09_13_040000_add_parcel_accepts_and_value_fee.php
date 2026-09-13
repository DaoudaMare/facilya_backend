<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travel_company_routes', function (Blueprint $table) {
            $table->boolean('accepts_parcels')->default(false)->after('is_active');
        });

        Schema::table('parcel_pricing_settings', function (Blueprint $table) {
            $table->decimal('value_fee_percent', 8, 4)->default(0)->after('delivery_per_km');
        });

        Schema::table('parcel_shipments', function (Blueprint $table) {
            $table->decimal('value_fee', 12, 2)->default(0)->after('margin_amount');
        });
    }

    public function down(): void
    {
        Schema::table('travel_company_routes', function (Blueprint $table) {
            $table->dropColumn('accepts_parcels');
        });

        Schema::table('parcel_pricing_settings', function (Blueprint $table) {
            $table->dropColumn('value_fee_percent');
        });

        Schema::table('parcel_shipments', function (Blueprint $table) {
            $table->dropColumn('value_fee');
        });
    }
};
