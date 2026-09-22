<?php

namespace App\Services\Tenancy;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The institution the current request (or console loop) acts for. Every
 * tenant-owned model filters itself through this, so a query can never see
 * another institution's rows, whatever id a client sends.
 *
 * Set by ResolveActiveBranch from the signed-in user. When nothing set it,
 * an authenticated user's own organisation still applies (fail closed); only
 * a context with no user at all (console, queue, platform tooling) runs
 * unscoped, and loops there enter each tenant with run().
 */
class TenantContext
{
    private ?string $organisationId = null;

    /** @var list<string>|null */
    private ?array $branchIds = null;

    /** @var list<string>|null */
    private ?array $storeIds = null;

    private bool $platform = false;

    /** For platform-console requests: no tenant unless one is entered with run(). */
    public function enterPlatform(): void
    {
        $this->platform = true;
        $this->set(null);
    }

    public function set(?string $organisationId): void
    {
        $this->organisationId = $organisationId;
        $this->branchIds = null;
        $this->storeIds = null;
    }

    public function organisationId(): ?string
    {
        if ($this->organisationId !== null) {
            return $this->organisationId;
        }

        // Platform screens act across institutions: a platform administrator
        // who also belongs to one must not be narrowed to it there.
        if ($this->platform) {
            return null;
        }

        $user = Auth::hasUser() ? Auth::user() : null;

        return $user?->organisation_id;
    }

    public function isActive(): bool
    {
        return $this->organisationId() !== null;
    }

    /**
     * The tenant's branch ids, for tables owned through a branch.
     *
     * @return list<string>
     */
    public function branchIds(): array
    {
        return $this->branchIds ??= DB::table('branches')
            ->where('organisation_id', $this->organisationId())
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /**
     * The tenant's store ids, for tables owned through a store.
     *
     * @return list<string>
     */
    public function storeIds(): array
    {
        return $this->storeIds ??= DB::table('stores')
            ->whereIn('branch_id', $this->branchIds())
            ->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    /** Forgets cached branch/store ids after a branch or store is created. */
    public function refresh(): void
    {
        $this->branchIds = null;
        $this->storeIds = null;
    }

    /**
     * Runs $callback as the given tenant, restoring the previous one after.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(?string $organisationId, callable $callback): mixed
    {
        $previous = [$this->organisationId, $this->branchIds, $this->storeIds];
        $this->set($organisationId);

        try {
            return $callback();
        } finally {
            [$this->organisationId, $this->branchIds, $this->storeIds] = $previous;
        }
    }
}
