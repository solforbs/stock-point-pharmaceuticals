<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\CustomerTier;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class KenyanPharmaMasterSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organisation::first();
        if (! $org) {
            $this->command?->error('Organisation not found. Run OrganisationSeeder first.');

            return;
        }

        $admin = User::where('email', 'admin@example.com')->first();
        $adminId = $admin?->id;

        DB::transaction(function () use ($org, $adminId) {
            // 1. Customer Tiers
            $tiersData = [
                [
                    'code' => 'HOSP',
                    'name' => 'Hospitals & Health Centres',
                    'default_discount_pct' => 5.000,
                    'max_discount_pct' => 12.000,
                    'credit_terms_days' => 30,
                ],
                [
                    'code' => 'PHARM',
                    'name' => 'Retail Pharmacies & Chemists',
                    'default_discount_pct' => 2.500,
                    'max_discount_pct' => 8.000,
                    'credit_terms_days' => 14,
                ],
                [
                    'code' => 'NGO',
                    'name' => 'NGOs & Relief Agencies',
                    'default_discount_pct' => 7.500,
                    'max_discount_pct' => 15.000,
                    'credit_terms_days' => 30,
                ],
                [
                    'code' => 'RETAIL',
                    'name' => 'Walk-In / Retail Cash',
                    'default_discount_pct' => 0.000,
                    'max_discount_pct' => 5.000,
                    'credit_terms_days' => 0,
                ],
            ];

            $tierMap = [];
            foreach ($tiersData as $td) {
                $tier = CustomerTier::updateOrCreate(
                    ['organisation_id' => $org->id, 'code' => $td['code']],
                    $td + ['organisation_id' => $org->id]
                );
                $tierMap[$td['code']] = $tier->id;
            }

            // 2. Kenyan Healthcare Customers
            $customersData = [
                [
                    'code' => 'CUST-HOSP-01',
                    'name' => 'Lodwar County Referral Hospital',
                    'customer_type' => 'HOSPITAL',
                    'tier_id' => $tierMap['HOSP'],
                    'tax_status' => 'EXEMPT',
                    'exemption_ref' => 'KRA/EX/MOH/2026/041',
                    'exemption_expiry' => Carbon::parse('2027-06-30'),
                    'payment_terms_days' => 30,
                    'email' => 'pharmacy@lodwarhospital.go.ke',
                    'phone' => '+254712345001',
                    'address' => 'Hospital Road, Lodwar Town, Turkana County',
                    'credit_limit' => 2500000,
                ],
                [
                    'code' => 'CUST-HOSP-02',
                    'name' => 'Kakuma Mission Hospital',
                    'customer_type' => 'HOSPITAL',
                    'tier_id' => $tierMap['HOSP'],
                    'tax_status' => 'STANDARD',
                    'payment_terms_days' => 30,
                    'email' => 'procurement@kakumamission.org',
                    'phone' => '+254712345002',
                    'address' => 'Mission Compound, Kakuma, Turkana West',
                    'credit_limit' => 1800000,
                ],
                [
                    'code' => 'CUST-PHARM-01',
                    'name' => 'Turkana West Chemist Ltd',
                    'customer_type' => 'RETAIL_PHARMACY',
                    'tier_id' => $tierMap['PHARM'],
                    'tax_status' => 'STANDARD',
                    'payment_terms_days' => 14,
                    'email' => 'turkanawestchemist@gmail.com',
                    'phone' => '+254722890112',
                    'address' => 'Main Commercial Street, Kakuma Town',
                    'credit_limit' => 600000,
                ],
                [
                    'code' => 'CUST-PHARM-02',
                    'name' => 'Lokichogio Community Pharmacy',
                    'customer_type' => 'RETAIL_PHARMACY',
                    'tier_id' => $tierMap['PHARM'],
                    'tax_status' => 'STANDARD',
                    'payment_terms_days' => 7,
                    'email' => 'loki.community.pharmacy@yahoo.com',
                    'phone' => '+254733445566',
                    'address' => 'Airport Road, Lokichogio',
                    'credit_limit' => 350000,
                ],
                [
                    'code' => 'CUST-CLINIC-01',
                    'name' => 'St. Monica Health Centre',
                    'customer_type' => 'CLINIC',
                    'tier_id' => $tierMap['HOSP'],
                    'tax_status' => 'STANDARD',
                    'payment_terms_days' => 14,
                    'email' => 'stmonica.clinic@dioceselodwar.org',
                    'phone' => '+254711998877',
                    'address' => 'Kanamkemer Ward, Lodwar',
                    'credit_limit' => 450000,
                ],
                [
                    'code' => 'CUST-NGO-01',
                    'name' => 'International Rescue Committee (IRC) Kakuma',
                    'customer_type' => 'NGO',
                    'tier_id' => $tierMap['NGO'],
                    'tax_status' => 'EXEMPT',
                    'exemption_ref' => 'KRA/NGO/IRC/2026/012',
                    'exemption_expiry' => Carbon::parse('2026-12-31'),
                    'payment_terms_days' => 30,
                    'email' => 'health.procurement@rescue.org',
                    'phone' => '+254720123987',
                    'address' => 'UNHCR Compound, Kakuma Refugee Camp',
                    'credit_limit' => 3000000,
                ],
                [
                    'code' => 'CUST-NGO-02',
                    'name' => 'Kenya Red Cross Turkana Branch',
                    'customer_type' => 'NGO',
                    'tier_id' => $tierMap['NGO'],
                    'tax_status' => 'EXEMPT',
                    'exemption_ref' => 'KRA/NGO/KRCS/2026/088',
                    'exemption_expiry' => Carbon::parse('2027-03-31'),
                    'payment_terms_days' => 30,
                    'email' => 'turkana@redcross.or.ke',
                    'phone' => '+254703037000',
                    'address' => 'Red Cross Offices, Lodwar',
                    'credit_limit' => 1500000,
                ],
                [
                    'code' => 'CUST-WALK-IN',
                    'name' => 'Walk-In Cash Customer',
                    'customer_type' => 'WALK_IN',
                    'tier_id' => $tierMap['RETAIL'],
                    'tax_status' => 'STANDARD',
                    'payment_terms_days' => 0,
                    'email' => null,
                    'phone' => null,
                    'address' => 'Over The Counter, Lodwar',
                    'credit_limit' => 0,
                ],
            ];

            foreach ($customersData as $cd) {
                $creditLimit = $cd['credit_limit'];
                unset($cd['credit_limit']);

                $customer = Customer::updateOrCreate(
                    ['organisation_id' => $org->id, 'code' => $cd['code']],
                    $cd + ['organisation_id' => $org->id, 'created_by' => $adminId]
                );

                CustomerCredit::updateOrCreate(
                    ['customer_id' => $customer->id],
                    [
                        'credit_limit' => $creditLimit,
                        'current_balance' => 0,
                        'unallocated_receipts' => 0,
                        'on_hold' => false,
                    ]
                );
            }

            // 3. Kenyan Pharmaceutical Suppliers
            $today = now()->startOfDay();
            $suppliersData = [
                [
                    'code' => 'MRL',
                    'name' => 'Medina Remedies Limited',
                    'contact_name' => 'Ibrahim Noor (Sales Director)',
                    'email' => 'orders@medinaremedies.co.ke',
                    'phone' => '+254722550011',
                    'address' => 'MRL House, Enterprise Road, Industrial Area, Nairobi',
                    'licence_number' => 'PPB/WL/2024/0911',
                    'licence_expiry' => Carbon::parse('2027-06-30'),
                    'payment_terms_days' => 30,
                    'lead_time_days' => 5,
                    'currency' => 'KES',
                    'bank_name' => 'KCB Bank Kenya - Industrial Area Branch',
                    'bank_account' => '1109823456',
                    'status' => 'ACTIVE',
                ],
                [
                    'code' => 'COSMOS',
                    'name' => 'Cosmos Limited',
                    'contact_name' => 'Pravin Patel (Head of Institutional Sales)',
                    'email' => 'sales@cosmos-pharm.com',
                    'phone' => '+254733600200',
                    'address' => 'Rangwe Road, Off Lunga Lunga Road, Industrial Area, Nairobi',
                    'licence_number' => 'PPB/MF/2023/0442',
                    'licence_expiry' => Carbon::parse('2027-12-31'),
                    'payment_terms_days' => 30,
                    'lead_time_days' => 7,
                    'currency' => 'KES',
                    'bank_name' => 'Standard Chartered Bank - Industrial Area',
                    'bank_account' => '0102049928100',
                    'status' => 'ACTIVE',
                ],
                [
                    'code' => 'LABALLIED',
                    'name' => 'Laboratory & Allied Ltd',
                    'contact_name' => 'Grace Wanjiru (Regional Distribution)',
                    'email' => 'orders@laballied.com',
                    'phone' => '+254721445588',
                    'address' => 'Plot 209/10349, Mombasa Road, Nairobi',
                    'licence_number' => 'PPB/MF/2023/0118',
                    'licence_expiry' => Carbon::parse('2027-09-30'),
                    'payment_terms_days' => 30,
                    'lead_time_days' => 6,
                    'currency' => 'KES',
                    'bank_name' => 'NCBA Bank - Upper Hill',
                    'bank_account' => '7728190021',
                    'status' => 'ACTIVE',
                ],
                [
                    'code' => 'DAWA',
                    'name' => 'Dawa Limited',
                    'contact_name' => 'Moses Kiprop (Supply Chain Executive)',
                    'email' => 'customercare@dawa.co.ke',
                    'phone' => '+254728551122',
                    'address' => 'Plot No. 49, Baba Dogo Road, Ruaraka, Nairobi',
                    'licence_number' => 'PPB/MF/2022/0890',
                    'licence_expiry' => Carbon::parse('2026-11-30'),
                    'payment_terms_days' => 30,
                    'lead_time_days' => 7,
                    'currency' => 'KES',
                    'bank_name' => 'Equity Bank - Community Branch',
                    'bank_account' => '0810293847561',
                    'status' => 'ACTIVE',
                ],
                [
                    'code' => 'HARLEYS',
                    'name' => 'Harleys Limited',
                    'contact_name' => 'Rajesh Shah (Wholesale Accounts)',
                    'email' => 'info@harleysltd.com',
                    'phone' => '+254722202030',
                    'address' => 'Harleys Complex, Westlands Road, Nairobi',
                    'licence_number' => 'PPB/WL/2024/1102',
                    'licence_expiry' => Carbon::parse('2026-12-31'),
                    'payment_terms_days' => 30,
                    'lead_time_days' => 4,
                    'currency' => 'KES',
                    'bank_name' => 'Absa Bank Kenya - Westlands Branch',
                    'bank_account' => '0309928172',
                    'status' => 'ACTIVE',
                ],
                // Expiring within 30 days -> triggers Dashboard Attention: "Supplier licences expiring within 30 days"
                [
                    'code' => 'SURGIPHARM',
                    'name' => 'Surgipharm Limited',
                    'contact_name' => 'David Omondi (Regulatory & Tenders)',
                    'email' => 'info@surgipharm.com',
                    'phone' => '+254722889900',
                    'address' => 'Enterprise Road, Industrial Area, Nairobi',
                    'licence_number' => 'PPB/WL/2023/0784',
                    'licence_expiry' => $today->copy()->addDays(18)->toDateString(),
                    'payment_terms_days' => 14,
                    'lead_time_days' => 5,
                    'currency' => 'KES',
                    'bank_name' => 'Stanbic Bank - Chiromo',
                    'bank_account' => '9082736451',
                    'status' => 'ACTIVE',
                ],
                // Expired 14 days ago -> triggers Dashboard Attention: "Suppliers with an expired licence"
                [
                    'code' => 'BIODEAL',
                    'name' => 'Biodeal Laboratories Ltd',
                    'contact_name' => 'Beatrice Muthoni (Compliance Officer)',
                    'email' => 'sales@biodeallabs.com',
                    'phone' => '+254734556677',
                    'address' => 'Kariobangi Light Industries, Outering Road, Nairobi',
                    'licence_number' => 'PPB/MF/2021/0312',
                    'licence_expiry' => $today->copy()->subDays(14)->toDateString(),
                    'payment_terms_days' => 30,
                    'lead_time_days' => 8,
                    'currency' => 'KES',
                    'bank_name' => 'Co-operative Bank - Industrial Area Branch',
                    'bank_account' => '01128374659200',
                    'status' => 'ACTIVE',
                ],
            ];

            foreach ($suppliersData as $sd) {
                Supplier::updateOrCreate(
                    ['organisation_id' => $org->id, 'code' => $sd['code']],
                    $sd + ['organisation_id' => $org->id, 'created_by' => $adminId, 'is_active' => true]
                );
            }

            // 4. Configure Reorder Parameters for Fast-Moving Essential Medicines
            $fastMovers = [
                'A0003' => ['reorder' => 40, 'safety' => 15],  // ABZ SUSPENSION 400MG 10ML
                'A0004' => ['reorder' => 60, 'safety' => 20],  // ABZ TABS 400MG 1S
                'A0010' => ['reorder' => 30, 'safety' => 10],  // ACEPAR CAPSULES 10S
                'A0015' => ['reorder' => 25, 'safety' => 10],  // ACINET 156 SRY SYRUP 100ML
                'A0019' => ['reorder' => 50, 'safety' => 20],  // ACINET TABS 1G *10
                'A0027' => ['reorder' => 35, 'safety' => 15],  // ACTIFED COUGH AND WET SYRUP 100ML
                'A0029' => ['reorder' => 80, 'safety' => 30],  // ACTION ANALGESIC TABS *100
                '30001' => ['reorder' => 20, 'safety' => 10],  // 3D CREAM 20GM
            ];

            foreach ($fastMovers as $code => $cfg) {
                Product::where('organisation_id', $org->id)
                    ->where('code', (string) $code)
                    ->update([
                        'reorder_point' => $cfg['reorder'],
                        'safety_stock' => $cfg['safety'],
                        'lead_time_days' => 5,
                    ]);
            }
        });
    }
}
