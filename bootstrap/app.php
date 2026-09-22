<?php

use App\Exceptions\ApiErrorMap;
use App\Http\Middleware\AttachRequestId;
use App\Http\Middleware\EnforceInstitutionAccess;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\ResolveActiveBranch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // A deploy puts the shop into maintenance mode, which would otherwise
        // also silence the screen the administrator is watching it from. The
        // deployment endpoints stay answerable so the log keeps arriving.
        $middleware->preventRequestsDuringMaintenance(except: [
            'api/admin/deployments',
            'api/admin/deployments/*',
        ]);
        $middleware->append(AttachRequestId::class);
        $middleware->alias([
            'branch.context' => ResolveActiveBranch::class,
            'tenant.access' => EnforceInstitutionAccess::class,
            'platform' => EnsurePlatformAdmin::class,
        ]);

        // This backend has no server-rendered login page — the SPA owns
        // /login. A guest hitting a protected route gets a 401, never a
        // redirect to a route that doesn't exist.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = fn (Request $request) => $request->is('api/*') || $request->is('auth/*') || $request->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        // Part 21.15 — domain exceptions become stable error codes.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request)) {
                return null;
            }

            if ($response = ApiErrorMap::toResponse($e)) {
                return $response;
            }

            if ($e instanceof AuthorizationException) {
                return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => $e->getMessage() ?: 'This action is unauthorized.', 'details' => (object) []]], 403);
            }

            if ($e instanceof ModelNotFoundException) {
                return response()->json(['error' => ['code' => 'NOT_FOUND', 'message' => 'The requested record does not exist.', 'details' => (object) []]], 404);
            }

            if ($e instanceof HttpException && $e->getStatusCode() === 403) {
                return response()->json(['error' => ['code' => 'FORBIDDEN', 'message' => $e->getMessage(), 'details' => (object) []]], 403);
            }

            return null;
        });
    })->create();
