<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 20)->unique();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');

            // Customer Info
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone')->nullable();

            // Status & Payment
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('pending');
            $table->decimal('subtotal', 10, 2);
            $table->decimal('total', 10, 2);

            // Stripe
            $table->string('stripe_checkout_session_id')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();

            // Timestamps
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('order_number');
            $table->index(['event_id', 'status']);
            $table->index('customer_email');
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
