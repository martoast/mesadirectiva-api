<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->foreignId('group_id')->nullable()->constrained()->onDelete('set null');

            // Basic Info
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('date');
            $table->time('time');
            $table->string('location');

            // Pricing & Inventory
            $table->decimal('price', 10, 2);
            $table->unsignedInteger('max_tickets');
            $table->unsignedInteger('tickets_sold')->default(0);

            // Status
            $table->enum('status', ['draft', 'live', 'closed'])->default('draft');
            $table->boolean('registration_open')->default(true);
            $table->dateTime('registration_deadline')->nullable();

            // Page Design
            $table->string('hero_title');
            $table->string('hero_subtitle', 500);
            $table->string('hero_image')->nullable();
            $table->text('about');

            // Stripe
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_price_id')->nullable();

            // Metadata
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['status', 'registration_open']);
            $table->index('group_id');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
