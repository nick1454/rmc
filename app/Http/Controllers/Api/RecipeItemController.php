<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\RecipeItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecipeItemController extends Controller
{
    /**
     * Display the master recipe for a specific FG item.
     */
    public function index(int $fgId): JsonResponse
    {
        $fgItem = Item::findOrFail($fgId);

        $recipeItems = RecipeItem::with(['rmItem:id,category,size,primary_unit,secondary_unit,type,conversion_factor'])
            ->where('fg_item_id', $fgId)
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'fg_item' => $fgItem,
                'recipe_items' => $recipeItems,
            ],
        ]);
    }

    /**
     * Sync/save the master recipe items for a specific FG item.
     */
    public function sync(Request $request, int $fgId): JsonResponse
    {
        $fgItem = Item::findOrFail($fgId);

        $validated = $request->validate([
            'items' => ['present', 'array'],
            'items.*.rm_item_id' => ['required', 'integer', 'exists:items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit' => ['required', 'string', 'max:20'],
        ]);

        $userId = $request->user()?->id;

        DB::transaction(function () use ($fgId, $validated, $userId) {
            // Soft delete existing recipe items
            RecipeItem::where('fg_item_id', $fgId)->delete();

            // Insert new recipe items
            foreach ($validated['items'] as $itemData) {
                RecipeItem::create([
                    'fg_item_id' => $fgId,
                    'rm_item_id' => $itemData['rm_item_id'],
                    'quantity' => $itemData['quantity'],
                    'unit' => $itemData['unit'],
                    'created_by' => $userId,
                ]);
            }
        });

        $recipeItems = RecipeItem::with(['rmItem:id,category,size,primary_unit,secondary_unit,type,conversion_factor'])
            ->where('fg_item_id', $fgId)
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Master recipe updated successfully',
            'data' => [
                'fg_item' => $fgItem,
                'recipe_items' => $recipeItems,
            ],
        ]);
    }

    /**
     * Remove a specific recipe item.
     */
    public function destroy(Request $request, int $fgId, int $id): JsonResponse
    {
        $recipeItem = RecipeItem::where('fg_item_id', $fgId)->findOrFail($id);
        $recipeItem->deleted_by = $request->user()?->id;
        $recipeItem->save();
        $recipeItem->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Recipe item deleted successfully',
        ]);
    }
}
