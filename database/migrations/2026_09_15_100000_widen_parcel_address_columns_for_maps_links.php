<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite keeps TEXT for strings already; no structural change needed.
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE parcel_shipments MODIFY pickup_address TEXT NOT NULL');
            DB::statement('ALTER TABLE parcel_shipments MODIFY recipient_address TEXT NULL');

            return;
        }

        Schema::table('parcel_shipments', function (Blueprint $table) {
            $table->text('pickup_address')->change();
            $table->text('recipient_address')->nullable()->change();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE parcel_shipments MODIFY pickup_address VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE parcel_shipments MODIFY recipient_address VARCHAR(255) NULL');
        }
    }
};
