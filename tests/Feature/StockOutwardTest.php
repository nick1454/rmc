<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Party;
use App\Models\Production;
use App\Models\Role;
use App\Models\StockOutward;
use App\Models\StockOutwardItem;
use App\Models\User;
use App\Services\StockService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->user = User::factory()->create(['role_id' => $role->id]);
    Sanctum::actingAs($this->user);

    $this->fgCategory = Category::firstOrCreate(['name' => 'Concrete']);
    $this->fgItem = Item::firstOrCreate(
        ['category' => 'Concrete', 'size' => 'M25 RMC Test'],
        [
            'category_id' => $this->fgCategory->id,
            'type' => 'fg',
            'primary_unit' => 'cum',
            'secondary_unit' => 'cum',
            'conversion_factor' => 1.0,
        ]
    );

    $this->customer = Party::firstOrCreate(
        ['name' => 'L&T Construction Test Customer'],
        [
            'type' => 'customer',
            'code' => 'CUST-9999',
            'phone' => '+91 98220 55443',
            'address' => 'Metro Site Powai',
        ]
    );

    $this->agent = Party::firstOrCreate(
        ['name' => 'Apex Infra Agent'],
        [
            'type' => 'agent',
            'code' => 'AGT-1010',
            'phone' => '+91 98111 22334',
        ]
    );

    // Initial production batch stock for tests
    Production::create([
        'production_no' => 'PROD-SETUP-TEST-01',
        'fg_item_id' => $this->fgItem->id,
        'batch_no' => 'BATCH-2026-M25-01',
        'cbm' => 100.0,
        'production_date' => now()->toDateString(),
    ]);
});

test('authenticated user can fetch stock outwards metadata', function () {
    $response = $this->getJson('/api/v1/outwards/meta');

    $response->assertOk()
        ->assertJsonStructure([
            'next_entry_no',
            'parties',
            'customers',
            'agents',
            'items',
            'users',
        ]);
});

