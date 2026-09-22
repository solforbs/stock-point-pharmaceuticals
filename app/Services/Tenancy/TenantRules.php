<?php

namespace App\Services\Tenancy;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Exists;

/**
 * "exists" validation that only accepts the active institution's records.
 * A plain exists:products,id passes for any tenant's product; these reject a
 * foreign id with a validation error before a service ever sees it.
 */
class TenantRules
{
    /** Tables owned directly by an institution. */
    private const OWNED = [
        'branches', 'products', 'product_batches', 'suppliers', 'customers', 'customer_tiers', 'tax_codes',
        'price_lists', 'recalls', 'sales', 'employees', 'leave_types', 'chart_of_accounts',
    ];

    /** Reference lists: the shared rows plus the institution's own. */
    private const SHARED = ['units_of_measure', 'dosage_forms', 'storage_conditions', 'product_categories', 'manufacturers'];

    /** Tables owned through a branch. */
    private const VIA_BRANCH = ['stores', 'purchase_orders'];

    public static function exists(string $table, string $column = 'id'): Exists
    {
        $context = app(TenantContext::class);
        $rule = new Exists($table, $column);

        return match (true) {
            in_array($table, self::OWNED, true) => $rule->where('organisation_id', $context->organisationId()),
            in_array($table, self::SHARED, true) => $rule->where(fn (Builder $q) => $q
                ->whereNull('organisation_id')->orWhere('organisation_id', $context->organisationId())),
            in_array($table, self::VIA_BRANCH, true) => $rule->whereIn('branch_id', $context->branchIds()),
            $table === 'purchase_order_lines' => $rule->where(fn (Builder $q) => $q->whereIn(
                'purchase_order_id',
                DB::table('purchase_orders')->select('id')->whereIn('branch_id', $context->branchIds()),
            )),
            $table === 'users' => $rule->where('organisation_id', $context->organisationId()),
            default => throw new \InvalidArgumentException("No tenant rule is defined for {$table}."),
        };
    }
}
