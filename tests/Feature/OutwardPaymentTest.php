<?php

use App\Models\OutwardPayment;
use App\Models\Party;
use App\Models\StockOutward;
use App\Models\User;

test('outward payment metadata endpoint returns next voucher and customers', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/outward-payments/meta');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'next_payment_no',
                'customers',
                'payment_modes',
            ],
        ]);
});

test('pending invoices endpoint returns unpaid and partially paid outward consignments for customer', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'Customer '.uniqid(),
        'type' => 'customer',
        'status' => 'active',
        'code' => 'CUST-'.rand(1000, 9999),
    ]);

    $outward = StockOutward::create([
        'entry_no' => 'OUT-TEST-01',
        'entry_type' => 'sales',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 75000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $response = $this->actingAs($user)->getJson("/api/v1/outward-payments/pending-invoices/{$customer->id}");

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $outward->id,
            'total_amount' => 75000,
            'balance_due' => 75000,
            'payment_status' => 'unpaid',
        ]);
});

test('can record full receipt against single outward invoice', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'Customer '.uniqid(),
        'type' => 'customer',
        'status' => 'active',
        'code' => 'CUST-'.rand(1000, 9999),
    ]);

    $outward = StockOutward::create([
        'entry_no' => 'OUT-TEST-FULL',
        'entry_type' => 'sales',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 45000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $payload = [
        'payment_no' => 'REC-TEST-001',
        'payment_date' => '2026-09-29',
        'party_id' => $customer->id,
        'payment_mode' => 'bank_transfer',
        'reference_no' => 'NEFT-TEST-12345',
        'bank_name' => 'ICICI Bank',
        'amount' => 45000.00,
        'remarks' => 'Full sales clearance test',
        'items' => [
            [
                'stock_outward_id' => $outward->id,
                'paid_amount' => 45000.00,
                'remarks' => 'Full invoice receipt',
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/outward-payments', $payload);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'success',
        ]);

    $outward->refresh();
    expect((float) $outward->paid_amount)->toBe(45000.00)
        ->and($outward->payment_status)->toBe('paid');
});

test('can record partial receipt against single outward invoice', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'Customer '.uniqid(),
        'type' => 'customer',
        'status' => 'active',
        'code' => 'CUST-'.rand(1000, 9999),
    ]);

    $outward = StockOutward::create([
        'entry_no' => 'OUT-TEST-PART',
        'entry_type' => 'sales',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 80000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $payload = [
        'payment_no' => 'REC-TEST-002',
        'payment_date' => '2026-09-29',
        'party_id' => $customer->id,
        'payment_mode' => 'cheque',
        'reference_no' => 'CHQ-554433',
        'amount' => 30000.00,
        'items' => [
            [
                'stock_outward_id' => $outward->id,
                'paid_amount' => 30000.00,
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/outward-payments', $payload);

    $response->assertStatus(201);

    $outward->refresh();
    expect((float) $outward->paid_amount)->toBe(30000.00)
        ->and($outward->payment_status)->toBe('partially_paid');
});

test('can record receipt across multiple outward invoices', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'Customer '.uniqid(),
        'type' => 'customer',
        'status' => 'active',
        'code' => 'CUST-'.rand(1000, 9999),
    ]);

    $outward1 = StockOutward::create([
        'entry_no' => 'OUT-TEST-M1',
        'entry_type' => 'sales',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 35000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $outward2 = StockOutward::create([
        'entry_no' => 'OUT-TEST-M2',
        'entry_type' => 'sales',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 50000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $payload = [
        'payment_no' => 'REC-TEST-MULTI',
        'payment_date' => '2026-09-29',
        'party_id' => $customer->id,
        'payment_mode' => 'upi',
        'amount' => 60000.00,
        'items' => [
            [
                'stock_outward_id' => $outward1->id,
                'paid_amount' => 35000.00, // Full for outward 1
            ],
            [
                'stock_outward_id' => $outward2->id,
                'paid_amount' => 25000.00, // Partial for outward 2
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/outward-payments', $payload);

    $response->assertStatus(201);

    $outward1->refresh();
    $outward2->refresh();

    expect((float) $outward1->paid_amount)->toBe(35000.00)
        ->and($outward1->payment_status)->toBe('paid')
        ->and((float) $outward2->paid_amount)->toBe(25000.00)
        ->and($outward2->payment_status)->toBe('partially_paid');
});

test('deleting a customer receipt voucher reverts the outward invoice payment status', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'Customer '.uniqid(),
        'type' => 'customer',
        'status' => 'active',
        'code' => 'CUST-'.rand(1000, 9999),
    ]);

    $outward = StockOutward::create([
        'entry_no' => 'OUT-TEST-DEL',
        'entry_type' => 'sales',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 22000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $createRes = $this->actingAs($user)->postJson('/api/v1/outward-payments', [
        'payment_no' => 'REC-TEST-DEL',
        'payment_date' => '2026-09-29',
        'party_id' => $customer->id,
        'payment_mode' => 'cash',
        'amount' => 22000.00,
        'items' => [
            [
                'stock_outward_id' => $outward->id,
                'paid_amount' => 22000.00,
            ],
        ],
    ]);

    $paymentId = $createRes->json('data.id');

    $outward->refresh();
    expect($outward->payment_status)->toBe('paid');

    // Delete receipt
    $deleteRes = $this->actingAs($user)->deleteJson("/api/v1/outward-payments/{$paymentId}");
    $deleteRes->assertOk();

    $outward->refresh();
    expect((float) $outward->paid_amount)->toBe(0.00)
        ->and($outward->payment_status)->toBe('unpaid');
});

test('outward payments listing enforces 5 items per page pagination', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'Customer '.uniqid(),
        'type' => 'customer',
        'status' => 'active',
        'code' => 'CUST-'.rand(1000, 9999),
    ]);

    for ($i = 1; $i <= 7; $i++) {
        OutwardPayment::create([
            'payment_no' => "REC-PAG-{$i}-".uniqid(),
            'payment_date' => '2026-09-29',
            'party_id' => $customer->id,
            'payment_mode' => 'cash',
            'amount' => 1000.00 * $i,
        ]);
    }

    $response = $this->actingAs($user)->getJson('/api/v1/outward-payments?page=1');

    $response->assertOk()
        ->assertJsonPath('meta.per_page', 5)
        ->assertJsonCount(5, 'data');
});

test('can record payment receipt with discount and commission settling outward invoice', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'Customer '.uniqid(),
        'type' => 'customer',
        'status' => 'active',
        'code' => 'CUST-'.rand(1000, 9999),
    ]);

    $agent = Party::create([
        'name' => 'Agent '.uniqid(),
        'type' => 'agent',
        'status' => 'active',
        'code' => 'AGT-'.rand(1000, 9999),
    ]);

    $outward = StockOutward::create([
        'entry_no' => 'OUT-SETTLE-01',
        'entry_type' => 'sales',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 50000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    // Customer pays 47,500 with 2,500 settlement discount (total 50,000) and 1,000 commission to agent
    $payload = [
        'payment_no' => 'REC-DISC-COMM-01',
        'payment_date' => '2026-09-29',
        'party_id' => $customer->id,
        'payment_mode' => 'bank_transfer',
        'reference_no' => 'NEFT-554433',
        'amount' => 47500.00,
        'discount_amount' => 2500.00,
        'commission_agent_id' => $agent->id,
        'commission_amount' => 1000.00,
        'items' => [
            [
                'stock_outward_id' => $outward->id,
                'paid_amount' => 47500.00,
                'discount_amount' => 2500.00,
                'commission_agent_id' => $agent->id,
                'commission_amount' => 1000.00,
                'remarks' => 'Full settlement with cash discount',
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/outward-payments', $payload);

    $response->assertCreated()
        ->assertJson(['status' => 'success']);

    $outward->refresh();
    expect((float) $outward->paid_amount)->toBe(47500.00)
        ->and($outward->payment_status)->toBe('paid'); // 47,500 + 2,500 = 50,000 >= 50,000

    $this->assertDatabaseHas('outward_payments', [
        'payment_no' => 'REC-DISC-COMM-01',
        'amount' => 47500.00,
        'discount_amount' => 2500.00,
        'commission_agent_id' => $agent->id,
        'commission_amount' => 1000.00,
    ]);

    $this->assertDatabaseHas('outward_payment_items', [
        'stock_outward_id' => $outward->id,
        'paid_amount' => 47500.00,
        'discount_amount' => 2500.00,
        'commission_agent_id' => $agent->id,
        'commission_amount' => 1000.00,
    ]);
});

test('all pending outwards endpoint returns sales outwards and excludes returns and paid outwards', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'Customer '.uniqid(),
        'type' => 'customer',
        'status' => 'active',
        'code' => 'CUST-'.rand(1000, 9999),
    ]);

    // 1. Pending Sales Outward (Should be returned)
    $salesOutward = StockOutward::create([
        'entry_no' => 'OUT-SALE-'.uniqid(),
        'entry_type' => 'sales',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 40000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    // 2. Return Outward (Should be EXCLUDED)
    $returnOutward = StockOutward::create([
        'entry_no' => 'OUT-RET-'.uniqid(),
        'entry_type' => 'return',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 10000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    // 3. Fully Paid Outward (Should be EXCLUDED)
    $paidOutward = StockOutward::create([
        'entry_no' => 'OUT-PAID-'.uniqid(),
        'entry_type' => 'sales',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 20000.00,
        'paid_amount' => 20000.00,
        'payment_status' => 'paid',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/outward-payments/all-pending-outwards');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data',
            'total_pending_count',
            'total_pending_amount',
        ]);

    $returnedIds = collect($response->json('data'))->pluck('id')->all();

    expect($returnedIds)->toContain($salesOutward->id)
        ->and($returnedIds)->not->toContain($returnOutward->id)
        ->and($returnedIds)->not->toContain($paidOutward->id);
});

test('can toggle lock status on outward payment receipt and prevent update or deletion when locked', function () {
    $user = User::factory()->create();

    $customer = Party::create([
        'name' => 'Customer '.uniqid(),
        'type' => 'customer',
        'status' => 'active',
        'code' => 'CUST-'.rand(1000, 9999),
    ]);

    $outward = StockOutward::create([
        'entry_no' => 'OUT-TEST-LOCK-'.uniqid(),
        'entry_type' => 'sales',
        'party_id' => $customer->id,
        'customer' => $customer->name,
        'invoice_date' => '2026-09-28',
        'total_amount' => 15000.00,
        'paid_amount' => 0.00,
        'payment_status' => 'unpaid',
    ]);

    $createRes = $this->actingAs($user)->postJson('/api/v1/outward-payments', [
        'payment_no' => 'REC-TEST-LOCK-'.uniqid(),
        'payment_date' => '2026-09-29',
        'party_id' => $customer->id,
        'payment_mode' => 'bank_transfer',
        'amount' => 15000.00,
        'items' => [
            [
                'stock_outward_id' => $outward->id,
                'paid_amount' => 15000.00,
            ],
        ],
    ]);

    $paymentId = $createRes->json('data.id');

    // 1. Toggle lock -> Lock
    $lockRes = $this->actingAs($user)->postJson("/api/v1/outward-payments/{$paymentId}/toggle-lock");
    $lockRes->assertOk();
    expect($lockRes->json('data.is_locked'))->toBeTrue()
        ->and($lockRes->json('data.locked_by.id'))->toBe($user->id);

    // 2. Attempt update while locked -> 422
    $updateRes = $this->actingAs($user)->putJson("/api/v1/outward-payments/{$paymentId}", [
        'payment_no' => $createRes->json('data.payment_no'),
        'payment_date' => '2026-09-29',
        'party_id' => $customer->id,
        'payment_mode' => 'cash',
        'amount' => 15000.00,
        'items' => [
            [
                'stock_outward_id' => $outward->id,
                'paid_amount' => 15000.00,
            ],
        ],
    ]);
    $updateRes->assertStatus(422);
    expect($updateRes->json('message'))->toContain('locked and cannot be modified');

    // 3. Attempt delete while locked -> 422
    $deleteRes = $this->actingAs($user)->deleteJson("/api/v1/outward-payments/{$paymentId}");
    $deleteRes->assertStatus(422);
    expect($deleteRes->json('message'))->toContain('locked and cannot be deleted');

    // 4. Toggle lock -> Unlock
    $unlockRes = $this->actingAs($user)->postJson("/api/v1/outward-payments/{$paymentId}/toggle-lock");
    $unlockRes->assertOk();
    expect($unlockRes->json('data.is_locked'))->toBeFalse();

    // 5. Delete succeeds after unlock
    $deleteRes2 = $this->actingAs($user)->deleteJson("/api/v1/outward-payments/{$paymentId}");
    $deleteRes2->assertOk();
});
