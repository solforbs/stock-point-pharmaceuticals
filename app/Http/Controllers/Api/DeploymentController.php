<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Services\Admin\DeploymentFailedException;
use App\Services\Admin\DeploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Part 17 — operations: the deployment screen. It reports the running
 * version, asks GitHub what is newer, and starts the one deploy script.
 * It accepts no command, path or branch from the browser: the only choices
 * are "check" and "deploy", and both need `deploy.run`.
 */
class DeploymentController extends ApiController
{
    /** GET /api/admin/deployments */
    public function status(Request $request, DeploymentService $deployments): JsonResponse
    {
        $this->requirePermission($request, 'deploy.run');

        return response()->json($deployments->status() + [
            'enabled' => (bool) config('deployment.enabled'),
            'root' => $deployments->root(),
        ]);
    }

    /** POST /api/admin/deployments/check — fetches from GitHub; never moves HEAD. */
    public function check(Request $request, DeploymentService $deployments): JsonResponse
    {
        $this->requirePermission($request, 'deploy.run');

        $result = $deployments->fetch();
        if (! $result['ok']) {
            return $this->error('FETCH_FAILED', $result['message'], 422);
        }

        return response()->json($deployments->status() + ['message' => $result['message']]);
    }

    /** POST /api/admin/deployments — pulls and deploys, in the background. */
    public function deploy(Request $request, DeploymentService $deployments): JsonResponse
    {
        $this->requirePermission($request, 'deploy.run');

        if (! config('deployment.enabled')) {
            return $this->error('DEPLOY_DISABLED', 'Deploying from the browser is switched off here (DEPLOY_ENABLED).', 422);
        }

        $data = $request->validate(['version' => ['nullable', 'string', 'max:40']]);

        try {
            $runId = $deployments->start((int) $request->user()->id, $data['version'] ?? null);
        } catch (DeploymentFailedException $e) {
            return $this->error('DEPLOY_FAILED', $e->getMessage(), 422);
        }

        $before = $deployments->status();
        AuditLog::record('DEPLOY_STARTED', 'deployment', $runId, [
            'reference' => $data['version'] ?? $runId,
            'before_json' => ['commit' => $before['commit'], 'version' => $before['version'], 'behind' => $before['behind']],
            'after_json' => ['requested' => $data['version'] ?? 'latest on '.$deployments->branch()],
        ]);

        return response()->json(['run_id' => $runId, 'status' => 'running', 'version' => $data['version'] ?? null], 202);
    }
}
