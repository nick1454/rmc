<?php

use App\Models\Item;
use App\Models\OutwardPayment;
use App\Models\Party;
use App\Models\PurchasePayment;
use App\Models\StockInward;
use App\Models\StockInwardItem;
use App\Models\StockOutward;
use App\Models\StockOutwardItem;
use App\Models\User;

test('financial report meta endpoint returns metadata for customers, suppliers, agents, banks, and payment modes', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/reports/financial/meta');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'customers',
                'suppliers',
                'agents',
                'parties',
                'payment_modes',
                'banks',
                'today',
                'first_day_of_month',
            ],
        ]);
});

test('customer outstanding report returns receivables and ageing breakdown', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'ABC Constructions',
        'code' => 'CUST-ABC',
        'type' => 'customer',
        'phone' => '9876543210',
        'city' => 'Gurugram',
        'status' => 'active',
    ]);

    $item = Item::create([
        'category' => 'M-30',
        'size' => 'Standard',
        'type' => 'fg',
        'primary_unit' => 'cbm',
        'secondary_unit' => 'cbm',
        'conversion_factor' => 1.0,
        'gst_per' => 18.0,
    ]);

    $outward = StockOutward::create([
        'entry_no' => 'OUT-ABC-01',
        'party_id' => $customer->id,
        'challan_no' => 'CH-ABC-01',
        'invoice_date' => now()->subDays(20)->format('Y-m-d'),
        'vehicle_no' => 'HR-55-1234',
        'destination' => 'Site 1',
        'subtotal' => 10000,
        'tax_amount' => 1800,
        'total_amount' => 11800,
        'paid_amount' => 5000,
        'payment_status' => 'partially_paid',
    ]);

    StockOutwardItem::create([
        'stock_outward_id' => $outward->id,
        'item_id' => $item->id,
        'qty' => 5.0,
        'rate' => 2000,
        'tax_per' => 18.0,
        'amount' => 10000,
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/reports/financial/customer-outstanding');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'kpis' => [
                    'total_customers',
                    'total_invoiced',
                    'total_received',
                    'total_outstanding',
                    'total_0_15',
                    'total_16_30',
                    'total_31_60',
                    'total_60_plus',
                ],
                'customers',
            ],
        ]);

    $data = $response->json('data');
    expect($data['kpis']['total_invoiced'])->toBeGreaterThanOrEqual(11800);
    expect($data['kpis']['total_outstanding'])->toBeGreaterThanOrEqual(6800);
});

test('supplier payables report returns payables and ageing breakdown', function () {
    $user = User::factory()->create();

    $supplier = Party::create([
        'name' => 'Ultratech Cement Supplier',
        'code' => 'SUPP-ULTRA',
        'type' => 'supplier',
        'phone' => '9876543222',
        'city' => 'Noida',
        'status' => 'active',
    ]);

    $item = Item::create([
        'category' => 'OPC 53 Cement',
        'size' => 'Bulk',
        'type' => 'rm',
        'primary_unit' => 'ton',
        'secondary_unit' => 'bag',
        'conversion_factor' => 20.0,
        'gst_per' => 28.0,
    ]);

    $inward = StockInward::create([
        'inward_no' => 'INW-TEST-01',
        'supplier_bill_no' => 'BILL-101',
        'supplier_party_id' => $supplier->id,
        'inward_date' => now()->subDays(10)->format('Y-m-d'),
        'vehicle_no' => 'UP-16-9999',
        'subtotal_amount' => 20000,
        'tax_amount' => 5600,
        'grand_total' => 25600,
        'paid_amount' => 10000,
        'payment_status' => 'partially_paid',
    ]);

    StockInwardItem::create([
        'stock_inward_id' => $inward->id,
        'item_id' => $item->id,
        'qty' => 10.0,
        'rate' => 2000,
        'tax_per' => 28.0,
        'total_amount' => 20000,
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/reports/financial/supplier-payables');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'kpis' => [
                    'total_suppliers',
                    'total_inward_amount',
                    'total_paid',
                    'total_payable',
                    'total_0_15',
                ],
                'suppliers',
            ],
        ]);

    $data = $response->json('data');
    expect($data['kpis']['total_inward_amount'])->toBeGreaterThanOrEqual(25600);
    expect($data['kpis']['total_payable'])->toBeGreaterThanOrEqual(15600);
});

