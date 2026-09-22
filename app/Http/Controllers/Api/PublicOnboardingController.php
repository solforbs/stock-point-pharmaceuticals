<?php

namespace App\Http\Controllers\Api;

use App\Models\Plan;
use App\Services\Tenancy\BillingService;
use App\Services\Tenancy\InvitationUnusableException;
use App\Services\Tenancy\OnboardingService;
use App\Services\Tenancy\PaymentMismatchException;
use App\Services\Tenancy\PaystackClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

/**
 * The unauthenticated edge of the platform: the plans on offer, the quote
 * request form, the one-time registration and activation links, and
 * Paystack's webhook. Every form here is rate-limited at the route.
 */
class PublicOnboardingController extends ApiController
{
    /** GET /api/public/plans */
    public function plans(): JsonResponse
    {
        return response()->json(Plan::where('is_active', true)->orderBy('sort_order')->orderBy('price_monthly')
            ->get(['id', 'code', 'name', 'description', 'currency', 'price_monthly', 'price_yearly', 'max_branches', 'max_users', 'features']));
    }

    /** POST /api/public/quote-requests */
    public function requestQuote(Request $request, OnboardingService $onboarding): JsonResponse
    {
        $data = $request->validate([
            'institution_name' => ['required', 'string', 'max:150'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'town' => ['nullable', 'string', 'max:80'],
            'branches_count' => ['nullable', 'integer', 'between:1,500'],
            'users_count' => ['nullable', 'integer', 'between:1,5000'],
            'plan_id' => ['nullable', 'uuid', 'exists:plans,id'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $onboarding->submitRequest($data + ['ip' => $request->ip()]);

        return response()->json(['received' => true], 201);
    }

    /**
     * POST /api/public/invitations/open — spends the emailed link and returns
     * the form session plus what the form needs to show.
     */
    public function openInvitation(Request $request, OnboardingService $onboarding): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:48']]);

        try {
            ['invitation' => $invitation, 'session_token' => $session] = $onboarding->open($data['token']);
        } catch (InvitationUnusableException $e) {
            return $this->error('INVITATION_UNUSABLE', $e->getMessage(), 410);
        }

        return response()->json([
            'purpose' => $invitation->purpose,
            'email' => $invitation->email,
            'session_token' => $session,
            'session_expires_at' => $invitation->session_expires_at?->toIso8601String(),
            'institution_name' => $invitation->tenantRequest?->institution_name ?? $invitation->organisation?->name,
            'contact_name' => $invitation->tenantRequest?->contact_name ?? $invitation->user?->name,
            'contact_phone' => $invitation->tenantRequest?->phone,
            'town' => $invitation->tenantRequest?->town,
        ]);
    }

    /** POST /api/public/register — creates the institution on its trial. */
    public function register(Request $request, OnboardingService $onboarding): JsonResponse
    {
        $data = $request->validate([
            'session_token' => ['required', 'string', 'size:48'],
            'institution_name' => ['required', 'string', 'max:150'],
            'legal_name' => ['nullable', 'string', 'max:150'],
            'kra_pin' => ['nullable', 'string', 'regex:/^[A-Z]\d{9}[A-Z]$/i'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'string', 'max:80'],
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_password' => ['required', 'confirmed', Password::min(12)],
        ]);

        try {
            ['organisation' => $organisation, 'user' => $user] = $onboarding->register($data['session_token'], collect($data)->except(['session_token', 'admin_password_confirmation'])->all());
        } catch (InvitationUnusableException $e) {
            return $this->error('INVITATION_UNUSABLE', $e->getMessage(), 410);
        }

        return response()->json([
            'institution' => $organisation->name,
            'email' => $user->email,
            'trial_ends_at' => $organisation->trial_ends_at?->toIso8601String(),
        ], 201);
    }

    /** POST /api/public/activate — the admin of a platform-created institution chooses a password. */
    public function activate(Request $request, OnboardingService $onboarding): JsonResponse
    {
        $data = $request->validate([
            'session_token' => ['required', 'string', 'size:48'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        try {
            $user = $onboarding->activate($data['session_token'], $data['password']);
        } catch (InvitationUnusableException $e) {
            return $this->error('INVITATION_UNUSABLE', $e->getMessage(), 410);
        }

        return response()->json(['email' => $user->email]);
    }

    /**
     * POST /api/public/paystack/webhook — trusted only with a valid
     * signature. Always answers 200 once verified, so Paystack does not keep
     * retrying an event that was received but could not be applied.
     */
    public function paystackWebhook(Request $request, PaystackClient $paystack, BillingService $billing): JsonResponse
    {
        if (! $paystack->validSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            return $this->error('INVALID_SIGNATURE', 'The webhook signature does not match.', 401);
        }

        try {
            $billing->handleWebhook((string) $request->input('event'), (array) $request->input('data', []));
        } catch (PaymentMismatchException $e) {
            Log::warning('Paystack webhook not applied: '.$e->getMessage(), ['event' => $request->input('event'), 'reference' => $request->input('data.reference')]);
        }

        return response()->json(['received' => true]);
    }
}
