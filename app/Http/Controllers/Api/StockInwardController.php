<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Party;
use App\Models\StockInward;
use App\Models\StockInwardItem;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockInwardController extends Controller
{
    /**
     * Display a listing of the stock inwards.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockInward::with(['supplier', 'items.item', 'creator', 'receivedBy']);

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('inward_no', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('challan_no', 'like', "%{$search}%")
                    ->orWhere('vehicle_no', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        // Supplier filter
        if ($supplierId = $request->input('supplier_id')) {
            if ($supplierId !== 'all') {
                $query->where('supplier_party_id', $supplierId);
            }
        }

        // Date range filter
        if ($fromDate = $request->input('from_date')) {
            $query->where(function ($q) use ($fromDate) {
                $q->whereDate('challan_date', '>=', $fromDate)
                    ->orWhereDate('inward_date', '>=', $fromDate);
            });
        }
        if ($toDate = $request->input('to_date')) {
            $query->where(function ($q) use ($toDate) {
                $q->whereDate('challan_date', '<=', $toDate)
                    ->orWhereDate('inward_date', '<=', $toDate);
            });
        }

        // Payment status filter
        if ($paymentStatus = $request->input('payment_status')) {
            if ($paymentStatus !== 'all') {
                $query->where('payment_status', $paymentStatus);
            }
        }

        $perPage = (int) $request->input('per_page', 5);
        $inwards = $query->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($inwards);
    }

    /**
     * Get metadata for Stock Inward creation/filtering.
     */
    public function meta(): JsonResponse
    {
        $nextInwardNo = StockInward::generateNextInwardNo();

        $suppliers = Party::suppliers()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type', 'phone', 'gstin']);

        $rmItems = Item::where('type', 'rm')
            ->orderBy('category')
            ->orderBy('size')
            ->get();

        $users = User::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'next_inward_no' => $nextInwardNo,
            'suppliers' => $suppliers,
            'rm_items' => $rmItems,
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created stock inward shipment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'inward_no' => 'nullable|string|max:50|unique:stock_inwards,inward_no',
            'inward_date' => 'nullable|date',
            'supplier_party_id' => 'nullable|exists:parties,id',
            'supplier_name' => 'nullable|string|max:255',
            'challan_no' => 'nullable|string|max:100',
            'challan_date' => 'nullable|date',
            'supplier_bill_no' => 'nullable|string|max:100',
            'supplier_bill_date' => 'nullable|date',
            'vehicle_no' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:100',
            'driver_phone' => 'nullable|string|max:30',
            'received_by_user_id' => 'nullable|exists:users,id',
            'remarks' => 'nullable|string',
            'freight_amount' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty_primary' => 'required|numeric|min:0.0001',
            'items.*.unit_primary' => 'nullable|string|max:20',
            'items.*.qty_secondary' => 'nullable|numeric|min:0',
            'items.*.unit_secondary' => 'nullable|string|max:20',
            'items.*.conversion_factor' => 'nullable|numeric|min:0.0001',
            'items.*.rate_per_unit' => 'nullable|numeric|min:0',
            'items.*.gst_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.taxable_amount' => 'nullable|numeric|min:0',
            'items.*.gst_amount' => 'nullable|numeric|min:0',
            'items.*.total_amount' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

        // Auto-fill supplier name if party selected
        if (! empty($validated['supplier_party_id']) && empty($validated['supplier_name'])) {
            $party = Party::find($validated['supplier_party_id']);
            $validated['supplier_name'] = $party?->name;
        }

        $inward = DB::transaction(function () use ($validated, $request) {
            $calculatedSubtotal = 0.0;
            $calculatedTax = 0.0;

            $itemsToCreate = [];
            foreach ($validated['items'] as $itemRow) {
                $masterItem = Item::find($itemRow['item_id']);
                $factor = (float) ($itemRow['conversion_factor'] ?? ($masterItem?->conversion_factor ?: 1.0));
                $primQty = (float) $itemRow['qty_primary'];
                $secQty = isset($itemRow['qty_secondary']) && $itemRow['qty_secondary'] > 0
                    ? (float) $itemRow['qty_secondary']
                    : ($factor > 0 ? ($primQty * $factor) : $primQty);

                $rate = (float) ($itemRow['rate_per_unit'] ?? 0.0);
                $gstRate = isset($itemRow['gst_rate'])
                    ? (float) $itemRow['gst_rate']
                    : (float) ($masterItem?->gst_per ?: 0.0);

                $taxable = round($primQty * $rate, 2);
                $gstAmt = round($taxable * ($gstRate / 100), 2);
                $lineTotal = round($taxable + $gstAmt, 2);

                $calculatedSubtotal += $taxable;
                $calculatedTax += $gstAmt;

                $itemsToCreate[] = [
                    'item_id' => $itemRow['item_id'],
                    'qty_primary' => $primQty,
                    'unit_primary' => $itemRow['unit_primary'] ?? ($masterItem?->primary_unit ?: 'ton'),
                    'qty_secondary' => $secQty,
                    'unit_secondary' => $itemRow['unit_secondary'] ?? ($masterItem?->secondary_unit ?: 'kgs'),
                    'conversion_factor' => $factor,
                    'rate_per_unit' => $rate,
                    'taxable_amount' => $taxable,
                    'gst_rate' => $gstRate,
                    'gst_amount' => $gstAmt,
                    'total_amount' => $lineTotal,
                    'remarks' => $itemRow['remarks'] ?? null,
                ];
            }

            $freight = (float) ($validated['freight_amount'] ?? 0.0);
            $other = (float) ($validated['other_charges'] ?? 0.0);
            $discount = (float) ($validated['discount_amount'] ?? 0.0);
            $grandTotal = max(0.0, round($calculatedSubtotal + $calculatedTax + $freight + $other - $discount, 2));

            $inwardDate = $validated['inward_date'] ?? ($validated['challan_date'] ?? now()->toDateString());

            $inward = StockInward::create([
                'inward_no' => $validated['inward_no'] ?? StockInward::generateNextInwardNo(),
                'inward_date' => $inwardDate,
                'supplier_party_id' => $validated['supplier_party_id'] ?? null,
                'supplier_name' => $validated['supplier_name'] ?? null,
                'challan_no' => $validated['challan_no'] ?? null,
                'challan_date' => $validated['challan_date'] ?? null,
                'supplier_bill_no' => $validated['supplier_bill_no'] ?? null,
                'supplier_bill_date' => $validated['supplier_bill_date'] ?? null,
                'vehicle_no' => $validated['vehicle_no'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'driver_phone' => $validated['driver_phone'] ?? null,
                'received_by_user_id' => $validated['received_by_user_id'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'subtotal_amount' => $calculatedSubtotal,
                'tax_amount' => $calculatedTax,
                'freight_amount' => $freight,
                'other_charges' => $other,
                'discount_amount' => $discount,
                'grand_total' => $grandTotal,
                'created_by' => $request->user()?->id,
            ]);

            foreach ($itemsToCreate as $itemData) {
                $itemData['stock_inward_id'] = $inward->id;
                StockInwardItem::create($itemData);
            }

            return $inward->load(['supplier', 'items.item', 'creator', 'receivedBy']);
        });

        return response()->json([
            'message' => 'Stock inward entry recorded successfully.',
            'data' => $inward,
        ], 201);
    }

    /**
     * Display the specified stock inward shipment.
     */
    public function show(StockInward $inward): JsonResponse
    {
        $inward->load(['supplier', 'items.item', 'creator', 'receivedBy']);

        return response()->json($inward);
    }

    /**
     * Update the specified stock inward entry.
     */
    public function update(Request $request, StockInward $inward): JsonResponse
    {
        $validated = $request->validate([
            'inward_no' => 'required|string|max:50|unique:stock_inwards,inward_no,'.$inward->id,
            'inward_date' => 'nullable|date',
            'supplier_party_id' => 'nullable|exists:parties,id',
            'supplier_name' => 'nullable|string|max:255',
            'challan_no' => 'nullable|string|max:100',
            'challan_date' => 'nullable|date',
            'supplier_bill_no' => 'nullable|string|max:100',
            'supplier_bill_date' => 'nullable|date',
            'vehicle_no' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:100',
            'driver_phone' => 'nullable|string|max:30',
            'received_by_user_id' => 'nullable|exists:users,id',
            'remarks' => 'nullable|string',
            'freight_amount' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty_primary' => 'required|numeric|min:0.0001',
            'items.*.unit_primary' => 'nullable|string|max:20',
            'items.*.qty_secondary' => 'nullable|numeric|min:0',
            'items.*.unit_secondary' => 'nullable|string|max:20',
            'items.*.conversion_factor' => 'nullable|numeric|min:0.0001',
            'items.*.rate_per_unit' => 'nullable|numeric|min:0',
            'items.*.gst_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.taxable_amount' => 'nullable|numeric|min:0',
            'items.*.gst_amount' => 'nullable|numeric|min:0',
            'items.*.total_amount' => 'nullable|numeric|min:0',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

        if (! empty($validated['supplier_party_id']) && empty($validated['supplier_name'])) {
            $party = Party::find($validated['supplier_party_id']);
            $validated['supplier_name'] = $party?->name;
        }

        $stockService = app(StockService::class);

        // Guard: Prevent reducing or removing items if materials are already consumed in production
        foreach ($inward->items as $currentItem) {
            $stock = $stockService->getItemStock($currentItem->item_id);
            if ($stockService->isStockCheckExempt($stock['category'])) {
                continue;
            }

            $matchingNewItem = collect($validated['items'])->firstWhere('item_id', $currentItem->item_id);
            $newQty = $matchingNewItem ? (float) $matchingNewItem['qty_primary'] : 0.0;
            $reduction = (float) $currentItem->qty_primary - $newQty;

            if ($reduction > 0.0001 && $reduction > (float) $stock['available_primary']) {
                $reductionFormatted = round($reduction, 4);
                $availableFormatted = round((float) $stock['available_primary'], 4);
                $unit = $stock['primary_unit'];
                $itemName = $stock['item_name'];

                return response()->json([
                    'message' => "Cannot reduce {$itemName} inward quantity by {$reductionFormatted} {$unit}. This material has already been consumed in production batches (Currently available stock: {$availableFormatted} {$unit}).",
                ], 422);
            }
        }

        DB::transaction(function () use ($inward, $validated, $request) {
            $calculatedSubtotal = 0.0;
            $calculatedTax = 0.0;

            $itemsToCreate = [];
            foreach ($validated['items'] as $itemRow) {
                $masterItem = Item::find($itemRow['item_id']);
                $factor = (float) ($itemRow['conversion_factor'] ?? ($masterItem?->conversion_factor ?: 1.0));
                $primQty = (float) $itemRow['qty_primary'];
                $secQty = isset($itemRow['qty_secondary']) && $itemRow['qty_secondary'] > 0
                    ? (float) $itemRow['qty_secondary']
                    : ($factor > 0 ? ($primQty * $factor) : $primQty);

                $rate = (float) ($itemRow['rate_per_unit'] ?? 0.0);
                $gstRate = isset($itemRow['gst_rate'])
                    ? (float) $itemRow['gst_rate']
                    : (float) ($masterItem?->gst_per ?: 0.0);

                $taxable = round($primQty * $rate, 2);
                $gstAmt = round($taxable * ($gstRate / 100), 2);
                $lineTotal = round($taxable + $gstAmt, 2);

                $calculatedSubtotal += $taxable;
                $calculatedTax += $gstAmt;

                $itemsToCreate[] = [
                    'stock_inward_id' => $inward->id,
                    'item_id' => $itemRow['item_id'],
                    'qty_primary' => $primQty,
                    'unit_primary' => $itemRow['unit_primary'] ?? ($masterItem?->primary_unit ?: 'ton'),
                    'qty_secondary' => $secQty,
                    'unit_secondary' => $itemRow['unit_secondary'] ?? ($masterItem?->secondary_unit ?: 'kgs'),
                    'conversion_factor' => $factor,
                    'rate_per_unit' => $rate,
                    'taxable_amount' => $taxable,
                    'gst_rate' => $gstRate,
                    'gst_amount' => $gstAmt,
                    'total_amount' => $lineTotal,
                    'remarks' => $itemRow['remarks'] ?? null,
                ];
            }

            $freight = (float) ($validated['freight_amount'] ?? 0.0);
            $other = (float) ($validated['other_charges'] ?? 0.0);
            $discount = (float) ($validated['discount_amount'] ?? 0.0);
            $grandTotal = max(0.0, round($calculatedSubtotal + $calculatedTax + $freight + $other - $discount, 2));

            $inwardDate = $validated['inward_date'] ?? ($inward->inward_date ?? ($validated['challan_date'] ?? now()->toDateString()));

            $inward->update([
                'inward_no' => $validated['inward_no'],
                'inward_date' => $inwardDate,
                'supplier_party_id' => $validated['supplier_party_id'] ?? null,
                'supplier_name' => $validated['supplier_name'] ?? null,
                'challan_no' => $validated['challan_no'] ?? null,
                'challan_date' => $validated['challan_date'] ?? null,
                'supplier_bill_no' => $validated['supplier_bill_no'] ?? null,
                'supplier_bill_date' => $validated['supplier_bill_date'] ?? null,
                'vehicle_no' => $validated['vehicle_no'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'driver_phone' => $validated['driver_phone'] ?? null,
                'received_by_user_id' => $validated['received_by_user_id'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'subtotal_amount' => $calculatedSubtotal,
                'tax_amount' => $calculatedTax,
                'freight_amount' => $freight,
                'other_charges' => $other,
                'discount_amount' => $discount,
                'grand_total' => $grandTotal,
                'updated_by' => $request->user()?->id,
            ]);

            // Replace items
            $inward->items()->delete();

            foreach ($itemsToCreate as $itemData) {
                StockInwardItem::create($itemData);
            }
        });

        return response()->json([
            'message' => 'Stock inward entry updated successfully.',
            'data' => $inward->fresh(['supplier', 'items.item', 'creator', 'receivedBy']),
        ]);
    }

    /**
     * Remove the specified stock inward entry from storage.
     */
    public function destroy(StockInward $inward): JsonResponse
    {
        $stockService = app(StockService::class);

        // Guard: Prevent deleting inward shipment if received materials have already been consumed
        foreach ($inward->items as $currentItem) {
            $stock = $stockService->getItemStock($currentItem->item_id);
            if ($stockService->isStockCheckExempt($stock['category'])) {
                continue;
            }

            $inwardQty = (float) $currentItem->qty_primary;
            if ($inwardQty > 0.0001 && $inwardQty > (float) $stock['available_primary']) {
                $inwardQtyFormatted = round($inwardQty, 4);
                $availableFormatted = round((float) $stock['available_primary'], 4);
                $unit = $stock['primary_unit'];
                $itemName = $stock['item_name'];

                return response()->json([
                    'message' => "Cannot delete inward entry. {$inwardQtyFormatted} {$unit} of {$itemName} was received in this shipment, but only {$availableFormatted} {$unit} is currently available as the rest has already been consumed in production.",
                ], 422);
            }
        }

        $inward->delete();

        return response()->json([
            'message' => 'Stock inward entry deleted successfully.',
        ]);
    }
}
