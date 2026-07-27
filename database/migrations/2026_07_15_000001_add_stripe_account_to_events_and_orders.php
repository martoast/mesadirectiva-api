<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Which Stripe account collects this event's money: cafeteria | rifa | eventos
            $table->string('stripe_account', 20)->default('cafeteria')->after('stripe_price_id');
            $table->index('stripe_account');
        });

        Schema::table('orders', function (Blueprint $table) {
            // Snapshot of the account the payment ran through (for refunds/reports)
            $table->string('stripe_account', 20)->nullable()->after('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['stripe_account']);
            $table->dropColumn('stripe_account');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('stripe_account');
        });
    }
};
