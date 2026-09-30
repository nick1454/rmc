<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Models\PurchasePayment;
use App\Models\PurchasePaymentItem;
use App\Models\StockInward;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PurchasePaymentController extends Controller
{
    /**
     * Display a listing of purchase payments.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PurchasePayment::with([
            'party:id,name,code,phone,gstin,city',
            'items.inward:id,inward_no,challan_no,supplier_bill_no,challan_date,grand_total,paid_amount,payment_status',
            'creator:id,name',
            'updater:id,name',
        ])->withCount('items');

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('payment_no', 'like', "%{$search}%")
                    ->orWhere('party_name', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('party', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        // Party / Supplier filter
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
     * Get metadata for Payment Voucher creation/filtering.
     */
    public function meta(): JsonResponse
    {
        $nextPaymentNo = PurchasePayment::generateNextPaymentNo();

        $suppliers = Party::whereIn('type', ['supplier', 'both'])
            ->where(function ($q) {
                $q->where('status', 'active')->orWhereNull('status');
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'phone', 'gstin', 'city', 'type']);

        $paymentModes = [
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer (NEFT/RTGS/IMPS)'],
            ['value' => 'cheque', 'label' => 'Cheque / Demand Draft'],
            ['value' => 'upi', 'label' => 'UPI / NetBanking'],
            ['value' => 'cash', 'label' => 'Cash'],
            ['value' => 'other', 'label' => 'Other / Adjustment'],
        ];

        return response()->json([
            'next_payment_no' => $nextPaymentNo,
            'suppliers' => $suppliers,
            'payment_modes' => $paymentModes,
            'data' => [
                'next_payment_no' => $nextPaymentNo,
                'suppliers' => $suppliers,
                'payment_modes' => $paymentModes,
            ],
            'status' => 'success',
        ]);
    }

    /**
     * Get pending / partially paid inward invoices for a supplier.
     */
    public function pendingInvoices(Request $request, int $partyId): JsonResponse
    {
        $excludePaymentId = $request->integer('exclude_payment_id', 0);

        $inwards = StockInward::with(['items.item:id,category,size,primary_unit'])
            ->where('supplier_party_id', $partyId)
            ->where(function ($q) use ($excludePaymentId) {
                $q->where('payment_status', '!=', 'paid')
                    ->orWhere('paid_amount', '<', DB::raw('grand_total'));

                if ($excludePaymentId > 0) {
                    $q->orWhereHas('paymentItems', function ($piq) use ($excludePaymentId) {
                        $piq->where('purchase_payment_id', $excludePaymentId);
                    });
                }
            })
            ->orderBy('challan_date', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $data = $inwards->map(function ($inw) use ($excludePaymentId) {
            $alreadyPaidOthers = (float) $inw->paymentItems()
                ->whereHas('payment', function ($q) use ($excludePaymentId) {
                    $q->whereNull('deleted_at');
                    if ($excludePaymentId > 0) {
                        $q->where('id', '!=', $excludePaymentId);
                    }
                })
                ->sum('paid_amount');

            $currentPaymentAllocated = 0.0;
            if ($excludePaymentId > 0) {
                $currentPaymentAllocated = (float) $inw->paymentItems()
                    ->where('purchase_payment_id', $excludePaymentId)
                    ->sum('paid_amount');
            }

            $grandTotal = (float) $inw->grand_total;
            $balanceDue = max(0.0, round($grandTotal - $alreadyPaidOthers, 2));

            // Summary of materials in this invoice
            $materials = $inw->items->map(function ($it) {
                $name = trim(($it->item?->category ?? 'RM').' '.($it->item?->size ?? ''));

                return "{$name} (".((float) $it->qty_primary)." {$it->unit_primary})";
            })->implode(', ');

            $challanDate = $inw->challan_date ?: $inw->inward_date;
            if ($challanDate instanceof Carbon || $challanDate instanceof \DateTimeInterface) {
                $formattedDate = $challanDate->format('Y-m-d');
            } elseif (is_string($challanDate) && ! empty($challanDate)) {
                $formattedDate = substr($challanDate, 0, 10);
            } else {
                $formattedDate = null;
            }

            return [
                'id' => $inw->id,
                'inward_no' => $inw->inward_no,
                'challan_no' => $inw->challan_no ?: $inw->supplier_bill_no,
                'challan_date' => $formattedDate,
                'grand_total' => $grandTotal,
                'already_paid_others' => $alreadyPaidOthers,
                'current_allocated' => $currentPaymentAllocated,
                'balance_due' => $balanceDue,
                'payment_status' => $inw->payment_status,
                'materials_summary' => $materials,
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
     * Store a newly created purchase payment voucher.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'payment_no' => ['nullable', 'string', 'max:50', Rule::unique('purchase_payments', 'payment_no')->whereNull('deleted_at')],
            'payment_date' => ['required', 'date'],
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'payment_mode' => ['required', 'string', 'max:50'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_inward_id' => ['required', 'integer', 'exists:stock_inwards,id'],
            'items.*.paid_amount' => ['required', 'numeric', 'min:0.01'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $party = Party::findOrFail($validated['party_id']);
        $validated['party_name'] = $party->name;

        // Verify total allocated items does not exceed payment amount
        $totalAllocated = 0;
        foreach ($validated['items'] as $item) {
            $totalAllocated += (float) $item['paid_amount'];
        }

        $paymentAmount = (float) $validated['amount'];
        if (round($totalAllocated, 2) > round($paymentAmount, 2)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Total allocated amount (₹ '.number_format($totalAllocated, 2).') cannot exceed payment amount (₹ '.number_format($paymentAmount, 2).').',
            ], 422);
        }

        $payment = DB::transaction(function () use ($validated) {
            $payment = PurchasePayment::create([
                'payment_no' => $validated['payment_no'] ?? PurchasePayment::generateNextPaymentNo(),
                'payment_date' => $validated['payment_date'],
                'party_id' => $validated['party_id'],
                'party_name' => $validated['party_name'],
                'payment_mode' => $validated['payment_mode'],
                'reference_no' => $validated['reference_no'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'amount' => $validated['amount'],
                'remarks' => $validated['remarks'] ?? null,
            ]);

            foreach ($validated['items'] as $itemInput) {
                $inward = StockInward::lockForUpdate()->findOrFail($itemInput['stock_inward_id']);

                PurchasePaymentItem::create([
                    'purchase_payment_id' => $payment->id,
                    'stock_inward_id' => $inward->id,
                    'invoice_amount' => $inward->grand_total,
                    'paid_amount' => $itemInput['paid_amount'],
                    'remarks' => $itemInput['remarks'] ?? null,
                ]);

                $inward->recalculatePaymentStatus();
            }

            return $payment;
        });

        $payment->load([
            'party',
            'items.inward',
            'creator',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase payment voucher recorded successfully.',
            'data' => $payment,
        ], 201);
    }

    /**
     * Display the specified purchase payment voucher.
     */
    public function show(int $id): JsonResponse
    {
        $payment = PurchasePayment::with([
            'party:id,name,code,phone,gstin,city,address',
            'items.inward.items.item',
            'creator:id,name',
            'updater:id,name',
        ])->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $payment,
        ]);
    }

    /**
     * Update the specified purchase payment voucher.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $payment = PurchasePayment::findOrFail($id);

        $validated = $request->validate([
            'payment_no' => ['required', 'string', 'max:50', Rule::unique('purchase_payments', 'payment_no')->ignore($id)->whereNull('deleted_at')],
            'payment_date' => ['required', 'date'],
            'party_id' => ['required', 'integer', 'exists:parties,id'],
            'party_name' => ['nullable', 'string', 'max:255'],
            'payment_mode' => ['required', 'string', 'max:50'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_inward_id' => ['required', 'integer', 'exists:stock_inwards,id'],
            'items.*.paid_amount' => ['required', 'numeric', 'min:0.01'],
            'items.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $party = Party::findOrFail($validated['party_id']);
        $validated['party_name'] = $party->name;

        // Verify total allocated items does not exceed payment amount
        $totalAllocated = 0;
        foreach ($validated['items'] as $item) {
            $totalAllocated += (float) $item['paid_amount'];
        }

        $paymentAmount = (float) $validated['amount'];
        if (round($totalAllocated, 2) > round($paymentAmount, 2)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Total allocated amount (₹ '.number_format($totalAllocated, 2).') cannot exceed payment amount (₹ '.number_format($paymentAmount, 2).').',
            ], 422);
        }

        DB::transaction(function () use ($payment, $validated) {
            // Track old inward ids to recalculate after update
            $oldInwardIds = $payment->items()->pluck('stock_inward_id')->toArray();

            $payment->update([
                'payment_no' => $validated['payment_no'],
                'payment_date' => $validated['payment_date'],
                'party_id' => $validated['party_id'],
                'party_name' => $validated['party_name'],
                'payment_mode' => $validated['payment_mode'],
                'reference_no' => $validated['reference_no'] ?? null,
                'bank_name' => $validated['bank_name'] ?? null,
                'amount' => $validated['amount'],
                'remarks' => $validated['remarks'] ?? null,
            ]);

            // Remove existing allocations and re-create
            $payment->items()->delete();

            $newInwardIds = [];
            foreach ($validated['items'] as $itemInput) {
                $inward = StockInward::lockForUpdate()->findOrFail($itemInput['stock_inward_id']);
                $newInwardIds[] = $inward->id;

                PurchasePaymentItem::create([
                    'purchase_payment_id' => $payment->id,
                    'stock_inward_id' => $inward->id,
                    'invoice_amount' => $inward->grand_total,
                    'paid_amount' => $itemInput['paid_amount'],
                    'remarks' => $itemInput['remarks'] ?? null,
                ]);
            }

            // Recalculate all affected inwards (both old and new)
            $allAffectedIds = array_unique(array_merge($oldInwardIds, $newInwardIds));
            foreach ($allAffectedIds as $inwId) {
                $inw = StockInward::find($inwId);
                $inw?->recalculatePaymentStatus();
            }
        });

        $payment->load([
            'party',
            'items.inward',
            'creator',
            'updater',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase payment voucher updated successfully.',
            'data' => $payment,
        ]);
    }

    /**
     * Remove the specified purchase payment voucher.
     */
    public function destroy(int $id): JsonResponse
    {
        $payment = PurchasePayment::findOrFail($id);

        DB::transaction(function () use ($payment) {
            $affectedInwardIds = $payment->items()->pluck('stock_inward_id')->toArray();

            $payment->delete(); // Soft delete

            // Recalculate payment status on all affected inwards
            foreach ($affectedInwardIds as $inwId) {
                $inw = StockInward::find($inwId);
                $inw?->recalculatePaymentStatus();
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Purchase payment voucher deleted successfully.',
        ]);
    }
}
