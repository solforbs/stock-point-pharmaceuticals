<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Models\TenantInvitation;
use App\Models\TenantRequest;
use App\Models\User;
use App\Services\Admin\BackupService;
use App\Services\Admin\TransactionPurgeService;
use App\Services\Tenancy\InvalidTenantRequestStateException;
use App\Services\Tenancy\InvitationUnusableException;
use App\Services\Tenancy\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The platform console (behind the `platform` middleware): quote requests,
 * institutions, their trials, suspensions and subscriptions, and the plans
 * on offer. It manages institutions; it does not open their business data.
 */
class PlatformController extends ApiController
{
    // ---------------------------------------------------------------- Quote requests

    /** GET /api/platform/quote-requests */
    public function quoteRequests(Request $request): JsonResponse
    {
        return response()->json(TenantRequest::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with(['plan:id,code,name', 'organisation:id,name'])
            ->orderByRaw("status = 'PENDING' DESC")->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25)));
    }

    /** POST /api/platform/quote-requests/{id}/approve — emails the one-time registration link. */
    public function approveRequest(Request $request, string $tenantRequest, OnboardingService $onboarding): JsonResponse
    {
        try {
            $invitation = $onboarding->approve(TenantRequest::findOrFail($tenantRequest), $request->user()->id);
        } catch (InvalidTenantRequestStateException $e) {
            return $this->error('INVALID_STATE', $e->getMessage(), 409);
        }

        return response()->json(['status' => 'APPROVED', 'invitation' => $this->invitationRow($invitation)]);
    }

    /** POST /api/platform/quote-requests/{id}/reject */
    public function rejectRequest(Request $request, string $tenantRequest, OnboardingService $onboarding): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        try {
            $row = $onboarding->reject(TenantRequest::findOrFail($tenantRequest), $request->user()->id, $data['reason']);
        } catch (InvalidTenantRequestStateException $e) {
            return $this->error('INVALID_STATE', $e->getMessage(), 409);
        }

        return response()->json($row);
    }

    // ---------------------------------------------------------------- Institutions

    /** GET /api/platform/tenants */
    public function tenants(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q', ''));
        $page = Organisation::query()
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('contact_email', 'like', "%{$term}%")))
            ->withCount('branches')
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

        $userCounts = User::whereIn('organisation_id', $page->getCollection()->pluck('id'))
            ->groupBy('organisation_id')->selectRaw('organisation_id, COUNT(*) as n')->pluck('n', 'organisation_id');

        $page->getCollection()->transform(fn (Organisation $o) => $this->tenantRow($o, (int) ($userCounts[$o->id] ?? 0)));

        return response()->json($page);
    }

    /** GET /api/platform/tenants/{id} */
    public function tenant(string $organisation): JsonResponse
    {
        $organisation = Organisation::withCount('branches')->findOrFail($organisation);

        return response()->json($this->tenantRow($organisation, User::where('organisation_id', $organisation->id)->count()) + [
            'subscriptions' => $organisation->subscriptions()->with('plan:id,code,name')->orderByDesc('created_at')->get(),
            'payments' => SubscriptionPayment::where('organisation_id', $organisation->id)->with('plan:id,name')->orderByDesc('created_at')->limit(50)->get(),
            'invitations' => TenantInvitation::where('organisation_id', $organisation->id)->orderByDesc('created_at')->get()->map(fn ($i) => $this->invitationRow($i)),
            'admins' => User::where('organisation_id', $organisation->id)->orderBy('id')->limit(10)->get(['id', 'name', 'email', 'is_active', 'last_login_at']),
        ]);
    }

    /** POST /api/platform/tenants — the platform creates an institution and emails its admin an activation link. */
    public function storeTenant(Request $request, OnboardingService $onboarding): JsonResponse
    {
        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'kra_pin' => ['nullable', 'string', 'regex:/^[A-Z]\d{9}[A-Z]$/i'],
            'contact_email' => ['required', 'email', 'max:150'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:80'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'trial' => ['nullable', 'boolean'],
            'complimentary' => ['nullable', 'boolean'],
        ]);

        $result = $onboarding->createTenant($data + ['trial' => $data['trial'] ?? true], $request->user()->id);

        return response()->json($this->tenantRow($result['organisation']->loadCount('branches'), 1) + [
            'invitation' => $this->invitationRow($result['invitation']),
        ], 201);
    }

    /** POST /api/platform/tenants/{id}/suspend */
    public function suspend(Request $request, string $organisation): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $organisation = Organisation::findOrFail($organisation);
        $organisation->forceFill(['suspended_at' => now(), 'suspension_reason' => $data['reason']])->save();
        $organisation->refreshSubscriptionStatus();
        AuditLog::record('TENANT_SUSPENDED', 'organisation', $organisation->id, ['reference' => $organisation->name, 'reason' => $data['reason']]);

        return response()->json($this->tenantRow($organisation->loadCount('branches')));
    }

    /** POST /api/platform/tenants/{id}/reactivate */
    public function reactivate(string $organisation): JsonResponse
    {
        $organisation = Organisation::findOrFail($organisation);
        $organisation->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();
        $organisation->refreshSubscriptionStatus();
        AuditLog::record('TENANT_REACTIVATED', 'organisation', $organisation->id, ['reference' => $organisation->name]);

        return response()->json($this->tenantRow($organisation->loadCount('branches')));
    }

    /** POST /api/platform/tenants/{id}/extend-trial — counts from today or the current trial end, whichever is later. */
    public function extendTrial(Request $request, string $organisation): JsonResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'between:1,90']]);
        $organisation = Organisation::findOrFail($organisation);
        $from = $organisation->trial_ends_at && $organisation->trial_ends_at->isFuture() ? $organisation->trial_ends_at : now();
        $organisation->forceFill(['trial_ends_at' => $from->copy()->addDays($data['days'])])->save();
        $organisation->refreshSubscriptionStatus();
        AuditLog::record('TENANT_TRIAL_EXTENDED', 'organisation', $organisation->id, ['reference' => $organisation->name, 'after_json' => ['trial_ends_at' => $organisation->trial_ends_at->toIso8601String()]]);

        return response()->json($this->tenantRow($organisation->loadCount('branches')));
    }

    /**
     * GET /api/platform/tenants/{id}/transactions-since?since= — what a
     * clear-out would remove, before anyone commits to it.
     */
    public function purgePreview(Request $request, string $organisation, TransactionPurgeService $purger): JsonResponse
    {
        $data = $request->validate(['since' => ['required', 'date', 'before_or_equal:now']]);
        $organisation = Organisation::findOrFail($organisation);

        return response()->json([
            'since' => Carbon::parse($data['since'])->toIso8601String(),
            'documents' => $purger->preview($organisation, Carbon::parse($data['since'])),
        ]);
    }

    /**
     * POST /api/platform/tenants/{id}/clear-transactions — removes demo or
     * training transactions recorded since a moment. The institution's name
     * must be typed back, and a database backup is taken first; if the
     * backup fails nothing is deleted.
     */
    public function purgeTransactions(Request $request, string $organisation, TransactionPurgeService $purger, BackupService $backups): JsonResponse
    {
        $organisation = Organisation::findOrFail($organisation);
        $data = $request->validate([
            'since' => ['required', 'date', 'before_or_equal:now'],
            'confirm_name' => ['required', 'string', Rule::in([$organisation->name])],
            'reason' => ['required', 'string', 'min:3', 'max:255'],
        ], ['confirm_name.in' => 'Type the institution\'s name exactly as shown to confirm.']);

        $backup = $backups->create();
        $summary = $purger->purge($organisation, Carbon::parse($data['since']), $data['reason']);

        return response()->json(['backup' => $backup['name'], ...$summary]);
    }

    /** POST /api/platform/tenants/{id}/complimentary — never billed (partners, pilots) or back to paying. */
    public function setComplimentary(Request $request, string $organisation): JsonResponse
    {
        $data = $request->validate(['is_complimentary' => ['required', 'boolean']]);
        $organisation = Organisation::findOrFail($organisation);
        $organisation->forceFill(['is_complimentary' => $data['is_complimentary']])->save();
        $organisation->refreshSubscriptionStatus();
        AuditLog::record('TENANT_COMPLIMENTARY_CHANGED', 'organisation', $organisation->id, ['reference' => $organisation->name, 'after_json' => $data]);

        return response()->json($this->tenantRow($organisation->loadCount('branches')));
    }

    /** POST /api/platform/invitations/{id}/resend */
    public function resendInvitation(Request $request, string $invitation, OnboardingService $onboarding): JsonResponse
    {
        try {
            $fresh = $onboarding->resend(TenantInvitation::findOrFail($invitation), $request->user()->id);
        } catch (InvitationUnusableException $e) {
            return $this->error('INVALID_STATE', $e->getMessage(), 409);
        }

        return response()->json($this->invitationRow($fresh), 201);
    }

    // ---------------------------------------------------------------- Plans and payments

    /** GET /api/platform/plans — every plan, inactive included, with the Paystack plan codes. */
    public function plans(): JsonResponse
    {
        return response()->json(Plan::orderBy('sort_order')->orderBy('price_monthly')->get()->map(fn (Plan $p) => $p->makeVisible(['paystack_plan_monthly', 'paystack_plan_yearly'])));
    }

    /** POST /api/platform/plans */
    public function storePlan(Request $request): JsonResponse
    {
        $plan = Plan::create($request->validate($this->planRules(null)));
        AuditLog::record('PLAN_CREATED', 'plan', $plan->id, ['reference' => $plan->code, 'after_json' => $plan->toArray()]);

        return response()->json($plan->makeVisible(['paystack_plan_monthly', 'paystack_plan_yearly']), 201);
    }

    /** PATCH /api/platform/plans/{id} — prices change for future payments only. */
    public function updatePlan(Request $request, string $plan): JsonResponse
    {
        $plan = Plan::findOrFail($plan);
        $before = $plan->toArray();
        $plan->update($request->validate($this->planRules($plan)));
        AuditLog::record('PLAN_UPDATED', 'plan', $plan->id, ['reference' => $plan->code, 'before_json' => $before, 'after_json' => $plan->fresh()?->toArray()]);

        return response()->json($plan->fresh()?->makeVisible(['paystack_plan_monthly', 'paystack_plan_yearly']));
    }

    /** GET /api/platform/payments */
    public function payments(Request $request): JsonResponse
    {
        return response()->json(SubscriptionPayment::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with(['organisation:id,name', 'plan:id,name'])
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 50)));
    }

    /**
     * @return array<string, mixed>
     */
    private function tenantRow(Organisation $organisation, ?int $usersCount = null): array
    {
        $current = $organisation->currentSubscription();

        return $organisation->only(['id', 'name', 'legal_name', 'kra_pin', 'contact_email', 'contact_phone', 'is_complimentary', 'suspension_reason', 'created_at']) + [
            'access_state' => $organisation->accessState(),
            'trial_ends_at' => $organisation->trial_ends_at?->toIso8601String(),
            'suspended_at' => $organisation->suspended_at?->toIso8601String(),
            'branches_count' => (int) ($organisation->branches_count ?? DB::table('branches')->where('organisation_id', $organisation->id)->count()),
            'users_count' => $usersCount ?? User::where('organisation_id', $organisation->id)->count(),
            'plan' => $current?->plan?->only(['id', 'code', 'name']),
            'current_period_end' => $current?->current_period_end?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invitationRow(TenantInvitation $invitation): array
    {
        return $invitation->only(['id', 'purpose', 'email', 'tenant_request_id', 'organisation_id']) + [
            'expires_at' => $invitation->expires_at?->toIso8601String(),
            'opened_at' => $invitation->opened_at?->toIso8601String(),
            'used_at' => $invitation->used_at?->toIso8601String(),
            'revoked_at' => $invitation->revoked_at?->toIso8601String(),
            'state' => match (true) {
                $invitation->used_at !== null => 'USED',
                $invitation->revoked_at !== null => 'REVOKED',
                $invitation->opened_at !== null => 'OPENED',
                $invitation->expires_at->isPast() => 'EXPIRED',
                default => 'SENT',
            },
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function planRules(?Plan $existing): array
    {
        $required = $existing ? 'sometimes' : 'required';

        return [
            'code' => [$required, 'string', 'max:30', 'alpha_dash', Rule::unique('plans', 'code')->ignore($existing?->id)],
            'name' => [$required, 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'price_monthly' => [$required, 'numeric', 'min:0'],
            'price_yearly' => ['nullable', 'numeric', 'min:0'],
            'max_branches' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:120'],
            'paystack_plan_monthly' => ['nullable', 'string', 'max:60'],
            'paystack_plan_yearly' => ['nullable', 'string', 'max:60'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
