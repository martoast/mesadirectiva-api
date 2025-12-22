<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('permission', ['view', 'edit', 'manage'])->default('view');
            $table->timestamps();

            $table->unique(['category_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_user');
    }
};
