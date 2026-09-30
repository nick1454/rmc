<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\NavigationCategory;
use App\Models\NavigationItem;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = User::where('email', 'admin@rmc.com')->value('id');

        $categories = [
            'Aggregate',
            'Cement',
            'Dust',
            'AddMixture',
            'Water',
            'Silo Pouch',
            'RMC',
        ];

        foreach ($categories as $categoryName) {
            $cat = Category::updateOrCreate(
                ['name' => $categoryName],
                [
                    'status' => 'active',
                    'created_by' => $adminId,
                ]
            );

            // Update items with category_id
            Item::where('category', $categoryName)->update(['category_id' => $cat->id]);
        }

        // Add Category Master navigation item under Master Data if not exists
        $masterCat = NavigationCategory::where('slug', 'master-data')->first();
        if ($masterCat) {
            $navCategory = NavigationItem::updateOrCreate(
                ['path' => '/categories'],
                [
                    'category_id' => $masterCat->id,
                    'title' => 'Category Master',
                    'icon' => 'Tag',
                    'order' => 2,
                    'is_active' => true,
                ]
            );

            // Attach to admin role
            $adminRole = Role::where('slug', 'admin')->first();
            if ($adminRole && ! $adminRole->navigationItems()->where('navigation_items.id', $navCategory->id)->exists()) {
                $adminRole->navigationItems()->attach($navCategory->id);
            }
        }
    }
}
