<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A subscription plan institutions choose from. Platform-wide, maintained by
 * the platform administrator.
 */
class Plan extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'name', 'description', 'currency', 'price_monthly', 'price_yearly',
        'max_branches', 'max_users', 'features', 'paystack_plan_monthly', 'paystack_plan_yearly',
        'is_active', 'sort_order',
    ];

    protected $hidden = ['paystack_plan_monthly', 'paystack_plan_yearly'];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function priceFor(string $interval): ?string
    {
        return $interval === 'YEARLY' ? $this->price_yearly : $this->price_monthly;
    }

    public function paystackPlanFor(string $interval): ?string
    {
        return $interval === 'YEARLY' ? $this->paystack_plan_yearly : $this->paystack_plan_monthly;
    }
}
