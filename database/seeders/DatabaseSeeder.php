<?php

namespace Database\Seeders;

use App\Models\Products;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        Products::insert([
            [
                'name' => 'Laptop',
                'description' => 'High performance laptop',
                'price' => 1200.00,
                'stock' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mouse',
                'description' => 'Wireless mouse',
                'price' => 25.00,
                'stock' => 100,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Keyboard',
                'description' => 'Mechanical keyboard',
                'price' => 75.00,
                'stock' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Monitor',
                'description' => '27 inch monitor',
                'price' => 220.00,
                'stock' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Headphones',
                'description' => 'Noise cancelling headphones',
                'price' => 90.00,
                'stock' => 40,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
