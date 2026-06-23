<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

/**
 * Public endpoint that lists food categories.
 * Used by the customer app to build the category filter chips.
 */
class CategoryController extends Controller
{
    /**
     * GET /api/categories
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Category::orderBy('name')->get(),
        ]);
    }
}
