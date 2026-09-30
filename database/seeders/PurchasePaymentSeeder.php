<?php

namespace Database\Seeders;

use App\Models\PurchasePayment;
use App\Models\PurchasePaymentItem;
use App\Models\StockInward;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PurchasePaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@rmc.com')->first() ?? User::first();
        $adminId = $admin?->id;

        // Reset existing payment tables
        DB::table('purchase_payment_items')->delete();
        DB::table('purchase_payments')->delete();

        // Reset all inwards to unpaid / 0 paid_amount first
        StockInward::query()->update([
            'paid_amount' => 0.00,
            'payment_status' => 'unpaid',
        ]);

        // Scenario 1: Full Payment for INW-0001 (UltraTech Cement Ltd)
        $inw1 = StockInward::where('inward_no', 'INW-0001')->first();
        if ($inw1 && $inw1->supplier_party_id) {
            $amt1 = (float) $inw1->grand_total;
            $pay1 = PurchasePayment::create([
                'payment_no' => 'PAY-0001',
                'payment_date' => '2026-09-22',
                'party_id' => $inw1->supplier_party_id,
                'party_name' => $inw1->supplier?->name ?? 'UltraTech Cement Ltd',
                'payment_mode' => 'bank_transfer',
                'reference_no' => 'UTR/HDFC/20260922/001948',
                'bank_name' => 'HDFC Bank',
                'amount' => $amt1,
                'remarks' => 'Full payment against invoice '.$inw1->inward_no,
                'created_by' => $adminId,
            ]);

            PurchasePaymentItem::create([
                'purchase_payment_id' => $pay1->id,
                'stock_inward_id' => $inw1->id,
                'invoice_amount' => $amt1,
                'paid_amount' => $amt1,
                'remarks' => 'Full clearance',
            ]);

            $inw1->recalculatePaymentStatus();
        }

        // Scenario 2: Partial Payment for INW-0005 (UltraTech Cement Ltd)
        $inw5 = StockInward::where('inward_no', 'INW-0005')->first();
        if ($inw5 && $inw5->supplier_party_id) {
            $partialAmt = min(150000.00, (float) $inw5->grand_total);
            $pay2 = PurchasePayment::create([
                'payment_no' => 'PAY-0002',
                'payment_date' => '2026-09-25',
                'party_id' => $inw5->supplier_party_id,
                'party_name' => $inw5->supplier?->name ?? 'UltraTech Cement Ltd',
                'payment_mode' => 'cheque',
                'reference_no' => 'CHQ-890214',
                'bank_name' => 'ICICI Bank',
                'amount' => $partialAmt,
                'remarks' => 'Part payment of ₹1.5L on cement delivery',
                'created_by' => $adminId,
            ]);

            PurchasePaymentItem::create([
                'purchase_payment_id' => $pay2->id,
                'stock_inward_id' => $inw5->id,
                'invoice_amount' => (float) $inw5->grand_total,
                'paid_amount' => $partialAmt,
                'remarks' => 'Partial advance against delivery',
            ]);

            $inw5->recalculatePaymentStatus();
        }

        // Scenario 3: Multi-Invoice Payment (paying across INW-0002 and INW-0004 for Stone Crusher)
        $inw2 = StockInward::where('inward_no', 'INW-0002')->first();
        $inw4 = StockInward::where('inward_no', 'INW-0004')->first();

        if ($inw2 && $inw4 && $inw2->supplier_party_id) {
            $alloc1 = (float) $inw2->grand_total; // Full for INW-0002
            $alloc2 = min(20000.00, (float) $inw4->grand_total); // Partial for INW-0004
            $totalMulti = $alloc1 + $alloc2;

            $pay3 = PurchasePayment::create([
                'payment_no' => 'PAY-0003',
                'payment_date' => '2026-09-27',
                'party_id' => $inw2->supplier_party_id,
                'party_name' => $inw2->supplier?->name ?? 'Shree Balaji Stone Crusher',
                'payment_mode' => 'bank_transfer',
                'reference_no' => 'NEFT-SBI-20260927-48201',
                'bank_name' => 'State Bank of India',
                'amount' => $totalMulti,
                'remarks' => 'Consolidated payment clearing INW-0002 and partial on INW-0004',
                'created_by' => $adminId,
            ]);

            PurchasePaymentItem::create([
                'purchase_payment_id' => $pay3->id,
                'stock_inward_id' => $inw2->id,
                'invoice_amount' => (float) $inw2->grand_total,
                'paid_amount' => $alloc1,
                'remarks' => '100% full invoice clearance',
            ]);

            PurchasePaymentItem::create([
                'purchase_payment_id' => $pay3->id,
                'stock_inward_id' => $inw4->id,
                'invoice_amount' => (float) $inw4->grand_total,
                'paid_amount' => $alloc2,
                'remarks' => 'Partial installment allocation',
            ]);

            $inw2->recalculatePaymentStatus();
            $inw4->recalculatePaymentStatus();
        }
    }
}
