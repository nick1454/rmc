<?php

use App\Models\Item;
use App\Models\Production;
use App\Models\User;

test('production metadata endpoint returns fg items, rm stock and next numbers', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/productions/meta');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'fg_items',
                'rm_stock',
                'next_production_no',
                'next_batch_no',
            ],
        ]);
});

test('production blocks entry when rm stock is insufficient', function () {
    $user = User::factory()->create();

    $cement = Item::firstOrCreate(
        ['category' => 'Cement', 'size' => 'opc40'],
        [
            'type' => 'rm',
            'primary_unit' => 'ton',
            'secondary_unit' => 'kgs',
            'conversion_factor' => 1000,
        ]
    );

    $fg = Item::firstOrCreate(
        ['category' => 'RMC', 'size' => 'M25'],
        [
            'type' => 'fg',
            'primary_unit' => 'cum',
            'secondary_unit' => 'cum',
            'conversion_factor' => 1,
        ]
    );

    $payload = [
        'production_date' => '2026-09-25',
        'fg_item_id' => $fg->id,
        'cbm' => 2.0,
        'plant_name' => 'Plant-01 Central',
        'recipe_items' => [
            [
                'rm_item_id' => $cement->id,
                'recipe_qty_per_cbm' => 350.0,
                'actual_qty_per_cbm' => 350.0,
                'unit' => 'kgs',
                'total_qty' => 100000.0,
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/productions', $payload);

    $response->assertStatus(422)
        ->assertJson([
            'status' => 'error',
        ]);
});

test('can validate and record production batch for exempt materials like water', function () {
    $user = User::factory()->create();

    $water = Item::firstOrCreate(
        ['category' => 'Water', 'size' => 'General'],
        [
            'type' => 'rm',
            'primary_unit' => 'ltr',
            'secondary_unit' => 'ltr',
            'conversion_factor' => 1,
        ]
    );

    $fg = Item::firstOrCreate(
        ['category' => 'RMC', 'size' => 'M20'],
        [
            'type' => 'fg',
            'primary_unit' => 'cum',
            'secondary_unit' => 'cum',
            'conversion_factor' => 1,
        ]
    );

    $payload = [
        'production_date' => '2026-09-25',
        'fg_item_id' => $fg->id,
        'cbm' => 2.0,
        'plant_name' => 'Plant-01 Central',
        'recipe_items' => [
            [
                'rm_item_id' => $water->id,
                'recipe_qty_per_cbm' => 180.0,
                'actual_qty_per_cbm' => 180.0,
                'unit' => 'ltr',
                'total_qty' => 360.0,
            ],
        ],
    ];

    $response = $this->actingAs($user)->postJson('/api/v1/productions', $payload);

    $response->assertCreated()
        ->assertJson([
            'status' => 'success',
        ]);

    $prodId = $response->json('data.id');
    expect($prodId)->not->toBeNull();

    $this->assertDatabaseHas('productions', [
        'id' => $prodId,
        'fg_item_id' => $fg->id,
        'cbm' => 2.0,
    ]);

    $this->assertDatabaseHas('production_recipe_items', [
        'production_id' => $prodId,
        'rm_item_id' => $water->id,
        'total_qty' => 360.0,
    ]);

    $this->assertDatabaseHas('production_consumptions', [
        'production_id' => $prodId,
        'item_id' => $water->id,
        'consumed_qty' => 360.0,
    ]);
});

test('can delete production batch', function () {
    $user = User::factory()->create();

    $fg = Item::firstOrCreate(
        ['category' => 'RMC', 'size' => 'M35'],
        [
            'type' => 'fg',
            'primary_unit' => 'cum',
            'secondary_unit' => 'cum',
            'conversion_factor' => 1,
        ]
    );

    $production = Production::create([
        'production_no' => 'PRD-DELETE-SAFE',
        'production_date' => '2026-09-28',
        'fg_item_id' => $fg->id,
        'cbm' => 5.0,
        'plant_name' => 'Plant-01 Central',
        'batch_no' => 'BAT-20260928-SAFE01',
    ]);

    $response = $this->actingAs($user)->deleteJson("/api/v1/productions/{$production->id}");

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ]);

    $this->assertSoftDeleted('productions', [
        'id' => $production->id,
    ]);
});
