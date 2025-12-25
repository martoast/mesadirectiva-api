<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('ticket_tier_id')
                ->nullable()
                ->after('item_id')
                ->constrained('ticket_tiers')
                ->onDelete('set null');
            $table->foreignId('seat_id')
                ->nullable()
                ->after('ticket_tier_id')
                ->constrained('seats')
                ->onDelete('set null');
            $table->foreignId('table_id')
                ->nullable()
                ->after('seat_id')
                ->constrained('tables')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['ticket_tier_id']);
            $table->dropForeign(['seat_id']);
            $table->dropForeign(['table_id']);
            $table->dropColumn(['ticket_tier_id', 'seat_id', 'table_id']);
        });
    }
};