test('user can record a new stock outward delivery consignment', function () {
    $payload = [
        'entry_no' => 'OUT-TEST-1001',
        'entry_type' => 'sales',
        'invoice_date' => now()->format('Y-m-d'),
        'party_id' => $this->customer->id,
        'customer' => $this->customer->name,
        'agent_id' => $this->agent->id,
        'agent_name' => $this->agent->name,
        'challan_no' => 'DC-8811',
        'challan_date' => now()->format('Y-m-d'),
        'vehicle_no' => 'MH 14 CC 8899',
        'driver_name' => 'Santosh Shinde',
        'driver_phone' => '+91 98765 00112',
        'destination' => 'Metro Line 4 Pier 42',
        'plant_name' => 'Central Plant - Batching Unit 1',
        'freight_charges' => 500.0,
        'discount_amount' => 100.0,
        'remarks' => 'Slump test 140mm passed',
        'items' => [
            [
                'item_id' => $this->fgItem->id,
                'qty' => 6.0,
                'unit' => 'cum',
                'secondary_qty' => 6.0,
                'secondary_unit' => 'cum',
                'conversion_factor' => 1.0,
                'rate' => 4500.0,
                'gst_rate' => 18.0,
                'lotno' => 'BATCH-2026-M25-01',
                'remarks' => 'Transit Mixer 04 Loaded',
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/outwards', $payload);

    $response->assertCreated()
        ->assertJsonFragment(['entry_no' => 'OUT-TEST-1001'])
        ->assertJsonFragment(['vehicle_no' => 'MH 14 CC 8899']);

    // Taxable = 6 * 4500 = 27,000. GST = 27,000 * 0.18 = 4,860. Subtotal = 27,000. Tax = 4,860.
    // Grand Total = 27,000 + 4,860 + 500 - 100 = 32,260.
    $this->assertDatabaseHas('stock_outwards', [
        'entry_no' => 'OUT-TEST-1001',
        'challan_no' => 'DC-8811',
        'vehicle_no' => 'MH 14 CC 8899',
        'subtotal' => 27000.00,
        'tax_amount' => 4860.00,
        'freight_charges' => 500.00,
        'discount_amount' => 100.00,
        'total_amount' => 32260.00,
    ]);

    $this->assertDatabaseHas('stock_outward_items', [
        'item_id' => $this->fgItem->id,
        'qty' => 6.0000,
        'rate' => 4500.00,
        'taxable_amount' => 27000.00,
        'gst_amount' => 4860.00,
        'total_amount' => 31860.00,
    ]);
});

test('user can list and paginate stock outwards with search filter', function () {
    StockOutward::create([
        'entry_no' => 'OUT-SEARCH-01',
        'entry_type' => 'sales',
        'customer' => 'Shapoorji Pallonji Special',
        'invoice_date' => now()->toDateString(),
        'total_amount' => 50000,
    ]);

    StockOutward::create([
        'entry_no' => 'OUT-SEARCH-02',
        'entry_type' => 'transfer',
        'customer' => 'Godrej Properties Depot',
        'invoice_date' => now()->toDateString(),
        'total_amount' => 25000,
    ]);

    $response = $this->getJson('/api/v1/outwards?search=Shapoorji');

    $response->assertOk()
        ->assertJsonFragment(['entry_no' => 'OUT-SEARCH-01'])
        ->assertJsonMissing(['entry_no' => 'OUT-SEARCH-02']);
});

test('user can update stock outward consignment and items', function () {
    $outward = StockOutward::create([
        'entry_no' => 'OUT-UPDATE-01',
        'entry_type' => 'sales',
        'party_id' => $this->customer->id,
        'customer' => $this->customer->name,
        'invoice_date' => now()->toDateString(),
        'subtotal' => 10000,
        'tax_amount' => 1800,
        'total_amount' => 11800,
    ]);

    $payload = [
        'entry_no' => 'OUT-UPDATE-01',
        'entry_type' => 'sales',
        'party_id' => $this->customer->id,
        'customer' => 'Updated Customer Name',
        'invoice_date' => now()->toDateString(),
        'destination' => 'New Site Sector 12',
        'freight_charges' => 200,
        'discount_amount' => 50,
        'items' => [
            [
                'item_id' => $this->fgItem->id,
                'qty' => 4.0,
                'unit' => 'cum',
                'secondary_qty' => 4.0,
                'secondary_unit' => 'cum',
                'rate' => 5000.0,
                'gst_rate' => 18.0,
            ],
        ],
    ];

    $response = $this->putJson("/api/v1/outwards/{$outward->id}", $payload);

    $response->assertOk()
        ->assertJsonFragment(['destination' => 'New Site Sector 12']);

    // Taxable = 4 * 5000 = 20,000. GST = 3,600. Grand = 20,000 + 3,600 + 200 - 50 = 23,750.
    $this->assertDatabaseHas('stock_outwards', [
        'id' => $outward->id,
        'destination' => 'New Site Sector 12',
        'subtotal' => 20000.00,
        'tax_amount' => 3600.00,
        'total_amount' => 23750.00,
    ]);
});

test('user can delete stock outward consignment and soft delete cascades', function () {
    $outward = StockOutward::create([
        'entry_no' => 'OUT-DEL-01',
        'entry_type' => 'sales',
        'invoice_date' => now()->toDateString(),
    ]);

    $item = StockOutwardItem::create([
        'stock_outward_id' => $outward->id,
        'item_id' => $this->fgItem->id,
        'qty' => 5.0,
        'unit' => 'cum',
    ]);

    $response = $this->deleteJson("/api/v1/outwards/{$outward->id}");

    $response->assertOk();

    $this->assertSoftDeleted('stock_outwards', ['id' => $outward->id]);
    $this->assertSoftDeleted('stock_outward_items', ['id' => $item->id]);
});

test('stock outward reduces inventory stock balance and soft delete restores it', function () {
    // 1. Create a production batch of 20 CBM of FG Item
    Production::create([
        'production_no' => 'PROD-TEST-FG-01',
        'fg_item_id' => $this->fgItem->id,
        'cbm' => 20.0,
        'production_date' => now()->toDateString(),
    ]);

    $stockService = app(StockService::class);
    $initialStock = $stockService->getItemStock($this->fgItem->id);
    expect($initialStock['available_primary'])->toBe(120.0);

    // 2. Dispatch 8 CBM via Stock Outward
    $outward = StockOutward::create([
        'entry_no' => 'OUT-STOCK-TEST-01',
        'entry_type' => 'sales',
        'invoice_date' => now()->toDateString(),
    ]);

    $item = StockOutwardItem::create([
        'stock_outward_id' => $outward->id,
        'item_id' => $this->fgItem->id,
        'qty' => 8.0,
        'unit' => 'cum',
        'secondary_qty' => 8.0,
        'secondary_unit' => 'cum',
    ]);

    $afterDispatchStock = $stockService->getItemStock($this->fgItem->id);
    expect($afterDispatchStock['total_outward_primary'])->toBe(8.0)
        ->and($afterDispatchStock['available_primary'])->toBe(112.0);

    // 3. Soft-delete the outward consignment
    $outward->items()->delete();
    $outward->delete();

    $afterDeleteStock = $stockService->getItemStock($this->fgItem->id);
    expect($afterDeleteStock['total_outward_primary'])->toBe(0.0)
        ->and($afterDeleteStock['available_primary'])->toBe(120.0);
});

test('can create stock outward with line item discounts and commissions', function () {
    $payload = [
        'entry_no' => 'OUT-DISC-COMM-01',
        'entry_type' => 'sales',
        'invoice_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'customer' => $this->customer->name,
        'agent_id' => $this->agent->id,
        'agent_name' => $this->agent->name,
        'freight_charges' => 1000.00,
        'items' => [
            [
                'item_id' => $this->fgItem->id,
                'qty' => 10.0,
                'rate' => 4000.00, // Gross = 40,000
                'discount_percent' => 5.0, // Overall cost = 40,000 + 7,200 (18% GST) = 47,200. Disc 5% = 2,360
                'gst_rate' => 18.0,
                'commission_agent_id' => $this->agent->id,
                'commission_rate' => 2.5, // Comm on taxable 40,000 * 2.5% = 1,000
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/outwards', $payload);

    $response->assertCreated()
        ->assertJsonFragment(['entry_no' => 'OUT-DISC-COMM-01']);

    $this->assertDatabaseHas('stock_outwards', [
        'entry_no' => 'OUT-DISC-COMM-01',
        'subtotal' => 40000.00,
        'tax_amount' => 7200.00,
        'discount_amount' => 2360.00,
        'commission_amount' => 1000.00,
        'freight_charges' => 1000.00,
        'total_amount' => 45840.00,
    ]);

    $this->assertDatabaseHas('stock_outward_items', [
        'item_id' => $this->fgItem->id,
        'discount_percent' => 5.00,
        'discount_amount' => 2360.00,
        'taxable_amount' => 40000.00,
        'gst_amount' => 7200.00,
        'commission_agent_id' => $this->agent->id,
        'commission_rate' => 2.50,
        'commission_amount' => 1000.00,
        'total_amount' => 44840.00,
    ]);
});

test('user can record outward item with pump cost and verify total cost inclusion', function () {
    $payload = [
        'entry_no' => 'OUT-PUMP-TEST-01',
        'entry_type' => 'sales',
        'invoice_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'items' => [
            [
                'item_id' => $this->fgItem->id,
                'qty' => 6.0,
                'rate' => 3500.00, // Gross = 21,000
                'pump_cost' => 1500.00, // Pump cost = 1,500
                'gst_rate' => 18.0, // GST = 3,780
                // Total = 21,000 + 3,780 + 1,500 = 26,280
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/outwards', $payload);

    $response->assertCreated()
        ->assertJsonFragment(['entry_no' => 'OUT-PUMP-TEST-01']);

    $this->assertDatabaseHas('stock_outwards', [
        'entry_no' => 'OUT-PUMP-TEST-01',
        'subtotal' => 21000.00,
        'tax_amount' => 3780.00,
        'total_amount' => 26280.00,
    ]);

    $this->assertDatabaseHas('stock_outward_items', [
        'item_id' => $this->fgItem->id,
        'rate' => 3500.00,
        'pump_cost' => 1500.00,
        'taxable_amount' => 21000.00,
        'gst_amount' => 3780.00,
        'total_amount' => 26280.00,
    ]);
});

test('outward cannot be deleted if payment is recorded', function () {
    $outward = StockOutward::create([
        'entry_no' => 'OUT-PAID-01',
        'entry_type' => 'sales',
        'invoice_date' => now()->toDateString(),
        'total_amount' => 10000.00,
        'paid_amount' => 5000.00,
        'payment_status' => 'partially_paid',
    ]);

    $item = StockOutwardItem::create([
        'stock_outward_id' => $outward->id,
        'item_id' => $this->fgItem->id,
        'qty' => 5.0,
        'rate' => 2000.00,
        'total_amount' => 10000.00,
    ]);

    $response = $this->deleteJson("/api/v1/outwards/{$outward->id}");

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => "Cannot delete outward consignment 'OUT-PAID-01' because payment has already been recorded against it."]);

    $this->assertDatabaseHas('stock_outwards', ['id' => $outward->id, 'deleted_at' => null]);
});

test('outward item quantity cannot be reduced below paid quantity', function () {
    $outward = StockOutward::create([
        'entry_no' => 'OUT-PAID-QTY-01',
        'entry_type' => 'sales',
        'invoice_date' => now()->toDateString(),
        'party_id' => $this->customer->id,
        'subtotal' => 10000.00,
        'total_amount' => 10000.00,
        'paid_amount' => 6000.00, // 60% paid
        'payment_status' => 'partially_paid',
    ]);

    StockOutwardItem::create([
        'stock_outward_id' => $outward->id,
        'item_id' => $this->fgItem->id,
        'qty' => 10.0, // 60% paid = min paid qty 6.0
        'rate' => 1000.00,
        'taxable_amount' => 10000.00,
        'total_amount' => 10000.00,
    ]);

    // Try reducing qty to 4.0 (which is less than paid 6.0)
    $payload = [
        'entry_no' => 'OUT-PAID-QTY-01',
        'party_id' => $this->customer->id,
        'items' => [
            [
                'item_id' => $this->fgItem->id,
                'qty' => 4.0, // Less than 6.0
                'rate' => 1000.00,
            ],
        ],
    ];

    $response = $this->putJson("/api/v1/outwards/{$outward->id}", $payload);

    $response->assertStatus(422);

    // Now update with qty 7.0 (>= 6.0), should succeed (provided stock exists)
    Production::create([
        'production_no' => 'PROD-FOR-UPDATE-01',
        'fg_item_id' => $this->fgItem->id,
        'cbm' => 20.0,
        'production_date' => now()->toDateString(),
    ]);

    $payload['items'][0]['qty'] = 7.0;
    $validResponse = $this->putJson("/api/v1/outwards/{$outward->id}", $payload);
    $validResponse->assertOk();
});

test('outward item quantity cannot exceed available stock for a batch', function () {
    // Produce 6 CBM for BATCH-A
    Production::create([
        'production_no' => 'PROD-BATCH-A',
        'fg_item_id' => $this->fgItem->id,
        'batch_no' => 'BATCH-A',
        'cbm' => 6.0,
        'production_date' => now()->toDateString(),
    ]);

    // Try to dispatch 8 CBM for BATCH-A
    $payload = [
        'entry_no' => 'OUT-STOCK-LIMIT-01',
        'entry_type' => 'sales',
        'party_id' => $this->customer->id,
        'items' => [
            [
                'item_id' => $this->fgItem->id,
                'qty' => 8.0, // Exceeds 6.0
                'lotno' => 'BATCH-A',
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/outwards', $payload);

    $response->assertStatus(422);

    // Now dispatch 5 CBM for BATCH-A, should succeed
    $payload['items'][0]['qty'] = 5.0;
    $validResponse = $this->postJson('/api/v1/outwards', $payload);
    $validResponse->assertCreated();
});
