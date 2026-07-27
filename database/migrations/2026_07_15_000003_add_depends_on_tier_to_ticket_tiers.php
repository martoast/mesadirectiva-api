<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_tiers', function (Blueprint $table) {
            // Sequential installments: this tier is only purchasable for a
            // student key that already completed the tier it depends on.
            $table->foreignId('depends_on_tier_id')
                ->nullable()
                ->after('sort_order')
                ->constrained('ticket_tiers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_tiers', function (Blueprint $table) {
            $table->dropForeign(['depends_on_tier_id']);
            $table->dropColumn('depends_on_tier_id');
        });
    }
};
