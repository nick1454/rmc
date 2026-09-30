<?php

use App\Models\Item;
use App\Models\OutwardPayment;
use App\Models\OutwardPaymentItem;
use App\Models\Party;
use App\Models\StockOutward;
use App\Models\StockOutwardItem;
use App\Models\User;

test('dispatch report meta endpoint returns customer, agent, and grade lists', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/reports/dispatch-payment/meta');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'customers',
                'agents',
                'items',
                'grades',
                'payment_modes',
            ],
        ]);
});

test('dispatch report index endpoint returns consolidated 17-column register data and totals', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'BHAVYA INFRA',
        'code' => 'CUST-BHAVYA',
        'type' => 'customer',
        'phone' => '9876543210',
        'address' => 'Sector 62',
        'city' => 'Gurugram',
        'is_active' => true,
    ]);

    $agent = Party::create([
        'name' => 'Rajender Seller',
        'code' => 'AGT-RAJ',
        'type' => 'agent',
        'phone' => '9876543211',
        'is_active' => true,
    ]);

    $item = Item::create([
        'category' => 'M-25',
        'size' => 'Design Mix',
        'type' => 'fg',
        'primary_unit' => 'cbm',
        'secondary_unit' => 'cbm',
        'conversion_factor' => 1.0,
        'gst_per' => 18.0,
    ]);

    $outward = StockOutward::create([
        'entry_no' => 'OUT-TEST-01',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'agent_id' => $agent->id,
        'agent_name' => $agent->name,
        'invoice_date' => '2026-09-25',
        'challan_no' => 'CH-8899',
        'destination' => 'Cyber City Tower B',
        'vehicle_no' => 'HR-55-AB-1234',
        'subtotal' => 34000.00,
        'tax_amount' => 0.00,
        'discount_amount' => 1000.00,
        'commission_amount' => 500.00,
        'total_amount' => 33000.00,
        'paid_amount' => 20000.00,
        'payment_status' => 'partially_paid',
        'remarks' => 'WITH GST PAYMENT',
    ]);

    StockOutwardItem::create([
        'stock_outward_id' => $outward->id,
        'item_id' => $item->id,
        'qty' => 10.0,
        'unit' => 'cbm',
        'rate' => 3400.00,
        'discount_amount' => 1000.00,
        'commission_agent_id' => $agent->id,
        'commission_agent_name' => $agent->name,
        'commission_amount' => 500.00,
        'taxable_amount' => 33000.00,
        'total_amount' => 33000.00,
        'lotno' => 'OPC 43/53',
        'remarks' => 'WITH GST PAYMENT',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/reports/dispatch-payment?party_id='.$customer->id);

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'records' => [
                    '*' => [
                        'id',
                        'entry_no',
                        'date',
                        'party_id',
                        'party_name',
                        'location',
                        'grade',
                        'opc',
                        'quant',
                        'rate',
                        'advance',
                        'payment_mode',
                        'amount',
                        'receive_pay',
                        'pending',
                        'relief',
                        'discount',
                        'commission',
                        'in_acc',
                        'seller',
                        'note',
                    ],
                ],
                'selected_party' => [
                    'id',
                    'name',
                ],
                'totals' => [
                    'count',
                    'total_qty',
                    'total_amount',
                    'total_advance',
                    'total_received',
                    'total_pending',
                    'total_relief',
                    'total_discount',
                    'total_commission',
                ],
            ],
        ]);

    $data = $response->json('data');
    expect($data['records'])->toHaveCount(1);
    expect($data['records'][0]['party_name'])->toBe('BHAVYA INFRA');
    expect($data['records'][0]['grade'])->toContain('M-25');
    expect((float) $data['records'][0]['quant'])->toBe(10.0);
    expect((float) $data['records'][0]['rate'])->toBe(3400.0);
    expect((float) $data['records'][0]['amount'])->toBe(33000.0);
    expect((float) $data['records'][0]['receive_pay'])->toBe(20000.0);
    expect((float) $data['records'][0]['relief'])->toBe(1000.0);
    expect((float) $data['records'][0]['discount'])->toBe(1000.0);
    expect((float) $data['records'][0]['commission'])->toBe(500.0);
    expect($data['records'][0]['seller'])->toBe('Rajender Seller');
});

test('dispatch report displays commission amount recorded on outward payment items', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'SKYLINE BUILDERS',
        'code' => 'CUST-SKY',
        'type' => 'customer',
        'status' => 'active',
    ]);

    $agent = Party::create([
        'name' => 'Broker Sharma',
        'code' => 'AGT-SHARMA',
        'type' => 'agent',
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
        'entry_no' => 'OUT-SKY-01',
        'party_id' => $customer->id,
        'invoice_date' => now()->format('Y-m-d'),
        'subtotal' => 20000,
        'tax_amount' => 3600,
        'total_amount' => 23600,
        'paid_amount' => 10000,
        'payment_status' => 'partially_paid',
    ]);

    StockOutwardItem::create([
        'stock_outward_id' => $outward->id,
        'item_id' => $item->id,
        'qty' => 5.0,
        'rate' => 4000,
        'tax_per' => 18.0,
        'total_amount' => 23600,
    ]);

    $payment = OutwardPayment::create([
        'payment_no' => 'RCT-SKY-01',
        'party_id' => $customer->id,
        'payment_date' => now()->format('Y-m-d'),
        'payment_mode' => 'RTGS',
        'bank_name' => 'HDFC Bank',
        'amount' => 10000,
        'discount_amount' => 500,
        'is_locked' => false,
    ]);

    OutwardPaymentItem::create([
        'outward_payment_id' => $payment->id,
        'stock_outward_id' => $outward->id,
        'invoice_amount' => 23600,
        'paid_amount' => 10000,
        'discount_amount' => 500,
        'commission_agent_id' => $agent->id,
        'commission_agent_name' => 'Broker Sharma',
        'commission_amount' => 750.00,
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/reports/dispatch-payment?party_id='.$customer->id);

    $response->assertOk();
    $data = $response->json('data');
    expect($data['records'])->toHaveCount(1);
    expect((float) $data['records'][0]['commission'])->toBe(750.0);
    expect($data['records'][0]['seller'])->toBe('Broker Sharma');
    expect((float) $data['totals']['total_commission'])->toBe(750.0);
    expect($data['records'][0]['primary_receipt_no'])->toBe('RCT-SKY-01');
    expect($data['records'][0]['receipts'])->toHaveCount(1);
    expect($data['records'][0]['receipts'][0]['payment_no'])->toBe('RCT-SKY-01');
});
