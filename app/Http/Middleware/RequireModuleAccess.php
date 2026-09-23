<?php

namespace App\Http\Middleware;

use App\Services\Modules\ModuleCatalogue;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-group gate for the healthcare modules: /api/hospital/* and
 * /api/laboratory/* refuse anyone without a permission from that module's
 * catalogue, so a URL can never reach another module's data. Fine-grained
 * checks still happen inside each controller, as everywhere else.
 */
class RequireModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if ($user === null || ! ModuleCatalogue::userHasModule($user, $module)) {
            return response()->json([
                'message' => 'You do not have access to this module.',
                'code' => 'MODULE_ACCESS_DENIED',
            ], 403);
        }

        return $next($request);
    }
}
