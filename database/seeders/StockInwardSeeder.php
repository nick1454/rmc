<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\NavigationCategory;
use App\Models\NavigationItem;
use App\Models\Party;
use App\Models\Role;
use App\Models\StockInward;
use App\Models\StockInwardItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StockInwardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = User::where('email', 'admin@rmc.com')->value('id') ?: User::first()?->id;

        // 1. Navigation item
        $opsCat = NavigationCategory::where('slug', 'operations')->first();
        if ($opsCat) {
            $navItem = NavigationItem::updateOrCreate(
                ['path' => '/inwards'],
                [
                    'category_id' => $opsCat->id,
                    'title' => 'Stock Inward',
                    'icon' => 'Truck',
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

        // 2. Fetch RM Items
        $cement = Item::where('category', 'Cement')->first();
        $agg10 = Item::where('category', 'Aggregate')->where('size', '10mm')->first();
        $agg20 = Item::where('category', 'Aggregate')->where('size', '20mm')->first();
        $agg30 = Item::where('category', 'Aggregate')->where('size', '30mm')->first();
        $dust = Item::where('category', 'Dust')->first();
        $admixture = Item::where('category', 'AddMixture')->first();
        $siloPouch = Item::where('category', 'Silo Pouch')->first();

        // Fetch Suppliers
        $ultraTech = Party::where('name', 'like', '%UltraTech%')->first();
        $balaji = Party::where('name', 'like', '%Balaji%')->first();
        $fosroc = Party::where('name', 'like', '%Fosroc%')->first();
        $navkar = Party::where('name', 'like', '%Navkar%')->first();
        $polypack = Party::where('name', 'like', '%Polypack%')->first();

        // 3. 10 Sample Stock Inwards Data
        $sampleEntries = [
            [
                'inward_no' => 'INW-0001',
                'inward_date' => '2026-09-20',
                'challan_date' => '2026-09-20',
                'challan_no' => 'UTC-INV-8821',
                'supplier_bill_no' => 'UTC-INV-8821',
                'supplier_bill_date' => '2026-09-20',
                'supplier_party_id' => $ultraTech?->id,
                'supplier_name' => $ultraTech?->name ?: 'UltraTech Cement Ltd',
                'vehicle_no' => 'MH 12 RN 8821',
                'driver_name' => 'Suresh Patil',
                'driver_phone' => '+91 98220 11001',
                'remarks' => 'Bulk OPC 43/53 cement unloaded into Silo-01. Test certificate verified.',
                'items' => [
                    [
                        'item_id' => $cement?->id ?: 4,
                        'qty_primary' => 50.00,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 50000.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 5800.00,
                        'gst_rate' => 28.00,
                        'remarks' => 'Silo 01 Bulker Discharge',
                    ],
                ],
            ],
            [
                'inward_no' => 'INW-0002',
                'inward_date' => '2026-09-21',
                'challan_date' => '2026-09-21',
                'challan_no' => 'SB-DC-4590',
                'supplier_bill_no' => 'SB-DC-4590',
                'supplier_bill_date' => '2026-09-21',
                'supplier_party_id' => $balaji?->id,
                'supplier_name' => $balaji?->name ?: 'Shree Balaji Stone Crusher',
                'vehicle_no' => 'MH 14 DT 4590',
                'driver_name' => 'Ramesh Shinde',
                'driver_phone' => '+91 98220 22002',
                'remarks' => 'Crushed basalt aggregates received at Bay-01 & Bay-02 for heavy mix design.',
                'items' => [
                    [
                        'item_id' => $agg10?->id ?: 1,
                        'qty_primary' => 40.00,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 40000.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 950.00,
                        'gst_rate' => 5.00,
                        'remarks' => 'Bin 01 Dump',
                    ],
                    [
                        'item_id' => $agg20?->id ?: 2,
                        'qty_primary' => 60.00,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 60000.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 1050.00,
                        'gst_rate' => 5.00,
                        'remarks' => 'Bin 02 Dump',
                    ],
                ],
            ],
            [
                'inward_no' => 'INW-0003',
                'inward_date' => '2026-09-22',
                'challan_date' => '2026-09-22',
                'challan_no' => 'NK-CS-3104',
                'supplier_bill_no' => 'NK-CS-3104',
                'supplier_bill_date' => '2026-09-22',
                'supplier_party_id' => $navkar?->id,
                'supplier_name' => $navkar?->name ?: 'Navkar Crushed Sand & Minerals',
                'vehicle_no' => 'MH 12 KQ 7712',
                'driver_name' => 'Ganesh Gaikwad',
                'driver_phone' => '+91 98220 33003',
                'remarks' => 'Washed manufactured sand / dust received for M25/M30 grade concrete.',
                'items' => [
                    [
                        'item_id' => $dust?->id ?: 5,
                        'qty_primary' => 75.00,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 75000.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 850.00,
                        'gst_rate' => 5.00,
                        'remarks' => 'Sand Bay Unloaded',
                    ],
                ],
            ],
            [
                'inward_no' => 'INW-0004',
                'inward_date' => '2026-09-23',
                'challan_date' => '2026-09-23',
                'challan_no' => 'FOS-CH-1102',
                'supplier_bill_no' => 'FOS-CH-1102',
                'supplier_bill_date' => '2026-09-23',
                'supplier_party_id' => $fosroc?->id,
                'supplier_name' => $fosroc?->name ?: 'Fosroc Chemicals India Pvt Ltd',
                'vehicle_no' => 'MH 43 AA 1102',
                'driver_name' => 'Mahesh Jadhav',
                'driver_phone' => '+91 98220 44004',
                'remarks' => 'Superplasticizer admixture barrels unloaded into chemical dosing room.',
                'items' => [
                    [
                        'item_id' => $admixture?->id ?: 6,
                        'qty_primary' => 2.50,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 2500.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 85000.00,
                        'gst_rate' => 18.00,
                        'remarks' => '12 Barrels Stored',
                    ],
                ],
            ],
            [
                'inward_no' => 'INW-0005',
                'inward_date' => '2026-09-24',
                'challan_date' => '2026-09-24',
                'challan_no' => 'UTC-INV-8950',
                'supplier_bill_no' => 'UTC-INV-8950',
                'supplier_bill_date' => '2026-09-24',
                'supplier_party_id' => $ultraTech?->id,
                'supplier_name' => $ultraTech?->name ?: 'UltraTech Cement Ltd',
                'vehicle_no' => 'MH 12 PQ 9044',
                'driver_name' => 'Anil Pawar',
                'driver_phone' => '+91 98220 55005',
                'remarks' => 'Direct bulker unloading into Silo-02 for high-grade commercial projects.',
                'items' => [
                    [
                        'item_id' => $cement?->id ?: 4,
                        'qty_primary' => 45.00,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 45000.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 5850.00,
                        'gst_rate' => 28.00,
                        'remarks' => 'Silo 02 Discharge',
                    ],
                ],
            ],
            [
                'inward_no' => 'INW-0006',
                'inward_date' => '2026-09-25',
                'challan_date' => '2026-09-25',
                'challan_no' => 'SB-DC-4780',
                'supplier_bill_no' => 'SB-DC-4780',
                'supplier_bill_date' => '2026-09-25',
                'supplier_party_id' => $balaji?->id,
                'supplier_name' => $balaji?->name ?: 'Shree Balaji Stone Crusher',
                'vehicle_no' => 'MH 14 CC 8823',
                'driver_name' => 'Santosh Kadam',
                'driver_phone' => '+91 98220 66006',
                'remarks' => '10mm aggregate and 30mm coarse aggregate batch receipt.',
                'items' => [
                    [
                        'item_id' => $agg10?->id ?: 1,
                        'qty_primary' => 50.00,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 50000.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 960.00,
                        'gst_rate' => 5.00,
                        'remarks' => 'Bay 01 Refilled',
                    ],
                    [
                        'item_id' => $agg30?->id ?: 3,
                        'qty_primary' => 40.00,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 40000.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 1120.00,
                        'gst_rate' => 5.00,
                        'remarks' => 'Bay 03 Coarse Stock',
                    ],
                ],
            ],
            [
                'inward_no' => 'INW-0007',
                'inward_date' => '2026-09-26',
                'challan_date' => '2026-09-26',
                'challan_no' => 'NK-CS-3280',
                'supplier_bill_no' => 'NK-CS-3280',
                'supplier_bill_date' => '2026-09-26',
                'supplier_party_id' => $navkar?->id,
                'supplier_name' => $navkar?->name ?: 'Navkar Crushed Sand & Minerals',
                'vehicle_no' => 'MH 12 NN 6500',
                'driver_name' => 'Kailash More',
                'driver_phone' => '+91 98220 77007',
                'remarks' => 'Fine aggregate / plaster grade m-sand lot inspection cleared.',
                'items' => [
                    [
                        'item_id' => $dust?->id ?: 5,
                        'qty_primary' => 80.00,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 80000.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 860.00,
                        'gst_rate' => 5.00,
                        'remarks' => 'Main Sand Stockpile',
                    ],
                ],
            ],
            [
                'inward_no' => 'INW-0008',
                'inward_date' => '2026-09-27',
                'challan_date' => '2026-09-27',
                'challan_no' => 'POLY-DC-901',
                'supplier_bill_no' => 'POLY-DC-901',
                'supplier_bill_date' => '2026-09-27',
                'supplier_party_id' => $polypack?->id,
                'supplier_name' => $polypack?->name ?: 'Polypack Silo Solutions',
                'vehicle_no' => 'MH 04 EB 3401',
                'driver_name' => 'Pradeep Joshi',
                'driver_phone' => '+91 98220 88008',
                'remarks' => 'Silo aeration filter pouches & vent bags received in carton boxes.',
                'items' => [
                    [
                        'item_id' => $siloPouch?->id ?: 7,
                        'qty_primary' => 120.00,
                        'unit_primary' => 'pcs',
                        'qty_secondary' => 120.00,
                        'unit_secondary' => 'pcs',
                        'conversion_factor' => 1.0,
                        'rate_per_unit' => 450.00,
                        'gst_rate' => 18.00,
                        'remarks' => 'Inventory Store Room',
                    ],
                ],
            ],
            [
                'inward_no' => 'INW-0009',
                'inward_date' => '2026-09-28',
                'challan_date' => '2026-09-28',
                'challan_no' => 'FOS-CH-1240',
                'supplier_bill_no' => 'FOS-CH-1240',
                'supplier_bill_date' => '2026-09-28',
                'supplier_party_id' => $fosroc?->id,
                'supplier_name' => $fosroc?->name ?: 'Fosroc Chemicals India Pvt Ltd',
                'vehicle_no' => 'MH 43 BB 5670',
                'driver_name' => 'Dattatray Mane',
                'driver_phone' => '+91 98220 99009',
                'remarks' => 'High-range water reducer & slump retarder chemicals batch receipt.',
                'items' => [
                    [
                        'item_id' => $admixture?->id ?: 6,
                        'qty_primary' => 3.00,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 3000.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 84500.00,
                        'gst_rate' => 18.00,
                        'remarks' => 'Dosing Tank Refill',
                    ],
                ],
            ],
            [
                'inward_no' => 'INW-0010',
                'inward_date' => '2026-09-29',
                'challan_date' => '2026-09-29',
                'challan_no' => 'SB-DC-4920',
                'supplier_bill_no' => 'SB-DC-4920',
                'supplier_bill_date' => '2026-09-29',
                'supplier_party_id' => $balaji?->id,
                'supplier_name' => $balaji?->name ?: 'Shree Balaji Stone Crusher',
                'vehicle_no' => 'MH 14 DT 9911',
                'driver_name' => 'Sanjay Rathod',
                'driver_phone' => '+91 98220 12345',
                'remarks' => '20mm coarse aggregate full dumper load delivered for pumpable mix.',
                'items' => [
                    [
                        'item_id' => $agg20?->id ?: 2,
                        'qty_primary' => 90.00,
                        'unit_primary' => 'ton',
                        'qty_secondary' => 90000.00,
                        'unit_secondary' => 'kgs',
                        'conversion_factor' => 1000.0,
                        'rate_per_unit' => 1080.00,
                        'gst_rate' => 5.00,
                        'remarks' => 'Bay 02 Stock Level Max',
                    ],
                ],
            ],
        ];

        DB::transaction(function () use ($sampleEntries, $adminId) {
            foreach ($sampleEntries as $entry) {
                $subtotal = 0.0;
                $taxAmount = 0.0;

                $calculatedItems = [];
                foreach ($entry['items'] as $it) {
                    $qty = (float) $it['qty_primary'];
                    $rate = (float) ($it['rate_per_unit'] ?? 0);
                    $gstRate = (float) ($it['gst_rate'] ?? 0);

                    $taxable = round($qty * $rate, 2);
                    $gstAmt = round($taxable * ($gstRate / 100), 2);
                    $lineTotal = round($taxable + $gstAmt, 2);

                    $subtotal += $taxable;
                    $taxAmount += $gstAmt;

                    $calculatedItems[] = [
                        'item_id' => $it['item_id'],
                        'qty_primary' => $qty,
                        'unit_primary' => $it['unit_primary'],
                        'qty_secondary' => (float) $it['qty_secondary'],
                        'unit_secondary' => $it['unit_secondary'],
                        'conversion_factor' => (float) $it['conversion_factor'],
                        'rate_per_unit' => $rate,
                        'taxable_amount' => $taxable,
                        'gst_rate' => $gstRate,
                        'gst_amount' => $gstAmt,
                        'total_amount' => $lineTotal,
                        'remarks' => $it['remarks'] ?? null,
                    ];
                }

                $grandTotal = round($subtotal + $taxAmount, 2);

                $inward = StockInward::updateOrCreate(
                    ['inward_no' => $entry['inward_no']],
                    [
                        'inward_date' => $entry['inward_date'],
                        'challan_date' => $entry['challan_date'],
                        'challan_no' => $entry['challan_no'],
                        'supplier_bill_no' => $entry['supplier_bill_no'],
                        'supplier_bill_date' => $entry['supplier_bill_date'],
                        'supplier_party_id' => $entry['supplier_party_id'],
                        'supplier_name' => $entry['supplier_name'],
                        'vehicle_no' => $entry['vehicle_no'],
                        'driver_name' => $entry['driver_name'],
                        'driver_phone' => $entry['driver_phone'],
                        'remarks' => $entry['remarks'],
                        'subtotal_amount' => $subtotal,
                        'tax_amount' => $taxAmount,
                        'freight_amount' => 0.0,
                        'other_charges' => 0.0,
                        'discount_amount' => 0.0,
                        'grand_total' => $grandTotal,
                        'created_by' => $adminId,
                    ]
                );

                $inward->items()->delete();
                foreach ($calculatedItems as $cItem) {
                    $cItem['stock_inward_id'] = $inward->id;
                    StockInwardItem::create($cItem);
                }
            }
        });
    }
}
