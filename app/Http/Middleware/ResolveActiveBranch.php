<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which branch the current request operates in and sets it as
 * Spatie Permission's "team" context, so role/permission checks are scoped
 * to the right branch (Part 18.2 — data scoping enforced on every request).
 *
 * Resolution order: an explicit X-Branch-Id header (validated against the
 * user's own role assignments), else the user's first assigned branch.
 * Guests and users with no branch assignment yet simply resolve to null —
 * permission checks then only match roles assigned with no branch context.
 */
class ResolveActiveBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $branchId = $this->resolveBranchId($request, $user);

            app(PermissionRegistrar::class)->setPermissionsTeamId($branchId);
            $request->attributes->set('active_branch_id', $branchId);
        }

        return $next($request);
    }

    private function resolveBranchId(Request $request, $user): ?string
    {
        // Queried directly against the pivot table rather than the model's
        // roles() relationship, because that relationship already filters by
        // the current team context — which is exactly what we're trying to
        // establish here.
        $assignedBranchIds = DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_id', $user->getKey())
            ->where('model_type', $user::class)
            ->pluck('branch_id')
            ->filter()
            ->unique()
            ->values();

        $requested = $request->header('X-Branch-Id');
        if ($requested && $assignedBranchIds->contains($requested)) {
            return $requested;
        }

        return $assignedBranchIds->first();
    }
}
