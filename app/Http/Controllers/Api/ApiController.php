<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

abstract class ApiController extends Controller
{
    /**
     * Part 18.2 — server-side on every request. A permission that has not
     * been defined denies rather than erroring: hidden buttons are not
     * security, and neither is a missing seed row.
     */
    protected function requirePermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if (! $user || ! $user->can($permission)) {
            throw new HttpException(403, "You do not have the '{$permission}' permission.");
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function requireAnyPermission(Request $request, array $permissions): void
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(403, 'Unauthorized.');
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        throw new HttpException(403, 'You do not have any of the required permissions: '.implode(', ', $permissions).'.');
    }

    protected function branchId(Request $request): string
    {
        $branchId = $request->attributes->get('active_branch_id');
        if (! $branchId) {
            throw new HttpException(403, 'Your account has no branch assignment; nothing can be posted without a branch context.');
        }

        return (string) $branchId;
    }

    protected function organisationId(Request $request): string
    {
        return (string) Branch::whereKey($this->branchId($request))->value('organisation_id');
    }

    /**
     * Part 21.1 — every mutating request carries an Idempotency-Key.
     */
    protected function idempotencyKey(Request $request): string
    {
        $key = $request->header('Idempotency-Key') ?: $request->input('idempotency_key');
        if (! $key) {
            throw new HttpException(400, 'An Idempotency-Key header is required on this request.');
        }

        return (string) $key;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    protected function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message, 'details' => (object) $details]], $status);
    }
}
