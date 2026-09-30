<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\NavigationCategory;
use App\Models\NavigationItem;
use App\Models\Production;
use App\Models\ProductionConsumption;
use App\Models\ProductionRecipeItem;
use App\Models\Role;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = User::where('email', 'admin@rmc.com')->value('id') ?: User::first()?->id;
        $stockService = app(StockService::class);

        // 1. Navigation item for Production
        $opsCat = NavigationCategory::where('slug', 'operations')->first();
        if ($opsCat) {
            $navItem = NavigationItem::updateOrCreate(
                ['path' => '/production'],
                [
                    'category_id' => $opsCat->id,
                    'title' => 'Production Entry',
                    'icon' => 'Factory',
                    'order' => 2,
                    'is_active' => true,
                ]
            );

            // Attach to admin role
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole && ! $adminRole->navigationItems()->where('navigation_items.id', $navItem->id)->exists()) {
                $adminRole->navigationItems()->attach($navItem->id);
            }
        }

        // 2. Sample Production Batches Definitions (Consuming various RM materials)
        $sampleBatches = [
            [
                'production_no' => 'PRD-0001',
                'production_date' => '2026-09-21',
                'fg_size' => 'M20',
                'cbm' => 12.0,
                'plant_name' => 'Plant-01 Central',
                'batch_no' => 'BAT-20260921-001',
                'remarks' => 'Metro Rail Elevated Viaduct Pier #44 pouring. Transit Mixer TM-04.',
            ],
            [
                'production_no' => 'PRD-0002',
                'production_date' => '2026-09-22',
                'fg_size' => 'M25',
                'cbm' => 16.0,
                'plant_name' => 'Plant-01 Central',
                'batch_no' => 'BAT-20260922-001',
                'remarks' => 'Flyover Girder Span-3 casting. Transit Mixer TM-01 & TM-02.',
            ],
            [
                'production_no' => 'PRD-0003',
                'production_date' => '2026-09-23',
                'fg_size' => 'M30',
                'cbm' => 18.0,
                'plant_name' => 'Plant-01 Central',
                'batch_no' => 'BAT-20260923-001',
                'remarks' => 'Commercial Tower Basement Raft foundation pouring. Transit Mixer TM-03.',
            ],
            [
                'production_no' => 'PRD-0004',
                'production_date' => '2026-09-24',
                'fg_size' => 'M15',
                'cbm' => 10.0,
                'plant_name' => 'Plant-01 Central',
                'batch_no' => 'BAT-20260924-001',
                'remarks' => 'Green Valley Residential PCC pavement flooring. Transit Mixer TM-05.',
            ],
            [
                'production_no' => 'PRD-0005',
                'production_date' => '2026-09-25',
                'fg_size' => 'M35',
                'cbm' => 20.0,
                'plant_name' => 'Plant-01 Central',
                'batch_no' => 'BAT-20260925-001',
                'remarks' => 'Heavy Duty Industrial Warehouse floor casting. Transit Mixer TM-01 & TM-04.',
            ],
            [
                'production_no' => 'PRD-0006',
                'production_date' => '2026-09-26',
                'fg_size' => 'M25',
                'cbm' => 24.0,
                'plant_name' => 'Plant-01 Central',
                'batch_no' => 'BAT-20260926-001',
                'remarks' => 'City Ring Road Box Culvert segments. Transit Mixer TM-02 & TM-03.',
            ],
            [
                'production_no' => 'PRD-0007',
                'production_date' => '2026-09-27',
                'fg_size' => 'M30',
                'cbm' => 15.0,
                'plant_name' => 'Plant-01 Central',
                'batch_no' => 'BAT-20260927-001',
                'remarks' => 'Water Treatment Plant Clarifier tank slab. High slump mix.',
            ],
            [
                'production_no' => 'PRD-0008',
                'production_date' => '2026-09-28',
                'fg_size' => 'M40',
                'cbm' => 14.0,
                'plant_name' => 'Plant-01 Central',
                'batch_no' => 'BAT-20260928-001',
                'remarks' => 'High-rise core shear wall casting with silica fume & retarder.',
            ],
        ];

        foreach ($sampleBatches as $batch) {
            $fg = Item::where('type', 'fg')->where('size', $batch['fg_size'])->with('recipeItems.rmItem')->first();
            if (! $fg || $fg->recipeItems->isEmpty()) {
                continue;
            }

            $cbm = (float) $batch['cbm'];
            $recipeItemsData = [];

            foreach ($fg->recipeItems as $rec) {
                $dosage = (float) $rec->quantity;
                $total = round($dosage * $cbm, 4);

                $recipeItemsData[] = [
                    'rm_item_id' => $rec->rm_item_id,
                    'recipe_qty_per_cbm' => $dosage,
                    'actual_qty_per_cbm' => $dosage,
                    'unit' => $rec->unit,
                    'total_qty' => $total,
                ];
            }

            // Create or update production batch (handle withTrashed)
            $prod = Production::withTrashed()->where('production_no', $batch['production_no'])->first();

            if ($prod) {
                if ($prod->trashed()) {
                    $prod->restore();
                }
                $prod->update([
                    'production_date' => $batch['production_date'],
                    'fg_item_id' => $fg->id,
                    'cbm' => $cbm,
                    'plant_name' => $batch['plant_name'],
                    'batch_no' => $batch['batch_no'],
                    'remarks' => $batch['remarks'],
                    'created_by' => $adminId,
                ]);
            } else {
                $prod = Production::create([
                    'production_no' => $batch['production_no'],
                    'production_date' => $batch['production_date'],
                    'fg_item_id' => $fg->id,
                    'cbm' => $cbm,
                    'plant_name' => $batch['plant_name'],
                    'batch_no' => $batch['batch_no'],
                    'remarks' => $batch['remarks'],
                    'created_by' => $adminId,
                ]);
            }

            // Recreate recipe items and consumptions
            ProductionRecipeItem::where('production_id', $prod->id)->forceDelete();
            ProductionConsumption::where('production_id', $prod->id)->forceDelete();

            foreach ($recipeItemsData as $item) {
                ProductionRecipeItem::create([
                    'production_id' => $prod->id,
                    'rm_item_id' => $item['rm_item_id'],
                    'recipe_qty_per_cbm' => $item['recipe_qty_per_cbm'],
                    'actual_qty_per_cbm' => $item['actual_qty_per_cbm'],
                    'unit' => $item['unit'],
                    'total_qty' => $item['total_qty'],
                    'created_by' => $adminId,
                ]);
            }

            $stockService->recordConsumptions($prod, $recipeItemsData, $adminId);
        }
    }
}
