<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_tiers', function (Blueprint $table) {
            // Rename max_quantity to quantity for consistency
            $table->renameColumn('max_quantity', 'quantity');
        });

        Schema::table('ticket_tiers', function (Blueprint $table) {
            // Add sales window fields (Eventbrite-style)
            $table->dateTime('sales_start')->nullable()->after('quantity_sold');
            $table->dateTime('sales_end')->nullable()->after('sales_start');

            // Add per-order limits
            $table->unsignedInteger('min_per_order')->default(1)->after('sales_end');
            $table->unsignedInteger('max_per_order')->default(10)->after('min_per_order');

            // Add display options
            $table->boolean('show_description')->default(false)->after('max_per_order');
            $table->boolean('is_hidden')->default(false)->after('show_description');

            // Drop old early bird fields (replaced by sales windows)
            $table->dropColumn(['early_bird_price', 'early_bird_deadline']);
        });
    }

    public function down(): void
    {
        Schema::table('ticket_tiers', function (Blueprint $table) {
            // Add back early bird fields
            $table->decimal('early_bird_price', 10, 2)->nullable()->after('price');
            $table->dateTime('early_bird_deadline')->nullable()->after('early_bird_price');

            // Drop new fields
            $table->dropColumn([
                'sales_start',
                'sales_end',
                'min_per_order',
                'max_per_order',
                'show_description',
                'is_hidden',
            ]);
        });

        Schema::table('ticket_tiers', function (Blueprint $table) {
            // Rename quantity back to max_quantity
            $table->renameColumn('quantity', 'max_quantity');
        });
    }
};
