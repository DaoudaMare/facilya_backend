<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trusted_payments', function (Blueprint $table) {
            $table->string('public_id', 6)->nullable()->unique()->after('reference');
            $table->string('validation_code', 6)->nullable()->unique()->after('public_id');
        });

        $usedPublic = [];
        $usedValidation = [];

        foreach (DB::table('trusted_payments')->select('id')->orderBy('id')->get() as $payment) {
            $publicId = $this->uniqueDigits($usedPublic);
            $validation = $this->uniqueDigits($usedValidation);
            $usedPublic[$publicId] = true;
            $usedValidation[$validation] = true;

            DB::table('trusted_payments')->where('id', $payment->id)->update([
                'public_id' => $publicId,
                'validation_code' => $validation,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('trusted_payments', function (Blueprint $table) {
            $table->dropUnique(['public_id']);
            $table->dropUnique(['validation_code']);
            $table->dropColumn(['public_id', 'validation_code']);
        });
    }

    /**
     * @param  array<string, true>  $used
     */
    protected function uniqueDigits(array $used): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (isset($used[$code]));

        return $code;
    }
};
