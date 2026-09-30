<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutwardPayment;
use App\Models\Party;
use App\Models\PurchasePayment;
use App\Models\StockInward;
use App\Models\StockOutward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinancialReportController extends Controller
{
    /**
     * Common metadata for financial reports (Dropdowns, date presets, parties).
     */
    public function meta(): JsonResponse
    {
        $customers = Party::whereIn('type', ['customer', 'both'])
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'inactive');
            })
            ->select('id', 'name', 'code', 'phone', 'city', 'gstin')
            ->orderBy('name')
            ->get();

        $suppliers = Party::whereIn('type', ['supplier', 'vendor', 'both'])
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'inactive');
            })
            ->select('id', 'name', 'code', 'phone', 'city', 'gstin')
            ->orderBy('name')
            ->get();

        $agents = Party::where('type', 'agent')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'inactive');
            })
            ->select('id', 'name', 'code')
            ->orderBy('name')
            ->get();

        $parties = Party::where(function ($q) {
            $q->whereNull('status')->orWhere('status', '!=', 'inactive');
        })
            ->select('id', 'name', 'code', 'type', 'phone', 'city', 'gstin', 'address')
            ->orderBy('name')
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'type' => $p->type,
                    'type_label' => ucfirst($p->type),
                    'phone' => $p->phone,
                    'city' => $p->city,
                    'gstin' => $p->gstin,
                    'address' => $p->address,
                ];
            });

        $paymentModes = [
            'Cash',
            'Cheque',
            'RTGS',
            'NEFT',
            'IMPS',
            'UPI',
            'Bank Transfer',
            'Online Transfer',
        ];

        $outwardBanks = OutwardPayment::whereNotNull('bank_name')->where('bank_name', '!=', '')->distinct()->pluck('bank_name');
        $purchaseBanks = PurchasePayment::whereNotNull('bank_name')->where('bank_name', '!=', '')->distinct()->pluck('bank_name');
        $banks = $outwardBanks->concat($purchaseBanks)->unique()->filter()->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'customers' => $customers,
                'suppliers' => $suppliers,
                'agents' => $agents,
                'parties' => $parties,
                'payment_modes' => $paymentModes,
                'banks' => $banks,
                'today' => Carbon::now()->format('Y-m-d'),
                'first_day_of_month' => Carbon::now()->startOfMonth()->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * 1. Customer Outstanding & Ageing Report (Accounts Receivable).
     */
    public function customerOutstanding(Request $request): JsonResponse
    {
        $asOfDate = $request->query('as_of_date') ? Carbon::parse($request->query('as_of_date'))->endOfDay() : Carbon::now()->endOfDay();
        $partyId = $request->query('customer_id') ?: $request->query('party_id');
        $agentId = $request->query('agent_id');
        $ageingFilter = $request->query('ageing_bracket', 'all');
        $search = $request->query('search');

        $query = StockOutward::with([
            'party:id,name,code,phone,city,gstin,address',
            'agent:id,name',
            'paymentItems.payment',
        ])
            ->whereNotIn('entry_type', ['return', 'return_to_supplier'])
            ->whereDate('invoice_date', '<=', $asOfDate);

        if ($partyId && $partyId !== 'all') {
            $query->where('party_id', $partyId);
        }

        if ($agentId && $agentId !== 'all') {
            $query->where('agent_id', $agentId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('entry_no', 'like', "%{$search}%")
                    ->orWhere('challan_no', 'like', "%{$search}%")
                    ->orWhere('customer', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhereHas('party', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('city', 'like', "%{$search}%");
                    });
            });
        }

        $outwards = $query->orderBy('invoice_date', 'asc')->get();

        $customerMap = [];
        $totalInvoiced = 0;
        $totalReceived = 0;
        $totalDiscount = 0;
        $totalOutstanding = 0;
        $total0_15 = 0;
        $total16_30 = 0;
        $total31_60 = 0;
        $total60Plus = 0;

        foreach ($outwards as $outward) {
            $invDate = $outward->invoice_date ? Carbon::parse($outward->invoice_date) : ($outward->challan_date ? Carbon::parse($outward->challan_date) : Carbon::now());
            $daysOld = max(0, $invDate->diffInDays($asOfDate, false));

            $totalAmount = (float) $outward->total_amount;
            $paymentItems = $outward->paymentItems->filter(fn ($pi) => ! empty($pi->payment) && empty($pi->payment->deleted_at));

            $paid = (float) $paymentItems->sum('paid_amount');
            $discount = (float) $paymentItems->sum('discount_amount') + (float) $outward->discount_amount;
            $balanceDue = max(0, round($totalAmount - $paid - $discount, 2));

            $bucket = '0_15';
            if ($daysOld > 60) {
                $bucket = '60_plus';
            } elseif ($daysOld > 30) {
                $bucket = '31_60';
            } elseif ($daysOld > 15) {
                $bucket = '16_30';
            }

            $cid = $outward->party_id ?: ('c_'.md5($outward->customer ?? 'Unknown'));

            if (! isset($customerMap[$cid])) {
                $customerMap[$cid] = [
                    'id' => $outward->party_id,
                    'name' => $outward->party?->name ?? $outward->customer ?? '—',
                    'code' => $outward->party?->code ?? '',
                    'phone' => $outward->party?->phone ?? '',
                    'city' => $outward->party?->city ?? '',
                    'gstin' => $outward->party?->gstin ?? '',
                    'agent_name' => $outward->agent?->name ?? $outward->agent_name ?? '',
                    'invoices_count' => 0,
                    'total_invoiced' => 0.0,
                    'total_received' => 0.0,
                    'total_discount' => 0.0,
                    'total_outstanding' => 0.0,
                    'age_0_15' => 0.0,
                    'age_16_30' => 0.0,
                    'age_31_60' => 0.0,
                    'age_60_plus' => 0.0,
                    'invoices' => [],
                ];
            }

            $customerMap[$cid]['invoices_count']++;
            $customerMap[$cid]['total_invoiced'] += $totalAmount;
            $customerMap[$cid]['total_received'] += $paid;
            $customerMap[$cid]['total_discount'] += $discount;
            $customerMap[$cid]['total_outstanding'] += $balanceDue;

            if ($balanceDue > 0) {
                if ($bucket === '0_15') {
                    $customerMap[$cid]['age_0_15'] += $balanceDue;
                } elseif ($bucket === '16_30') {
                    $customerMap[$cid]['age_16_30'] += $balanceDue;
                } elseif ($bucket === '31_60') {
                    $customerMap[$cid]['age_31_60'] += $balanceDue;
                } else {
                    $customerMap[$cid]['age_60_plus'] += $balanceDue;
                }
            }

            $customerMap[$cid]['invoices'][] = [
                'outward_id' => $outward->id,
                'entry_no' => $outward->entry_no,
                'challan_no' => $outward->challan_no ?: $outward->entry_no,
                'date' => $invDate->format('Y-m-d'),
                'vehicle_no' => $outward->vehicle_no ?: '—',
                'site_location' => $outward->destination ?: '—',
                'grand_total' => $totalAmount,
                'received_amount' => $paid,
                'discount_amount' => $discount,
                'balance_due' => $balanceDue,
                'age_days' => $daysOld,
                'age_bracket' => $bucket,
                'payment_status' => $outward->payment_status ?: 'unpaid',
            ];
        }

        $customersList = collect($customerMap)->values();

        if ($ageingFilter !== 'all') {
            $customersList = $customersList->filter(function ($c) use ($ageingFilter) {
                if ($ageingFilter === '0_15') {
                    return $c['age_0_15'] > 0;
                }
                if ($ageingFilter === '16_30') {
                    return $c['age_16_30'] > 0;
                }
                if ($ageingFilter === '31_60') {
                    return $c['age_31_60'] > 0;
                }
                if ($ageingFilter === '60_plus') {
                    return $c['age_60_plus'] > 0;
                }

                return true;
            });
        }

        $customersList = $customersList->sortByDesc('total_outstanding')->values();

        foreach ($customersList as $c) {
            $totalInvoiced += $c['total_invoiced'];
            $totalReceived += $c['total_received'];
            $totalDiscount += $c['total_discount'];
            $totalOutstanding += $c['total_outstanding'];
            $total0_15 += $c['age_0_15'];
            $total16_30 += $c['age_16_30'];
            $total31_60 += $c['age_31_60'];
            $total60Plus += $c['age_60_plus'];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'kpis' => [
                    'total_customers' => $customersList->count(),
                    'total_invoiced' => round($totalInvoiced, 2),
                    'total_received' => round($totalReceived, 2),
                    'total_discount' => round($totalDiscount, 2),
                    'total_outstanding' => round($totalOutstanding, 2),
                    'total_0_15' => round($total0_15, 2),
                    'total_16_30' => round($total16_30, 2),
                    'total_31_60' => round($total31_60, 2),
                    'total_60_plus' => round($total60Plus, 2),
                ],
                'customers' => $customersList,
                'filters' => [
                    'as_of_date' => $asOfDate->format('Y-m-d'),
                    'customer_id' => $partyId ?? 'all',
                    'agent_id' => $agentId ?? 'all',
                    'ageing_bracket' => $ageingFilter,
                    'search' => $search ?? '',
                ],
            ],
        ]);
    }

    /**
     * 2. Supplier Payables Register (Accounts Payable).
     */
    public function supplierPayables(Request $request): JsonResponse
    {
        $asOfDate = $request->query('as_of_date') ? Carbon::parse($request->query('as_of_date'))->endOfDay() : Carbon::now()->endOfDay();
        $supplierId = $request->query('supplier_id');
        $ageingFilter = $request->query('ageing_bracket', 'all');
        $search = $request->query('search');

        $query = StockInward::with([
            'supplier:id,name,code,phone,city,gstin,address',
            'items.item:id,category,size,type,primary_unit',
            'paymentItems.payment',
        ])->whereDate('inward_date', '<=', $asOfDate);

        if ($supplierId && $supplierId !== 'all') {
            $query->where('supplier_party_id', $supplierId);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('inward_no', 'like', "%{$search}%")
                    ->orWhere('challan_no', 'like', "%{$search}%")
                    ->orWhere('supplier_bill_no', 'like', "%{$search}%")
                    ->orWhere('supplier_name', 'like', "%{$search}%")
                    ->orWhere('vehicle_no', 'like', "%{$search}%")
                    ->orWhereHas('supplier', function ($sq) use ($search) {
                        $sq->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        $inwards = $query->orderBy('inward_date', 'asc')->get();

        $supplierMap = [];
        $totalPurchases = 0;
        $totalPaid = 0;
        $totalDiscount = 0;
        $totalPayable = 0;
        $total0_15 = 0;
        $total16_30 = 0;
        $total31_60 = 0;
        $total60Plus = 0;

        foreach ($inwards as $inward) {
            $inwDate = $inward->inward_date ? Carbon::parse($inward->inward_date) : ($inward->challan_date ? Carbon::parse($inward->challan_date) : Carbon::now());
            $daysOld = max(0, $inwDate->diffInDays($asOfDate, false));

            $grandTotal = (float) $inward->grand_total;
            $paymentItems = $inward->paymentItems->filter(fn ($pi) => ! empty($pi->payment) && empty($pi->payment->deleted_at));
            $paid = (float) $paymentItems->sum('paid_amount');
            $discount = (float) $inward->discount_amount;
            $balanceDue = max(0, round($grandTotal - $paid - $discount, 2));

            $bucket = '0_15';
            if ($daysOld > 60) {
                $bucket = '60_plus';
            } elseif ($daysOld > 30) {
                $bucket = '31_60';
            } elseif ($daysOld > 15) {
                $bucket = '16_30';
            }

            $sid = $inward->supplier_party_id ?: ('s_'.md5($inward->supplier_name ?? 'Unknown'));

            if (! isset($supplierMap[$sid])) {
                $supplierMap[$sid] = [
                    'id' => $inward->supplier_party_id,
                    'name' => $inward->supplier?->name ?? $inward->supplier_name ?? '—',
                    'code' => $inward->supplier?->code ?? '',
                    'phone' => $inward->supplier?->phone ?? '',
                    'city' => $inward->supplier?->city ?? '',
                    'gstin' => $inward->supplier?->gstin ?? '',
                    'inwards_count' => 0,
                    'total_inward_amount' => 0.0,
                    'total_paid' => 0.0,
                    'total_discount' => 0.0,
                    'total_payable' => 0.0,
                    'age_0_15' => 0.0,
                    'age_16_30' => 0.0,
                    'age_31_60' => 0.0,
                    'age_60_plus' => 0.0,
                    'inwards' => [],
                ];
            }

            $supplierMap[$sid]['inwards_count']++;
            $supplierMap[$sid]['total_inward_amount'] += $grandTotal;
            $supplierMap[$sid]['total_paid'] += $paid;
            $supplierMap[$sid]['total_discount'] += $discount;
            $supplierMap[$sid]['total_payable'] += $balanceDue;

            if ($balanceDue > 0) {
                if ($bucket === '0_15') {
                    $supplierMap[$sid]['age_0_15'] += $balanceDue;
                } elseif ($bucket === '16_30') {
                    $supplierMap[$sid]['age_16_30'] += $balanceDue;
                } elseif ($bucket === '31_60') {
                    $supplierMap[$sid]['age_31_60'] += $balanceDue;
                } else {
                    $supplierMap[$sid]['age_60_plus'] += $balanceDue;
                }
            }

            $materialsSummary = $inward->items->map(function ($it) {
                $name = trim(($it->item?->category ?? 'Material').' '.($it->item?->size ?? ''));

                return "{$name} (".((float) $it->qty).' '.($it->unit ?? 'MT').')';
            })->implode(', ');

            $supplierMap[$sid]['inwards'][] = [
                'inward_id' => $inward->id,
                'inward_no' => $inward->inward_no,
                'bill_no' => $inward->supplier_bill_no ?: ($inward->challan_no ?: '—'),
                'date' => $inwDate->format('Y-m-d'),
                'vehicle_no' => $inward->vehicle_no ?: '—',
                'items_summary' => $materialsSummary,
                'total_qty' => (float) $inward->items->sum('qty'),
                'grand_total' => $grandTotal,
                'paid_amount' => $paid,
                'discount_amount' => $discount,
                'balance_payable' => $balanceDue,
                'age_days' => $daysOld,
                'age_bracket' => $bucket,
                'payment_status' => $inward->payment_status ?: 'unpaid',
            ];
        }

        $suppliersList = collect($supplierMap)->values();

        if ($ageingFilter !== 'all') {
            $suppliersList = $suppliersList->filter(function ($s) use ($ageingFilter) {
                if ($ageingFilter === '0_15') {
                    return $s['age_0_15'] > 0;
                }
                if ($ageingFilter === '16_30') {
                    return $s['age_16_30'] > 0;
                }
                if ($ageingFilter === '31_60') {
                    return $s['age_31_60'] > 0;
                }
                if ($ageingFilter === '60_plus') {
                    return $s['age_60_plus'] > 0;
                }

                return true;
            });
        }

        $suppliersList = $suppliersList->sortByDesc('total_payable')->values();

        foreach ($suppliersList as $s) {
            $totalPurchases += $s['total_inward_amount'];
            $totalPaid += $s['total_paid'];
            $totalDiscount += $s['total_discount'];
            $totalPayable += $s['total_payable'];
            $total0_15 += $s['age_0_15'];
            $total16_30 += $s['age_16_30'];
            $total31_60 += $s['age_31_60'];
            $total60Plus += $s['age_60_plus'];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'kpis' => [
                    'total_suppliers' => $suppliersList->count(),
                    'total_inward_amount' => round($totalPurchases, 2),
                    'total_paid' => round($totalPaid, 2),
                    'total_discount' => round($totalDiscount, 2),
                    'total_payable' => round($totalPayable, 2),
                    'total_0_15' => round($total0_15, 2),
                    'total_16_30' => round($total16_30, 2),
                    'total_31_60' => round($total31_60, 2),
                    'total_60_plus' => round($total60Plus, 2),
                ],
                'suppliers' => $suppliersList,
                'filters' => [
                    'as_of_date' => $asOfDate->format('Y-m-d'),
                    'supplier_id' => $supplierId ?? 'all',
                    'ageing_bracket' => $ageingFilter,
                    'search' => $search ?? '',
                ],
            ],
        ]);
    }

    /**
     * 3. Cash & Bank Book Register.
     */
    public function cashBankBook(Request $request): JsonResponse
    {
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');
        $mode = $request->query('payment_mode');
        $bankName = $request->query('bank_name');
        $txnType = $request->query('type') ?: $request->query('transaction_type', 'all');
        $search = $request->query('search');

        // 1. Inflows from Customers (Outward Payments)
        $inflowQuery = OutwardPayment::with(['party:id,name,code,phone,city,type'])
            ->whereNull('deleted_at');

        if ($fromDate) {
            $inflowQuery->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $inflowQuery->whereDate('payment_date', '<=', $toDate);
        }
        if ($mode && $mode !== 'all') {
            $inflowQuery->where('payment_mode', $mode);
        }
        if ($bankName && $bankName !== 'all') {
            $inflowQuery->where('bank_name', $bankName);
        }
        if ($search) {
            $inflowQuery->where(function ($q) use ($search) {
                $q->where('payment_no', 'like', "%{$search}%")
                    ->orWhere('party_name', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('party', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $inflowList = ($txnType === 'payment' || $txnType === 'outflow') ? collect() : $inflowQuery->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'type' => 'receipt',
                'type_label' => 'Receipt',
                'date' => $item->payment_date ? Carbon::parse($item->payment_date)->format('Y-m-d') : '',
                'voucher_no' => $item->payment_no,
                'reference_no' => $item->payment_no,
                'party_id' => $item->party_id,
                'party_name' => $item->party?->name ?? $item->party_name ?? 'Customer',
                'party_type' => $item->party?->type ?? 'customer',
                'payment_mode' => $item->payment_mode ?: 'Cash',
                'bank_name' => $item->bank_name ?: null,
                'reference_number' => $item->reference_no ?: null,
                'remarks' => $item->remarks ?: null,
                'is_locked' => (bool) $item->is_locked,
                'inflow' => (float) $item->amount,
                'outflow' => 0.0,
                'discount' => (float) ($item->discount_amount ?? 0),
            ];
        });

        // 2. Outflows to Suppliers (Purchase Payments)
        $outflowQuery = PurchasePayment::with(['party:id,name,code,phone,city,type'])
            ->whereNull('deleted_at');

        if ($fromDate) {
            $outflowQuery->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $outflowQuery->whereDate('payment_date', '<=', $toDate);
        }
        if ($mode && $mode !== 'all') {
            $outflowQuery->where('payment_mode', $mode);
        }
        if ($bankName && $bankName !== 'all') {
            $outflowQuery->where('bank_name', $bankName);
        }
        if ($search) {
            $outflowQuery->where(function ($q) use ($search) {
                $q->where('payment_no', 'like', "%{$search}%")
                    ->orWhere('party_name', 'like', "%{$search}%")
                    ->orWhere('reference_no', 'like', "%{$search}%")
                    ->orWhere('bank_name', 'like', "%{$search}%")
                    ->orWhere('remarks', 'like', "%{$search}%")
                    ->orWhereHas('party', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $outflowList = ($txnType === 'receipt' || $txnType === 'inflow') ? collect() : $outflowQuery->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'type' => 'payment',
                'type_label' => 'Payment',
                'date' => $item->payment_date ? Carbon::parse($item->payment_date)->format('Y-m-d') : '',
                'voucher_no' => $item->payment_no,
                'reference_no' => $item->payment_no,
                'party_id' => $item->party_id,
                'party_name' => $item->party?->name ?? $item->party_name ?? 'Supplier',
                'party_type' => $item->party?->type ?? 'supplier',
                'payment_mode' => $item->payment_mode ?: 'Cash',
                'bank_name' => $item->bank_name ?: null,
                'reference_number' => $item->reference_no ?: null,
                'remarks' => $item->remarks ?: null,
                'is_locked' => (bool) $item->is_locked,
                'inflow' => 0.0,
                'outflow' => (float) $item->amount,
                'discount' => 0.0,
            ];
        });

        // Merge and sort chronologically
        $transactions = $inflowList->concat($outflowList)->sortBy([
            ['date', 'asc'],
            ['voucher_no', 'asc'],
        ])->values();

        $runningBalance = 0.0;
        $totalInflow = 0.0;
        $totalOutflow = 0.0;
        $bankTotals = [];
        $modeTotals = [];

        $entries = $transactions->map(function ($row) use (&$runningBalance, &$totalInflow, &$totalOutflow, &$bankTotals, &$modeTotals) {
            $inflow = $row['inflow'];
            $outflow = $row['outflow'];
            $runningBalance += ($inflow - $outflow);

            $totalInflow += $inflow;
            $totalOutflow += $outflow;

            // Bank stats
            $b = $row['bank_name'] ?: 'Cash / Counter';
            if (! isset($bankTotals[$b])) {
                $bankTotals[$b] = ['bank' => $b, 'inflow' => 0.0, 'outflow' => 0.0, 'net' => 0.0];
            }
            $bankTotals[$b]['inflow'] += $inflow;
            $bankTotals[$b]['outflow'] += $outflow;
            $bankTotals[$b]['net'] += ($inflow - $outflow);

            // Mode stats
            $m = $row['payment_mode'];
            if (! isset($modeTotals[$m])) {
                $modeTotals[$m] = ['mode' => $m, 'inflow' => 0.0, 'outflow' => 0.0, 'net' => 0.0];
            }
            $modeTotals[$m]['inflow'] += $inflow;
            $modeTotals[$m]['outflow'] += $outflow;
            $modeTotals[$m]['net'] += ($inflow - $outflow);

            $row['running_balance'] = round($runningBalance, 2);

            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'kpis' => [
                    'total_inflow' => round($totalInflow, 2),
                    'total_outflow' => round($totalOutflow, 2),
                    'net_cashflow' => round($totalInflow - $totalOutflow, 2),
                    'transactions_count' => $entries->count(),
                ],
                'summary_by_mode' => array_values($modeTotals),
                'summary_by_bank' => array_values($bankTotals),
                'transactions' => $entries,
                'filters' => [
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'payment_mode' => $mode ?? 'all',
                    'bank_name' => $bankName ?? 'all',
                    'type' => $txnType,
                    'search' => $search ?? '',
                ],
            ],
        ]);
    }

    /**
     * 4. Party Account Ledger Statement (Dr / Cr Running Balance).
     */
    public function partyLedger(Request $request): JsonResponse
    {
        $partyId = $request->query('party_id');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        if (! $partyId || $partyId === 'all') {
            return response()->json([
                'status' => 'error',
                'message' => 'Please select a specific customer or vendor to view their ledger statement.',
            ], 422);
        }

        $party = Party::findOrFail($partyId);

        // 1. Calculate Opening Balance before $fromDate
        $openingBalance = 0.0;
        if ($fromDate) {
            // Outward sales before from_date (+ Debit for customer)
            $preOutwards = StockOutward::where('party_id', $partyId)
                ->whereNotIn('entry_type', ['return', 'return_to_supplier'])
                ->whereDate('invoice_date', '<', $fromDate)
                ->sum('total_amount');

            // Inward purchases before from_date (- Credit for vendor)
            $preInwards = StockInward::where('supplier_party_id', $partyId)
                ->whereDate('inward_date', '<', $fromDate)
                ->sum('grand_total');

            // Customer receipts before from_date (- Credit against customer)
            $preReceipts = OutwardPayment::where('party_id', $partyId)
                ->whereNull('deleted_at')
                ->whereDate('payment_date', '<', $fromDate)
                ->sum('amount');

            // Customer discounts allowed before from_date
            $preReceiptDiscounts = OutwardPayment::where('party_id', $partyId)
                ->whereNull('deleted_at')
                ->whereDate('payment_date', '<', $fromDate)
                ->sum('discount_amount');

            // Supplier payments before from_date (+ Debit against vendor)
            $preSupplierPayments = PurchasePayment::where('party_id', $partyId)
                ->whereNull('deleted_at')
                ->whereDate('payment_date', '<', $fromDate)
                ->sum('amount');

            $openingBalance = ($preOutwards - $preReceipts - $preReceiptDiscounts) + ($preSupplierPayments - $preInwards);
        }

        // 2. Fetch Period Transactions
        $entries = collect();

        // 2.1 Outward Dispatches / Sales
        $outwardQuery = StockOutward::with(['items.item'])
            ->where('party_id', $partyId)
            ->whereNotIn('entry_type', ['return', 'return_to_supplier']);

        if ($fromDate) {
            $outwardQuery->whereDate('invoice_date', '>=', $fromDate);
        }
        if ($toDate) {
            $outwardQuery->whereDate('invoice_date', '<=', $toDate);
        }

        foreach ($outwardQuery->get() as $out) {
            $matSummary = $out->items->map(function ($it) {
                return trim(($it->item?->category ?? 'Concrete').' '.($it->item?->size ?? '')).' ('.((float) $it->qty)." {$it->unit})";
            })->implode(', ');

            $entries->push([
                'id' => 'out_'.$out->id,
                'date' => $out->invoice_date ? Carbon::parse($out->invoice_date)->format('Y-m-d') : ($out->challan_date ? Carbon::parse($out->challan_date)->format('Y-m-d') : ''),
                'voucher_type' => 'Dispatch',
                'voucher_no' => $out->entry_no,
                'reference_no' => $out->challan_no ?: '—',
                'particulars' => "Concrete Dispatch: {$matSummary}".($out->destination ? " to {$out->destination}" : ''),
                'debit' => (float) $out->total_amount,
                'credit' => 0.0,
                'remarks' => $out->remarks ?: null,
            ]);
        }

        // 2.2 Customer Payment Receipts
        $receiptQuery = OutwardPayment::where('party_id', $partyId)->whereNull('deleted_at');
        if ($fromDate) {
            $receiptQuery->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $receiptQuery->whereDate('payment_date', '<=', $toDate);
        }

        foreach ($receiptQuery->get() as $rec) {
            $entries->push([
                'id' => 'rec_'.$rec->id,
                'date' => $rec->payment_date ? Carbon::parse($rec->payment_date)->format('Y-m-d') : '',
                'voucher_type' => 'Customer Receipt',
                'voucher_no' => $rec->payment_no,
                'reference_no' => $rec->reference_no ?: '—',
                'particulars' => "Payment Received via {$rec->payment_mode}".($rec->bank_name ? " ({$rec->bank_name})" : ''),
                'debit' => 0.0,
                'credit' => (float) $rec->amount,
                'remarks' => $rec->remarks ?: null,
            ]);

            if ((float) ($rec->discount_amount ?? 0) > 0) {
                $entries->push([
                    'id' => 'disc_'.$rec->id,
                    'date' => $rec->payment_date ? Carbon::parse($rec->payment_date)->format('Y-m-d') : '',
                    'voucher_type' => 'Discount Allowed',
                    'voucher_no' => $rec->payment_no,
                    'reference_no' => $rec->reference_no ?: '—',
                    'particulars' => "Settlement Discount Allowed against Voucher {$rec->payment_no}",
                    'debit' => 0.0,
                    'credit' => (float) $rec->discount_amount,
                    'remarks' => null,
                ]);
            }
        }

        // 2.3 Inward Purchases (if vendor/both)
        $inwardQuery = StockInward::with(['items.item'])->where('supplier_party_id', $partyId);
        if ($fromDate) {
            $inwardQuery->whereDate('inward_date', '>=', $fromDate);
        }
        if ($toDate) {
            $inwardQuery->whereDate('inward_date', '<=', $toDate);
        }

        foreach ($inwardQuery->get() as $inw) {
            $matSummary = $inw->items->map(function ($it) {
                return trim(($it->item?->category ?? 'Material').' '.($it->item?->size ?? '')).' ('.((float) $it->qty).' '.($it->unit ?? 'MT').')';
            })->implode(', ');

            $entries->push([
                'id' => 'inw_'.$inw->id,
                'date' => $inw->inward_date ? Carbon::parse($inw->inward_date)->format('Y-m-d') : ($inw->challan_date ? Carbon::parse($inw->challan_date)->format('Y-m-d') : ''),
                'voucher_type' => 'Inward',
                'voucher_no' => $inw->inward_no,
                'reference_no' => $inw->supplier_bill_no ?: ($inw->challan_no ?: '—'),
                'particulars' => "Inward Material Purchase: {$matSummary}",
                'debit' => 0.0,
                'credit' => (float) $inw->grand_total,
                'remarks' => $inw->remarks ?: null,
            ]);
        }

        // 2.4 Purchase Payments to Supplier
        $purchasePayQuery = PurchasePayment::where('party_id', $partyId)->whereNull('deleted_at');
        if ($fromDate) {
            $purchasePayQuery->whereDate('payment_date', '>=', $fromDate);
        }
        if ($toDate) {
            $purchasePayQuery->whereDate('payment_date', '<=', $toDate);
        }

        foreach ($purchasePayQuery->get() as $ppay) {
            $entries->push([
                'id' => 'ppay_'.$ppay->id,
                'date' => $ppay->payment_date ? Carbon::parse($ppay->payment_date)->format('Y-m-d') : '',
                'voucher_type' => 'Supplier Payment',
                'voucher_no' => $ppay->payment_no,
                'reference_no' => $ppay->reference_no ?: '—',
                'particulars' => "Disbursement via {$ppay->payment_mode}".($ppay->bank_name ? " ({$ppay->bank_name})" : ''),
                'debit' => (float) $ppay->amount,
                'credit' => 0.0,
                'remarks' => $ppay->remarks ?: null,
            ]);
        }

        // Sort entries chronologically
        $sortedEntries = $entries->sortBy([
            ['date', 'asc'],
            ['voucher_no', 'asc'],
        ])->values();

        // Calculate running balance
        $currentBalance = $openingBalance;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        $statementEntries = $sortedEntries->map(function ($row) use (&$currentBalance, &$totalDebit, &$totalCredit) {
            $dr = (float) $row['debit'];
            $cr = (float) $row['credit'];
            $totalDebit += $dr;
            $totalCredit += $cr;

            $currentBalance += ($dr - $cr);

            $row['balance'] = round(abs($currentBalance), 2);
            $row['balance_type'] = $currentBalance >= 0 ? 'Dr' : 'Cr';

            return $row;
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'party' => [
                    'id' => $party->id,
                    'name' => $party->name,
                    'code' => $party->code,
                    'phone' => $party->phone,
                    'address' => $party->address,
                    'city' => $party->city,
                    'gstin' => $party->gstin,
                    'type' => $party->type,
                    'type_label' => ucfirst($party->type),
                ],
                'opening_balance' => [
                    'amount' => round(abs($openingBalance), 2),
                    'type' => $openingBalance >= 0 ? 'Dr' : 'Cr',
                ],
                'entries' => $statementEntries,
                'totals' => [
                    'total_debit' => round($totalDebit, 2),
                    'total_credit' => round($totalCredit, 2),
                    'net_movement' => round($totalDebit - $totalCredit, 2),
                ],
                'closing_balance' => [
                    'amount' => round(abs($currentBalance), 2),
                    'type' => $currentBalance >= 0 ? 'Dr' : 'Cr',
                ],
                'filters' => [
                    'party_id' => $party->id,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                ],
            ],
        ]);
    }
}
