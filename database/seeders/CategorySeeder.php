<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Create some default food categories (so the customer app has chips to show).
     */
    public function run(): void
    {
        foreach (['Pizza', 'Burgers', 'Drinks', 'Desserts', 'Sushi', 'Salads'] as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
