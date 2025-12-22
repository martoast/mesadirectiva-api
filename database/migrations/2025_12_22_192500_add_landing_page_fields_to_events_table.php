<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Hero Section Enhancement
            $table->string('hero_cta_text')->nullable()->after('hero_image');

            // About Section Enhancement
            $table->string('about_title')->nullable()->after('hero_cta_text');
            $table->text('about_content')->nullable()->after('about_title'); // HTML allowed
            $table->string('about_image')->nullable()->after('about_content');
            $table->enum('about_image_position', ['left', 'right'])->default('right')->after('about_image');

            // Highlights Section (feature cards)
            // [{icon: "star", title: "VIP Access", description: "..."}]
            $table->json('highlights')->nullable()->after('about_image_position');

            // Schedule/Agenda Section
            // [{time: "18:00", title: "Doors Open", description: "..."}]
            $table->json('schedule')->nullable()->after('highlights');

            // Gallery Section
            // ["https://...", "https://..."]
            $table->json('gallery_images')->nullable()->after('schedule');

            // FAQ Section
            // [{question: "...", answer: "..."}]
            $table->json('faq_items')->nullable()->after('gallery_images');

            // Venue Details
            $table->string('venue_name')->nullable()->after('faq_items');
            $table->text('venue_address')->nullable()->after('venue_name');
            $table->string('venue_map_url')->nullable()->after('venue_address');

            // Contact Info
            $table->string('contact_email')->nullable()->after('venue_map_url');
            $table->string('contact_phone')->nullable()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'hero_cta_text',
                'about_title',
                'about_content',
                'about_image',
                'about_image_position',
                'highlights',
                'schedule',
                'gallery_images',
                'faq_items',
                'venue_name',
                'venue_address',
                'venue_map_url',
                'contact_email',
                'contact_phone',
            ]);
        });
    }
};
