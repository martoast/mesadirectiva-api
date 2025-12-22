<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'super_admin')->first();

        if (!$admin) {
            return;
        }

        $categories = [
            ['name' => 'Primaria', 'color' => '#22c55e'],
            ['name' => 'Secundaria', 'color' => '#3b82f6'],
            ['name' => 'Preparatoria', 'color' => '#8b5cf6'],
            ['name' => 'General', 'color' => '#6b7280'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'slug' => Str::slug($category['name']),
                    'color' => $category['color'],
                    'created_by' => $admin->id,
                ]
            );
        }
    }
}
