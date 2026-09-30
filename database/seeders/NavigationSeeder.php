<?php

namespace Database\Seeders;

use App\Models\NavigationCategory;
use App\Models\NavigationItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class NavigationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Clean existing navigation tables to remove old dummy links
        DB::table('role_navigation_item')->truncate();
        DB::table('navigation_items')->delete();
        DB::table('navigation_categories')->delete();

        // 2. Seed Roles
        $adminRole = Role::updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'description' => 'Full access to all RMC modules, master data, and settings']
        );

        $driverRole = Role::updateOrCreate(
            ['slug' => 'driver'],
            ['name' => 'Transit Mixer Driver', 'description' => 'Driver trip navigation, pouring tracking and site sign-off']
        );

        // 3. Seed Navigation Categories (Operations, Reports, Master Data)
        $catOperations = NavigationCategory::create([
            'slug' => 'operations',
            'name' => 'Operations',
            'order' => 1,
            'is_active' => true,
        ]);

        $catReports = NavigationCategory::create([
            'slug' => 'reports',
            'name' => 'Reports',
            'order' => 2,
            'is_active' => true,
        ]);

        $catMaster = NavigationCategory::create([
            'slug' => 'master-data',
            'name' => 'Master Data',
            'order' => 3,
            'is_active' => true,
        ]);

        // 4. Seed Navigation Items
        // 4.1 Operations
        $itemDashboard = NavigationItem::create([
            'category_id' => $catOperations->id,
            'title' => 'Dashboard',
            'path' => '/dashboard',
            'icon' => 'LayoutDashboard',
            'order' => 1,
            'is_active' => true,
        ]);

        $itemInward = NavigationItem::create([
            'category_id' => $catOperations->id,
            'title' => 'Stock Inward',
            'path' => '/inward',
            'icon' => 'Truck',
            'order' => 2,
            'is_active' => true,
        ]);

        $itemOutward = NavigationItem::create([
            'category_id' => $catOperations->id,
            'title' => 'Stock Outward',
            'path' => '/outward',
            'icon' => 'Truck',
            'order' => 3,
            'is_active' => true,
        ]);

        $itemProduction = NavigationItem::create([
            'category_id' => $catOperations->id,
            'title' => 'Production Entry',
            'path' => '/production',
            'icon' => 'Factory',
            'order' => 4,
            'is_active' => true,
        ]);

        $itemPayments = NavigationItem::create([
            'category_id' => $catOperations->id,
            'title' => 'Purchase Payments',
            'path' => '/purchase-payments',
            'icon' => 'CreditCard',
            'order' => 5,
            'is_active' => true,
        ]);

        $itemOutwardPayments = NavigationItem::create([
            'category_id' => $catOperations->id,
            'title' => 'Customer Receipts',
            'path' => '/outward-payments',
            'icon' => 'Receipt',
            'order' => 6,
            'is_active' => true,
        ]);

        // 4.2 Reports
        $itemStockReport = NavigationItem::create([
            'category_id' => $catReports->id,
            'title' => 'Stock Report',
            'path' => '/stock-report',
            'icon' => 'BarChart3',
            'order' => 1,
            'is_active' => true,
        ]);

        $itemDispatchReport = NavigationItem::create([
            'category_id' => $catReports->id,
            'title' => 'Dispatch & Billing Report',
            'path' => '/reports/dispatch-payment',
            'icon' => 'FileSpreadsheet',
            'order' => 2,
            'is_active' => true,
        ]);

        // 4.3 Master Data
        $itemMaster = NavigationItem::create([
            'category_id' => $catMaster->id,
            'title' => 'Item Master',
            'path' => '/items',
            'icon' => 'Layers',
            'order' => 1,
            'is_active' => true,
        ]);

        $itemParties = NavigationItem::create([
            'category_id' => $catMaster->id,
            'title' => 'Customer / Vendor',
            'path' => '/parties',
            'icon' => 'Building2',
            'order' => 2,
            'is_active' => true,
        ]);

        // 5. Attach Navigation Permissions to Roles
        $adminRole->navigationItems()->sync([
            $itemDashboard->id,
            $itemInward->id,
            $itemOutward->id,
            $itemProduction->id,
            $itemPayments->id,
            $itemOutwardPayments->id,
            $itemStockReport->id,
            $itemDispatchReport->id,
            $itemMaster->id,
            $itemParties->id,
        ]);

        // Driver gets Dashboard
        $driverRole->navigationItems()->sync([
            $itemDashboard->id,
        ]);

        // 6. Seed Demo Users
        User::updateOrCreate(
            ['email' => 'admin@rmc.com'],
            [
                'name' => 'RMC Administrator',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'driver@rmc.com'],
            [
                'name' => 'Rajesh Kumar (TM-04)',
                'password' => Hash::make('password'),
                'role_id' => $driverRole->id,
                'email_verified_at' => now(),
            ]
        );
    }
}
