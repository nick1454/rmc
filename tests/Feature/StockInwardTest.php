<?php

use App\Models\Category;
use App\Models\Item;
use App\Models\Party;
use App\Models\Production;
use App\Models\ProductionConsumption;
use App\Models\Role;
use App\Models\StockInward;
use App\Models\StockInwardItem;
use App\Models\User;
use App\Services\StockService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $this->user = User::factory()->create(['role_id' => $role->id]);
    Sanctum::actingAs($this->user);

    $this->category = Category::firstOrCreate(['name' => 'Cement']);
    $this->rmItem = Item::firstOrCreate(
        ['category' => 'Cement', 'size' => 'OPC 53 Test'],
        [
            'category_id' => $this->category->id,
            'type' => 'rm',
            'primary_unit' => 'ton',
            'secondary_unit' => 'kgs',
            'conversion_factor' => 1000,
        ]
    );

    $this->supplier = Party::firstOrCreate(
        ['name' => 'UltraTech Test Supplier'],
        [
            'type' => 'supplier',
            'code' => 'SUP-9999',
            'phone' => '+91 98230 11223',
        ]
    );
});

test('authenticated user can fetch stock inwards metadata', function () {
    $response = $this->getJson('/api/v1/inwards/meta');

    $response->assertOk()
        ->assertJsonStructure([
            'next_inward_no',
            'suppliers',
            'rm_items',
            'users',
        ]);
});

