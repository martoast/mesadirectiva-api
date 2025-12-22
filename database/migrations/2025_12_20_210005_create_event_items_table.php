<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_items');
    }
};
