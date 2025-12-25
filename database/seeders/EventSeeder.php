<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'super_admin')->first();
        $generalCategory = Category::where('slug', 'general')->first();
        $primariaCategory = Category::where('slug', 'primaria')->first();

        if (!$admin || !$generalCategory) {
            $this->command->warn('Please run AdminUserSeeder and CategorySeeder first.');
            return;
        }

        // ========================================
        // EVENT 1: General Admission with Ticket Tiers
        // ========================================
        $gaEvent = Event::create([
            'category_id' => $generalCategory->id,
            'name' => 'Spring Concert 2025',
            'description' => 'Join us for an amazing night of music featuring local bands and artists.',
            'date' => now()->addMonths(1)->format('Y-m-d'),
            'time' => '19:00',
            'location' => 'City Amphitheater',
            'price' => 50.00, // Legacy fallback price
            'max_tickets' => 500,
            'tickets_sold' => 0,
            'status' => 'live',
            'seating_type' => 'general_admission',
            'registration_open' => true,
            'registration_deadline' => now()->addMonths(1)->subDays(1),

            // Hero Section
            'hero_title' => 'Spring Concert 2025',
            'hero_subtitle' => 'A Night of Music Under the Stars',
            'hero_image' => 'https://images.unsplash.com/photo-1470229722913-7c0e2dbbafd3?w=1920&q=80',
            'hero_cta_text' => 'Get Tickets Now',

            // About Section
            'about' => 'Experience live music like never before at our annual Spring Concert.',
            'about_title' => 'Music for Everyone',
            'about_content' => '<p>The Spring Concert is our <strong>annual celebration of music</strong> featuring the best local talent. From rock to jazz, there\'s something for everyone.</p><p>Food trucks, drinks, and good vibes included!</p>',
            'about_image' => 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?w=800&q=80',
            'about_image_position' => 'left',

            // Highlights
            'highlights' => [
                ['icon' => 'music', 'title' => '5 Live Bands', 'description' => 'Local and regional artists performing live'],
                ['icon' => 'utensils', 'title' => 'Food Trucks', 'description' => 'Variety of food options available'],
                ['icon' => 'beer', 'title' => 'Drinks', 'description' => 'Full bar with local craft beers'],
                ['icon' => 'star', 'title' => 'VIP Experience', 'description' => 'Exclusive areas for VIP ticket holders'],
            ],

            // Schedule
            'schedule' => [
                ['time' => '7:00 PM', 'title' => 'Gates Open', 'description' => 'Food and drinks available'],
                ['time' => '7:30 PM', 'title' => 'Opening Act', 'description' => 'Local band performance'],
                ['time' => '8:30 PM', 'title' => 'Main Acts', 'description' => 'Featured artists take the stage'],
                ['time' => '11:00 PM', 'title' => 'Headliner', 'description' => 'Special guest performance'],
            ],

            'gallery_images' => [
                'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?w=600&q=80',
                'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=600&q=80',
                'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600&q=80',
            ],

            'faq_items' => [
                ['question' => 'Can I bring my own food?', 'answer' => 'Outside food is not permitted. Food trucks will be available on-site.'],
                ['question' => 'Is there parking?', 'answer' => 'Free parking available at the venue.'],
                ['question' => 'What if it rains?', 'answer' => 'The event is rain or shine. Bring a poncho if needed!'],
            ],

            'venue_name' => 'City Amphitheater',
            'venue_address' => '456 Park Avenue, Downtown',
            'venue_map_url' => 'https://maps.google.com/?q=City+Amphitheater',
            'contact_email' => 'concerts@example.com',
            'contact_phone' => '+1 (555) 987-6543',

            'created_by' => $admin->id,
        ]);

        // Add Ticket Tiers for General Admission Event
        $gaEvent->ticketTiers()->createMany([
            [
                'name' => 'General Admission',
                'description' => 'Standard entry with access to all general areas',
                'price' => 50.00,
                'early_bird_price' => 35.00,
                'early_bird_deadline' => now()->addWeeks(2),
                'max_quantity' => 300,
                'quantity_sold' => 0,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'VIP',
                'description' => 'Priority entry, exclusive VIP area, complimentary drink',
                'price' => 100.00,
                'early_bird_price' => 80.00,
                'early_bird_deadline' => now()->addWeeks(2),
                'max_quantity' => 100,
                'quantity_sold' => 0,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Premium',
                'description' => 'All VIP benefits plus meet & greet with artists, premium seating',
                'price' => 200.00,
                'early_bird_price' => null, // No early bird for premium
                'early_bird_deadline' => null,
                'max_quantity' => 50,
                'quantity_sold' => 0,
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
            'category_id' => $primariaCategory?->id ?? $generalCategory->id,
            'name' => 'Annual School Gala 2025',
            'description' => 'Join us for an unforgettable evening celebrating our school community.',
            'date' => now()->addMonths(2)->format('Y-m-d'),
            'time' => '18:00',
            'location' => 'Grand Ballroom, Hotel Marriott',
            'price' => 150.00, // Not used for seated events, but required field
            'max_tickets' => 200,
            'tickets_sold' => 0,
            'status' => 'live',
            'seating_type' => 'seated',
            'reservation_minutes' => 15,
            'registration_open' => true,
            'registration_deadline' => now()->addMonths(2)->subDays(3),

            // Hero Section
            'hero_title' => 'Annual School Gala 2025',
            'hero_subtitle' => 'An Evening of Elegance and Community',
            'hero_image' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1920&q=80',
            'hero_cta_text' => 'Reserve Your Table',

            // About Section
            'about' => 'The Annual School Gala is our premier fundraising event of the year.',
            'about_title' => 'A Night to Remember',
            'about_content' => '<p>The Annual School Gala is our <strong>premier fundraising event</strong> of the year. All proceeds go directly to supporting student scholarships and school improvements.</p><p>Join us for an unforgettable evening of fine dining, live entertainment, and community celebration. This year\'s theme celebrates 25 years of excellence in education.</p><ul><li>Gourmet dinner with wine pairings</li><li>Live music and dancing</li><li>Silent auction with exclusive items</li><li>Special recognition of outstanding students</li></ul>',
            'about_image' => 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=800&q=80',
            'about_image_position' => 'right',

            // Highlights Section
            'highlights' => [
                ['icon' => 'utensils', 'title' => 'Gourmet Dining', 'description' => 'Five-course meal prepared by award-winning chefs'],
                ['icon' => 'music', 'title' => 'Live Entertainment', 'description' => 'Jazz quartet and DJ for dancing all night'],
                ['icon' => 'gift', 'title' => 'Silent Auction', 'description' => 'Exclusive items and experiences up for bid'],
                ['icon' => 'heart', 'title' => 'For a Good Cause', 'description' => '100% of proceeds support student scholarships'],
            ],

            // Schedule Section
            'schedule' => [
                ['time' => '6:00 PM', 'title' => 'Doors Open', 'description' => 'Welcome reception with cocktails and hors d\'oeuvres'],
                ['time' => '7:00 PM', 'title' => 'Dinner Served', 'description' => 'Five-course gourmet dinner with wine pairings'],
                ['time' => '8:30 PM', 'title' => 'Awards Ceremony', 'description' => 'Recognition of outstanding students and faculty'],
                ['time' => '9:00 PM', 'title' => 'Live Auction', 'description' => 'Bid on exclusive experiences and items'],
                ['time' => '10:00 PM', 'title' => 'Dancing', 'description' => 'Live band and DJ until midnight'],
            ],

            // Gallery Section
            'gallery_images' => [
                'https://images.unsplash.com/photo-1519167758481-83f550bb49b3?w=600&q=80',
                'https://images.unsplash.com/photo-1464366400600-7168b8af9bc3?w=600&q=80',
                'https://images.unsplash.com/photo-1505236858219-8359eb29e329?w=600&q=80',
                'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=600&q=80',
            ],

            // FAQ Section
            'faq_items' => [
                ['question' => 'What is the dress code?', 'answer' => 'Black tie optional. We encourage guests to dress elegantly for this special evening.'],
                ['question' => 'Is parking available?', 'answer' => 'Complimentary valet parking is included with your ticket. Self-parking is also available in the hotel garage.'],
                ['question' => 'Can I purchase individual seats?', 'answer' => 'Yes! You can purchase individual seats or reserve an entire table for your group.'],
                ['question' => 'Are dietary restrictions accommodated?', 'answer' => 'Yes! Please indicate any dietary restrictions when purchasing your tickets, and our chefs will prepare an alternative meal.'],
            ],

            // Venue & Contact
            'venue_name' => 'Grand Ballroom at Hotel Marriott',
            'venue_address' => '123 Main Street, Downtown City, ST 12345',
            'venue_map_url' => 'https://maps.google.com/?q=Hotel+Marriott+Downtown',
            'contact_email' => 'gala@school.edu',
            'contact_phone' => '+1 (555) 123-4567',

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
        // EVENT 3: Draft General Admission Event
        // ========================================
        $draftEvent = Event::create([
            'category_id' => $generalCategory->id,
            'name' => 'Summer Festival 2025',
            'description' => 'Coming soon - our biggest outdoor festival yet!',
            'date' => now()->addMonths(4)->format('Y-m-d'),
            'time' => '12:00',
            'location' => 'Central Park',
            'price' => 75.00,
            'max_tickets' => 1000,
            'tickets_sold' => 0,
            'status' => 'draft', // Not yet published
            'seating_type' => 'general_admission',
            'registration_open' => false,
            'registration_deadline' => now()->addMonths(4)->subDays(7),

            'hero_title' => 'Summer Festival 2025',
            'hero_subtitle' => 'The Ultimate Outdoor Experience',
            'hero_image' => 'https://images.unsplash.com/photo-1533174072545-7a4b6ad7a6c3?w=1920&q=80',
            'hero_cta_text' => 'Coming Soon',

            'about' => 'Get ready for an all-day festival featuring music, food, and fun!',
            'about_title' => 'Save the Date',
            'about_content' => '<p>More details coming soon...</p>',

            'created_by' => $admin->id,
        ]);

        // Add placeholder tiers for draft event
        $draftEvent->ticketTiers()->createMany([
            [
                'name' => 'Early Bird',
                'description' => 'Limited early bird pricing',
                'price' => 75.00,
                'early_bird_price' => 50.00,
                'early_bird_deadline' => now()->addMonths(3),
                'max_quantity' => 200,
                'quantity_sold' => 0,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Regular',
                'description' => 'Standard festival admission',
                'price' => 75.00,
                'max_quantity' => 500,
                'quantity_sold' => 0,
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'VIP All-Access',
                'description' => 'VIP areas, backstage access, premium amenities',
                'price' => 150.00,
                'max_quantity' => 100,
                'quantity_sold' => 0,
                'sort_order' => 3,
                'is_active' => true,
            ],
        ]);

        $this->command->info("Created Draft event: {$draftEvent->name} (slug: {$draftEvent->slug})");

        $this->command->newLine();
        $this->command->info('Event seeding complete!');
        $this->command->table(
            ['Event', 'Type', 'Status', 'Slug'],
            [
                [$gaEvent->name, 'General Admission', 'live', $gaEvent->slug],
                [$seatedEvent->name, 'Seated', 'live', $seatedEvent->slug],
                [$draftEvent->name, 'General Admission', 'draft', $draftEvent->slug],
            ]
        );
    }
}
