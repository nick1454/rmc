<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\RecipeItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class RecipeItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = User::where('email', 'admin@rmc.com')->value('id');

        // Fetch Raw Material Item IDs
        $cementId = Item::where('category', 'Cement')->where('size', 'opc40')->value('id');
        $agg10mmId = Item::where('category', 'Aggregate')->where('size', '10mm')->value('id');
        $agg20mmId = Item::where('category', 'Aggregate')->where('size', '20mm')->value('id');
        $dustId = Item::where('category', 'Dust')->value('id');
        $admixtureId = Item::where('category', 'AddMixture')->value('id');
        $waterId = Item::where('category', 'Water')->value('id');

        if (! $cementId || ! $agg10mmId || ! $agg20mmId || ! $dustId || ! $admixtureId || ! $waterId) {
            return;
        }

        // Master recipe definitions for each FG Grade per 1 cbm
        $recipes = [
            'M10' => [
                ['rm_item_id' => $cementId, 'quantity' => 220.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $dustId, 'quantity' => 850.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg10mmId, 'quantity' => 450.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg20mmId, 'quantity' => 650.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $waterId, 'quantity' => 160.0000, 'unit' => 'ltr'],
            ],
            'M15' => [
                ['rm_item_id' => $cementId, 'quantity' => 260.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $dustId, 'quantity' => 800.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg10mmId, 'quantity' => 480.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg20mmId, 'quantity' => 680.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $admixtureId, 'quantity' => 1.5000, 'unit' => 'kgs'],
                ['rm_item_id' => $waterId, 'quantity' => 165.0000, 'unit' => 'ltr'],
            ],
            'M20' => [
                ['rm_item_id' => $cementId, 'quantity' => 320.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $dustId, 'quantity' => 760.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg10mmId, 'quantity' => 500.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg20mmId, 'quantity' => 720.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $admixtureId, 'quantity' => 2.2000, 'unit' => 'kgs'],
                ['rm_item_id' => $waterId, 'quantity' => 170.0000, 'unit' => 'ltr'],
            ],
            'M25' => [
                ['rm_item_id' => $cementId, 'quantity' => 380.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $dustId, 'quantity' => 720.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg10mmId, 'quantity' => 520.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg20mmId, 'quantity' => 740.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $admixtureId, 'quantity' => 2.8000, 'unit' => 'kgs'],
                ['rm_item_id' => $waterId, 'quantity' => 175.0000, 'unit' => 'ltr'],
            ],
            'M30' => [
                ['rm_item_id' => $cementId, 'quantity' => 420.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $dustId, 'quantity' => 680.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg10mmId, 'quantity' => 540.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg20mmId, 'quantity' => 760.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $admixtureId, 'quantity' => 3.2000, 'unit' => 'kgs'],
                ['rm_item_id' => $waterId, 'quantity' => 180.0000, 'unit' => 'ltr'],
            ],
            'M35' => [
                ['rm_item_id' => $cementId, 'quantity' => 460.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $dustId, 'quantity' => 640.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg10mmId, 'quantity' => 560.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg20mmId, 'quantity' => 780.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $admixtureId, 'quantity' => 3.8000, 'unit' => 'kgs'],
                ['rm_item_id' => $waterId, 'quantity' => 180.0000, 'unit' => 'ltr'],
            ],
            'M40' => [
                ['rm_item_id' => $cementId, 'quantity' => 500.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $dustId, 'quantity' => 600.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg10mmId, 'quantity' => 580.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $agg20mmId, 'quantity' => 800.0000, 'unit' => 'kgs'],
                ['rm_item_id' => $admixtureId, 'quantity' => 4.2000, 'unit' => 'kgs'],
                ['rm_item_id' => $waterId, 'quantity' => 185.0000, 'unit' => 'ltr'],
            ],
        ];

        foreach ($recipes as $grade => $ingredients) {
            $fgItem = Item::where('category', 'RMC')->where('size', $grade)->first();
            if ($fgItem) {
                // Delete existing if any
                RecipeItem::where('fg_item_id', $fgItem->id)->forceDelete();

                foreach ($ingredients as $ing) {
                    RecipeItem::create([
                        'fg_item_id' => $fgItem->id,
                        'rm_item_id' => $ing['rm_item_id'],
                        'quantity' => $ing['quantity'],
                        'unit' => $ing['unit'],
                        'created_by' => $adminId,
                    ]);
                }
            }
        }
    }
}
