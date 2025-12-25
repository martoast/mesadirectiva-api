<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->enum('seating_type', ['general_admission', 'seated'])
                ->default('general_admission')
                ->after('status');
            $table->unsignedInteger('reservation_minutes')
                ->default(15)
                ->after('seating_type');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['seating_type', 'reservation_minutes']);
        });
    }
};
