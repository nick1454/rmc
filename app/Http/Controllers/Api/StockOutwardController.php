<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Party;
use App\Models\StockOutward;
use App\Models\StockOutwardItem;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockOutwardController extends Controller
{
    /**
     * Display a paginated listing of the stock outwards (dispatches).
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockOutward::with(['party', 'agent', 'items.item', 'creator'])
            ->withSum(['paymentItems as payment_discount_amount' => function ($q) {
                $q->whereHas('payment', fn ($pq) => $pq->whereNull('deleted_at'));
            }], 'discount_amount');

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('entry_no', 'like', "%{$search}%")
                    ->orWhere('customer', 'like', "%{$search}%")
                    ->orWhere('agent_name', 'like', "%{$search}%")
                    ->orWhere('challan_no', 'like', "%{$search}%")
                    ->orWhere('vehicle_no', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhere('plant_name', 'like', "%{$search}%")
                    ->orWhereHas('party', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        // Customer / Party filter
        if ($partyId = $request->input('party_id')) {
            if ($partyId !== 'all') {
                $query->where('party_id', $partyId);
            }
        }

        // Entry Type filter
        if ($entryType = $request->input('entry_type')) {
            if ($entryType !== 'all') {
                $query->where('entry_type', $entryType);
            }
        }

        // Date range filter
        if ($fromDate = $request->input('from_date')) {
            $query->where(function ($q) use ($fromDate) {
                $q->whereDate('invoice_date', '>=', $fromDate)
                    ->orWhereDate('challan_date', '>=', $fromDate);
            });
        }
        if ($toDate = $request->input('to_date')) {
            $query->where(function ($q) use ($toDate) {
                $q->whereDate('invoice_date', '<=', $toDate)
                    ->orWhereDate('challan_date', '<=', $toDate);
            });
        }

        $perPage = (int) $request->input('per_page', 5);
        $outwards = $query->orderByDesc('id')
            ->paginate($perPage);

        return response()->json($outwards);
    }

    /**
     * Get metadata for Stock Outward creation and filters.
     */
    public function meta(): JsonResponse
    {
        $nextEntryNo = StockOutward::generateNextEntryNo();

        // Customers / Parties
        $parties = Party::active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type', 'phone', 'gstin', 'address', 'city']);

        $customers = $parties->filter(fn ($p) => in_array($p->type, ['customer', 'both', 'vendor', 'supplier']))->values();
        $agents = $parties->filter(fn ($p) => in_array($p->type, ['agent', 'broker', 'other', 'both']))->values();

        // All items (RM and FG)
        $items = Item::orderBy('type')
            ->orderBy('category')
            ->orderBy('size')
            ->get(['id', 'type', 'category', 'size', 'hsn', 'primary_unit', 'secondary_unit', 'conversion_factor', 'gst_per']);

        $users = User::select('id', 'name')->orderBy('name')->get();

        return response()->json([
            'next_entry_no' => $nextEntryNo,
            'parties' => $parties,
            'customers' => $customers->isNotEmpty() ? $customers : $parties,
            'agents' => $agents->isNotEmpty() ? $agents : $parties,
            'items' => $items,
            'users' => $users,
        ]);
    }

    /**
     * Store a newly created stock outward consignment.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entry_no' => 'nullable|string|max:50|unique:stock_outwards,entry_no',
            'entry_type' => 'nullable|string|max:50',
            'party_id' => 'nullable|exists:parties,id',
            'customer' => 'nullable|string|max:255',
            'agent_id' => 'nullable|exists:parties,id',
            'agent_name' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'challan_no' => 'nullable|string|max:100',
            'challan_date' => 'nullable|date',
            'vehicle_no' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:100',
            'driver_phone' => 'nullable|string|max:30',
            'destination' => 'nullable|string|max:255',
            'plant_name' => 'nullable|string|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'commission_amount' => 'nullable|numeric|min:0',
            'freight_charges' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|numeric|min:0.0001',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.secondary_qty' => 'nullable|numeric|min:0',
            'items.*.secondary_unit' => 'nullable|string|max:20',
            'items.*.conversion_factor' => 'nullable|numeric|min:0.0001',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.pump_cost' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.gst_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.taxable_amount' => 'nullable|numeric|min:0',
            'items.*.gst_amount' => 'nullable|numeric|min:0',
            'items.*.total_amount' => 'nullable|numeric|min:0',
            'items.*.commission_agent_id' => 'nullable|exists:parties,id',
            'items.*.commission_agent_name' => 'nullable|string|max:255',
            'items.*.commission_rate' => 'nullable|numeric|min:0',
            'items.*.commission_amount' => 'nullable|numeric|min:0',
            'items.*.lotno' => 'nullable|string|max:100',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

        // Auto-fill customer/agent names if IDs provided
        if (! empty($validated['party_id']) && empty($validated['customer'])) {
            $party = Party::find($validated['party_id']);
            $validated['customer'] = $party?->name;
        }
        if (! empty($validated['agent_id']) && empty($validated['agent_name'])) {
            $agent = Party::find($validated['agent_id']);
            $validated['agent_name'] = $agent?->name;
        }

        $outward = DB::transaction(function () use ($validated) {
            $calculatedSubtotal = 0.0;
            $calculatedTax = 0.0;
            $calculatedPumpCost = 0.0;
            $calculatedItemDiscounts = 0.0;
            $calculatedItemCommissions = 0.0;

            $itemsToCreate = [];
            foreach ($validated['items'] as $itemRow) {
                $qty = (float) ($itemRow['qty'] ?? 0);
                $rate = (float) ($itemRow['rate'] ?? 0);
                $gross = $qty * $rate;

                $taxable = isset($itemRow['taxable_amount']) && $itemRow['taxable_amount'] !== null && $itemRow['taxable_amount'] !== ''
                    ? (float) $itemRow['taxable_amount']
                    : $gross;

                $gstRate = (float) ($itemRow['gst_rate'] ?? 0);
                $gst = isset($itemRow['gst_amount']) && $itemRow['gst_amount'] !== null && $itemRow['gst_amount'] !== ''
                    ? (float) $itemRow['gst_amount']
                    : round($taxable * ($gstRate / 100), 2);

                $pumpCost = (float) ($itemRow['pump_cost'] ?? 0);
                $overallCost = round($taxable + $gst + $pumpCost, 2);

                $discPercent = (float) ($itemRow['discount_percent'] ?? 0);
                $discAmt = isset($itemRow['discount_amount']) && $itemRow['discount_amount'] !== null && $itemRow['discount_amount'] !== ''
                    ? (float) $itemRow['discount_amount']
                    : ($discPercent > 0 ? round($overallCost * ($discPercent / 100), 2) : 0);

                $total = isset($itemRow['total_amount']) && $itemRow['total_amount'] !== null && $itemRow['total_amount'] !== ''
                    ? (float) $itemRow['total_amount']
                    : max(0.0, round($overallCost - $discAmt, 2));

                // Commission calculation
                $commAgentId = ! empty($itemRow['commission_agent_id']) ? (int) $itemRow['commission_agent_id'] : ($validated['agent_id'] ?? null);
                $commAgentName = $itemRow['commission_agent_name'] ?? null;
                if ($commAgentId && empty($commAgentName)) {
                    $commParty = Party::find($commAgentId);
                    $commAgentName = $commParty?->name;
                }

                $commRate = (float) ($itemRow['commission_rate'] ?? 0);
                $commAmt = isset($itemRow['commission_amount']) && $itemRow['commission_amount'] !== null && $itemRow['commission_amount'] !== ''
                    ? (float) $itemRow['commission_amount']
                    : ($commRate > 0 ? round($taxable * ($commRate / 100), 2) : 0);

                $calculatedSubtotal += $taxable;
                $calculatedTax += $gst;
                $calculatedPumpCost += $pumpCost;
                $calculatedItemDiscounts += $discAmt;
                $calculatedItemCommissions += $commAmt;

                $itemsToCreate[] = [
                    'item_id' => $itemRow['item_id'],
                    'qty' => $qty,
                    'unit' => $itemRow['unit'] ?? 'ton',
                    'secondary_qty' => $itemRow['secondary_qty'] ?? 0,
                    'secondary_unit' => $itemRow['secondary_unit'] ?? 'kgs',
                    'conversion_factor' => $itemRow['conversion_factor'] ?? 1.0,
                    'rate' => $rate,
                    'pump_cost' => $pumpCost,
                    'discount_percent' => $discPercent,
                    'discount_amount' => $discAmt,
                    'gst_rate' => $gstRate,
                    'taxable_amount' => $taxable,
                    'gst_amount' => $gst,
                    'total_amount' => $total,
                    'commission_agent_id' => $commAgentId,
                    'commission_agent_name' => $commAgentName,
                    'commission_rate' => $commRate,
                    'commission_amount' => $commAmt,
                    'lotno' => $itemRow['lotno'] ?? null,
                    'remarks' => $itemRow['remarks'] ?? null,
                    'created_by' => auth()->id(),
                ];
            }

            $headerDiscount = isset($validated['discount_amount']) && $validated['discount_amount'] !== null && $validated['discount_amount'] !== ''
                ? (float) $validated['discount_amount']
                : $calculatedItemDiscounts;

            $headerCommission = isset($validated['commission_amount']) && $validated['commission_amount'] !== null && $validated['commission_amount'] !== ''
                ? (float) $validated['commission_amount']
                : $calculatedItemCommissions;

            $freight = (float) ($validated['freight_charges'] ?? 0);
            $grandTotal = max(0.0, round($calculatedSubtotal + $calculatedTax + $calculatedPumpCost + $freight - $headerDiscount, 2));

            $outwardData = [
                'entry_no' => $validated['entry_no'] ?? StockOutward::generateNextEntryNo(),
                'entry_type' => $validated['entry_type'] ?? 'sales',
                'party_id' => $validated['party_id'] ?? null,
                'customer' => $validated['customer'] ?? null,
                'agent_id' => $validated['agent_id'] ?? null,
                'agent_name' => $validated['agent_name'] ?? null,
                'invoice_date' => $validated['invoice_date'] ?? now()->toDateString(),
                'challan_no' => $validated['challan_no'] ?? null,
                'challan_date' => $validated['challan_date'] ?? null,
                'vehicle_no' => $validated['vehicle_no'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'driver_phone' => $validated['driver_phone'] ?? null,
                'destination' => $validated['destination'] ?? null,
                'plant_name' => $validated['plant_name'] ?? null,
                'subtotal' => $calculatedSubtotal,
                'tax_amount' => $calculatedTax,
                'discount_amount' => $headerDiscount,
                'commission_amount' => $headerCommission,
                'freight_charges' => $freight,
                'total_amount' => $grandTotal,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
            ];

            $stockOutward = StockOutward::create($outwardData);

            foreach ($itemsToCreate as $itemData) {
                $itemData['stock_outward_id'] = $stockOutward->id;
                StockOutwardItem::create($itemData);
            }

            return $stockOutward->load(['party', 'agent', 'items.item', 'items.commissionAgent', 'creator']);
        });

        return response()->json([
            'message' => 'Stock outward entry created successfully.',
            'outward' => $outward,
        ], 201);
    }

    /**
     * Display the specified stock outward entry.
     */
    public function show(StockOutward $outward): JsonResponse
    {
        $outward->load(['party', 'agent', 'items.item', 'creator', 'updater']);

        return response()->json($outward);
    }

    /**
     * Update the specified stock outward entry.
     */
    public function update(Request $request, StockOutward $outward): JsonResponse
    {
        $validated = $request->validate([
            'entry_no' => 'nullable|string|max:50|unique:stock_outwards,entry_no,'.$outward->id,
            'entry_type' => 'nullable|string|max:50',
            'party_id' => 'nullable|exists:parties,id',
            'customer' => 'nullable|string|max:255',
            'agent_id' => 'nullable|exists:parties,id',
            'agent_name' => 'nullable|string|max:255',
            'invoice_date' => 'nullable|date',
            'challan_no' => 'nullable|string|max:100',
            'challan_date' => 'nullable|date',
            'vehicle_no' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:100',
            'driver_phone' => 'nullable|string|max:30',
            'destination' => 'nullable|string|max:255',
            'plant_name' => 'nullable|string|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'commission_amount' => 'nullable|numeric|min:0',
            'freight_charges' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|integer',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.qty' => 'required|numeric|min:0.0001',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.secondary_qty' => 'nullable|numeric|min:0',
            'items.*.secondary_unit' => 'nullable|string|max:20',
            'items.*.conversion_factor' => 'nullable|numeric|min:0.0001',
            'items.*.rate' => 'nullable|numeric|min:0',
            'items.*.pump_cost' => 'nullable|numeric|min:0',
            'items.*.discount_percent' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
            'items.*.gst_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.taxable_amount' => 'nullable|numeric|min:0',
            'items.*.gst_amount' => 'nullable|numeric|min:0',
            'items.*.total_amount' => 'nullable|numeric|min:0',
            'items.*.commission_agent_id' => 'nullable|exists:parties,id',
            'items.*.commission_agent_name' => 'nullable|string|max:255',
            'items.*.commission_rate' => 'nullable|numeric|min:0',
            'items.*.commission_amount' => 'nullable|numeric|min:0',
            'items.*.lotno' => 'nullable|string|max:100',
            'items.*.remarks' => 'nullable|string|max:255',
        ]);

        if (! empty($validated['party_id']) && empty($validated['customer'])) {
            $party = Party::find($validated['party_id']);
            $validated['customer'] = $party?->name;
        }
        if (! empty($validated['agent_id']) && empty($validated['agent_name'])) {
            $agent = Party::find($validated['agent_id']);
            $validated['agent_name'] = $agent?->name;
        }

        DB::transaction(function () use ($outward, $validated) {
            $calculatedSubtotal = 0.0;
            $calculatedTax = 0.0;
            $calculatedPumpCost = 0.0;
            $calculatedItemDiscounts = 0.0;
            $calculatedItemCommissions = 0.0;

            // Delete old line items
            $outward->items()->delete();

            foreach ($validated['items'] as $itemRow) {
                $qty = (float) ($itemRow['qty'] ?? 0);
                $rate = (float) ($itemRow['rate'] ?? 0);
                $gross = $qty * $rate;

                $taxable = isset($itemRow['taxable_amount']) && $itemRow['taxable_amount'] !== null && $itemRow['taxable_amount'] !== ''
                    ? (float) $itemRow['taxable_amount']
                    : $gross;

                $gstRate = (float) ($itemRow['gst_rate'] ?? 0);
                $gst = isset($itemRow['gst_amount']) && $itemRow['gst_amount'] !== null && $itemRow['gst_amount'] !== ''
                    ? (float) $itemRow['gst_amount']
                    : round($taxable * ($gstRate / 100), 2);

                $pumpCost = (float) ($itemRow['pump_cost'] ?? 0);
                $overallCost = round($taxable + $gst + $pumpCost, 2);

                $discPercent = (float) ($itemRow['discount_percent'] ?? 0);
                $discAmt = isset($itemRow['discount_amount']) && $itemRow['discount_amount'] !== null && $itemRow['discount_amount'] !== ''
                    ? (float) $itemRow['discount_amount']
                    : ($discPercent > 0 ? round($overallCost * ($discPercent / 100), 2) : 0);

                $total = isset($itemRow['total_amount']) && $itemRow['total_amount'] !== null && $itemRow['total_amount'] !== ''
                    ? (float) $itemRow['total_amount']
                    : max(0.0, round($overallCost - $discAmt, 2));

                // Commission calculation
                $commAgentId = ! empty($itemRow['commission_agent_id']) ? (int) $itemRow['commission_agent_id'] : ($validated['agent_id'] ?? null);
                $commAgentName = $itemRow['commission_agent_name'] ?? null;
                if ($commAgentId && empty($commAgentName)) {
                    $commParty = Party::find($commAgentId);
                    $commAgentName = $commParty?->name;
                }

                $commRate = (float) ($itemRow['commission_rate'] ?? 0);
                $commAmt = isset($itemRow['commission_amount']) && $itemRow['commission_amount'] !== null && $itemRow['commission_amount'] !== ''
                    ? (float) $itemRow['commission_amount']
                    : ($commRate > 0 ? round($taxable * ($commRate / 100), 2) : 0);

                $calculatedSubtotal += $taxable;
                $calculatedTax += $gst;
                $calculatedPumpCost += $pumpCost;
                $calculatedItemDiscounts += $discAmt;
                $calculatedItemCommissions += $commAmt;

                StockOutwardItem::create([
                    'stock_outward_id' => $outward->id,
                    'item_id' => $itemRow['item_id'],
                    'qty' => $qty,
                    'unit' => $itemRow['unit'] ?? 'ton',
                    'secondary_qty' => $itemRow['secondary_qty'] ?? 0,
                    'secondary_unit' => $itemRow['secondary_unit'] ?? 'kgs',
                    'conversion_factor' => $itemRow['conversion_factor'] ?? 1.0,
                    'rate' => $rate,
                    'pump_cost' => $pumpCost,
                    'discount_percent' => $discPercent,
                    'discount_amount' => $discAmt,
                    'gst_rate' => $gstRate,
                    'taxable_amount' => $taxable,
                    'gst_amount' => $gst,
                    'total_amount' => $total,
                    'commission_agent_id' => $commAgentId,
                    'commission_agent_name' => $commAgentName,
                    'commission_rate' => $commRate,
                    'commission_amount' => $commAmt,
                    'lotno' => $itemRow['lotno'] ?? null,
                    'remarks' => $itemRow['remarks'] ?? null,
                    'created_by' => auth()->id(),
                ]);
            }

            $headerDiscount = isset($validated['discount_amount']) && $validated['discount_amount'] !== null && $validated['discount_amount'] !== ''
                ? (float) $validated['discount_amount']
                : $calculatedItemDiscounts;

            $headerCommission = isset($validated['commission_amount']) && $validated['commission_amount'] !== null && $validated['commission_amount'] !== ''
                ? (float) $validated['commission_amount']
                : $calculatedItemCommissions;

            $freight = (float) ($validated['freight_charges'] ?? 0);
            $grandTotal = max(0.0, round($calculatedSubtotal + $calculatedTax + $calculatedPumpCost + $freight - $headerDiscount, 2));

            $outward->update([
                'entry_no' => $validated['entry_no'] ?? $outward->entry_no,
                'entry_type' => $validated['entry_type'] ?? $outward->entry_type,
                'party_id' => $validated['party_id'] ?? null,
                'customer' => $validated['customer'] ?? null,
                'agent_id' => $validated['agent_id'] ?? null,
                'agent_name' => $validated['agent_name'] ?? null,
                'invoice_date' => $validated['invoice_date'] ?? $outward->invoice_date,
                'challan_no' => $validated['challan_no'] ?? null,
                'challan_date' => $validated['challan_date'] ?? null,
                'vehicle_no' => $validated['vehicle_no'] ?? null,
                'driver_name' => $validated['driver_name'] ?? null,
                'driver_phone' => $validated['driver_phone'] ?? null,
                'destination' => $validated['destination'] ?? null,
                'plant_name' => $validated['plant_name'] ?? null,
                'subtotal' => $calculatedSubtotal,
                'tax_amount' => $calculatedTax,
                'discount_amount' => $headerDiscount,
                'commission_amount' => $headerCommission,
                'freight_charges' => $freight,
                'total_amount' => $grandTotal,
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => auth()->id(),
            ]);
        });

        return response()->json([
            'message' => 'Stock outward entry updated successfully.',
            'outward' => $outward->fresh(['party', 'agent', 'items.item', 'items.commissionAgent', 'creator', 'updater']),
        ]);
    }

    /**
     * Remove the specified stock outward entry (soft delete).
     */
    public function destroy(StockOutward $outward): JsonResponse
    {
        DB::transaction(function () use ($outward) {
            $outward->items()->delete();
            $outward->delete();
        });

        return response()->json([
            'message' => 'Stock outward entry deleted successfully.',
        ]);
    }
}
