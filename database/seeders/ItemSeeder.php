<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = User::where('email', 'admin@rmc.com')->value('id');

        $items = [
            [
                'category' => 'Aggregate',
                'size' => '10mm',
                'secondary_unit' => 'kgs',
                'primary_unit' => 'ton',
                'conversion_factor' => 1000.0000,
                'type' => 'rm',
                'hsn' => '25171010',
                'gst_per' => 5.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'Aggregate',
                'size' => '20mm',
                'secondary_unit' => 'kgs',
                'primary_unit' => 'ton',
                'conversion_factor' => 1000.0000,
                'type' => 'rm',
                'hsn' => '25171010',
                'gst_per' => 5.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'Aggregate',
                'size' => '30mm',
                'secondary_unit' => 'kgs',
                'primary_unit' => 'ton',
                'conversion_factor' => 1000.0000,
                'type' => 'rm',
                'hsn' => '25171010',
                'gst_per' => 5.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'Cement',
                'size' => 'opc40',
                'secondary_unit' => 'kgs',
                'primary_unit' => 'ton',
                'conversion_factor' => 1000.0000,
                'type' => 'rm',
                'hsn' => '25232910',
                'gst_per' => 28.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'Dust',
                'size' => null,
                'secondary_unit' => 'kgs',
                'primary_unit' => 'ton',
                'conversion_factor' => 1000.0000,
                'type' => 'rm',
                'hsn' => '25059000',
                'gst_per' => 5.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'AddMixture',
                'size' => null,
                'secondary_unit' => 'kgs',
                'primary_unit' => 'ton',
                'conversion_factor' => 1000.0000,
                'type' => 'rm',
                'hsn' => '38244090',
                'gst_per' => 18.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'Water',
                'size' => null,
                'secondary_unit' => 'ltr',
                'primary_unit' => 'ltr',
                'conversion_factor' => 1.0000,
                'type' => 'rm',
                'hsn' => '22019010',
                'gst_per' => 0.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'Silo Pouch',
                'size' => null,
                'secondary_unit' => 'pcs',
                'primary_unit' => 'pcs',
                'conversion_factor' => 1.0000,
                'type' => 'rm',
                'hsn' => '39232990',
                'gst_per' => 18.00,
                'created_by' => $adminId,
            ],
            // Finished Goods (FG) - Ready Mix Concrete Grades
            [
                'category' => 'RMC',
                'size' => 'M10',
                'primary_unit' => 'cbm',
                'secondary_unit' => 'kgs',
                'conversion_factor' => 2400.0000,
                'type' => 'fg',
                'hsn' => '38245010',
                'gst_per' => 18.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'RMC',
                'size' => 'M15',
                'primary_unit' => 'cbm',
                'secondary_unit' => 'kgs',
                'conversion_factor' => 2400.0000,
                'type' => 'fg',
                'hsn' => '38245010',
                'gst_per' => 18.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'RMC',
                'size' => 'M20',
                'primary_unit' => 'cbm',
                'secondary_unit' => 'kgs',
                'conversion_factor' => 2400.0000,
                'type' => 'fg',
                'hsn' => '38245010',
                'gst_per' => 18.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'RMC',
                'size' => 'M25',
                'primary_unit' => 'cbm',
                'secondary_unit' => 'kgs',
                'conversion_factor' => 2400.0000,
                'type' => 'fg',
                'hsn' => '38245010',
                'gst_per' => 18.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'RMC',
                'size' => 'M30',
                'primary_unit' => 'cbm',
                'secondary_unit' => 'kgs',
                'conversion_factor' => 2400.0000,
                'type' => 'fg',
                'hsn' => '38245010',
                'gst_per' => 18.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'RMC',
                'size' => 'M35',
                'primary_unit' => 'cbm',
                'secondary_unit' => 'kgs',
                'conversion_factor' => 2400.0000,
                'type' => 'fg',
                'hsn' => '38245010',
                'gst_per' => 18.00,
                'created_by' => $adminId,
            ],
            [
                'category' => 'RMC',
                'size' => 'M40',
                'primary_unit' => 'cbm',
                'secondary_unit' => 'kgs',
                'conversion_factor' => 2400.0000,
                'type' => 'fg',
                'hsn' => '38245010',
                'gst_per' => 18.00,
                'created_by' => $adminId,
            ],
        ];

        foreach ($items as $itemData) {
            Item::updateOrCreate(
                [
                    'category' => $itemData['category'],
                    'size' => $itemData['size'],
                ],
                $itemData
            );
        }
    }
}
