<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Guarded: renamed from misdated 2025_01_15; production already has this.
        if (Schema::hasColumn('orders', 'customer_company')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->string('customer_company')->nullable()->after('customer_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('customer_company');
        });
    }
};
