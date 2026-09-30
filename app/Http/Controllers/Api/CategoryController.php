<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories with search and status filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::with(['creator:id,name', 'updater:id,name'])
            ->withCount('items');

        // Search by category name
        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        // Filter by status
        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        // Return non-paginated list if requested (for dropdown selections)
        if ($request->boolean('all')) {
            $categories = $query->orderBy('name')->get();

            return response()->json([
                'status' => 'success',
                'data' => $categories,
            ]);
        }

        $perPage = $request->integer('per_page', 5);
        $categories = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $categories->items(),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'total' => $categories->total(),
            ],
        ]);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->whereNull('deleted_at'),
            ],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $validated['created_by'] = $request->user()?->id;

        $category = Category::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category created successfully',
            'data' => $category,
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(int $id): JsonResponse
    {
        $category = Category::with(['creator:id,name', 'updater:id,name'])
            ->withCount('items')
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $category,
        ]);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($id)->whereNull('deleted_at'),
            ],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
        ]);

        $validated['updated_by'] = $request->user()?->id;

        $category->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Category updated successfully',
            'data' => $category,
        ]);
    }

    /**
     * Remove the specified category (SoftDelete).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $category->deleted_by = $request->user()?->id;
        $category->save();
        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Category deleted successfully',
        ]);
    }
}
