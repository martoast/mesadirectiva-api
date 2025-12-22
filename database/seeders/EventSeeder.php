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
            'description' => 'Join us for an unforgettable evening celebrating our school community. Enjoy fine dining, live entertainment, and silent auctions.',
            'date' => now()->addMonths(2)->format('Y-m-d'),
            'time' => '18:00',
            'location' => 'Grand Ballroom, Hotel Marriott',
            'price' => 150.00,
            'max_tickets' => 200,
            'tickets_sold' => 0,
            'status' => 'draft',
            'registration_open' => true,
            'registration_deadline' => now()->addMonths(2)->subDays(3),
            'hero_title' => 'Annual School Gala 2025',
            'hero_subtitle' => 'An Evening of Elegance and Community',
            'hero_image' => 'https://upload.wikimedia.org/wikipedia/commons/thumb/3/3f/Placeholder_view_vector.svg/330px-Placeholder_view_vector.svg.png',
            'about' => "## About This Event\n\nThe Annual School Gala is our premier fundraising event of the year. All proceeds go directly to supporting student scholarships and school improvements.\n\n### What to Expect\n\n- Gourmet dinner with wine pairings\n- Live music and dancing\n- Silent auction with exclusive items\n- Special recognition of outstanding students and faculty\n\n### Dress Code\n\nBlack tie optional. Come dressed to impress!\n\n### Important Notes\n\n- Doors open at 6:00 PM\n- Dinner served at 7:00 PM\n- Valet parking available",
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
