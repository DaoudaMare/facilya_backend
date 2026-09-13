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
            $table->string('sender_cnib_number', 64)->nullable()->after('sender_phone');
            $table->string('sender_cnib_photo')->nullable()->after('sender_cnib_number');
            $table->string('recipient_cnib_number', 64)->nullable()->after('recipient_phone');
            $table->string('recipient_cnib_photo')->nullable()->after('recipient_cnib_number');
        });

        // Aligner les anciens statuts ops vers le suivi simplifié.
        if (Schema::hasTable('parcel_shipments')) {
            $map = [
                'pickup_assigned' => 'confirmed',
                'at_origin_station' => 'collected',
                'in_transit' => 'shipped',
                'at_destination_station' => 'arrived_station',
                'out_for_delivery' => 'arrived_station',
                'ready_for_pickup' => 'arrived_station',
            ];

            foreach ($map as $from => $to) {
                DB::table('parcel_shipments')->where('status', $from)->update(['status' => $to]);
                DB::table('parcel_events')->where('status', $from)->update(['status' => $to]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('parcel_shipments', function (Blueprint $table) {
            $table->dropColumn([
                'sender_cnib_number',
                'sender_cnib_photo',
                'recipient_cnib_number',
                'recipient_cnib_photo',
            ]);
        });
    }
};
