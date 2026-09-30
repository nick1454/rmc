<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Production;
use App\Models\ProductionConsumption;
use App\Models\ProductionRecipeItem;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductionController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * Display a listing of production batch entries.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Production::with([
            'fgItem:id,category,size,primary_unit,secondary_unit,type',
            'recipeItems.rmItem:id,category,size,primary_unit,secondary_unit,conversion_factor',
            'consumptions.item:id,category,size,primary_unit,secondary_unit',
            'creator:id,name',
            'updater:id,name',
        ])
            ->withCount(['recipeItems', 'consumptions']);

        // Search Filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('production_no', 'like', "%{$search}%")
                    ->orWhere('batch_no', 'like', "%{$search}%")
                    ->orWhere('plant_name', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('fgItem', function ($sub) use ($search) {
                        $sub->where('category', 'like', "%{$search}%")
                            ->orWhere('size', 'like', "%{$search}%");
                    });
            });
        }

        // Date Range Filter
        if ($fromDate = $request->query('from_date')) {
            $query->whereDate('production_date', '>=', $fromDate);
        }
        if ($toDate = $request->query('to_date')) {
            $query->whereDate('production_date', '<=', $toDate);
        }

        // FG Item Filter
        if ($fgItemId = $request->query('fg_item_id')) {
            if ($fgItemId !== 'all') {
                $query->where('fg_item_id', $fgItemId);
            }
        }

        $perPage = (int) $request->query('per_page', 5);
        $productions = $query->orderByDesc('production_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $productions->items(),
            'meta' => [
                'current_page' => $productions->currentPage(),
                'last_page' => $productions->lastPage(),
                'per_page' => $productions->perPage(),
                'total' => $productions->total(),
            ],
        ]);
    }

    /**
     * Store a newly created production batch with recipe & consumption tracking.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'production_no' => ['nullable', 'string', 'max:100', 'unique:productions,production_no'],
            'production_date' => ['required', 'date'],
            'fg_item_id' => ['required', 'integer', 'exists:items,id'],
            'cbm' => ['required', 'numeric', 'gt:0'],
            'plant_name' => ['nullable', 'string', 'max:255'],
            'batch_no' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
            'recipe_items' => ['required', 'array', 'min:1'],
            'recipe_items.*.rm_item_id' => ['required', 'integer', 'exists:items,id'],
            'recipe_items.*.recipe_qty_per_cbm' => ['required', 'numeric', 'gte:0'],
            'recipe_items.*.actual_qty_per_cbm' => ['required', 'numeric', 'gt:0'],
            'recipe_items.*.unit' => ['required', 'string', 'max:20'],
            'recipe_items.*.total_qty' => ['required', 'numeric', 'gt:0'],
        ]);

        // 1. Stock Availability Check
        $stockCheck = $this->stockService->validateStockAvailability($validated['recipe_items']);

        if (! $stockCheck['is_valid']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Production blocked: Insufficient raw material stock in inventory.',
                'shortages' => $stockCheck['shortages'],
                'errors' => [
                    'stock' => array_map(function ($s) {
                        return "Item {$s['item_name']} is short by {$s['shortage_qty']} {$s['unit']} (Available: {$s['available_qty']} {$s['unit']}, Required: {$s['required_qty']} {$s['unit']}).";
                    }, $stockCheck['shortages']),
                ],
            ], 422);
        }

        $userId = $request->user()?->id;

        // 2. Database Transaction: Save Production, Recipe Items, and Consumptions
        $production = DB::transaction(function () use ($validated, $userId) {
            $prod = Production::create([
                'production_no' => $validated['production_no'] ?? Production::generateNextProductionNo(),
                'production_date' => $validated['production_date'],
                'fg_item_id' => $validated['fg_item_id'],
                'cbm' => $validated['cbm'],
                'plant_name' => $validated['plant_name'] ?? 'Plant-01 Central',
                'batch_no' => $validated['batch_no'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($validated['recipe_items'] as $item) {
                ProductionRecipeItem::create([
                    'production_id' => $prod->id,
                    'rm_item_id' => $item['rm_item_id'],
                    'recipe_qty_per_cbm' => $item['recipe_qty_per_cbm'],
                    'actual_qty_per_cbm' => $item['actual_qty_per_cbm'],
                    'unit' => $item['unit'],
                    'total_qty' => $item['total_qty'],
                    'created_by' => $userId,
                ]);
            }

            // Save material consumptions separately
            $this->stockService->recordConsumptions($prod, $validated['recipe_items'], $userId);

            return $prod;
        });

        $production->load([
            'fgItem:id,category,size,primary_unit,secondary_unit,type',
            'recipeItems.rmItem:id,category,size,primary_unit,secondary_unit,conversion_factor',
            'consumptions.item:id,category,size,primary_unit,secondary_unit',
            'creator:id,name',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Production batch {$production->production_no} ({$production->cbm} CBM) saved successfully and stock deducted.",
            'data' => $production,
        ], 201);
    }

    /**
     * Display the specified production batch details.
     */
    public function show(int $id): JsonResponse
    {
        $production = Production::with([
            'fgItem:id,category,size,primary_unit,secondary_unit,type',
            'recipeItems.rmItem:id,category,size,primary_unit,secondary_unit,conversion_factor',
            'consumptions.item:id,category,size,primary_unit,secondary_unit',
            'creator:id,name',
            'updater:id,name',
        ])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $production,
        ]);
    }

    /**
     * Remove the specified production entry and restore consumed stock.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $production = Production::with('fgItem')->findOrFail($id);
        $userId = $request->user()?->id;

        DB::transaction(function () use ($production, $userId) {
            $production->deleted_by = $userId;
            $production->save();
            $production->delete();

            ProductionRecipeItem::where('production_id', $production->id)->update([
                'deleted_by' => $userId,
            ]);
            ProductionRecipeItem::where('production_id', $production->id)->delete();

            ProductionConsumption::where('production_id', $production->id)->update([
                'deleted_by' => $userId,
            ]);
            ProductionConsumption::where('production_id', $production->id)->delete();
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Production batch deleted successfully and stock restored.',
        ]);
    }

    /**
     * Validate draft stock availability on the fly without saving.
     */
    public function validateStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'recipe_items' => ['required', 'array', 'min:1'],
            'recipe_items.*.rm_item_id' => ['required', 'integer', 'exists:items,id'],
            'recipe_items.*.total_qty' => ['required', 'numeric', 'gt:0'],
            'recipe_items.*.unit' => ['nullable', 'string'],
        ]);

        $stockCheck = $this->stockService->validateStockAvailability($validated['recipe_items']);

        return response()->json([
            'status' => 'success',
            'data' => $stockCheck,
        ]);
    }

    /**
     * Metadata for production entry: FG items with master recipes, RM stock levels, next production & batch numbers.
     */
    public function meta(Request $request): JsonResponse
    {
        $date = $request->query('date', date('Y-m-d'));
        $fgItems = Item::where('type', 'fg')
            ->with([
                'recipeItems.rmItem:id,category,size,primary_unit,secondary_unit,conversion_factor,type',
            ])
            ->orderBy('category')
            ->orderBy('size')
            ->get(['id', 'category', 'size', 'primary_unit', 'secondary_unit', 'conversion_factor', 'type']);

        $rmStock = $this->stockService->getAllRmStock();
        $nextProductionNo = Production::generateNextProductionNo();
        $nextBatchNo = Production::generateNextBatchNo($date);

        return response()->json([
            'status' => 'success',
            'data' => [
                'fg_items' => $fgItems,
                'rm_stock' => $rmStock,
                'next_production_no' => $nextProductionNo,
                'next_batch_no' => $nextBatchNo,
            ],
        ]);
    }
}
