<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which institution and branch the current request operates in.
 *
 * The institution is the signed-in user's own organisation: it becomes the
 * tenant context every tenant-owned model filters through, so nothing the
 * client sends can reach another institution's data. An account with no
 * institution (a platform account) is refused here, because running tenant
 * endpoints without a tenant would mean running them unscoped.
 *
 * The branch is then Spatie Permission's "team" context, so role checks are
 * scoped to it (Part 18.2). Resolution order: an explicit X-Branch-Id header
 * (validated against the user's own role assignments within their
 * institution), else the user's first assigned branch there.
 */
class ResolveActiveBranch
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if (! $user->organisation_id) {
                return response()->json(['error' => [
                    'code' => 'NO_INSTITUTION',
                    'message' => 'This account does not belong to an institution, so it cannot open institution data.',
                    'details' => (object) [],
                ]], 403);
            }

            $this->tenant->set((string) $user->organisation_id);
            $branchId = $this->resolveBranchId($request, $user);

            app(PermissionRegistrar::class)->setPermissionsTeamId($branchId);
            $request->attributes->set('active_branch_id', $branchId);
            $request->attributes->set('active_organisation_id', (string) $user->organisation_id);
        }

        return $next($request);
    }

    private function resolveBranchId(Request $request, $user): ?string
    {
        // Queried directly against the pivot table rather than the model's
        // roles() relationship, because that relationship already filters by
        // the current team context — which is exactly what we're trying to
        // establish here. Only branches of the user's own institution count.
        $pivot = config('permission.table_names.model_has_roles');
        $assignedBranchIds = DB::table($pivot)
            ->join('branches', 'branches.id', '=', "{$pivot}.branch_id")
            ->where("{$pivot}.model_id", $user->getKey())
            ->where("{$pivot}.model_type", $user::class)
            ->where('branches.organisation_id', $user->organisation_id)
            ->pluck("{$pivot}.branch_id")
            ->unique()
            ->values();

        $requested = $request->header('X-Branch-Id');
        if ($requested && $assignedBranchIds->contains($requested)) {
            return $requested;
        }

        return $assignedBranchIds->first();
    }
}
