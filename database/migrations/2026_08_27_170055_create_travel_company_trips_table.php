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
        Schema::create('travel_company_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_company_route_id')
                ->constrained('travel_company_routes')
                ->cascadeOnDelete();
            $table->foreignId('travel_company_station_id')
                ->constrained('travel_company_stations')
                ->cascadeOnDelete();
            $table->time('departure_hour');
            $table->time('arrival_hour')->nullable();
            $table->unsignedSmallInteger('available_seats')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(
                ['travel_company_route_id', 'travel_company_station_id'],
                'travel_company_trips_route_station_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_company_trips');
    }
};
