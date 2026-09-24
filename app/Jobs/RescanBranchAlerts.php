<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Services\Alerts\AlertScanner;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Recomputes one branch's standing alerts outside the morning schedule,
 * so an alert whose condition just arose (a prescription waiting at the
 * pharmacy, say) shows in the bell now rather than tomorrow at 05:30.
 * The scan is a recompute, so running it early is always safe.
 */
class RescanBranchAlerts implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $branchId) {}

    public function handle(AlertScanner $scanner, TenantContext $tenant): void
    {
        $branch = Branch::find($this->branchId);
        if (! $branch) {
            return;
        }

        $tenant->run($branch->organisation_id, fn () => $scanner->scan($branch));
    }
}
