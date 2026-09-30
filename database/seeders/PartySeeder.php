<?php

namespace Database\Seeders;

use App\Models\NavigationCategory;
use App\Models\NavigationItem;
use App\Models\Party;
use App\Models\Role;
use App\Models\StockInward;
use App\Models\User;
use Illuminate\Database\Seeder;

class PartySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminId = User::where('email', 'admin@rmc.com')->value('id');

        // 1. Navigation Item under Master Data
        $masterCat = NavigationCategory::where('slug', 'master-data')->first();
        if ($masterCat) {
            $navItem = NavigationItem::updateOrCreate(
                ['path' => '/parties'],
                [
                    'category_id' => $masterCat->id,
                    'title' => 'Customer / Vendor',
                    'icon' => 'Building2',
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

        // 2. Demo Suppliers / Vendors
        $suppliers = [
            [
                'name' => 'UltraTech Cement Ltd',
                'type' => 'supplier',
                'code' => 'SUP-0001',
                'contact_person' => 'Rajesh Sharma (Sales Head)',
                'phone' => '+91 98230 11223',
                'email' => 'sales.west@ultratechcement.com',
                'gstin' => '27AAACU1234F1Z5',
                'pan_no' => 'AAACU1234F',
                'address' => 'Ahura Centre, 2nd Floor, Mahakali Caves Road, Andheri East',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'pincode' => '400093',
                'status' => 'active',
                'remarks' => 'Primary OPC 53 and OPC 43 bulk cement supplier. Delivers via bulkers.',
                'created_by' => $adminId,
            ],
            [
                'name' => 'Shree Balaji Stone Crusher',
                'type' => 'supplier',
                'code' => 'SUP-0002',
                'contact_person' => 'Mahesh Patil',
                'phone' => '+91 94220 55667',
                'email' => 'balaji.crusher@gmail.com',
                'gstin' => '27AASPB5678H1Z2',
                'pan_no' => 'AASPB5678H',
                'address' => 'Gat No 142, Stone Quarry Zone, Wagholi',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '412207',
                'status' => 'active',
                'remarks' => 'Supplier of 10mm and 20mm basalt crushed aggregate.',
                'created_by' => $adminId,
            ],
            [
                'name' => 'Fosroc Chemicals India Pvt Ltd',
                'type' => 'supplier',
                'code' => 'SUP-0003',
                'contact_person' => 'Anand Kulkarni (Technical Exec)',
                'phone' => '+91 98810 44332',
                'email' => 'enquiry.india@fosroc.com',
                'gstin' => '27AAACF9012K1Z9',
                'pan_no' => 'AAACF9012K',
                'address' => 'Plot 34, MIDC Bhosari Industrial Area',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411026',
                'status' => 'active',
                'remarks' => 'Admixture supplier (Hyperplasticizers, retarders, silica fume).',
                'created_by' => $adminId,
            ],
            [
                'name' => 'Navkar Mineral Dust Suppliers',
                'type' => 'supplier',
                'code' => 'SUP-0004',
                'contact_person' => 'Vikas Jain',
                'phone' => '+91 97650 99881',
                'email' => 'navkarmkd@yahoo.com',
                'gstin' => '27ABVPN3456J1Z1',
                'pan_no' => 'ABVPN3456J',
                'address' => 'Survey No 88, Near Toll Plaza, Talegaon',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '410507',
                'status' => 'active',
                'remarks' => 'Crushed manufactured sand and stone dust supplier.',
                'created_by' => $adminId,
            ],
            [
                'name' => 'Polypack Industries',
                'type' => 'supplier',
                'code' => 'SUP-0005',
                'contact_person' => 'Sunil Agarwal',
                'phone' => '+91 93710 22334',
                'email' => 'polypack@rediffmail.com',
                'gstin' => '27AAECP7890M1Z3',
                'pan_no' => 'AAECP7890M',
                'address' => 'G-12, Chakan MIDC Phase 2',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '410501',
                'status' => 'active',
                'remarks' => 'Silo pouch and packaging supplies.',
                'created_by' => $adminId,
            ],
        ];

        // 3. Demo Customers
        $customers = [
            [
                'name' => 'L&T Construction - Metro Rail Project',
                'type' => 'customer',
                'code' => 'CUS-0001',
                'contact_person' => 'Suresh Nair (Project Manager)',
                'phone' => '+91 98200 45678',
                'email' => 'nair.s@intecc.com',
                'gstin' => '27AAACL0123D1ZX',
                'pan_no' => 'AAACL0123D',
                'address' => 'Site Office, Metro Line 3 Pier Cast Yard, Shivaji Nagar',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411005',
                'status' => 'active',
                'remarks' => 'Bulk supply of M30, M35, and M40 high-grade RMC concrete.',
                'created_by' => $adminId,
            ],
            [
                'name' => 'Shapoorji Pallonji Infra - Highway Package 4',
                'type' => 'customer',
                'code' => 'CUS-0002',
                'contact_person' => 'Ramesh Deshmukh',
                'phone' => '+91 98900 67890',
                'email' => 'ramesh.d@shapoorji.com',
                'gstin' => '27AAACS4567E1ZY',
                'pan_no' => 'AAACS4567E',
                'address' => 'Ring Road Highway Site Office, Kharadi Bypass',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411014',
                'status' => 'active',
                'remarks' => 'Pavement Quality Concrete (PQC) and M25 foundation concrete.',
                'created_by' => $adminId,
            ],
            [
                'name' => 'Godrej Properties - Horizon Towers',
                'type' => 'customer',
                'code' => 'CUS-0003',
                'contact_person' => 'Deepak Verma (Site Engineer)',
                'phone' => '+91 97630 11224',
                'email' => 'dverma@godrejproperties.com',
                'gstin' => '27AAACG7890A1ZT',
                'pan_no' => 'AAACG7890A',
                'address' => 'Horizon Towers Site, Baner-Pashan Link Road',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411045',
                'status' => 'active',
                'remarks' => 'Residential high-rise tower project. Standard M20 and M25 slab pours.',
                'created_by' => $adminId,
            ],
            [
                'name' => 'Tata Projects Ltd - Airport Expansion',
                'type' => 'both', // Both Customer and Supplier/Partner
                'code' => 'PTY-0001',
                'contact_person' => 'Amitabh Sen',
                'phone' => '+91 99220 88776',
                'email' => 'amitabh.sen@tataprojects.com',
                'gstin' => '27AAACT9876C1ZQ',
                'pan_no' => 'AAACT9876C',
                'address' => 'Terminal 2 Runway Area Project Camp',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411032',
                'status' => 'active',
                'remarks' => 'Major infrastructure contractor (RMC buyer & raw material supplier).',
                'created_by' => $adminId,
            ],
        ];

        // 4. Demo Agents / Brokers
        $agents = [
            [
                'name' => 'Mahalaxmi Cement & Material Brokers',
                'type' => 'agent',
                'code' => 'AGT-0001',
                'contact_person' => 'Pramod Joshi (Principal Agent)',
                'phone' => '+91 98224 55112',
                'email' => 'pramod.joshi@mahalaxmibrokers.in',
                'gstin' => '27AAPFM8899P1Z4',
                'pan_no' => 'AAPFM8899P',
                'address' => 'Office 402, Trade Centre, Near Market Yard',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411037',
                'status' => 'active',
                'remarks' => 'Commission broker for bulk OPC/PPC cement loads and fly ash sourcing.',
                'created_by' => $adminId,
            ],
            [
                'name' => 'Apex Infrastructure & Sourcing Agency',
                'type' => 'agent',
                'code' => 'AGT-0002',
                'contact_person' => 'Virendra Singh',
                'phone' => '+91 94225 66778',
                'email' => 'virendra@apexinfraagency.com',
                'gstin' => '27AAMFA3344Q1Z8',
                'pan_no' => 'AAMFA3344Q',
                'address' => 'Suite 12, Highway Commerce Hub, Wakad Bypass',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411057',
                'status' => 'active',
                'remarks' => 'Sales liaison agent for highway PQC contracts and commercial client orders.',
                'created_by' => $adminId,
            ],
            [
                'name' => 'Sai Crushed Sand & Aggregate Agency',
                'type' => 'agent',
                'code' => 'AGT-0003',
                'contact_person' => 'Santosh Gaikwad',
                'phone' => '+91 98812 77889',
                'email' => 'santosh.saiagency@gmail.com',
                'gstin' => '27ABQFS5566R1Z2',
                'pan_no' => 'ABQFS5566R',
                'address' => 'Gat 84, Industrial Freight Corridor, Chakan',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '410501',
                'status' => 'active',
                'remarks' => 'Quarry aggregate supplier broker and transit tipper logistics agent.',
                'created_by' => $adminId,
            ],
        ];

        foreach (array_merge($suppliers, $customers, $agents) as $partyData) {
            $party = Party::updateOrCreate(
                ['name' => $partyData['name']],
                $partyData
            );

            // Backfill matching stock inward records
            StockInward::where('vendor', $party->name)->whereNull('party_id')->update([
                'party_id' => $party->id,
            ]);
        }
    }
}
