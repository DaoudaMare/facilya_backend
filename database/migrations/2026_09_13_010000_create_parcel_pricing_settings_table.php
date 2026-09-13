<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcel_pricing_settings', function (Blueprint $table) {
            $table->id();

            // Prix compagnie de bus (fixe ou % du prix ticket de la route)
            $table->string('agency_mode')->default('fixed');
            $table->decimal('agency_value', 12, 4)->default(0);

            // Marge Facilya (fixe ou % du prix agence)
            $table->string('margin_mode')->default('fixed');
            $table->decimal('margin_value', 12, 4)->default(0);

            // Collecte porte : base fixe/% + frais auto au km
            $table->string('pickup_mode')->default('fixed');
            $table->decimal('pickup_value', 12, 4)->default(0);
            $table->decimal('pickup_per_km', 12, 4)->default(0);

            // Livraison porte : base fixe/% + frais auto au km
            $table->string('delivery_mode')->default('fixed');
            $table->decimal('delivery_value', 12, 4)->default(0);
            $table->decimal('delivery_per_km', 12, 4)->default(0);

            $table->timestamps();
        });

        Schema::table('parcel_shipments', function (Blueprint $table) {
            $table->decimal('agency_fee', 12, 2)->default(0)->after('declared_value');
            $table->decimal('margin_amount', 12, 2)->default(0)->after('agency_fee');
            $table->decimal('pickup_distance_km', 8, 2)->nullable()->after('pickup_fee');
            $table->decimal('delivery_distance_km', 8, 2)->nullable()->after('delivery_fee');
            $table->decimal('pickup_distance_fee', 12, 2)->default(0)->after('pickup_distance_km');
            $table->decimal('delivery_distance_fee', 12, 2)->default(0)->after('delivery_distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('parcel_shipments', function (Blueprint $table) {
            $table->dropColumn([
                'agency_fee',
                'margin_amount',
                'pickup_distance_km',
                'delivery_distance_km',
                'pickup_distance_fee',
                'delivery_distance_fee',
            ]);
        });

        Schema::dropIfExists('parcel_pricing_settings');
    }
};
