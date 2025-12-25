<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'super_admin')->first();

        if (!$admin) {
            return;
        }

        $groups = [
            ['name' => 'Primaria', 'color' => '#22c55e'],
            ['name' => 'Secundaria', 'color' => '#3b82f6'],
            ['name' => 'Preparatoria', 'color' => '#8b5cf6'],
            ['name' => 'General', 'color' => '#6b7280'],
        ];

        foreach ($groups as $group) {
            Group::firstOrCreate(
                ['slug' => Str::slug($group['name'])],
                [
                    'name' => $group['name'],
                    'slug' => Str::slug($group['name']),
                    'color' => $group['color'],
                    'created_by' => $admin->id,
                ]
            );
        }
    }
}
