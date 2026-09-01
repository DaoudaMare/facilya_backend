<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fees', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('transaction_type');
            $table->string('part');
            $table->string('mode');
            $table->decimal('value', 12, 4);
            $table->decimal('min_fee', 12, 2)->nullable();
            $table->decimal('max_fee', 12, 2)->nullable();
            $table->decimal('min_amount', 12, 2)->nullable();
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->foreignId('network_id')
                ->nullable()
                ->constrained('transfer_networks')
                ->restrictOnDelete();
            $table->foreignId('counterpart_network_id')
                ->nullable()
                ->constrained('transfer_networks')
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['transaction_type', 'part', 'is_active']);
        });

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE fees ADD CONSTRAINT fees_value_positive CHECK (value > 0)');
        DB::statement(<<<'SQL'
            ALTER TABLE fees ADD CONSTRAINT fees_percentage_range CHECK (
                mode <> 'percentage'
                OR value <= 100
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE fees ADD CONSTRAINT fees_min_max_fee CHECK (
                min_fee IS NULL
                OR max_fee IS NULL
                OR min_fee <= max_fee
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE fees ADD CONSTRAINT fees_amount_range CHECK (
                min_amount IS NULL
                OR max_amount IS NULL
                OR min_amount <= max_amount
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE fees ADD CONSTRAINT fees_counterpart_needs_network CHECK (
                counterpart_network_id IS NULL
                OR (
                    network_id IS NOT NULL
                    AND counterpart_network_id <> network_id
                )
            )
        SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE fees ADD CONSTRAINT fees_ticket_has_no_counterpart CHECK (
                transaction_type <> 'ticket_purchase'
                OR counterpart_network_id IS NULL
            )
        SQL);
        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX fees_unique_active_rule
            ON fees (
                transaction_type,
                part,
                COALESCE(network_id, 0),
                COALESCE(counterpart_network_id, 0),
                COALESCE(min_amount, 0)
            )
            WHERE is_active = true
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fees');
    }
};
