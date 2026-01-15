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
        Schema::table('events', function (Blueprint $table) {
            // Focal point coordinates as percentages (0-100)
            // Default 50,50 = center
            $table->decimal('image_focal_x', 5, 2)->default(50)->after('image');
            $table->decimal('image_focal_y', 5, 2)->default(50)->after('image_focal_x');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['image_focal_x', 'image_focal_y']);
        });
    }
};
