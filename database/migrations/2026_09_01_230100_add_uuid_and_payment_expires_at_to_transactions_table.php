<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->timestamp('payment_expires_at')->nullable()->after('paid_at');
        });

        foreach (DB::table('transactions')->whereNull('uuid')->orderBy('id')->get() as $row) {
            DB::table('transactions')->where('id', $row->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn(['uuid', 'payment_expires_at']);
        });
    }
};
