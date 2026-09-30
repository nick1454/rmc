<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    /**
     * Display a listing of items with search and category filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Item::with(['creator:id,name', 'deleter:id,name', 'categoryItem:id,name,status'])
            ->withCount('recipeItems');

        // Search by category, size, or HSN
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('category', 'like', "%{$search}%")
                    ->orWhere('size', 'like', "%{$search}%")
                    ->orWhere('hsn', 'like', "%{$search}%")
                    ->orWhere('primary_unit', 'like', "%{$search}%")
                    ->orWhere('secondary_unit', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%");
            });
        }

        // Filter by category
        if ($category = $request->input('category')) {
            if ($category !== 'all') {
                $query->where('category', $category);
            }
        }

        // Filter by type
        if ($type = $request->input('type')) {
            if ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        // Return non-paginated list if requested (for dropdowns)
        if ($request->boolean('all')) {
            $items = $query->orderBy('category')->orderBy('size')->get();

            return response()->json([
                'status' => 'success',
                'data' => $items,
            ]);
        }

        $perPage = $request->integer('per_page', 5);
        $items = $query->orderBy('id', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    /**
     * Store a newly created item.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'size' => ['nullable', 'string', 'max:50'],
            'secondary_unit' => ['required', 'string', 'max:20'],
            'primary_unit' => ['required', 'string', 'max:20'],
            'conversion_factor' => ['required', 'numeric', 'min:0.0001'],
            'type' => ['required', 'string', Rule::in(['rm', 'fg'])],
            'hsn' => ['nullable', 'string', 'max:20'],
            'gst_per' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if (empty($validated['category_id'])) {
            $validated['category_id'] = Category::where('name', $validated['category'])->value('id');
        }

        $validated['created_by'] = $request->user()?->id;

        $item = Item::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Item created successfully',
            'data' => $item,
        ], 201);
    }

    /**
     * Display the specified item.
     */
    public function show(int $id): JsonResponse
    {
        $item = Item::with(['creator:id,name', 'deleter:id,name', 'categoryItem:id,name,status'])
            ->withCount('recipeItems')
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $item,
        ]);
    }

    /**
     * Update the specified item.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = Item::findOrFail($id);

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'size' => ['nullable', 'string', 'max:50'],
            'secondary_unit' => ['required', 'string', 'max:20'],
            'primary_unit' => ['required', 'string', 'max:20'],
            'conversion_factor' => ['required', 'numeric', 'min:0.0001'],
            'type' => ['required', 'string', Rule::in(['rm', 'fg'])],
            'hsn' => ['nullable', 'string', 'max:20'],
            'gst_per' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        if (empty($validated['category_id'])) {
            $validated['category_id'] = Category::where('name', $validated['category'])->value('id');
        }

        $item->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Item updated successfully',
            'data' => $item,
        ]);
    }

    /**
     * Remove the specified item (SoftDelete).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = Item::findOrFail($id);
        $item->deleted_by = $request->user()?->id;
        $item->save();
        $item->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Item deleted successfully',
        ]);
    }

    /**
     * Metadata for dropdown selections.
     */
    public function meta(): JsonResponse
    {
        $dbCategories = Category::where('status', 'active')->pluck('name')->all();
        $defaultCategories = ['Aggregate', 'Cement', 'Dust', 'AddMixture', 'Silo Pouch', 'RMC'];
        $allCategories = ! empty($dbCategories) ? array_values(array_unique(array_merge($defaultCategories, $dbCategories))) : $defaultCategories;

        $defaultSizes = ['10mm', '20mm', '30mm', 'opc40', 'M10', 'M15', 'M20', 'M25', 'M30', 'M35', 'M40'];
        $dbSizes = Item::whereNotNull('size')->where('size', '!=', '')->distinct()->pluck('size')->values()->all();
        $allSizes = array_values(array_unique(array_merge($defaultSizes, $dbSizes)));

        $defaultUnits = ['ton', 'kgs', 'cbm', 'pcs', 'bag', 'ltr'];
        $dbPrimaryUnits = Item::distinct()->pluck('primary_unit')->values()->all();
        $dbSecondaryUnits = Item::distinct()->pluck('secondary_unit')->values()->all();
        $allUnits = array_values(array_unique(array_merge($defaultUnits, $dbPrimaryUnits, $dbSecondaryUnits)));

        return response()->json([
            'status' => 'success',
            'data' => [
                'categories' => $allCategories,
                'sizes' => $allSizes,
                'types' => [
                    'rm',
                    'fg',
                ],
                'primary_units' => $allUnits,
                'secondary_units' => $allUnits,
            ],
        ]);
    }
}
