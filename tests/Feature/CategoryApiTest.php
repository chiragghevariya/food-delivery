<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_categories(): void
    {
        Category::create(['name' => 'Pizza']);
        Category::create(['name' => 'Drinks']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name']]]);
    }
}
