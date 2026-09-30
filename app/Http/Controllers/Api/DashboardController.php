<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\OutwardPayment;
use App\Models\Party;
use App\Models\PurchasePayment;
use App\Models\StockInward;
use App\Models\StockOutward;
use App\Models\StockOutwardItem;
use App\Services\StockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(
        protected StockService $stockService
    ) {}

    /**
     * Get consolidated real-time metrics and summaries for the executive dashboard.
     */
    public function stats(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        // 1. Today's Dispatches & Volume
        $todayOutwards = StockOutward::where(function ($q) use ($today) {
            $q->whereDate('challan_date', $today)
                ->orWhere(function ($sub) use ($today) {
                    $sub->whereNull('challan_date')->whereDate('invoice_date', $today);
                })
                ->orWhere(function ($sub) use ($today) {
                    $sub->whereNull('challan_date')->whereNull('invoice_date')->whereDate('created_at', $today);
                });
        });

        $todayDispatchesCount = (clone $todayOutwards)->count();
        $todayOutwardAmount = (float) (clone $todayOutwards)->sum('total_amount');

        $todayDispatchedVolume = (float) StockOutwardItem::whereHas('stockOutward', function ($q) use ($today) {
            $q->whereDate('challan_date', $today)
                ->orWhere(function ($sub) use ($today) {
                    $sub->whereNull('challan_date')->whereDate('invoice_date', $today);
                })
                ->orWhere(function ($sub) use ($today) {
                    $sub->whereNull('challan_date')->whereNull('invoice_date')->whereDate('created_at', $today);
                });
        })->sum('qty');

        // Today's Payments Received
        $todayReceivedAmount = (float) OutwardPayment::whereDate('payment_date', $today)->sum('amount');

        // 2. Month's Dispatches & Volume
        $monthOutwards = StockOutward::where(function ($q) use ($startOfMonth, $endOfMonth) {
            $q->whereBetween('challan_date', [$startOfMonth, $endOfMonth])
                ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                    $sub->whereNull('challan_date')->whereBetween('invoice_date', [$startOfMonth, $endOfMonth]);
                })
                ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                    $sub->whereNull('challan_date')->whereNull('invoice_date')->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                });
        });

        $monthDispatchesCount = (clone $monthOutwards)->count();
        $monthOutwardAmount = (float) (clone $monthOutwards)->sum('total_amount');

        $monthDispatchedVolume = (float) StockOutwardItem::whereHas('stockOutward', function ($q) use ($startOfMonth, $endOfMonth) {
            $q->whereBetween('challan_date', [$startOfMonth, $endOfMonth])
                ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                    $sub->whereNull('challan_date')->whereBetween('invoice_date', [$startOfMonth, $endOfMonth]);
                })
                ->orWhere(function ($sub) use ($startOfMonth, $endOfMonth) {
                    $sub->whereNull('challan_date')->whereNull('invoice_date')->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
                });
        })->sum('qty');

        $monthReceivedAmount = (float) OutwardPayment::whereBetween('payment_date', [$startOfMonth, $endOfMonth])->sum('amount');

        // 3. Customer Outstanding & Supplier Payables
        $totalCustomerBilling = (float) StockOutward::sum('total_amount');
        $totalCustomerReceived = (float) OutwardPayment::sum('amount');
        $totalCustomerOutstanding = max(0, $totalCustomerBilling - $totalCustomerReceived);

        $totalSupplierPurchases = (float) StockInward::sum('grand_total');
        $totalSupplierPaid = (float) PurchasePayment::sum('amount');
        $totalSupplierPayable = max(0, $totalSupplierPurchases - $totalSupplierPaid);

        // 4. Counts
        $totalCustomers = Party::whereIn('type', ['customer', 'both'])->count();
        $totalSuppliers = Party::whereIn('type', ['supplier', 'vendor', 'both'])->count();
        $totalItems = Item::count();

        // 5. Recent 5 Concrete Dispatches
        $recentDispatches = StockOutward::with(['party', 'items.item'])
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($o) {
                $firstItem = $o->items->first();
                $totalQty = (float) $o->items->sum('qty');

                return [
                    'id' => $o->id,
                    'entry_no' => $o->entry_no,
                    'challan_no' => $o->challan_no ?: $o->entry_no,
                    'date' => $o->challan_date ? Carbon::parse($o->challan_date)->format('Y-m-d') : ($o->invoice_date ? Carbon::parse($o->invoice_date)->format('Y-m-d') : $o->created_at->format('Y-m-d')),
                    'customer_name' => $o->party?->name ?: ($o->customer ?: 'Direct Customer'),
                    'destination' => $o->destination ?: '-',
                    'vehicle_no' => $o->vehicle_no ?: '-',
                    'grade' => $firstItem?->item?->name ?: ($firstItem?->item_name ?: 'Concrete Dispatch'),
                    'quantity' => $totalQty > 0 ? $totalQty : (float) ($firstItem?->qty ?? 0),
                    'unit' => $firstItem?->unit ?: 'CUM',
                    'total_amount' => (float) $o->total_amount,
                    'paid_amount' => (float) $o->paid_amount,
                    'payment_status' => $o->payment_status ?: ($o->paid_amount >= $o->total_amount ? 'paid' : ($o->paid_amount > 0 ? 'partial' : 'pending')),
                ];
            });

        // 6. Recent 5 Customer Payments
        $recentPayments = OutwardPayment::with('party')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'payment_no' => $p->payment_no,
                    'date' => $p->payment_date ? Carbon::parse($p->payment_date)->format('Y-m-d') : $p->created_at->format('Y-m-d'),
                    'customer_name' => $p->party?->name ?: ($p->party_name ?: 'Customer'),
                    'amount' => (float) $p->amount,
                    'payment_mode' => strtoupper($p->payment_mode ?: 'CASH'),
                    'bank_name' => $p->bank_name ?: '-',
                ];
            });

        // 7. Top Concrete Grades (By volume)
        $topGrades = StockOutwardItem::with('item')
            ->select('item_id', DB::raw('SUM(qty) as total_qty'), DB::raw('SUM(total_amount) as total_revenue'))
            ->groupBy('item_id')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                return [
                    'item_id' => $row->item_id,
                    'name' => $row->item?->name ?: 'Grade #'.$row->item_id,
                    'code' => $row->item?->code ?: '',
                    'total_qty' => (float) $row->total_qty,
                    'total_revenue' => (float) $row->total_revenue,
                ];
            });

        // 8. Key Raw Material Stock Summary
        $rawMaterialItems = Item::where('type', 'rm')->orderBy('id')->limit(5)->get();
        $stockSummary = [];
        foreach ($rawMaterialItems as $rm) {
            $stockData = $this->stockService->getItemStock($rm->id);
            $stockSummary[] = [
                'id' => $rm->id,
                'name' => $rm->name,
                'code' => $rm->code,
                'current_stock' => (float) ($stockData['available_primary'] ?? 0),
                'unit' => $stockData['primary_unit'] ?? 'Ton',
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'today' => [
                    'dispatches_count' => $todayDispatchesCount,
                    'dispatched_volume' => $todayDispatchedVolume,
                    'outward_amount' => $todayOutwardAmount,
                    'received_amount' => $todayReceivedAmount,
                ],
                'month' => [
                    'dispatches_count' => $monthDispatchesCount,
                    'dispatched_volume' => $monthDispatchedVolume,
                    'outward_amount' => $monthOutwardAmount,
                    'received_amount' => $monthReceivedAmount,
                ],
                'financials' => [
                    'customer_outstanding' => $totalCustomerOutstanding,
                    'supplier_payable' => $totalSupplierPayable,
                    'total_customers' => $totalCustomers,
                    'total_suppliers' => $totalSuppliers,
                    'total_items' => $totalItems,
                ],
                'recent_dispatches' => $recentDispatches,
                'recent_payments' => $recentPayments,
                'top_grades' => $topGrades,
                'key_stock' => $stockSummary,
            ],
        ]);
    }
}
