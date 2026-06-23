<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoints for browsing a restaurant's menu.
 */
class MenuController extends Controller
{
    /**
     * GET /api/restaurants/{restaurant}/menu
     * List the available menu items for one restaurant (optional ?category= filter).
     */
    public function index(Request $request, Restaurant $restaurant): JsonResponse
    {
        $data = $request->validate([
            'category' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        // start from this restaurant's items and include each item's category
        $query = $restaurant->menuItems()->with('category');

        // only show items that are currently available
        $query->where('is_available', true);

        // optional: filter by a category
        if (! empty($data['category'])) {
            $query->where('category_id', $data['category']);
        }

        return response()->json(['data' => $query->get()]);
    }

    /**
     * GET /api/menu-items/{menuItem}
     * Show a single menu item with its category and restaurant.
     */
    public function show(MenuItem $menuItem): JsonResponse
    {
        $menuItem->load(['category', 'restaurant']);

        return response()->json(['data' => $menuItem]);
    }
}
