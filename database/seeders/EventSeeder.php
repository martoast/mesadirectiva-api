<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'super_admin')->first();
        $generalGroup = Group::where('slug', 'general')->first();
        $primariaGroup = Group::where('slug', 'primaria')->first();

        if (!$admin || !$generalGroup) {
            $this->command->warn('Please run AdminUserSeeder and GroupSeeder first.');
            return;
        }

        // ========================================
        // EVENT 1: General Admission with Ticket Tiers
        // ========================================
        $gaEvent = Event::create([
            'group_id' => $generalGroup->id,
            'name' => 'Spring Concert 2025',
            'description' => '<p>Join us for an amazing night of music featuring local bands and artists.</p><p>From rock to jazz, there\'s something for everyone. Food trucks, drinks, and good vibes included!</p>',
            'image' => $this->copyPlaceholderImage('spring-concert-2025', 'hero'),

            // Date/Time
            'starts_at' => now()->addMonths(1)->setTime(19, 0),
            'ends_at' => now()->addMonths(1)->setTime(23, 0),
            'timezone' => 'America/Los_Angeles',

            // Location
            'location_type' => 'venue',
            'location' => [
                'name' => 'City Amphitheater',
                'address' => '456 Park Avenue',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'country' => 'USA',
                'postal_code' => '90001',
                'map_url' => 'https://maps.google.com/?q=City+Amphitheater',
            ],

            // Media Gallery
            'media' => [
                'images' => $this->createGalleryImages('spring-concert-2025', 3),
                'videos' => [
                    ['type' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'video_id' => 'dQw4w9WgXcQ'],
                ],
            ],

            // Event Type
            'status' => 'live',
            'seating_type' => 'general_admission',
            'is_private' => false,
            'show_remaining' => true,

            // Organizer
            'organizer_name' => 'City Music Foundation',
            'organizer_description' => 'Bringing live music to the community since 2010. We organize concerts, festivals, and music education programs.',

            // FAQ
            'faq_items' => [
                ['question' => 'Can I bring my own food?', 'answer' => 'Outside food is not permitted. Food trucks will be available on-site.'],
                ['question' => 'Is there parking?', 'answer' => 'Free parking available at the venue.'],
                ['question' => 'What if it rains?', 'answer' => 'The event is rain or shine. Bring a poncho if needed!'],
            ],

            'created_by' => $admin->id,
        ]);

        // Add Ticket Tiers with Eventbrite-style sales windows
        $gaEvent->ticketTiers()->createMany([
            [
                'name' => 'Early Bird',
                'description' => 'Limited time pricing - get your tickets early and save!',
                'price' => 35.00,
                'quantity' => 100,
                'quantity_sold' => 0,
                'sales_start' => now(),
                'sales_end' => now()->addWeeks(2),
                'min_per_order' => 1,
                'max_per_order' => 4,
                'show_description' => true,
                'is_hidden' => false,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'General Admission',
                'description' => 'Standard entry with access to all general areas',
                'price' => 50.00,
                'quantity' => 300,
                'quantity_sold' => 0,
                'sales_start' => now()->addWeeks(2),
                'sales_end' => now()->addMonths(1)->subDay(),
                'min_per_order' => 1,
                'max_per_order' => 10,
                'show_description' => true,
                'is_hidden' => false,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'VIP',
                'description' => 'Priority entry, exclusive VIP area, complimentary drink',
                'price' => 100.00,
                'quantity' => 100,
                'quantity_sold' => 0,
                'sales_start' => now(),
                'sales_end' => now()->addMonths(1)->subDay(),
                'min_per_order' => 1,
                'max_per_order' => 6,
                'show_description' => true,
                'is_hidden' => false,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ]);

        // Add extra items
        $gaEvent->items()->createMany([
            [
                'name' => 'Parking Pass',
                'description' => 'Reserved parking spot close to venue entrance',
                'price' => 20.00,
                'max_quantity' => 50,
                'quantity_sold' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Event T-Shirt',
                'description' => 'Official Spring Concert 2025 commemorative t-shirt',
                'price' => 25.00,
                'max_quantity' => 200,
                'quantity_sold' => 0,
                'is_active' => true,
            ],
        ]);

        $this->command->info("Created General Admission event: {$gaEvent->name} (slug: {$gaEvent->slug})");

        // ========================================
        // EVENT 2: Seated Event (Gala Dinner)
        // ========================================
        $seatedEvent = Event::create([
            'group_id' => $primariaGroup?->id ?? $generalGroup->id,
            'name' => 'Annual School Gala 2025',
            'description' => '<p>The Annual School Gala is our <strong>premier fundraising event</strong> of the year. All proceeds go directly to supporting student scholarships and school improvements.</p><p>Join us for an unforgettable evening of fine dining, live entertainment, and community celebration. This year\'s theme celebrates 25 years of excellence in education.</p><ul><li>Gourmet dinner with wine pairings</li><li>Live music and dancing</li><li>Silent auction with exclusive items</li><li>Special recognition of outstanding students</li></ul>',
            'image' => $this->copyPlaceholderImage('annual-school-gala-2025', 'hero'),

            // Date/Time
            'starts_at' => now()->addMonths(2)->setTime(18, 0),
            'ends_at' => now()->addMonths(2)->setTime(23, 59),
            'timezone' => 'America/Los_Angeles',

            // Location
            'location_type' => 'venue',
            'location' => [
                'name' => 'Grand Ballroom at Hotel Marriott',
                'address' => '123 Main Street',
                'city' => 'Downtown City',
                'state' => 'CA',
                'country' => 'USA',
                'postal_code' => '12345',
                'map_url' => 'https://maps.google.com/?q=Hotel+Marriott+Downtown',
            ],

            // Media Gallery
            'media' => [
                'images' => $this->createGalleryImages('annual-school-gala-2025', 4),
                'videos' => [],
            ],

            // Event Type
            'status' => 'live',
            'seating_type' => 'seated',
            'reservation_minutes' => 15,
            'is_private' => false,
            'show_remaining' => true,

            // Organizer
            'organizer_name' => 'School Foundation',
            'organizer_description' => 'Supporting educational excellence for over 25 years. Our foundation raises funds for scholarships, facilities, and educational programs.',

            // FAQ
            'faq_items' => [
                ['question' => 'What is the dress code?', 'answer' => 'Black tie optional. We encourage guests to dress elegantly for this special evening.'],
                ['question' => 'Is parking available?', 'answer' => 'Complimentary valet parking is included with your ticket. Self-parking is also available in the hotel garage.'],
                ['question' => 'Can I purchase individual seats?', 'answer' => 'Yes! You can purchase individual seats or reserve an entire table for your group.'],
                ['question' => 'Are dietary restrictions accommodated?', 'answer' => 'Yes! Please indicate any dietary restrictions when purchasing your tickets, and our chefs will prepare an alternative meal.'],
            ],

            'created_by' => $admin->id,
        ]);

        // Add Tables for Seated Event
        // VIP Tables (sold as whole)
        for ($i = 1; $i <= 5; $i++) {
            $table = $seatedEvent->tables()->create([
                'name' => "VIP Table {$i}",
                'capacity' => 8,
                'price' => 2000.00, // $250 per person for 8 people
                'sell_as_whole' => true,
                'status' => 'available',
                'position_x' => 100 + (($i - 1) % 3) * 150,
                'position_y' => 100 + floor(($i - 1) / 3) * 150,
                'is_active' => true,
            ]);
        }

        // Regular Tables (sold by individual seats)
        for ($i = 1; $i <= 10; $i++) {
            $table = $seatedEvent->tables()->create([
                'name' => "Table {$i}",
                'capacity' => 6,
                'price' => 900.00, // Price if sold as whole (not used since sell_as_whole = false)
                'sell_as_whole' => false,
                'status' => 'available',
                'position_x' => 100 + (($i - 1) % 5) * 120,
                'position_y' => 400 + floor(($i - 1) / 5) * 120,
                'is_active' => true,
            ]);

            // Create seats for this table
            $seatLabels = ['A', 'B', 'C', 'D', 'E', 'F'];
            foreach ($seatLabels as $index => $label) {
                $table->seats()->create([
                    'label' => "{$label}" . ($i),
                    'price' => 150.00,
                    'status' => 'available',
                    'position_x' => 10 + ($index % 3) * 30,
                    'position_y' => 10 + floor($index / 3) * 30,
                    'is_active' => true,
                ]);
            }
        }

        // Add extra items for Gala
        $seatedEvent->items()->createMany([
            [
                'name' => 'Valet Parking',
                'description' => 'Complimentary valet parking for the evening',
                'price' => 25.00,
                'max_quantity' => 50,
                'quantity_sold' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Commemorative Program',
                'description' => 'Beautifully printed event program with photos',
                'price' => 15.00,
                'max_quantity' => 100,
                'quantity_sold' => 0,
                'is_active' => true,
            ],
            [
                'name' => 'Wine Pairing Upgrade',
                'description' => 'Premium wine selection with each course',
                'price' => 50.00,
                'max_quantity' => 100,
                'quantity_sold' => 0,
                'is_active' => true,
            ],
        ]);

        $tableCount = $seatedEvent->tables()->count();
        $seatCount = $seatedEvent->tables()->withCount('seats')->get()->sum('seats_count');
        $this->command->info("Created Seated event: {$seatedEvent->name} (slug: {$seatedEvent->slug})");
        $this->command->info("  - {$tableCount} tables created (5 VIP whole-table, 10 with individual seats)");
        $this->command->info("  - {$seatCount} individual seats created");

        // ========================================
        // EVENT 3: Online Event (Webinar)
        // ========================================
        $onlineEvent = Event::create([
            'group_id' => $generalGroup->id,
            'name' => 'Digital Marketing Workshop 2025',
            'description' => '<p>Join us for an interactive online workshop where industry experts share the latest digital marketing strategies.</p><p>Learn about SEO, social media marketing, content strategy, and more from the comfort of your home.</p>',
            'image' => $this->copyPlaceholderImage('digital-marketing-workshop-2025', 'hero'),

            // Date/Time
            'starts_at' => now()->addWeeks(3)->setTime(10, 0),
            'ends_at' => now()->addWeeks(3)->setTime(16, 0),
            'timezone' => 'America/Los_Angeles',

            // Location - Online Event
            'location_type' => 'online',
            'location' => [
                'platform' => 'Zoom',
                'url' => 'https://zoom.us/j/123456789',
                'instructions' => 'The Zoom link will be sent to registered attendees 1 hour before the event. Make sure to have Zoom installed on your device.',
            ],

            // Media Gallery
            'media' => [
                'images' => $this->createGalleryImages('digital-marketing-workshop-2025', 2),
                'videos' => [],
            ],

            // Event Type
            'status' => 'live',
            'seating_type' => 'general_admission',
            'is_private' => false,
            'show_remaining' => true,

            // Organizer
            'organizer_name' => 'Digital Skills Academy',
            'organizer_description' => 'Providing top-quality online education in digital marketing, web development, and business strategy since 2018.',

            // FAQ
            'faq_items' => [
                ['question' => 'Do I need any special software?', 'answer' => 'You\'ll need Zoom installed on your computer or mobile device. A stable internet connection is recommended.'],
                ['question' => 'Will there be recordings available?', 'answer' => 'Yes! All registered attendees will receive access to the workshop recordings within 48 hours.'],
                ['question' => 'Can I ask questions during the workshop?', 'answer' => 'There will be Q&A sessions after each module where you can interact with the instructors.'],
            ],

            'created_by' => $admin->id,
        ]);

        // Add Ticket Tiers for Online Event
        $onlineEvent->ticketTiers()->createMany([
            [
                'name' => 'Free Registration',
                'description' => 'Basic access to live workshop sessions',
                'price' => 0.00,
                'quantity' => 500,
                'quantity_sold' => 0,
                'sales_start' => now(),
                'sales_end' => now()->addWeeks(3)->subHour(),
                'min_per_order' => 1,
                'max_per_order' => 1,
                'show_description' => true,
                'is_hidden' => false,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Premium Access',
                'description' => 'Full workshop access plus recordings, downloadable materials, and certificate',
                'price' => 49.00,
                'quantity' => 200,
                'quantity_sold' => 0,
                'sales_start' => now(),
                'sales_end' => now()->addWeeks(3)->subHour(),
                'min_per_order' => 1,
                'max_per_order' => 5,
                'show_description' => true,
                'is_hidden' => false,
                'sort_order' => 2,
                'is_active' => true,
            ],
        ]);

        $this->command->info("Created Online event: {$onlineEvent->name} (slug: {$onlineEvent->slug})");

        // ========================================
        // EVENT 4: Draft General Admission Event
        // ========================================
        $draftEvent = Event::create([
            'group_id' => $generalGroup->id,
            'name' => 'Summer Festival 2025',
            'description' => '<p>Coming soon - our biggest outdoor festival yet!</p><p>More details will be announced soon. Save the date!</p>',
            'image' => $this->copyPlaceholderImage('summer-festival-2025', 'hero'),

            // Date/Time
            'starts_at' => now()->addMonths(4)->setTime(12, 0),
            'ends_at' => now()->addMonths(4)->setTime(23, 0),
            'timezone' => 'America/Los_Angeles',

            // Location
            'location_type' => 'venue',
            'location' => [
                'name' => 'Central Park',
                'address' => 'Central Park West',
                'city' => 'Los Angeles',
                'state' => 'CA',
            ],

            // Media Gallery
            'media' => [
                'images' => $this->createGalleryImages('summer-festival-2025', 2),
                'videos' => [],
            ],

            // Event Type
            'status' => 'draft', // Not yet published
            'seating_type' => 'general_admission',
            'is_private' => false,
            'show_remaining' => true,

            // Organizer
            'organizer_name' => 'City Events Committee',
            'organizer_description' => 'Organizing community events and festivals since 2005.',

            'created_by' => $admin->id,
        ]);

        // Add placeholder tiers for draft event (sales dates in the future)
        $draftEvent->ticketTiers()->createMany([
            [
                'name' => 'Super Early Bird',
                'description' => 'Limited super early bird pricing - first 100 tickets only!',
                'price' => 50.00,
                'quantity' => 100,
                'quantity_sold' => 0,
                'sales_start' => now()->addMonths(2),
                'sales_end' => now()->addMonths(3),
                'min_per_order' => 1,
                'max_per_order' => 4,
                'show_description' => true,
                'is_hidden' => false,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Early Bird',
                'description' => 'Early bird pricing',
                'price' => 65.00,
                'quantity' => 200,
                'quantity_sold' => 0,
                'sales_start' => now()->addMonths(3),
                'sales_end' => now()->addMonths(3)->addWeeks(2),
                'min_per_order' => 1,
                'max_per_order' => 6,
                'show_description' => false,
                'is_hidden' => false,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Regular',
                'description' => 'Standard festival admission',
                'price' => 75.00,
                'quantity' => 500,
                'quantity_sold' => 0,
                'sales_start' => now()->addMonths(3)->addWeeks(2),
                'sales_end' => now()->addMonths(4)->subDay(),
                'min_per_order' => 1,
                'max_per_order' => 10,
                'show_description' => false,
                'is_hidden' => false,
                'sort_order' => 3,
                'is_active' => true,
            ],
            [
                'name' => 'VIP All-Access',
                'description' => 'VIP areas, backstage access, premium amenities, free food and drinks',
                'price' => 150.00,
                'quantity' => 100,
                'quantity_sold' => 0,
                'sales_start' => now()->addMonths(2),
                'sales_end' => now()->addMonths(4)->subDay(),
                'min_per_order' => 1,
                'max_per_order' => 4,
                'show_description' => true,
                'is_hidden' => false,
                'sort_order' => 4,
                'is_active' => true,
            ],
        ]);

        $this->command->info("Created Draft event: {$draftEvent->name} (slug: {$draftEvent->slug})");

        $this->command->newLine();
        $this->command->info('Event seeding complete!');
        $this->command->table(
            ['Event', 'Type', 'Location', 'Status', 'Slug'],
            [
                [$gaEvent->name, 'General Admission', 'Venue', 'live', $gaEvent->slug],
                [$seatedEvent->name, 'Seated', 'Venue', 'live', $seatedEvent->slug],
                [$onlineEvent->name, 'General Admission', 'Online', 'live', $onlineEvent->slug],
                [$draftEvent->name, 'General Admission', 'Venue', 'draft', $draftEvent->slug],
            ]
        );
    }

    /**
     * Copy the placeholder image to storage for an event's hero image
     */
    private function copyPlaceholderImage(string $eventSlug, string $type = 'hero'): string
    {
        $placeholderPath = public_path('placeholder.png');

        if (!file_exists($placeholderPath)) {
            $this->command->warn("Placeholder image not found at {$placeholderPath}");
            return '';
        }

        $filename = sprintf(
            'events/%s/%s-%s.png',
            $eventSlug,
            $type,
            Str::random(8)
        );

        $disk = config('filesystems.media_disk', 'public');
        Storage::disk($disk)->put($filename, file_get_contents($placeholderPath), 'public');

        $this->command->line("  -> Created image: {$filename}");

        return $filename;
    }

    /**
     * Create multiple gallery images for an event
     */
    private function createGalleryImages(string $eventSlug, int $count = 3): array
    {
        $images = [];
        $placeholderPath = public_path('placeholder.png');

        if (!file_exists($placeholderPath)) {
            $this->command->warn("Placeholder image not found at {$placeholderPath}");
            return $images;
        }

        $disk = config('filesystems.media_disk', 'public');

        for ($i = 1; $i <= $count; $i++) {
            $filename = sprintf(
                'events/%s/gallery/image-%d-%s.png',
                $eventSlug,
                $i,
                Str::random(8)
            );

            Storage::disk($disk)->put($filename, file_get_contents($placeholderPath), 'public');

            $images[] = [
                'type' => 'upload',
                'path' => $filename,
                'url' => Storage::disk($disk)->url($filename),
            ];
        }

        $this->command->line("  -> Created {$count} gallery images");

        return $images;
    }
}
