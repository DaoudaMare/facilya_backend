<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name', 60)->nullable()->after('name');
            $table->string('last_name', 60)->nullable()->after('first_name');
            $table->string('phone_secondary', 32)->nullable()->unique()->after('phone');
        });

        $users = DB::table('users')->select('id', 'name')->get();
        foreach ($users as $user) {
            $name = trim((string) $user->name);
            if ($name === '' || strcasecmp($name, 'Client Facilya') === 0) {
                continue;
            }

            $parts = preg_split('/\s+/', $name, 2) ?: [];
            $first = $parts[0] ?? null;
            $last = $parts[1] ?? null;

            DB::table('users')->where('id', $user->id)->update([
                'first_name' => $first,
                'last_name' => $last,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone_secondary']);
            $table->dropColumn(['first_name', 'last_name', 'phone_secondary']);
        });
    }
};
