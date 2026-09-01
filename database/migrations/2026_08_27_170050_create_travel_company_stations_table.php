<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('travel_company_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_company_id')
                ->constrained('travel_companies')
                ->cascadeOnDelete();
            $table->string('station_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('google_maps_link')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['travel_company_id', 'station_name'],
                'travel_company_stations_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_company_stations');
    }
};
