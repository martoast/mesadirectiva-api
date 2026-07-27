<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: this migration was originally misdated 2025_01_15 (before the
        // table creations) and renamed; production already has the column.
        if (Schema::hasColumn('ticket_tiers', 'currency')) {
            return;
        }

        Schema::table('ticket_tiers', function (Blueprint $table) {
            $table->string('currency', 3)->default('MXN')->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_tiers', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
