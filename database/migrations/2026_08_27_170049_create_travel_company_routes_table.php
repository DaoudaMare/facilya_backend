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
        Schema::create('travel_company_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_company_id')
                ->constrained('travel_companies')
                ->cascadeOnDelete();
            $table->string('departure');
            $table->string('arrival');
            $table->string('travel_type');
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['travel_company_id', 'departure', 'arrival', 'travel_type'],
                'travel_company_routes_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travel_company_routes');
    }
};
