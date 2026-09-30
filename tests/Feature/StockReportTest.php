<?php

use App\Models\Item;
use App\Models\User;

test('stock report meta endpoint returns categories and items', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/reports/stock/meta');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'categories',
                'items',
            ],
        ]);
});

test('stock report summary endpoint returns periodic balances and kpis', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/v1/reports/stock/summary?from_date=2026-09-01&to_date=2026-09-30');

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'kpis' => [
                    'total_rm_items',
                    'total_fg_items',
                    'total_inward_ton',
                    'total_consumed_ton',
                    'total_rm_outward_ton',
                    'total_fg_produced_cbm',
                    'total_fg_dispatched_cbm',
                    'total_fg_closing_cbm',
                ],
                'items' => [
                    '*' => [
                        'item_id',
                        'code',
                        'item_name',
                        'category',
                        'size',
                        'type',
                        'primary_unit',
                        'secondary_unit',
                        'conversion_factor',
                        'opening_primary',
                        'opening_secondary',
                        'inward_primary',
                        'inward_secondary',
                        'consumed_primary',
                        'consumed_secondary',
                        'outward_primary',
                        'outward_secondary',
                        'closing_primary',
                        'closing_secondary',
                        'status',
                    ],
                ],
                'filters',
            ],
        ]);
});

test('stock report ledger endpoint returns detailed item transaction history and running balances', function () {
    $user = User::factory()->create();

    $item = Item::where('type', 'rm')->first() ?: Item::first();

    $response = $this->actingAs($user)->getJson("/api/v1/reports/stock/ledger/{$item->id}?from_date=2026-09-01&to_date=2026-09-30");

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'data' => [
                'item' => [
                    'id',
                    'code',
                    'name',
                    'category',
                    'size',
                    'type',
                    'primary_unit',
                    'secondary_unit',
                ],
                'opening_stock' => [
                    'primary',
                    'secondary',
                ],
                'entries',
                'closing_stock' => [
                    'primary',
                    'secondary',
                ],
                'summary' => [
                    'total_in_primary',
                    'total_in_secondary',
                    'total_out_primary',
                    'total_out_secondary',
                    'net_change_primary',
                    'net_change_secondary',
                ],
            ],
        ]);
});
