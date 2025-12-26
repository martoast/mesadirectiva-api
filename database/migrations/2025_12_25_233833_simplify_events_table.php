<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Add new datetime fields
            $table->dateTime('starts_at')->nullable()->after('description');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->string('timezone')->default('America/Los_Angeles')->after('ends_at');

            // Add location type and JSON location
            $table->enum('location_type', ['venue', 'online'])->default('venue')->after('timezone');
            $table->json('location_data')->nullable()->after('location_type');

            // Add simplified image field
            $table->string('image')->nullable()->after('location_data');

            // Add media gallery (images + videos)
            $table->json('media')->nullable()->after('image');

            // Add visibility settings
            $table->boolean('is_private')->default(false)->after('reservation_minutes');
            $table->boolean('show_remaining')->default(true)->after('is_private');

            // Add organizer fields
            $table->string('organizer_name')->nullable()->after('show_remaining');
            $table->text('organizer_description')->nullable()->after('organizer_name');
        });

        // Migrate existing data
        DB::table('events')->orderBy('id')->chunk(100, function ($events) {
            foreach ($events as $event) {
                $updates = [];

                // Migrate date + time to starts_at
                if ($event->date && $event->time) {
                    $startsAt = $event->date . ' ' . $event->time;
                    $updates['starts_at'] = $startsAt;
                    // Default ends_at to 3 hours after starts_at
                    $updates['ends_at'] = date('Y-m-d H:i:s', strtotime($startsAt . ' +3 hours'));
                }

                // Migrate hero_image to image
                if ($event->hero_image) {
                    $updates['image'] = $event->hero_image;
                }

                // Migrate location string to location_data JSON
                if ($event->location) {
                    $locationData = [
                        'name' => $event->venue_name ?? $event->location,
                        'address' => $event->venue_address ?? $event->location,
                    ];
                    if ($event->venue_map_url) {
                        $locationData['map_url'] = $event->venue_map_url;
                    }
                    $updates['location_data'] = json_encode($locationData);
                }

                // Migrate gallery_images to media
                if ($event->gallery_images) {
                    $galleryImages = json_decode($event->gallery_images, true) ?? [];
                    $media = [
                        'images' => array_map(fn($url) => ['type' => 'url', 'url' => $url], $galleryImages),
                        'videos' => [],
                    ];
                    $updates['media'] = json_encode($media);
                }

                if (!empty($updates)) {
                    DB::table('events')->where('id', $event->id)->update($updates);
                }
            }
        });

        // Make starts_at and ends_at required after data migration
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable(false)->change();
            $table->dateTime('ends_at')->nullable(false)->change();
        });

        // Drop old columns
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['status', 'registration_open']);
            $table->dropIndex(['date']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                // Old date/time fields
                'date',
                'time',
                'location',

                // Old pricing/inventory (now on ticket tiers)
                'price',
                'max_tickets',
                'tickets_sold',
                'registration_open',
                'registration_deadline',

                // Old hero section
                'hero_title',
                'hero_subtitle',
                'hero_image',
                'hero_cta_text',

                // Old about section
                'about',
                'about_title',
                'about_content',
                'about_image',
                'about_image_position',

                // Old schedule/highlights
                'highlights',
                'schedule',

                // Old gallery (migrated to media)
                'gallery_images',

                // Old venue fields (migrated to location_data)
                'venue_name',
                'venue_address',
                'venue_map_url',

                // Old contact fields (use organizer now)
                'contact_email',
                'contact_phone',
            ]);
        });

        // Rename location_data to location
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('location_data', 'location');
        });

        // Add new indexes
        Schema::table('events', function (Blueprint $table) {
            $table->index(['status', 'is_private']);
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        // Rename location back
        Schema::table('events', function (Blueprint $table) {
            $table->renameColumn('location', 'location_data');
        });

        // Drop new indexes
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['status', 'is_private']);
            $table->dropIndex(['starts_at']);
        });

        // Add back old columns
        Schema::table('events', function (Blueprint $table) {
            $table->date('date')->nullable()->after('description');
            $table->time('time')->nullable()->after('date');
            $table->string('location')->nullable()->after('time');
            $table->decimal('price', 10, 2)->nullable()->after('location');
            $table->unsignedInteger('max_tickets')->nullable()->after('price');
            $table->unsignedInteger('tickets_sold')->default(0)->after('max_tickets');
            $table->boolean('registration_open')->default(true)->after('tickets_sold');
            $table->dateTime('registration_deadline')->nullable()->after('registration_open');
            $table->string('hero_title')->nullable()->after('status');
            $table->string('hero_subtitle', 500)->nullable()->after('hero_title');
            $table->string('hero_image')->nullable()->after('hero_subtitle');
            $table->string('hero_cta_text')->nullable()->after('hero_image');
            $table->text('about')->nullable()->after('hero_cta_text');
            $table->string('about_title')->nullable()->after('about');
            $table->text('about_content')->nullable()->after('about_title');
            $table->string('about_image')->nullable()->after('about_content');
            $table->enum('about_image_position', ['left', 'right'])->default('right')->after('about_image');
            $table->json('highlights')->nullable()->after('about_image_position');
            $table->json('schedule')->nullable()->after('highlights');
            $table->json('gallery_images')->nullable()->after('schedule');
            $table->string('venue_name')->nullable()->after('faq_items');
            $table->text('venue_address')->nullable()->after('venue_name');
            $table->string('venue_map_url')->nullable()->after('venue_address');
            $table->string('contact_email')->nullable()->after('venue_map_url');
            $table->string('contact_phone')->nullable()->after('contact_email');

            $table->index(['status', 'registration_open']);
            $table->index('date');
        });

        // Drop new columns
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'starts_at',
                'ends_at',
                'timezone',
                'location_type',
                'location_data',
                'image',
                'media',
                'is_private',
                'show_remaining',
                'organizer_name',
                'organizer_description',
            ]);
        });
    }
};
