<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Per-event checkout field configuration:
            // { collect_student_fields, require_student_fields, require_attendee_note }
            $table->json('checkout_settings')->nullable()->after('email_settings');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // Clave del alumno — also the matching key for dependent tier payments
            $table->string('student_key', 50)->nullable()->after('attendee_note');
            $table->index('student_key');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('checkout_settings');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['student_key']);
            $table->dropColumn('student_key');
        });
    }
};
