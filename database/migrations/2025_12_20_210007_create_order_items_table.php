<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->enum('item_type', ['ticket', 'extra_item']);
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('item_name');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('total_price', 10, 2);
            $table->timestamps();

            $table->index(['order_id', 'item_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
