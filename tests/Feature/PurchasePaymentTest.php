<?php

use App\Models\Party;
use App\Models\StockInward;
use App\Models\User;

test('purchase payment metadata endpoint returns next voucher and suppliers', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/purchase-payments/meta');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'next_payment_no',
                'suppliers',
                'payment_modes',
            ],
        ]);
});

test('pending invoices endpoint returns unpaid and partially paid inwards for supplier', function () {
    $user = User::factory()->create();

    $supplier = Party::create([
        'name' => 'Supplier '.uniqid(),
        'type' => 'supplier',
        'status' => 'active',
        'code' => 'SUP-'.rand(1000, 9999),
    ]);

    $inward = StockInward::create([
        'inward_no' => 'INW-TEST-01',
        'inward_date' => '2026-09-28',
        'supplier_party_id' => $supplier->id,
        'grand_total' => 50000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $response = $this->actingAs($user)->getJson("/api/v1/purchase-payments/pending-invoices/{$supplier->id}");

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $inward->id,
            'grand_total' => 50000,
            'balance_due' => 50000,
            'payment_status' => 'unpaid',
        ]);
});

test('can record full payment against single purchase invoice', function () {
    $user = User::factory()->create();

    $supplier = Party::create([
        'name' => 'Supplier '.uniqid(),
        'type' => 'supplier',
        'status' => 'active',
        'code' => 'SUP-'.rand(1000, 9999),
    ]);

    $inward = StockInward::create([
        'inward_no' => 'INW-TEST-FULL',
        'inward_date' => '2026-09-28',
        'supplier_party_id' => $supplier->id,
        'grand_total' => 25000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $payload = [
        'payment_no' => 'PAY-TEST-001',
        'payment_date' => '2026-09-29',
        'party_id' => $supplier->id,
        'payment_mode' => 'bank_transfer',
        'reference_no' => 'UTR-TEST-12345',
        'bank_name' => 'HDFC Bank',
        'amount' => 25000.00,
        'remarks' => 'Full clearance test',
        'items' => [
            [
                'stock_inward_id' => $inward->id,
                'paid_amount' => 25000.00,
                'remarks' => 'Full invoice payment',
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/purchase-payments', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
        ]);

    $inward->refresh();
    expect((float) $inward->paid_amount)->toBe(25000.00)
        ->and($inward->payment_status)->toBe('paid');
});

test('can record partial payment against single purchase invoice', function () {
    $user = User::factory()->create();

    $supplier = Party::create([
        'name' => 'Supplier '.uniqid(),
        'type' => 'supplier',
        'status' => 'active',
        'code' => 'SUP-'.rand(1000, 9999),
    ]);

    $inward = StockInward::create([
        'inward_no' => 'INW-TEST-PART',
        'inward_date' => '2026-09-28',
        'supplier_party_id' => $supplier->id,
        'grand_total' => 60000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $payload = [
        'payment_no' => 'PAY-TEST-002',
        'payment_date' => '2026-09-29',
        'party_id' => $supplier->id,
        'payment_mode' => 'cheque',
        'reference_no' => 'CHQ-998877',
        'amount' => 20000.00,
        'items' => [
            [
                'stock_inward_id' => $inward->id,
                'paid_amount' => 20000.00,
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/purchase-payments', $payload);

    $response->assertStatus(201);

    $inward->refresh();
    expect((float) $inward->paid_amount)->toBe(20000.00)
        ->and($inward->payment_status)->toBe('partially_paid');
});

test('can record payment across multiple purchase invoices', function () {
    $user = User::factory()->create();

    $supplier = Party::create([
        'name' => 'Supplier '.uniqid(),
        'type' => 'supplier',
        'status' => 'active',
        'code' => 'SUP-'.rand(1000, 9999),
    ]);

    $inward1 = StockInward::create([
        'inward_no' => 'INW-TEST-M1',
        'inward_date' => '2026-09-28',
        'supplier_party_id' => $supplier->id,
        'grand_total' => 30000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $inward2 = StockInward::create([
        'inward_no' => 'INW-TEST-M2',
        'inward_date' => '2026-09-28',
        'supplier_party_id' => $supplier->id,
        'grand_total' => 40000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $payload = [
        'payment_no' => 'PAY-TEST-MULTI',
        'payment_date' => '2026-09-29',
        'party_id' => $supplier->id,
        'payment_mode' => 'bank_transfer',
        'amount' => 50000.00,
        'items' => [
            [
                'stock_inward_id' => $inward1->id,
                'paid_amount' => 30000.00, // Full for inward 1
            ],
            [
                'stock_inward_id' => $inward2->id,
                'paid_amount' => 20000.00, // Partial for inward 2
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/purchase-payments', $payload);

    $response->assertStatus(201);

    $inward1->refresh();
    $inward2->refresh();

    expect((float) $inward1->paid_amount)->toBe(30000.00)
        ->and($inward1->payment_status)->toBe('paid')
        ->and((float) $inward2->paid_amount)->toBe(20000.00)
        ->and($inward2->payment_status)->toBe('partially_paid');
});

test('deleting a payment voucher reverts the invoice payment status', function () {
    $user = User::factory()->create();

    $supplier = Party::create([
        'name' => 'Supplier '.uniqid(),
        'type' => 'supplier',
        'status' => 'active',
        'code' => 'SUP-'.rand(1000, 9999),
    ]);

    $inward = StockInward::create([
        'inward_no' => 'INW-TEST-DEL',
        'inward_date' => '2026-09-28',
        'supplier_party_id' => $supplier->id,
        'grand_total' => 15000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $createRes = $this->actingAs($user)->postJson('/api/v1/purchase-payments', [
        'payment_no' => 'PAY-TEST-DEL',
        'payment_date' => '2026-09-29',
        'party_id' => $supplier->id,
        'payment_mode' => 'cash',
        'amount' => 15000.00,
        'items' => [
            [
                'stock_inward_id' => $inward->id,
                'paid_amount' => 15000.00,
            ],
        ],
    ]);

    $paymentId = $createRes->json('data.id');

    $inward->refresh();
    expect($inward->payment_status)->toBe('paid');

    // Delete payment
    $deleteRes = $this->actingAs($user)->deleteJson("/api/v1/purchase-payments/{$paymentId}");
    $deleteRes->assertOk();

    $inward->refresh();
    expect((float) $inward->paid_amount)->toBe(0.00)
        ->and($inward->payment_status)->toBe('unpaid');
});
