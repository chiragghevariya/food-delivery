<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restaurant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public endpoints for browsing restaurants.
 */
class RestaurantController extends Controller
{
    /**
     * GET /api/restaurants
     * List restaurants, with optional ?search= and ?category= filters.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'search'   => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'integer', 'exists:categories,id'],
        ]);

        $query = Restaurant::query();

        // Filter by name OR description containing the search text.
        if (! empty($data['search'])) {
            $search = $data['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Keep only restaurants that have at least one menu item in this category.
        if (! empty($data['category'])) {
            $query->whereHas('menuItems', function ($q) use ($data) {
                $q->where('category_id', $data['category']);
            });
        }

        // Open restaurants first, then highest rated. 15 per page.
        $restaurants = $query->orderByDesc('is_open')
            ->orderByDesc('rating')
            ->paginate(15);

        return response()->json($restaurants);
    }

    /**
     * GET /api/restaurants/{restaurant}
     * Show one restaurant together with its menu items.
     */
    public function show(Restaurant $restaurant): JsonResponse
    {
        // load the menu (and each item's category) along with the restaurant
        $restaurant->load(['menuItems.category']);

        return response()->json(['data' => $restaurant]);
    }

    /**
     * GET /api/restaurants/nearby?lat=..&lng=..&radius=..
     * Find restaurants within "radius" km of a point (default 5 km).
     */
    public function nearby(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lat'    => ['required', 'numeric', 'between:-90,90'],
            'lng'    => ['required', 'numeric', 'between:-180,180'],
            'radius' => ['nullable', 'numeric', 'min:0.1', 'max:50'], // kilometres
        ]);

        $lat    = (float) $data['lat'];
        $lng    = (float) $data['lng'];
        $radius = (float) ($data['radius'] ?? 5);

        // STEP 1 — fast, database-friendly pre-filter using a square "box" around
        // the point. This works on MySQL AND SQLite (no special map functions).
        // Roughly 111 km per degree of latitude.
        $latDelta = $radius / 111.0;
        $lngDelta = $radius / (111.0 * max(cos(deg2rad($lat)), 0.0001));

        $candidates = Restaurant::query()
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->whereBetween('lat', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('lng', [$lng - $lngDelta, $lng + $lngDelta])
            ->get();

        // STEP 2 — compute the exact distance for each one and keep those in range,
        // closest first.
        $restaurants = $candidates
            ->map(function (Restaurant $restaurant) use ($lat, $lng) {
                $restaurant->distance_km = round(
                    $this->distanceInKm($lat, $lng, (float) $restaurant->lat, (float) $restaurant->lng),
                    2
                );
                return $restaurant;
            })
            ->filter(fn (Restaurant $restaurant) => $restaurant->distance_km <= $radius)
            ->sortBy('distance_km')
            ->values();

        return response()->json(['data' => $restaurants]);
    }

    /**
     * Haversine formula: the distance in kilometres between two lat/lng points.
     */
    private function distanceInKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
