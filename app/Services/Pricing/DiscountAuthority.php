<?php

namespace App\Services\Pricing;

use App\Models\RoleDiscountAuthority;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Part 4.5 — each role carries a discount authority. A user's effective
 * authority in a branch is the most generous of the roles they hold there
 * (a Director who is also a Cashier is still a Director). Users with no
 * authority row at all may not discount.
 */
final class Authority
{
    public function __construct(
        public readonly string $maxLineDiscountPct,
        public readonly string $maxHeaderDiscountPct,
        public readonly bool $mayOverrideFloor,
    ) {}

    public static function none(): self
    {
        return new self('0.000', '0.000', false);
    }
}

class DiscountAuthority
{
    public function for(User $user, ?string $branchId): Authority
    {
        $pivot = config('permission.table_names.model_has_roles');

        $roleIds = DB::table($pivot)
            ->where('model_id', $user->getKey())
            ->where('model_type', $user::class)
            ->when($branchId, fn ($q) => $q->where(fn ($w) => $w->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return Authority::none();
        }

        $rows = RoleDiscountAuthority::whereIn('role_id', $roleIds)->get();
        if ($rows->isEmpty()) {
            return Authority::none();
        }

        $line = '0.000';
        $header = '0.000';
        $override = false;
        foreach ($rows as $row) {
            if (bccomp((string) $row->max_line_discount_pct, $line, 3) > 0) {
                $line = (string) $row->max_line_discount_pct;
            }
            if (bccomp((string) $row->max_header_discount_pct, $header, 3) > 0) {
                $header = (string) $row->max_header_discount_pct;
            }
            $override = $override || (bool) $row->may_override_floor;
        }

        return new Authority($line, $header, $override);
    }
}
