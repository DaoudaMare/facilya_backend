<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transfer_networks', function (Blueprint $table) {
            $table->string('receive_phone', 32)->nullable()->after('can_receive');
        });
    }

    public function down(): void
    {
        Schema::table('transfer_networks', function (Blueprint $table) {
            $table->dropColumn('receive_phone');
        });
    }
};
