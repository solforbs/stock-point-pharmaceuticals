<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * [ASSUMPTION] Starting plans so the plan page is never empty. Names,
 * limits and prices are a commercial decision for the platform owner and
 * are edited in the platform console; seeding again never overwrites them.
 */
class PlanSeeder extends Seeder
{
    /** @var list<array{code: string, name: string, description: string, price_monthly: string, max_branches: ?int, max_users: ?int, features: list<string>}> */
    public const PLANS = [
        [
            'code' => 'STARTER', 'name' => 'Starter', 'price_monthly' => '2500.00', 'max_branches' => 1, 'max_users' => 3,
            'description' => 'A single pharmacy counter.',
            'features' => ['Point of sale with offline selling', 'Stock, batches and expiry', 'Cash sale and tax invoices'],
        ],
        [
            'code' => 'PROFESSIONAL', 'name' => 'Professional', 'price_monthly' => '6000.00', 'max_branches' => 3, 'max_users' => 15,
            'description' => 'A growing pharmacy or small wholesaler.',
            'features' => ['Everything in Starter', 'Wholesale quotations and orders', 'Procurement and supplier returns', 'Finance and reports'],
        ],
        [
            'code' => 'ENTERPRISE', 'name' => 'Enterprise', 'price_monthly' => '15000.00', 'max_branches' => null, 'max_users' => null,
            'description' => 'Multi-branch distributors and hospital pharmacies.',
            'features' => ['Everything in Professional', 'Unlimited branches and users', 'Payroll and quality compliance', 'Priority support'],
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $order => $plan) {
            Plan::firstOrCreate(['code' => $plan['code']], $plan + [
                'currency' => 'KES',
                // Two months free on a yearly plan.
                'price_yearly' => bcmul($plan['price_monthly'], '10', 2),
                'sort_order' => $order,
                'is_active' => true,
            ]);
        }
    }
}
