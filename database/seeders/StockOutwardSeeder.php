<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\NavigationCategory;
use App\Models\NavigationItem;
use App\Models\Party;
use App\Models\Role;
use App\Models\StockOutward;
use App\Models\StockOutwardItem;
use App\Models\User;
use Illuminate\Database\Seeder;

class StockOutwardSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = User::where('email', 'admin@rmc.com')->value('id') ?: User::first()?->id;

        // 1. Add Stock Outward navigation item under operations
        $opsCat = NavigationCategory::where('slug', 'operations')->first();
        if ($opsCat) {
            $navItem = NavigationItem::updateOrCreate(
                ['path' => '/outwards'],
                [
                    'category_id' => $opsCat->id,
                    'title' => 'Stock Outward',
                    'icon' => 'ReceiptText',
                    'order' => 3,
                    'is_active' => true,
                ]
            );

            // Attach to admin role
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole && ! $adminRole->navigationItems()->where('navigation_items.id', $navItem->id)->exists()) {
                $adminRole->navigationItems()->attach($navItem->id);
            }
        }

        // 2. Fetch Reference Items
        // Raw Materials (RM)
        $cement = Item::where('category', 'Cement')->where('size', 'opc40')->first();
        $agg10 = Item::where('category', 'Aggregate')->where('size', '10mm')->first();
        $agg20 = Item::where('category', 'Aggregate')->where('size', '20mm')->first();
        $dust = Item::where('category', 'Dust')->first();
        $admixture = Item::where('category', 'AddMixture')->first();
        $siloPouch = Item::where('category', 'Silo Pouch')->first();

        // Finished Goods (FG)
        $m15 = Item::where('category', 'RMC')->where('size', 'M15')->first();
        $m20 = Item::where('category', 'RMC')->where('size', 'M20')->first();
        $m25 = Item::where('category', 'RMC')->where('size', 'M25')->first();
        $m30 = Item::where('category', 'RMC')->where('size', 'M30')->first();
        $m35 = Item::where('category', 'RMC')->where('size', 'M35')->first();

        // 3. Fetch Parties
        $ltCustomer = Party::where('name', 'like', '%L&T%')->first();
        $spCustomer = Party::where('name', 'like', '%Shapoorji%')->first();
        $godrejCustomer = Party::where('name', 'like', '%Godrej%')->first();
        $tataCustomer = Party::where('name', 'like', '%Tata%')->first();
        $ultraTechSupplier = Party::where('name', 'like', '%UltraTech%')->first();

        $mahalaxmiAgent = Party::where('name', 'like', '%Mahalaxmi%')->first();
        $apexAgent = Party::where('name', 'like', '%Apex%')->first();
        $saiAgent = Party::where('name', 'like', '%Sai%')->first();

        // 4. Sample Stock Outward Batches (Finished Goods Dispatches + Raw Material Returns/Transfers)
        $demoOutwards = [
            [
                'entry_no' => 'OUT-0001',
                'entry_type' => 'invoice',
                'party_id' => $ltCustomer?->id,
                'customer' => $ltCustomer?->name ?: 'L&T Construction - Metro Rail Project',
                'agent_id' => $mahalaxmiAgent?->id,
                'agent_name' => $mahalaxmiAgent?->name ?: 'Mahalaxmi Cement & Material Brokers',
                'invoice_date' => '2026-09-23',
                'vehicle_no' => 'TM-04 (MH12-RN-8821)',
                'destination' => 'Metro Line 3 Pier P-42, Shivaji Nagar',
                'remarks' => 'M20 RMC concrete dispatched for underground metro pier base casting.',
                'items' => [
                    [
                        'item_id' => $m20?->id,
                        'qty' => 6.0000,
                        'unit' => 'cbm',
                        'secondary_qty' => 14400.0000,
                        'secondary_unit' => 'kgs',
                        'rate' => 4200.00,
                        'taxable_amount' => 25200.00,
                        'gst_rate' => 18.00,
                        'gst_amount' => 4536.00,
                        'total_amount' => 29736.00,
                        'lotno' => 'BAT-20260921-001',
                    ],
                ],
            ],
            [
                'entry_no' => 'OUT-0002',
                'entry_type' => 'invoice',
                'party_id' => $spCustomer?->id,
                'customer' => $spCustomer?->name ?: 'Shapoorji Pallonji Infra - Highway Package 4',
                'agent_id' => $apexAgent?->id,
                'agent_name' => $apexAgent?->name ?: 'Apex Infrastructure & Sourcing Agency',
                'invoice_date' => '2026-09-24',
                'vehicle_no' => 'TM-01 (MH14-DT-4590)',
                'destination' => 'Ring Road Expressway Flyover Girder Span-3',
                'remarks' => 'M25 grade ready mix concrete with high workability for flyover deck.',
                'items' => [
                    [
                        'item_id' => $m25?->id,
                        'qty' => 8.0000,
                        'unit' => 'cbm',
                        'secondary_qty' => 19200.0000,
                        'secondary_unit' => 'kgs',
                        'rate' => 4600.00,
                        'taxable_amount' => 36800.00,
                        'gst_rate' => 18.00,
                        'gst_amount' => 6624.00,
                        'total_amount' => 43424.00,
                        'lotno' => 'BAT-20260922-001',
                    ],
                ],
            ],
            [
                'entry_no' => 'OUT-0003',
                'entry_type' => 'invoice',
                'party_id' => $godrejCustomer?->id,
                'customer' => $godrejCustomer?->name ?: 'Godrej Properties - Horizon Towers',
                'agent_id' => $apexAgent?->id,
                'agent_name' => $apexAgent?->name ?: 'Apex Infrastructure & Sourcing Agency',
                'invoice_date' => '2026-09-25',
                'vehicle_no' => 'TM-02 (MH12-Q-7789)',
                'destination' => 'Horizon Towers Wing-A Podium Slab Pour',
                'remarks' => 'M30 heavy strength concrete dispatched for commercial podium slab.',
                'items' => [
                    [
                        'item_id' => $m30?->id,
                        'qty' => 6.0000,
                        'unit' => 'cbm',
                        'secondary_qty' => 14400.0000,
                        'secondary_unit' => 'kgs',
                        'rate' => 5100.00,
                        'taxable_amount' => 30600.00,
                        'gst_rate' => 18.00,
                        'gst_amount' => 5508.00,
                        'total_amount' => 36108.00,
                        'lotno' => 'BAT-20260923-001',
                    ],
                ],
            ],
            [
                'entry_no' => 'OUT-0004',
                'entry_type' => 'challan',
                'challan_no' => 'CH-RET-2026-01',
                'challan_date' => '2026-09-25',
                'party_id' => $ultraTechSupplier?->id,
                'customer' => $ultraTechSupplier?->name ?: 'UltraTech Cement Ltd',
                'agent_id' => $mahalaxmiAgent?->id,
                'agent_name' => $mahalaxmiAgent?->name ?: 'Mahalaxmi Cement & Material Brokers',
                'invoice_date' => '2026-09-25',
                'vehicle_no' => 'Bulker MH12-CL-9901',
                'destination' => 'UltraTech Cement Bulk Depot, Kharadi',
                'remarks' => 'Raw Material Return: Moisture affected OPC bulk cement returned to vendor.',
                'items' => [
                    [
                        'item_id' => $cement?->id,
                        'qty' => 5.0000,
                        'unit' => 'ton',
                        'secondary_qty' => 5000.0000,
                        'secondary_unit' => 'kgs',
                        'rate' => 6100.00,
                        'taxable_amount' => 30500.00,
                        'gst_rate' => 28.00,
                        'gst_amount' => 8540.00,
                        'total_amount' => 39040.00,
                        'lotno' => 'UTC-BL-2026-09',
                    ],
                ],
            ],
            [
                'entry_no' => 'OUT-0005',
                'entry_type' => 'invoice',
                'party_id' => $tataCustomer?->id,
                'customer' => $tataCustomer?->name ?: 'Tata Projects Ltd - Airport Expansion',
                'agent_id' => $saiAgent?->id,
                'agent_name' => $saiAgent?->name ?: 'Sai Crushed Sand & Aggregate Agency',
                'invoice_date' => '2026-09-26',
                'vehicle_no' => 'TM-05 (MH43-AP-7730)',
                'destination' => 'Terminal 2 Runway Apron Bay 7',
                'remarks' => 'M35 airport pavement high-strength concrete with rapid setting admixture.',
                'items' => [
                    [
                        'item_id' => $m35?->id,
                        'qty' => 6.0000,
                        'unit' => 'cbm',
                        'secondary_qty' => 14400.0000,
                        'secondary_unit' => 'kgs',
                        'rate' => 5600.00,
                        'taxable_amount' => 33600.00,
                        'gst_rate' => 18.00,
                        'gst_amount' => 6048.00,
                        'total_amount' => 39648.00,
                        'lotno' => 'BAT-20260925-001',
                    ],
                ],
            ],
            [
                'entry_no' => 'OUT-0006',
                'entry_type' => 'challan',
                'challan_no' => 'CH-MAT-2026-908',
                'challan_date' => '2026-09-27',
                'party_id' => $tataCustomer?->id,
                'customer' => $tataCustomer?->name ?: 'Tata Projects Ltd - Airport Expansion',
                'agent_id' => $saiAgent?->id,
                'agent_name' => $saiAgent?->name ?: 'Sai Crushed Sand & Aggregate Agency',
                'invoice_date' => '2026-09-27',
                'vehicle_no' => 'Tipper MH14-EZ-3412',
                'destination' => 'Tata Projects Airport On-Site Batching Plant #2',
                'remarks' => 'Raw Material Transfer: Excess crushed aggregates and sand transferred to contractor site batching depot.',
                'items' => [
                    [
                        'item_id' => $agg10?->id,
                        'qty' => 10.0000,
                        'unit' => 'ton',
                        'secondary_qty' => 10000.0000,
                        'secondary_unit' => 'kgs',
                        'rate' => 0,
                        'taxable_amount' => 0,
                        'gst_rate' => 5.00,
                        'gst_amount' => 0,
                        'total_amount' => 0,
                        'lotno' => 'AGG-10-B1',
                    ],
                    [
                        'item_id' => $agg20?->id,
                        'qty' => 15.0000,
                        'unit' => 'ton',
                        'secondary_qty' => 15000.0000,
                        'secondary_unit' => 'kgs',
                        'rate' => 0,
                        'taxable_amount' => 0,
                        'gst_rate' => 5.00,
                        'gst_amount' => 0,
                        'total_amount' => 0,
                        'lotno' => 'AGG-20-B2',
                    ],
                    [
                        'item_id' => $dust?->id,
                        'qty' => 12.0000,
                        'unit' => 'ton',
                        'secondary_qty' => 12000.0000,
                        'secondary_unit' => 'kgs',
                        'rate' => 0,
                        'taxable_amount' => 0,
                        'gst_rate' => 5.00,
                        'gst_amount' => 0,
                        'total_amount' => 0,
                        'lotno' => 'DST-M-771',
                    ],
                ],
            ],
        ];

        foreach ($demoOutwards as $outwardData) {
            $items = $outwardData['items'];
            unset($outwardData['items']);

            $calcSubtotal = 0.0;
            $calcTax = 0.0;
            $calcTotal = 0.0;

            if ($outwardData['entry_type'] === 'invoice' || ! empty($items[0]['rate'])) {
                foreach ($items as $item) {
                    $calcSubtotal += $item['taxable_amount'];
                    $calcTax += $item['gst_amount'];
                    $calcTotal += $item['total_amount'];
                }
            }

            $outwardData['subtotal'] = round($calcSubtotal, 2);
            $outwardData['tax_amount'] = round($calcTax, 2);
            $outwardData['total_amount'] = round($calcTotal, 2);
            $outwardData['created_by'] = $adminId;

            // Handle withTrashed
            $outward = StockOutward::withTrashed()->where('entry_no', $outwardData['entry_no'])->first();

            if ($outward) {
                if ($outward->trashed()) {
                    $outward->restore();
                }
                $outward->update($outwardData);
            } else {
                $outward = StockOutward::create($outwardData);
            }

            // Clean old items and recreate
            StockOutwardItem::where('stock_outward_id', $outward->id)->forceDelete();

            foreach ($items as $item) {
                if (! empty($item['item_id'])) {
                    StockOutwardItem::create([
                        'stock_outward_id' => $outward->id,
                        'item_id' => $item['item_id'],
                        'qty' => $item['qty'],
                        'unit' => $item['unit'],
                        'secondary_qty' => $item['secondary_qty'],
                        'secondary_unit' => $item['secondary_unit'],
                        'rate' => $item['rate'],
                        'taxable_amount' => $item['taxable_amount'],
                        'gst_rate' => $item['gst_rate'],
                        'gst_amount' => $item['gst_amount'],
                        'total_amount' => $item['total_amount'],
                        'lotno' => $item['lotno'],
                        'created_by' => $adminId,
                    ]);
                }
            }
        }
    }
}
