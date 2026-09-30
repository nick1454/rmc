<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockReportController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * Get periodic stock summary balance report.
     */
    public function summary(Request $request): JsonResponse
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $type = $request->query('type');
        $category = $request->query('category');
        $search = $request->query('search');

        $report = $this->stockService->getStockSummaryReport(
            fromDate: $fromDate,
            toDate: $toDate,
            type: $type,
            category: $category,
            search: $search
        );

        return response()->json([
            'status' => 'success',
            'data' => $report,
        ]);
    }

    /**
     * Get detailed transaction movement ledger for an item.
     */
    public function ledger(Request $request, int $itemId): JsonResponse
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        $report = $this->stockService->getItemLedgerReport(
            itemId: $itemId,
            fromDate: $fromDate,
            toDate: $toDate
        );

        return response()->json([
            'status' => 'success',
            'data' => $report,
        ]);
    }

    /**
     * Get meta information for report filters (categories, items list).
     */
    public function meta(): JsonResponse
    {
        $categories = Category::active()->orderBy('name')->get(['id', 'name']);
        $items = Item::orderBy('type')
            ->orderBy('category')
            ->orderBy('size')
            ->get(['id', 'category', 'size', 'type', 'primary_unit', 'secondary_unit', 'conversion_factor', 'hsn', 'gst_per']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'categories' => $categories,
                'items' => $items,
            ],
        ]);
    }
}
