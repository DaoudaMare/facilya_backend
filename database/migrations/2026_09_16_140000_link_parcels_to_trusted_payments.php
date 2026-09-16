<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_shipments', function (Blueprint $table) {
            $table->foreignId('trusted_payment_id')
                ->nullable()
                ->after('transaction_id')
                ->constrained('trusted_payments')
                ->nullOnDelete();
            $table->unique('trusted_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('parcel_shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trusted_payment_id');
        });
    }
};
