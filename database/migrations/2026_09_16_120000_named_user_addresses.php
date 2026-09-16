<?php

use App\Data\UserAddressKindEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->string('name', 80)->nullable()->after('user_id');
        });

        $rows = DB::table('user_addresses')->select('id', 'kind')->get();
        foreach ($rows as $row) {
            $kind = UserAddressKindEnum::tryFrom((string) ($row->kind ?? ''));
            DB::table('user_addresses')->where('id', $row->id)->update([
                'name' => $kind?->label() ?: 'Adresse',
            ]);
        }

        Schema::table('user_addresses', function (Blueprint $table) {
            $sm = Schema::getConnection()->getSchemaBuilder();
            $indexes = $sm->getIndexes('user_addresses');
            foreach ($indexes as $index) {
                $cols = $index['columns'] ?? [];
                if ($cols === ['user_id', 'kind'] || ($index['name'] ?? '') === 'user_addresses_user_id_kind_unique') {
                    $table->dropUnique($index['name']);
                    break;
                }
            }

            if (Schema::hasColumn('user_addresses', 'kind')) {
                $table->dropColumn('kind');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_addresses', function (Blueprint $table) {
            $table->string('kind', 16)->nullable()->after('user_id');
        });

        Schema::table('user_addresses', function (Blueprint $table) {
            $table->dropColumn('name');
            $table->unique(['user_id', 'kind']);
        });
    }
};
