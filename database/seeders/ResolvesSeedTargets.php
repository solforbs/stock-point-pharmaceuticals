<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Organisation;

/**
 * The demo seeders post into one deliberate institution and branch — the
 * configured ones, falling back to the oldest. An unordered first() picked
 * whichever row the database offered, and on a database holding a second
 * branch (e.g. from tenancy work) the demo landed where no stores exist.
 */
trait ResolvesSeedTargets
{
    /**
     * SEED_ORGANISATION (an institution's id or exact name) points the demo
     * seeders at that institution instead of the configured one.
     */
    private function seedOrganisation(): ?Organisation
    {
        $wanted = (string) env('SEED_ORGANISATION', '');
        if ($wanted !== '') {
            return Organisation::whereKey($wanted)->orWhere('name', $wanted)->firstOrFail();
        }

        return Organisation::where('kra_pin', config('app.organisation.kra_pin'))->first()
            ?? Organisation::orderBy('created_at')->first();
    }

    private function seedBranch(Organisation $org): ?Branch
    {
        return Branch::where('organisation_id', $org->id)->where('code', config('app.organisation.branch_code'))->first()
            ?? Branch::where('organisation_id', $org->id)->orderBy('created_at')->first();
    }
}
