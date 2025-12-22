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
        $category = Category::where('slug', 'general')->first();

        if (!$admin || !$category) {
            $this->command->warn('Please run AdminUserSeeder and CategorySeeder first.');
            return;
        }

        $event = Event::create([
            'category_id' => $category->id,
            'name' => 'Annual School Gala 2025',
            'description' => 'Join us for an unforgettable evening celebrating our school community.',
            'date' => now()->addMonths(2)->format('Y-m-d'),
            'time' => '18:00',
            'location' => 'Grand Ballroom, Hotel Marriott',
            'price' => 150.00,
            'max_tickets' => 200,
            'tickets_sold' => 0,
            'status' => 'live',
            'registration_open' => true,
            'registration_deadline' => now()->addMonths(2)->subDays(3),

            // Hero Section
            'hero_title' => 'Annual School Gala 2025',
            'hero_subtitle' => 'An Evening of Elegance and Community',
            'hero_image' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1920&q=80',
            'hero_cta_text' => 'Get Your Tickets',

            // About Section
            'about' => 'The Annual School Gala is our premier fundraising event of the year.',
            'about_title' => 'A Night to Remember',
            'about_content' => '<p>The Annual School Gala is our <strong>premier fundraising event</strong> of the year. All proceeds go directly to supporting student scholarships and school improvements.</p><p>Join us for an unforgettable evening of fine dining, live entertainment, and community celebration. This year\'s theme celebrates 25 years of excellence in education.</p><ul><li>Gourmet dinner with wine pairings</li><li>Live music and dancing</li><li>Silent auction with exclusive items</li><li>Special recognition of outstanding students</li></ul>',
            'about_image' => 'https://images.unsplash.com/photo-1511795409834-ef04bbd61622?w=800&q=80',
            'about_image_position' => 'right',

            // Highlights Section
            'highlights' => [
                [
                    'icon' => 'utensils',
                    'title' => 'Gourmet Dining',
                    'description' => 'Five-course meal prepared by award-winning chefs',
                ],
                [
                    'icon' => 'music',
                    'title' => 'Live Entertainment',
                    'description' => 'Jazz quartet and DJ for dancing all night',
                ],
                [
                    'icon' => 'gift',
                    'title' => 'Silent Auction',
                    'description' => 'Exclusive items and experiences up for bid',
                ],
                [
                    'icon' => 'heart',
                    'title' => 'For a Good Cause',
                    'description' => '100% of proceeds support student scholarships',
                ],
            ],

            // Schedule Section
            'schedule' => [
                [
                    'time' => '6:00 PM',
                    'title' => 'Doors Open',
                    'description' => 'Welcome reception with cocktails and hors d\'oeuvres',
                ],
                [
                    'time' => '7:00 PM',
                    'title' => 'Dinner Served',
                    'description' => 'Five-course gourmet dinner with wine pairings',
                ],
                [
                    'time' => '8:30 PM',
                    'title' => 'Awards Ceremony',
                    'description' => 'Recognition of outstanding students and faculty',
                ],
                [
                    'time' => '9:00 PM',
                    'title' => 'Live Auction',
                    'description' => 'Bid on exclusive experiences and items',
                ],
                [
                    'time' => '10:00 PM',
                    'title' => 'Dancing',
                    'description' => 'Live band and DJ until midnight',
                ],
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
                [
                    'question' => 'What is the dress code?',
                    'answer' => 'Black tie optional. We encourage guests to dress elegantly for this special evening.',
                ],
                [
                    'question' => 'Is parking available?',
                    'answer' => 'Complimentary valet parking is included with your ticket. Self-parking is also available in the hotel garage.',
                ],
                [
                    'question' => 'Can I purchase tickets at the door?',
                    'answer' => 'Tickets must be purchased in advance. We cannot guarantee availability at the door.',
                ],
                [
                    'question' => 'Are dietary restrictions accommodated?',
                    'answer' => 'Yes! Please indicate any dietary restrictions when purchasing your tickets, and our chefs will prepare an alternative meal.',
                ],
            ],

            // Venue & Contact
            'venue_name' => 'Grand Ballroom at Hotel Marriott',
            'venue_address' => '123 Main Street, Downtown City, ST 12345',
            'venue_map_url' => 'https://maps.google.com/?q=Hotel+Marriott+Downtown',
            'contact_email' => 'gala@school.edu',
            'contact_phone' => '+1 (555) 123-4567',

            'created_by' => $admin->id,
        ]);

        // Add some extra items
        $event->items()->createMany([
            [
                'name' => 'VIP Table Upgrade',
                'description' => 'Premium front-row seating with complimentary champagne',
                'price' => 75.00,
                'max_quantity' => 20,
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
        ]);

        $this->command->info("Created event: {$event->name} (slug: {$event->slug})");
    }
}
