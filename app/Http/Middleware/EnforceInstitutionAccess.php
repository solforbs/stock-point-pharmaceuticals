<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * What an institution's subscription allows, enforced on every request:
 *
 *   ACTIVE / TRIAL  everything
 *   LAPSED          read-only — view and export everything, post nothing —
 *                   until a plan is paid for; billing stays open
 *   SUSPENDED       stopped by the platform; only the account's own status
 *                   and billing answer
 *
 * Runs after ResolveActiveBranch, which has already fixed the institution.
 */
class EnforceInstitutionAccess
{
    /** Always reachable, so a lapsed or suspended institution can see why and pay. */
    private const ALWAYS_OPEN = ['api/user', 'api/billing', 'api/billing/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $organisationId = $request->attributes->get('active_organisation_id');
        if (! $organisationId || $request->is(...self::ALWAYS_OPEN)) {
            return $next($request);
        }

        $organisation = Organisation::find($organisationId);
        $state = $organisation?->accessState() ?? Organisation::ACCESS_SUSPENDED;

        if ($state === Organisation::ACCESS_SUSPENDED) {
            return $this->refuse('INSTITUTION_SUSPENDED', 'This institution has been suspended. Contact the platform administrator.', 403, $state);
        }

        if ($state === Organisation::ACCESS_LAPSED && ! $request->isMethodSafe()) {
            return $this->refuse('SUBSCRIPTION_REQUIRED', 'Your trial or subscription has ended. Choose a plan to continue working; your data stays available to view in the meantime.', 402, $state);
        }

        return $next($request);
    }

    private function refuse(string $code, string $message, int $status, string $state): Response
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'details' => ['access_state' => $state]]], $status);
    }
}
