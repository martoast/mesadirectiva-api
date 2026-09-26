<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // What is being sold: 'event' (tickets) or 'product' (tienda merch).
            // Products reuse ticket tiers as variants and always route to Tiendita.
            $table->string('kind', 20)->default('event')->after('slug');
            $table->index('kind');
        });

        // Products have an optional "available until" date, so ends_at may be empty.
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('ends_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('ends_at')->nullable(false)->change();
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
