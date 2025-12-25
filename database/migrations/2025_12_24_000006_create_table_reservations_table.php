<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained()->onDelete('cascade');
            $table->string('session_token');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('cascade');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index('session_token');
            $table->index('expires_at');
            $table->unique('table_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_reservations');
    }
};
