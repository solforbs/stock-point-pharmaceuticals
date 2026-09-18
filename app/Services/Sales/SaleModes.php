<?php

namespace App\Services\Sales;

use App\Models\Branch;
use App\Models\Setting;

/**
 * V6 Part 10.1 — retail, wholesale and dispensing are configuration, not
 * separate systems. A branch enables the modes it trades in; one of them is
 * the mode a terminal opens in, and moving off it is a permission-gated,
 * audited switch (Part 24.2).
 */
class SaleModes
{
    public const RETAIL = 'RETAIL';

    public const WHOLESALE = 'WHOLESALE';

    public const DISPENSING = 'DISPENSING';

    public const ALL = [self::RETAIL, self::WHOLESALE, self::DISPENSING];

    /**
     * @return list<string>
     */
    public static function enabledFor(Branch $branch): array
    {
        $modes = [];
        if ($branch->retail_enabled) {
            $modes[] = self::RETAIL;
        }
        if ($branch->wholesale_enabled) {
            $modes[] = self::WHOLESALE;
        }
        if ($branch->dispensing_enabled) {
            $modes[] = self::DISPENSING;
        }

        return $modes;
    }

    /**
     * The mode the POS opens in: a per-branch setting when the owner has
     * chosen one, otherwise the first enabled mode in retail → wholesale →
     * dispensing order. Null when the branch trades in nothing.
     */
    public static function defaultFor(Branch $branch): ?string
    {
        $enabled = self::enabledFor($branch);
        $configured = Setting::resolve($branch->organisation_id, $branch->id, 'pos', 'default_sale_mode');

        if (is_string($configured) && in_array($configured, $enabled, true)) {
            return $configured;
        }

        return $enabled[0] ?? null;
    }
}