test('cash and bank book register returns chronological inflow and outflow entries', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'Greenfield Developers',
        'code' => 'CUST-GREEN',
        'type' => 'customer',
        'status' => 'active',
    ]);

    $supplier = Party::create([
        'name' => 'Stone Crushers Ltd',
        'code' => 'SUPP-STONE',
        'type' => 'supplier',
        'status' => 'active',
    ]);

    // Receipt (Inflow)
    OutwardPayment::create([
        'payment_no' => 'RCT-TEST-01',
        'party_id' => $customer->id,
        'payment_date' => '2026-09-30',
        'payment_mode' => 'RTGS',
        'bank_name' => 'HDFC Bank',
        'amount' => 50000,
        'discount_amount' => 0,
        'is_locked' => false,
    ]);

    // Payment (Outflow)
    PurchasePayment::create([
        'payment_no' => 'PAY-TEST-01',
        'party_id' => $supplier->id,
        'payment_date' => '2026-09-30',
        'payment_mode' => 'Cheque',
        'bank_name' => 'SBI Bank',
        'amount' => 20000,
        'is_locked' => false,
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/reports/financial/cash-bank-book?from_date=2026-09-30&to_date=2026-09-30');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'kpis' => [
                    'total_inflow',
                    'total_outflow',
                    'net_cashflow',
                    'transactions_count',
                ],
                'summary_by_mode',
                'summary_by_bank',
                'transactions',
            ],
        ]);

    $data = $response->json('data');
    expect($data['kpis']['total_inflow'])->toBeGreaterThanOrEqual(50000);
    expect($data['kpis']['total_outflow'])->toBeGreaterThanOrEqual(20000);
});

test('party ledger statement returns running debit credit balance for selected party', function () {
    $user = User::factory()->create();

    $party = Party::create([
        'name' => 'Metro Infratech',
        'code' => 'CUST-METRO',
        'type' => 'customer',
        'phone' => '9988776655',
        'city' => 'Delhi',
        'status' => 'active',
    ]);

    $outward = StockOutward::create([
        'entry_no' => 'OUT-METRO-01',
        'party_id' => $party->id,
        'challan_no' => 'CH-METRO-01',
        'invoice_date' => now()->format('Y-m-d'),
        'vehicle_no' => 'DL-01-5678',
        'destination' => 'Metro Site',
        'subtotal' => 40000,
        'tax_amount' => 7200,
        'total_amount' => 47200,
        'paid_amount' => 0,
        'payment_status' => 'unpaid',
    ]);

    OutwardPayment::create([
        'payment_no' => 'RCT-METRO-01',
        'party_id' => $party->id,
        'payment_date' => now()->format('Y-m-d'),
        'payment_mode' => 'NEFT',
        'bank_name' => 'ICICI Bank',
        'amount' => 20000,
        'discount_amount' => 1000,
        'is_locked' => false,
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/reports/financial/party-ledger?party_id='.$party->id);

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'party',
                'opening_balance',
                'entries',
                'totals' => [
                    'total_debit',
                    'total_credit',
                    'net_movement',
                ],
                'closing_balance',
            ],
        ]);

    $data = $response->json('data');
    expect($data['party']['name'])->toBe('Metro Infratech');
    expect((float) $data['totals']['total_debit'])->toEqual(47200.0);
    expect((float) $data['totals']['total_credit'])->toEqual(21000.0);
    expect((float) $data['closing_balance']['amount'])->toEqual(26200.0);
    expect($data['closing_balance']['type'])->toBe('Dr');
});