test('user can record a new stock inward shipment with commercials', function () {
    $payload = [
        'inward_no' => 'INW-TEST-1001',
        'inward_date' => now()->format('Y-m-d'),
        'supplier_party_id' => $this->supplier->id,
        'supplier_name' => $this->supplier->name,
        'challan_no' => 'CH-8899',
        'supplier_bill_no' => 'BILL-8899',
        'supplier_bill_date' => now()->format('Y-m-d'),
        'vehicle_no' => 'MH 12 AB 1234',
        'driver_name' => 'Ramesh Patil',
        'freight_amount' => 500.0,
        'other_charges' => 200.0,
        'discount_amount' => 100.0,
        'items' => [
            [
                'item_id' => $this->rmItem->id,
                'qty_primary' => 10.0,
                'unit_primary' => 'ton',
                'qty_secondary' => 10000.0,
                'unit_secondary' => 'kgs',
                'conversion_factor' => 1000.0,
                'rate_per_unit' => 4000.0,
                'gst_rate' => 18.0,
                'remarks' => 'Silo-01 Unloaded',
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/inwards', $payload);

    $response->assertCreated()
        ->assertJsonFragment(['inward_no' => 'INW-TEST-1001'])
        ->assertJsonFragment(['supplier_bill_no' => 'BILL-8899']);

    // Taxable = 10 * 4000 = 40,000. GST = 40,000 * 0.18 = 7,200. Total Item = 47,200.
    // Grand Total = 40,000 + 7,200 + 500 + 200 - 100 = 47,800.
    $this->assertDatabaseHas('stock_inwards', [
        'inward_no' => 'INW-TEST-1001',
        'challan_no' => 'CH-8899',
        'supplier_bill_no' => 'BILL-8899',
        'subtotal_amount' => 40000.00,
        'tax_amount' => 7200.00,
        'freight_amount' => 500.00,
        'other_charges' => 200.00,
        'discount_amount' => 100.00,
        'grand_total' => 47800.00,
    ]);

    $this->assertDatabaseHas('stock_inward_items', [
        'item_id' => $this->rmItem->id,
        'qty_primary' => 10.0,
        'rate_per_unit' => 4000.00,
        'taxable_amount' => 40000.00,
        'gst_rate' => 18.00,
        'gst_amount' => 7200.00,
        'total_amount' => 47200.00,
    ]);
});

test('stock inward increases raw material available stock balance', function () {
    $stockService = app(StockService::class);

    // Initial inward
    $inward = StockInward::create([
        'inward_no' => 'INW-TEST-1002',
        'inward_date' => now()->format('Y-m-d'),
        'supplier_party_id' => $this->supplier->id,
    ]);

    StockInwardItem::create([
        'stock_inward_id' => $inward->id,
        'item_id' => $this->rmItem->id,
        'qty_primary' => 30.0,
        'unit_primary' => 'ton',
        'qty_secondary' => 30000.0,
        'unit_secondary' => 'kgs',
        'conversion_factor' => 1000.0,
    ]);

    // Available stock should be >= 30 Tons
    $updatedStock = $stockService->getItemStock($this->rmItem->id);
    expect((float) $updatedStock['total_inward_primary'])->toBeGreaterThanOrEqual(30.0)
        ->and((float) $updatedStock['available_secondary'])->toBeGreaterThanOrEqual(30000.0);
});

test('stock inward appears in summary report and ledger audit trail', function () {
    $inward = StockInward::create([
        'inward_no' => 'INW-TEST-1003',
        'inward_date' => now()->format('Y-m-d'),
        'supplier_party_id' => $this->supplier->id,
        'vehicle_no' => 'MH 14 CC 9999',
    ]);

    StockInwardItem::create([
        'stock_inward_id' => $inward->id,
        'item_id' => $this->rmItem->id,
        'qty_primary' => 40.0,
        'unit_primary' => 'ton',
        'qty_secondary' => 40000.0,
        'unit_secondary' => 'kgs',
        'conversion_factor' => 1000.0,
    ]);

    // Test Summary API
    $summaryResponse = $this->getJson('/api/v1/reports/stock/summary');
    $summaryResponse->assertOk()
        ->assertJsonFragment(['item_id' => $this->rmItem->id]);

    // Test Ledger API
    $ledgerResponse = $this->getJson("/api/v1/reports/stock/ledger/{$this->rmItem->id}");
    $ledgerResponse->assertOk()
        ->assertJsonFragment(['reference_no' => 'INW-TEST-1003'])
        ->assertJsonFragment(['action_label' => 'Inward Receipt (+)'])
        ->assertJsonFragment(['qty_in_primary' => 40.0]);
});

test('user can update and delete a stock inward entry', function () {
    $inward = StockInward::create([
        'inward_no' => 'INW-TEST-1004',
        'inward_date' => now()->format('Y-m-d'),
        'supplier_party_id' => $this->supplier->id,
    ]);

    StockInwardItem::create([
        'stock_inward_id' => $inward->id,
        'item_id' => $this->rmItem->id,
        'qty_primary' => 15.0,
        'unit_primary' => 'ton',
        'qty_secondary' => 15000.0,
        'unit_secondary' => 'kgs',
        'conversion_factor' => 1000.0,
    ]);

    // Update to 20 Tons with commercial details
    $updatePayload = [
        'inward_no' => 'INW-TEST-1004',
        'inward_date' => now()->format('Y-m-d'),
        'supplier_party_id' => $this->supplier->id,
        'freight_amount' => 300.0,
        'discount_amount' => 50.0,
        'items' => [
            [
                'item_id' => $this->rmItem->id,
                'qty_primary' => 20.0,
                'unit_primary' => 'ton',
                'qty_secondary' => 20000.0,
                'unit_secondary' => 'kgs',
                'conversion_factor' => 1000.0,
                'rate_per_unit' => 3500.0,
                'gst_rate' => 18.0,
            ],
        ],
    ];

    $updateResponse = $this->putJson("/api/v1/inwards/{$inward->id}", $updatePayload);
    $updateResponse->assertOk();

    // 20 * 3500 = 70,000. GST = 70,000 * 0.18 = 12,600. Grand = 70,000 + 12,600 + 300 - 50 = 82,850.
    $this->assertDatabaseHas('stock_inwards', [
        'id' => $inward->id,
        'subtotal_amount' => 70000.00,
        'tax_amount' => 12600.00,
        'freight_amount' => 300.00,
        'discount_amount' => 50.00,
        'grand_total' => 82850.00,
    ]);

    $this->assertDatabaseHas('stock_inward_items', [
        'stock_inward_id' => $inward->id,
        'qty_primary' => 20.0,
        'rate_per_unit' => 3500.00,
        'taxable_amount' => 70000.00,
        'gst_rate' => 18.00,
        'gst_amount' => 12600.00,
        'total_amount' => 82600.00,
    ]);

    // Delete
    $deleteResponse = $this->deleteJson("/api/v1/inwards/{$inward->id}");
    $deleteResponse->assertOk();

    $this->assertDatabaseMissing('stock_inwards', ['id' => $inward->id]);
    $this->assertDatabaseMissing('stock_inward_items', ['stock_inward_id' => $inward->id]);
});

test('cannot reduce stock inward item quantity if material has been consumed in production', function () {
    $fgItem = Item::firstOrCreate(
        ['category' => 'Concrete', 'size' => 'M25 Test'],
        ['type' => 'fg', 'primary_unit' => 'cum', 'secondary_unit' => 'cum']
    );

    $inward = StockInward::create([
        'inward_no' => 'INW-TEST-GUARD-01',
        'inward_date' => now()->format('Y-m-d'),
        'supplier_party_id' => $this->supplier->id,
    ]);

    StockInwardItem::create([
        'stock_inward_id' => $inward->id,
        'item_id' => $this->rmItem->id,
        'qty_primary' => 10.0,
        'unit_primary' => 'ton',
        'qty_secondary' => 10000.0,
        'unit_secondary' => 'kgs',
        'conversion_factor' => 1000.0,
    ]);

    // Simulate concrete batch production consuming 8 Tons (8000 kgs)
    $production = Production::create([
        'production_no' => 'PRD-TEST-G01',
        'production_date' => now()->format('Y-m-d'),
        'plant_name' => 'Plant 1',
        'fg_item_id' => $fgItem->id,
        'cbm' => 10.0,
        'created_by' => $this->user->id,
    ]);

    ProductionConsumption::create([
        'production_id' => $production->id,
        'item_id' => $this->rmItem->id,
        'consumed_qty' => 8000.0,
        'unit' => 'kgs',
        'consumed_primary_qty' => 8.0,
        'primary_unit' => 'ton',
        'consumed_date' => now()->format('Y-m-d'),
    ]);

    // Available stock is now 2 Tons (10 - 8).
    // Attempting to reduce inward quantity from 10 Tons down to 5 Tons (reduction of 5 Tons > 2 Tons available) should fail
    $updatePayload = [
        'inward_no' => 'INW-TEST-GUARD-01',
        'inward_date' => now()->format('Y-m-d'),
        'supplier_party_id' => $this->supplier->id,
        'items' => [
            [
                'item_id' => $this->rmItem->id,
                'qty_primary' => 5.0,
                'unit_primary' => 'ton',
                'qty_secondary' => 5000.0,
                'unit_secondary' => 'kgs',
                'conversion_factor' => 1000.0,
            ],
        ],
    ];

    $response = $this->putJson("/api/v1/inwards/{$inward->id}", $updatePayload);

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot reduce Cement - OPC 53 Test inward quantity by 5 ton. This material has already been consumed in production batches (Currently available stock: 2 ton).',
        ]);
});

test('cannot delete stock inward shipment if material has been consumed in production', function () {
    $fgItem = Item::firstOrCreate(
        ['category' => 'Concrete', 'size' => 'M25 Test'],
        ['type' => 'fg', 'primary_unit' => 'cum', 'secondary_unit' => 'cum']
    );

    $inward = StockInward::create([
        'inward_no' => 'INW-TEST-GUARD-02',
        'inward_date' => now()->format('Y-m-d'),
        'supplier_party_id' => $this->supplier->id,
    ]);

    StockInwardItem::create([
        'stock_inward_id' => $inward->id,
        'item_id' => $this->rmItem->id,
        'qty_primary' => 10.0,
        'unit_primary' => 'ton',
        'qty_secondary' => 10000.0,
        'unit_secondary' => 'kgs',
        'conversion_factor' => 1000.0,
    ]);

    // Simulate concrete batch production consuming 6 Tons
    $production = Production::create([
        'production_no' => 'PRD-TEST-G02',
        'production_date' => now()->format('Y-m-d'),
        'plant_name' => 'Plant 1',
        'fg_item_id' => $fgItem->id,
        'cbm' => 8.0,
        'created_by' => $this->user->id,
    ]);

    ProductionConsumption::create([
        'production_id' => $production->id,
        'item_id' => $this->rmItem->id,
        'consumed_qty' => 6000.0,
        'unit' => 'kgs',
        'consumed_primary_qty' => 6.0,
        'primary_unit' => 'ton',
        'consumed_date' => now()->format('Y-m-d'),
    ]);

    // Deleting the entire 10 Tons inward when only 4 Tons are available should fail
    $response = $this->deleteJson("/api/v1/inwards/{$inward->id}");

    $response->assertStatus(422)
        ->assertJsonFragment([
            'message' => 'Cannot delete inward entry. 10 ton of Cement - OPC 53 Test was received in this shipment, but only 4 ton is currently available as the rest has already been consumed in production.',
        ]);
});
