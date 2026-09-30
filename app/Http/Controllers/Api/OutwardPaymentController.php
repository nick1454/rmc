<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutwardPayment;
use App\Models\OutwardPaymentItem;
use App\Models\Party;
use App\Models\StockOutward;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OutwardPaymentController extends Controller
{
    /**
     * Display a listing of outward payment receipts (customer receivables).
     */
    public function index(Request $request): JsonResponse
    {
        $query = OutwardPayment::with([
            'party:id,name,code,phone,gstin,city',
            'commissionAgent:id,name,code,phone',
            'items.outward:id,entry_no,challan_no,invoice_date,total_amount,paid_amount,payment_status,destination',
            'items.commissionAgent:id,name,code',
            'creator:id,name',
            'updater:id,name',
            'lockedBy:id,name',
        ])->withCount('items');

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_no', 'like', "%{$search}%")
                    ->orWhere('party_name', 'like', "%{$search}%")
                    ->orWhere('commission_agent_name', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('party', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('commissionAgent', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
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

        // Payment Mode filter
        if ($mode = $request->input('payment_mode')) {
            if ($mode !== 'all') {
                $query->where('payment_mode', $mode);
            }
        }

        // Date range filter
        if ($fromDate = $request->input('from_date')) {
            $query->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate = $request->input('to_date')) {
            $query->whereDate('payment_date', '<=', $toDate);
        }

        $perPage = (int) $request->input('per_page', 5);
        $payments = $query->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $payments->items(),
            'meta' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /**
     * Get metadata for Payment Receipt Voucher creation and filtering.
     */
    public function meta(): JsonResponse
    {
        $nextPaymentNo = OutwardPayment::generateNextPaymentNo();

        $allParties = Party::active()
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'phone', 'gstin', 'city', 'type', 'address']);

        $customers = $allParties->filter(fn ($p) => in_array($p->type, ['customer', 'both', 'vendor', 'supplier']))->values();
        $agents = $allParties->filter(fn ($p) => in_array($p->type, ['agent', 'broker', 'other', 'both']))->values();

        $paymentModes = [
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer (NEFT/RTGS/IMPS)'],
            ['value' => 'cheque', 'label' => 'Cheque / Demand Draft'],
            ['value' => 'upi', 'label' => 'UPI / NetBanking'],
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'other', 'label' => 'Other / Adjustment'],
        ];

        return response()->json([
            'next_payment_no' => $nextPaymentNo,
            'customers' => $customers->isNotEmpty() ? $customers : $allParties,
            'agents' => $agents->isNotEmpty() ? $agents : $allParties,
            'payment_modes' => $paymentModes,
            'data' => [
                'next_payment_no' => $nextPaymentNo,
                'customers' => $customers->isNotEmpty() ? $customers : $allParties,
                'agents' => $agents->isNotEmpty() ? $agents : $allParties,
                'payment_modes' => $paymentModes,
            ],
            'status' => 'success',
        ]);
    }

    /**
     * Get pending / partially paid outward dispatches/invoices for a customer.
     */
    public function pendingInvoices(Request $request, int $partyId): JsonResponse
    {
        $excludePaymentId = $request->integer('exclude_payment_id', 0);

        $outwards = StockOutward::with(['items.item:id,category,size,primary_unit', 'items.commissionAgent'])
            ->where('party_id', $partyId)
            ->whereNotIn('entry_type', ['return', 'return_to_supplier'])
            ->where(function ($q) use ($excludePaymentId) {
                $q->where('payment_status', '!=', 'paid')
                    ->orWhere('paid_amount', '<', DB::raw('total_amount'));

                if ($excludePaymentId > 0) {
                    $q->orWhereHas('paymentItems', function ($piq) use ($excludePaymentId) {
                        $piq->where('outward_payment_id', $excludePaymentId);
                    });
                }
            })
            ->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $data = $outwards->map(function ($outward) use ($excludePaymentId) {
            $prevPaymentItems = $outward->paymentItems()
                ->whereHas('payment', function ($q) use ($excludePaymentId) {
                    $q->whereNull('deleted_at');
                    if ($excludePaymentId > 0) {
                        $q->where('id', '!=', $excludePaymentId);
                    }
                })
                ->get(['paid_amount', 'discount_amount']);

            $alreadyPaidOthers = (float) $prevPaymentItems->sum('paid_amount');
            $alreadyDiscountedOthers = (float) $prevPaymentItems->sum('discount_amount');

            $currentPaymentAllocated = 0.0;
            $currentDiscountAllocated = 0.0;
            if ($excludePaymentId > 0) {
                $curItems = $outward->paymentItems()
                    ->where('outward_payment_id', $excludePaymentId)
                    ->get(['paid_amount', 'discount_amount']);
                $currentPaymentAllocated = (float) $curItems->sum('paid_amount');
                $currentDiscountAllocated = (float) $curItems->sum('discount_amount');
            }

            $totalAmount = (float) $outward->total_amount;
            $balanceDue = max(0.0, round($totalAmount - ($alreadyPaidOthers + $alreadyDiscountedOthers), 2));

            // Summary of dispatched items
            $itemsSummary = $outward->items->map(function ($it) {
                $name = trim(($it->item?->category ?? 'Concrete').' '.($it->item?->size ?? ''));

                return "{$name} (".((float) $it->qty)." {$it->unit})";
            })->implode(', ');

            $invDate = $outward->invoice_date ?: $outward->challan_date;
            if ($invDate instanceof Carbon || $invDate instanceof \DateTimeInterface) {
                $formattedDate = $invDate->format('Y-m-d');
            } elseif (is_string($invDate) && ! empty($invDate)) {
                $formattedDate = substr($invDate, 0, 10);
            } else {
                $formattedDate = null;
            }

            return [
                'id' => $outward->id,
                'entry_no' => $outward->entry_no,
                'challan_no' => $outward->challan_no,
                'invoice_date' => $formattedDate,
                'destination' => $outward->destination,
                'agent_id' => $outward->agent_id,
                'agent_name' => $outward->agent_name,
                'total_amount' => $totalAmount,
                'already_paid_others' => $alreadyPaidOthers,
                'already_discounted_others' => $alreadyDiscountedOthers,
                'current_allocated' => $currentPaymentAllocated,
                'current_discount' => $currentDiscountAllocated,
                'balance_due' => $balanceDue,
                'payment_status' => $outward->payment_status,
                'items_summary' => $itemsSummary,
            ];
        })->filter(function ($item) {
            return $item['balance_due'] > 0 || $item['current_allocated'] > 0;
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * Get all pending outward sales invoices to customers across the system.
     * Excludes returns, internal transfers, and fully settled invoices.
     */
    public function allPendingOutwards(Request $request): JsonResponse
    {
        $query = StockOutward::with([
            'party:id,name,code,phone,city,type',
            'items.item:id,category,size,primary_unit',
            'agent:id,name',
        ])
            ->whereNotIn('entry_type', ['return', 'return_to_supplier', 'transfer'])
            ->where(function ($q) {
                $q->where('payment_status', '!=', 'paid')
                    ->orWhere('paid_amount', '<', DB::raw('total_amount'));
            });

        // Filter by specific customer
        if ($partyId = $request->input('party_id')) {
            if ($partyId !== 'all') {
                $query->where('party_id', $partyId);
            }
        }

        // Search by outward #, customer name, challan #, vehicle, destination
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('entry_no', 'like', "%{$search}%")
                    ->orWhere('customer', 'like', "%{$search}%")
                    ->orWhere('challan_no', 'like', "%{$search}%")
                    ->orWhere('vehicle_no', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhereHas('party', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $outwards = $query->orderBy('invoice_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $data = $outwards->map(function ($outward) {
            $paymentItems = $outward->paymentItems()
                ->whereHas('payment', function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->get(['paid_amount', 'discount_amount']);

            $alreadyPaid = (float) $paymentItems->sum('paid_amount');
            $alreadyDiscounted = (float) $paymentItems->sum('discount_amount');
            $totalSettled = $alreadyPaid + $alreadyDiscounted;
            $totalAmount = (float) $outward->total_amount;
            $balanceDue = max(0.0, round($totalAmount - $totalSettled, 2));

            if ($balanceDue <= 0) {
                return null;
            }

            $itemsSummary = $outward->items->map(function ($it) {
                $name = trim(($it->item?->category ?? 'Concrete').' '.($it->item?->size ?? ''));

                return "{$name} (".((float) $it->qty)." {$it->unit})";
            })->implode(', ');

            $invDate = $outward->invoice_date ?: $outward->challan_date;
            if ($invDate instanceof Carbon || $invDate instanceof \DateTimeInterface) {
                $formattedDate = $invDate->format('Y-m-d');
            } elseif (is_string($invDate) && ! empty($invDate)) {
                $formattedDate = substr($invDate, 0, 10);
            } else {
                $formattedDate = null;
            }

            return [
                'id' => $outward->id,
                'entry_no' => $outward->entry_no,
                'entry_type' => $outward->entry_type ?? 'sales',
                'party_id' => $outward->party_id,
                'party_name' => $outward->party?->name ?? $outward->customer ?? '—',
                'party_code' => $outward->party?->code ?? '',
                'party_city' => $outward->party?->city ?? '',
                'party_phone' => $outward->party?->phone ?? '',
                'agent_name' => $outward->agent?->name ?? $outward->agent_name ?? '',
                'invoice_date' => $formattedDate,
                'challan_no' => $outward->challan_no ?? '',
                'vehicle_no' => $outward->vehicle_no ?? '',
                'destination' => $outward->destination ?? '',
                'total_amount' => $totalAmount,
                'already_paid' => $alreadyPaid,
                'already_discounted' => $alreadyDiscounted,
                'balance_due' => $balanceDue,
                'payment_status' => $outward->payment_status,
                'items_summary' => $itemsSummary,
            ];
        })->filter()->values();

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'total_pending_count' => $data->count(),
            'total_pending_amount' => round($data->sum('balance_due'), 2),
        ]);
    }

    /**
     * Store a newly created outward payment receipt voucher.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_no' => ['nullable', 'string', 'max:50', Rule::unique('outward_payments', 'payment_no')->whereNull('deleted_at')],
            'payment_date' => ['required', 'date'],
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'payment_mode' => ['required', 'string', 'max:50'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'commission_agent_id' => ['nullable', 'integer', 'exists:parties,id'],
            'commission_agent_name' => ['nullable', 'string', 'max:255'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_outward_id' => ['required', 'integer', 'exists:stock_outwards,id'],
            'items.*.paid_amount' => ['required', 'numeric', 'min:0.01'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.commission_agent_id' => ['nullable', 'integer', 'exists:parties,id'],
            'items.*.commission_agent_name' => ['nullable', 'string', 'max:255'],
            'items.*.commission_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $party = Party::findOrFail($validated['party_id']);
        $validated['party_name'] = $party->name;

        if (! empty($validated['commission_agent_id']) && empty($validated['commission_agent_name'])) {
            $agent = Party::find($validated['commission_agent_id']);
            $validated['commission_agent_name'] = $agent?->name;
        }

        // Verify total allocated items does not exceed payment receipt amount
        $totalAllocated = 0;
        $totalItemDiscounts = 0;
        $totalItemCommissions = 0;

        foreach ($validated['items'] as $item) {
            $totalAllocated += (float) $item['paid_amount'];
            $totalItemDiscounts += (float) ($item['discount_amount'] ?? 0);
            $totalItemCommissions += (float) ($item['commission_amount'] ?? 0);
        }

        $paymentAmount = (float) $validated['amount'];
        if (round($totalAllocated, 2) > round($paymentAmount, 2)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Total allocated amount (₹ '.number_format($totalAllocated, 2).') cannot exceed payment receipt amount (₹ '.number_format($paymentAmount, 2).').',
            ], 422);
        }

        $headerDiscount = isset($validated['discount_amount']) && $validated['discount_amount'] !== null && $validated['discount_amount'] !== ''
            ? (float) $validated['discount_amount']
            : $totalItemDiscounts;

        $headerCommission = isset($validated['commission_amount']) && $validated['commission_amount'] !== null && $validated['commission_amount'] !== ''
            ? (float) $validated['commission_amount']
            : $totalItemCommissions;

        $payment = DB::transaction(function () use ($validated, $headerDiscount, $headerCommission) {
            $payment = OutwardPayment::create([
                'payment_no' => $validated['payment_no'] ?? OutwardPayment::generateNextPaymentNo(),
                'payment_date' => $validated['payment_date'],
                'party_id' => $validated['party_id'],
                'party_name' => $validated['party_name'],
                'payment_mode' => $validated['payment_mode'],
                'reference_no' => $validated['reference_no'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'discount_amount' => $headerDiscount,
                'commission_agent_id' => $validated['commission_agent_id'] ?? null,
                'commission_agent_name' => $validated['commission_agent_name'] ?? null,
                'commission_amount' => $headerCommission,
                'amount' => $validated['amount'],
                'remarks' => $validated['remarks'] ?? null,
            ]);

            foreach ($validated['items'] as $itemInput) {
                $outward = StockOutward::lockForUpdate()->findOrFail($itemInput['stock_outward_id']);

                $itemAgentId = ! empty($itemInput['commission_agent_id']) ? (int) $itemInput['commission_agent_id'] : ($validated['commission_agent_id'] ?? null);
                $itemAgentName = $itemInput['commission_agent_name'] ?? null;
                if ($itemAgentId && empty($itemAgentName)) {
                    $agent = Party::find($itemAgentId);
                    $itemAgentName = $agent?->name;
                }

                OutwardPaymentItem::create([
                    'outward_payment_id' => $payment->id,
                    'stock_outward_id' => $outward->id,
                    'invoice_amount' => $outward->total_amount,
                    'paid_amount' => $itemInput['paid_amount'],
                    'discount_amount' => $itemInput['discount_amount'] ?? 0,
                    'commission_agent_id' => $itemAgentId,
                    'commission_agent_name' => $itemAgentName,
                    'commission_amount' => $itemInput['commission_amount'] ?? 0,
                    'remarks' => $itemInput['remarks'] ?? null,
                ]);

                $outward->recalculatePaymentStatus();
            }

            return $payment;
        });

        $payment->load([
            'party',
            'commissionAgent',
            'items.outward',
            'items.commissionAgent',
            'creator',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Outward payment receipt voucher recorded successfully.',
            'data' => $payment,
        ], 201);
    }

    /**
     * Display the specified outward payment receipt voucher.
     */
    public function show(int $id): JsonResponse
    {
        $payment = OutwardPayment::with([
            'party:id,name,code,phone,gstin,city,address',
            'commissionAgent:id,name,code,phone',
            'items.outward.items.item',
            'items.commissionAgent:id,name,code',
            'creator:id,name',
            'updater:id,name',
            'lockedBy:id,name',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $payment,
        ]);
    }

    /**
     * Update the specified outward payment receipt voucher.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $payment = OutwardPayment::findOrFail($id);

        if ($payment->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'This customer receipt voucher is locked and cannot be modified. Please unlock it first.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_no' => ['required', 'string', 'max:50', Rule::unique('outward_payments', 'payment_no')->ignore($id)->whereNull('deleted_at')],
            'payment_date' => ['required', 'date'],
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'payment_mode' => ['required', 'string', 'max:50'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'commission_agent_id' => ['nullable', 'integer', 'exists:parties,id'],
            'commission_agent_name' => ['nullable', 'string', 'max:255'],
            'commission_amount' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_outward_id' => ['required', 'integer', 'exists:stock_outwards,id'],
            'items.*.paid_amount' => ['required', 'numeric', 'min:0.01'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.commission_agent_id' => ['nullable', 'integer', 'exists:parties,id'],
            'items.*.commission_agent_name' => ['nullable', 'string', 'max:255'],
            'items.*.commission_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $party = Party::findOrFail($validated['party_id']);
        $validated['party_name'] = $party->name;

        if (! empty($validated['commission_agent_id']) && empty($validated['commission_agent_name'])) {
            $agent = Party::find($validated['commission_agent_id']);
            $validated['commission_agent_name'] = $agent?->name;
        }

        // Verify total allocated items does not exceed payment receipt amount
        $totalAllocated = 0;
        $totalItemDiscounts = 0;
        $totalItemCommissions = 0;

        foreach ($validated['items'] as $item) {
            $totalAllocated += (float) $item['paid_amount'];
            $totalItemDiscounts += (float) ($item['discount_amount'] ?? 0);
            $totalItemCommissions += (float) ($item['commission_amount'] ?? 0);
        }

        $paymentAmount = (float) $validated['amount'];
        if (round($totalAllocated, 2) > round($paymentAmount, 2)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Total allocated amount (₹ '.number_format($totalAllocated, 2).') cannot exceed payment receipt amount (₹ '.number_format($paymentAmount, 2).').',
            ], 422);
        }

        $headerDiscount = isset($validated['discount_amount']) && $validated['discount_amount'] !== null && $validated['discount_amount'] !== ''
            ? (float) $validated['discount_amount']
            : $totalItemDiscounts;

        $headerCommission = isset($validated['commission_amount']) && $validated['commission_amount'] !== null && $validated['commission_amount'] !== ''
            ? (float) $validated['commission_amount']
            : $totalItemCommissions;

        DB::transaction(function () use ($payment, $validated, $headerDiscount, $headerCommission) {
            // Track old outward ids to recalculate after update
            $oldOutwardIds = $payment->items()->pluck('stock_outward_id')->toArray();

            $payment->update([
                'payment_no' => $validated['payment_no'],
                'payment_date' => $validated['payment_date'],
                'party_id' => $validated['party_id'],
                'party_name' => $validated['party_name'],
                'payment_mode' => $validated['payment_mode'],
                'reference_no' => $validated['reference_no'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'discount_amount' => $headerDiscount,
                'commission_agent_id' => $validated['commission_agent_id'] ?? null,
                'commission_agent_name' => $validated['commission_agent_name'] ?? null,
                'commission_amount' => $headerCommission,
                'amount' => $validated['amount'],
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // Remove existing allocations and re-create
            $payment->items()->delete();

            $newOutwardIds = [];
            foreach ($validated['items'] as $itemInput) {
                $outward = StockOutward::lockForUpdate()->findOrFail($itemInput['stock_outward_id']);
                $newOutwardIds[] = $outward->id;

                $itemAgentId = ! empty($itemInput['commission_agent_id']) ? (int) $itemInput['commission_agent_id'] : ($validated['commission_agent_id'] ?? null);
                $itemAgentName = $itemInput['commission_agent_name'] ?? null;
                if ($itemAgentId && empty($itemAgentName)) {
                    $agent = Party::find($itemAgentId);
                    $itemAgentName = $agent?->name;
                }

                OutwardPaymentItem::create([
                    'outward_payment_id' => $payment->id,
                    'stock_outward_id' => $outward->id,
                    'invoice_amount' => $outward->total_amount,
                    'paid_amount' => $itemInput['paid_amount'],
                    'discount_amount' => $itemInput['discount_amount'] ?? 0,
                    'commission_agent_id' => $itemAgentId,
                    'commission_agent_name' => $itemAgentName,
                    'commission_amount' => $itemInput['commission_amount'] ?? 0,
                    'remarks' => $itemInput['remarks'] ?? null,
                ]);
            }

            // Recalculate all affected outwards (both old and new)
            $allAffectedIds = array_unique(array_merge($oldOutwardIds, $newOutwardIds));
            foreach ($allAffectedIds as $outId) {
                $out = StockOutward::find($outId);
                $out?->recalculatePaymentStatus();
            }
        });

        $payment->load([
            'party',
            'commissionAgent',
            'items.outward',
            'items.commissionAgent',
            'creator',
            'updater',
            'lockedBy',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Outward payment receipt voucher updated successfully.',
            'data' => $payment,
        ]);
    }

    /**
     * Remove the specified outward payment receipt voucher (soft delete).
     */
    public function destroy(int $id): JsonResponse
    {
        $payment = OutwardPayment::findOrFail($id);

        if ($payment->is_locked) {
            return response()->json([
                'status' => 'error',
                'message' => 'This customer receipt voucher is locked and cannot be deleted. Please unlock it first.',
            ], 422);
        }

        DB::transaction(function () use ($payment) {
            $affectedOutwardIds = $payment->items()->pluck('stock_outward_id')->toArray();

            $payment->delete(); // Soft delete

            // Recalculate payment status on all affected outwards
            foreach ($affectedOutwardIds as $outId) {
                $out = StockOutward::find($outId);
                $out?->recalculatePaymentStatus();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Outward payment receipt voucher deleted successfully.',
        ]);
    }

    /**
     * Toggle lock status for an outward payment receipt voucher.
     */
    public function toggleLock(int $id): JsonResponse
    {
        $payment = OutwardPayment::findOrFail($id);

        $isCurrentlyLocked = (bool) $payment->is_locked;
        $newLockedState = ! $isCurrentlyLocked;

        $payment->update([
            'is_locked' => $newLockedState,
            'locked_at' => $newLockedState ? Carbon::now() : null,
            'locked_by' => $newLockedState ? auth()->id() : null,
        ]);

        $payment->load([
            'party:id,name,code,phone,gstin,city',
            'commissionAgent:id,name,code,phone',
            'items.outward:id,entry_no,challan_no,invoice_date,total_amount,paid_amount,payment_status,destination',
            'items.commissionAgent:id,name,code',
            'creator:id,name',
            'updater:id,name',
            'lockedBy:id,name',
        ]);

        $statusText = $newLockedState ? 'locked' : 'unlocked';

        return response()->json([
            'status' => 'success',
            'message' => "Customer receipt voucher {$statusText} successfully.",
            'data' => $payment,
        ]);
    }
}
