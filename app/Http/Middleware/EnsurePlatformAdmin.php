<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * The platform console: institutions, quote requests, plans and payments
 * across the whole service. Only platform administrators, and never
 * narrowed to the institution a platform administrator may also belong to.
 */
class EnsurePlatformAdmin
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->is_platform_admin) {
            return response()->json(['error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Only a platform administrator can open the platform console.',
                'details' => (object) [],
            ]], 403);
        }

        $this->tenant->enterPlatform();
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $next($request);
    }
}
