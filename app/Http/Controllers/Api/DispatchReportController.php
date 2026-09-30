<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Party;
use App\Models\StockOutward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispatchReportController extends Controller
{
    /**
     * Get the consolidated Dispatch, Production Payment & Billing Register report.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockOutward::with([
            'party',
            'agent',
            'items.item',
            'items.commissionAgent',
            'paymentItems.payment.party',
        ])->withSum(['paymentItems as payment_discount_amount' => function ($q) {
            $q->whereHas('payment', fn ($pq) => $pq->whereNull('deleted_at'));
        }], 'discount_amount');

        // Filter: Customer / Party
        $partyId = $request->query('party_id');
        if ($partyId && $partyId !== 'all') {
            $query->where('party_id', $partyId);
        }

        // Filter: Sales Agent / Seller
        $agentId = $request->query('agent_id');
        if ($agentId && $agentId !== 'all') {
            $query->where(function ($q) use ($agentId) {
                $q->where('agent_id', $agentId)
                    ->orWhereHas('items', function ($iq) use ($agentId) {
                        $iq->where('commission_agent_id', $agentId);
                    });
            });
        }

        // Filter: Concrete Grade / Item
        $itemId = $request->query('item_id');
        $grade = $request->query('grade');
        if ($itemId && $itemId !== 'all') {
            $query->whereHas('items', function ($iq) use ($itemId) {
                $iq->where('item_id', $itemId);
            });
        } elseif ($grade && $grade !== 'all') {
            $query->whereHas('items.item', function ($itq) use ($grade) {
                $itq->where('category', $grade)
                    ->orWhere('size', $grade);
            });
        }

        // Filter: Payment Status (all, paid, partially_paid, unpaid)
        $paymentStatus = $request->query('payment_status');
        if ($paymentStatus && $paymentStatus !== 'all') {
            $query->where('payment_status', $paymentStatus);
        }

        // Filter: Date Range
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        if ($fromDate) {
            $query->where(function ($q) use ($fromDate) {
                $q->whereDate('invoice_date', '>=', $fromDate)
                    ->orWhereDate('challan_date', '>=', $fromDate);
            });
        }
        if ($toDate) {
            $query->where(function ($q) use ($toDate) {
                $q->whereDate('invoice_date', '<=', $toDate)
                    ->orWhereDate('challan_date', '<=', $toDate);
            });
        }

        // Search Filter
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('entry_no', 'like', "%{$search}%")
                    ->orWhere('challan_no', 'like', "%{$search}%")
                    ->orWhere('customer', 'like', "%{$search}%")
                    ->orWhere('agent_name', 'like', "%{$search}%")
                    ->orWhere('vehicle_no', 'like', "%{$search}%")
                    ->orWhere('driver_name', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhere('plant_name', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('party', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    })
                    ->orWhereHas('items', function ($iq) use ($search) {
                        $iq->where('remarks', 'like', "%{$search}%")
                            ->orWhere('lotno', 'like', "%{$search}%")
                            ->orWhere('commission_agent_name', 'like', "%{$search}%")
                            ->orWhereHas('item', function ($itq) use ($search) {
                                $itq->where('category', 'like', "%{$search}%")
                                    ->orWhere('size', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $sortOrder = $request->query('sort', 'asc'); // 'asc' for chronological ledger register, 'desc' for latest first
        $outwards = $query->orderBy('invoice_date', $sortOrder === 'asc' ? 'asc' : 'desc')
            ->orderBy('id', $sortOrder === 'asc' ? 'asc' : 'desc')
            ->get();

        $rows = [];
        $totalQty = 0;
        $totalAmount = 0;
        $totalAdvance = 0;
        $totalReceived = 0;
        $totalPending = 0;
        $totalRelief = 0;
        $totalCommission = 0;

        foreach ($outwards as $outward) {
            $items = $outward->items;
            $itemCount = $items->count();

            // Extract payment metadata
            $paymentModes = [];
            $bankNames = [];
            $advancePaid = 0;

            foreach ($outward->paymentItems as $pItem) {
                $payment = $pItem->payment;
                if ($payment) {
                    if (! empty($payment->payment_mode)) {
                        $paymentModes[] = $payment->payment_mode;
                    }
                    if (! empty($payment->bank_name)) {
                        $bankNames[] = $payment->bank_name;
                    }
                    // Check if payment was an advance (payment date <= outward invoice date or remarks contain advance)
                    if (
                        ($payment->payment_date && $outward->invoice_date && $payment->payment_date <= $outward->invoice_date) ||
                        stripos((string) $payment->remarks, 'advance') !== false ||
                        stripos((string) $pItem->remarks, 'advance') !== false
                    ) {
                        $advancePaid += (float) $pItem->paid_amount;
                    }
                }
            }

            $uniqueModes = array_values(array_unique(array_filter($paymentModes)));
            $uniqueBanks = array_values(array_unique(array_filter($bankNames)));
            $paymentModeStr = count($uniqueModes) > 0 ? implode(', ', $uniqueModes) : ($outward->remarks && stripos($outward->remarks, 'cash') !== false ? 'Cash' : '—');
            $inAccStr = count($uniqueBanks) > 0 ? implode(', ', $uniqueBanks) : '—';

            $outwardPaid = (float) $outward->paid_amount;
            $outwardDiscount = (float) $outward->discount_amount + (float) ($outward->payment_discount_amount ?? 0);
            $outwardTotal = (float) $outward->total_amount;
            $outwardSubtotal = (float) $outward->subtotal;

            if ($itemCount === 0) {
                // Outward without separate line items fallback
                $pending = max(0, round($outwardTotal - $outwardPaid - $outwardDiscount, 2));

                $row = [
                    'id' => $outward->id,
                    'item_id' => null,
                    'entry_no' => $outward->entry_no,
                    'date' => $outward->invoice_date ? $outward->invoice_date->format('Y-m-d') : ($outward->challan_date ? $outward->challan_date->format('Y-m-d') : ''),
                    'party_id' => $outward->party_id,
                    'party_name' => $outward->party?->name ?? $outward->customer ?? '—',
                    'party_code' => $outward->party?->code ?? '',
                    'location' => $outward->destination ?? '—',
                    'grade' => 'Concrete',
                    'opc' => 'OPC 43/53',
                    'quant' => 0.0,
                    'unit' => 'cbm',
                    'rate' => 0.0,
                    'advance' => $advancePaid,
                    'payment_mode' => $paymentModeStr,
                    'amount' => $outwardTotal,
                    'receive_pay' => $outwardPaid,
                    'pending' => $pending,
                    'relief' => $outwardDiscount,
                    'commission' => (float) ($outward->commission_amount ?? 0),
                    'in_acc' => $inAccStr,
                    'seller' => $outward->agent?->name ?: ($outward->agent_name ?: 'Open'),
                    'note' => $outward->remarks ?: '',
                    'vehicle_no' => $outward->vehicle_no,
                    'challan_no' => $outward->challan_no,
                    'payment_status' => $outward->payment_status,
                ];

                $rows[] = $row;
                $totalAmount += $outwardTotal;
                $totalAdvance += $advancePaid;
                $totalReceived += $outwardPaid;
                $totalPending += $pending;
                $totalRelief += $outwardDiscount;
                $totalCommission += (float) ($outward->commission_amount ?? 0);
            } else {
                $sumItemDiscounts = (float) $items->sum('discount_amount');
                $headerDiscountOnly = max(0, (float) $outward->discount_amount - $sumItemDiscounts);

                foreach ($items as $item) {
                    $itemQty = (float) $item->qty;
                    $itemRate = (float) $item->rate;
                    $itemTotal = (float) $item->total_amount;

                    // Proportional distribution for paid & relief if multi-item outward
                    $ratio = ($outwardSubtotal > 0) ? ($itemTotal / $outwardSubtotal) : (1 / $itemCount);
                    $allocatedPaid = $itemCount === 1 ? $outwardPaid : round($outwardPaid * $ratio, 2);
                    $itemDirectDiscount = (float) $item->discount_amount;
                    $allocatedHeaderDiscount = $itemCount === 1 ? $headerDiscountOnly : round($headerDiscountOnly * $ratio, 2);
                    $allocatedPaymentDiscount = $itemCount === 1 ? (float) ($outward->payment_discount_amount ?? 0) : round((float) ($outward->payment_discount_amount ?? 0) * $ratio, 2);
                    $allocatedRelief = round($itemDirectDiscount + $allocatedHeaderDiscount + $allocatedPaymentDiscount, 2);
                    $allocatedAdvance = $itemCount === 1 ? $advancePaid : round($advancePaid * $ratio, 2);

                    $pending = max(0, round($itemTotal - $allocatedPaid - $allocatedRelief, 2));

                    // Grade name
                    $gradeName = 'Concrete';
                    if ($item->item) {
                        $gradeName = $item->item->category;
                        if (! empty($item->item->size)) {
                            $gradeName .= ' '.$item->item->size;
                        }
                    } elseif (! empty($item->remarks)) {
                        $gradeName = $item->remarks;
                    }

                    // OPC Mix recipe
                    $opcVal = ! empty($item->lotno) ? $item->lotno : ($item->item?->type === 'fg' ? 'OPC' : ($item->item?->category ?? 'OPC'));

                    // Commission
                    $commission = (float) ($item->commission_amount > 0 ? $item->commission_amount : ($itemCount === 1 ? ($outward->commission_amount ?? 0) : 0));

                    // Seller / Agent
                    $seller = $item->commission_agent_name ?: ($item->commissionAgent?->name ?: ($outward->agent?->name ?: ($outward->agent_name ?: 'Open')));

                    // Note
                    $note = $item->remarks ?: ($outward->remarks ?: '');

                    $row = [
                        'id' => $outward->id,
                        'item_id' => $item->id,
                        'entry_no' => $outward->entry_no,
                        'date' => $outward->invoice_date ? $outward->invoice_date->format('Y-m-d') : ($outward->challan_date ? $outward->challan_date->format('Y-m-d') : ''),
                        'party_id' => $outward->party_id,
                        'party_name' => $outward->party?->name ?? $outward->customer ?? '—',
                        'party_code' => $outward->party?->code ?? '',
                        'location' => $outward->destination ?? '—',
                        'grade' => $gradeName,
                        'opc' => $opcVal,
                        'quant' => $itemQty,
                        'unit' => $item->unit ?? 'cbm',
                        'rate' => $itemRate,
                        'pump_cost' => (float) ($item->pump_cost ?? 0),
                        'advance' => $allocatedAdvance,
                        'payment_mode' => $paymentModeStr,
                        'amount' => $itemTotal,
                        'receive_pay' => $allocatedPaid,
                        'pending' => $pending,
                        'relief' => $allocatedRelief,
                        'commission' => $commission,
                        'in_acc' => $inAccStr,
                        'seller' => $seller,
                        'note' => $note,
                        'vehicle_no' => $outward->vehicle_no,
                        'challan_no' => $outward->challan_no,
                        'payment_status' => $outward->payment_status,
                    ];

                    $rows[] = $row;
                    $totalQty += $itemQty;
                    $totalAmount += $itemTotal;
                    $totalAdvance += $allocatedAdvance;
                    $totalReceived += $allocatedPaid;
                    $totalPending += $pending;
                    $totalRelief += $allocatedRelief;
                    $totalCommission += $commission;
                }
            }
        }

        // Active customer info banner if filtered by a specific party
        $selectedParty = null;
        if ($partyId && $partyId !== 'all') {
            $partyModel = Party::find($partyId);
            if ($partyModel) {
                $selectedParty = [
                    'id' => $partyModel->id,
                    'name' => $partyModel->name,
                    'code' => $partyModel->code,
                    'phone' => $partyModel->phone,
                    'address' => $partyModel->address,
                    'city' => $partyModel->city,
                    'gstin' => $partyModel->gstin,
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'records' => $rows,
                'selected_party' => $selectedParty,
                'totals' => [
                    'count' => count($rows),
                    'total_qty' => round($totalQty, 4),
                    'total_amount' => round($totalAmount, 2),
                    'total_advance' => round($totalAdvance, 2),
                    'total_received' => round($totalReceived, 2),
                    'total_pending' => round($totalPending, 2),
                    'total_relief' => round($totalRelief, 2),
                    'total_commission' => round($totalCommission, 2),
                ],
            ],
        ]);
    }

    /**
     * Get metadata for report filter dropdowns.
     */
    public function meta(): JsonResponse
    {
        // Customers / Parties with outward records or customer type
        $customers = Party::whereIn('type', ['customer', 'both', 'vendor', 'supplier'])
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'phone', 'city']);

        // Sales Agents / Brokers
        $agents = Party::whereIn('type', ['agent', 'broker', 'other', 'both'])
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        // Concrete Grades / Items
        $items = Item::orderBy('type')
            ->orderBy('category')
            ->orderBy('size')
            ->get(['id', 'category', 'size', 'type', 'primary_unit']);

        // Unique concrete grades list
        $grades = Item::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->filter()
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'customers' => $customers,
                'agents' => $agents,
                'items' => $items,
                'grades' => $grades,
                'payment_modes' => ['Cash', 'PhonePe', 'GPay', 'NEFT', 'RTGS', 'Cheque', 'Bank Transfer'],
            ],
        ]);
    }
}
