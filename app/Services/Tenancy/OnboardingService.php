<?php

namespace App\Services\Tenancy;

use App\Mail\NewTenantRequestMail;
use App\Mail\TenantInvitationMail;
use App\Mail\TenantRequestReceivedMail;
use App\Mail\TenantRequestRejectedMail;
use App\Models\AuditLog;
use App\Models\Organisation;
use App\Models\TenantInvitation;
use App\Models\TenantRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class InvitationUnusableException extends \RuntimeException {}

class InvalidTenantRequestStateException extends \RuntimeException {}

/**
 * How an institution comes onto the platform.
 *
 *   quote request → platform approves → REGISTER link emailed → prospect
 *   opens it (the link dies) → fills in the form within the hour →
 *   institution provisioned on a seven-day trial.
 *
 * Or the platform creates the institution itself and emails its admin an
 * ACTIVATE link to choose a password. Links are stored only as hashes.
 */
class OnboardingService
{
    public function __construct(private readonly TenantProvisioner $provisioner) {}

    /**
     * @param  array{institution_name: string, contact_name: string, email: string, phone?: ?string, town?: ?string, branches_count?: ?int, users_count?: ?int, plan_id?: ?string, message?: ?string, ip?: ?string}  $data
     */
    public function submitRequest(array $data): TenantRequest
    {
        $request = TenantRequest::create($data);

        Mail::to($request->email)->send(new TenantRequestReceivedMail($request));
        $admins = User::where('is_platform_admin', true)->where('is_active', true)->pluck('email')->all();
        if ($admins !== []) {
            Mail::to($admins)->send(new NewTenantRequestMail($request));
        }

        return $request;
    }

    /** Approves a quote request and emails the one-time registration link. */
    public function approve(TenantRequest $request, int $reviewerId): TenantInvitation
    {
        if (! in_array($request->status, ['PENDING', 'APPROVED'], true)) {
            throw new InvalidTenantRequestStateException("This request is {$request->status} and cannot be approved.");
        }

        return DB::transaction(function () use ($request, $reviewerId) {
            $request->forceFill(['status' => 'APPROVED', 'reviewed_by' => $reviewerId, 'reviewed_at' => now()])->save();
            $invitation = $this->issue('REGISTER', $request->email, ['tenant_request_id' => $request->id], $reviewerId);
            AuditLog::record('TENANT_REQUEST_APPROVED', 'tenant_request', $request->id, ['reference' => $request->institution_name, 'user_id' => $reviewerId]);

            return $invitation;
        });
    }

    public function reject(TenantRequest $request, int $reviewerId, string $reason): TenantRequest
    {
        if ($request->status !== 'PENDING') {
            throw new InvalidTenantRequestStateException("This request is {$request->status} and cannot be rejected.");
        }

        $request->forceFill(['status' => 'REJECTED', 'reviewed_by' => $reviewerId, 'reviewed_at' => now(), 'rejection_reason' => $reason])->save();
        AuditLog::record('TENANT_REQUEST_REJECTED', 'tenant_request', $request->id, ['reference' => $request->institution_name, 'reason' => $reason, 'user_id' => $reviewerId]);
        Mail::to($request->email)->send(new TenantRequestRejectedMail($request));

        return $request;
    }

    /**
     * The platform creates the institution directly; its admin receives an
     * ACTIVATE link to choose a password.
     *
     * @param  array<string, mixed>  $data  TenantProvisioner::provision() input, minus the password
     * @return array{organisation: Organisation, user: User, invitation: TenantInvitation}
     */
    public function createTenant(array $data, int $creatorId): array
    {
        return DB::transaction(function () use ($data, $creatorId) {
            $provisioned = $this->provisioner->provision(['admin_password' => null, 'created_by' => $creatorId] + $data);
            $invitation = $this->issue('ACTIVATE', $provisioned['user']->email, [
                'organisation_id' => $provisioned['organisation']->id,
                'user_id' => $provisioned['user']->id,
            ], $creatorId);

            return ['organisation' => $provisioned['organisation'], 'user' => $provisioned['user'], 'invitation' => $invitation];
        });
    }

    /** Sends a fresh link, revoking any still-open earlier one for the same purpose. */
    public function resend(TenantInvitation $previous, int $senderId): TenantInvitation
    {
        if ($previous->used_at) {
            throw new InvitationUnusableException('That invitation has already been used.');
        }
        $previous->forceFill(['revoked_at' => now()])->save();

        return $this->issue($previous->purpose, $previous->email, array_filter([
            'tenant_request_id' => $previous->tenant_request_id,
            'organisation_id' => $previous->organisation_id,
            'user_id' => $previous->user_id,
        ]), $senderId);
    }

    /**
     * Opening the link spends it: the token can never be opened again. What
     * comes back is a short-lived form session the page submits with.
     *
     * @return array{invitation: TenantInvitation, session_token: string}
     */
    public function open(string $token): array
    {
        return DB::transaction(function () use ($token) {
            $invitation = TenantInvitation::where('token_hash', TenantInvitation::hash($token))->lockForUpdate()->first();
            if (! $invitation || $invitation->revoked_at || $invitation->used_at || $invitation->opened_at) {
                throw new InvitationUnusableException('This link has already been opened or is no longer valid. Ask for a new one.');
            }
            if ($invitation->expires_at->isPast()) {
                throw new InvitationUnusableException('This link has expired. Ask for a new one.');
            }

            $session = Str::random(48);
            $invitation->forceFill([
                'opened_at' => now(),
                'session_hash' => TenantInvitation::hash($session),
                'session_expires_at' => now()->addMinutes(TenantInvitation::SESSION_MINUTES),
            ])->save();

            return ['invitation' => $invitation->load('tenantRequest'), 'session_token' => $session];
        });
    }

    /**
     * Completes a REGISTER invitation: the institution is created on a
     * seven-day trial with the prospect as its owner.
     *
     * @param  array{institution_name: string, legal_name?: ?string, kra_pin?: ?string, contact_phone?: ?string, branch_name?: ?string, county?: ?string, admin_name: string, admin_password: string}  $data
     * @return array{organisation: Organisation, user: User}
     */
    public function register(string $sessionToken, array $data): array
    {
        return DB::transaction(function () use ($sessionToken, $data) {
            $invitation = $this->liveSession($sessionToken, 'REGISTER');
            $provisioned = $this->provisioner->provision([
                'contact_email' => $invitation->email,
                'admin_email' => $invitation->email,
                'created_by' => $invitation->created_by,
            ] + $data);

            $invitation->forceFill(['used_at' => now(), 'organisation_id' => $provisioned['organisation']->id, 'user_id' => $provisioned['user']->id])->save();
            $invitation->tenantRequest?->forceFill(['status' => 'REGISTERED', 'organisation_id' => $provisioned['organisation']->id])->save();

            return ['organisation' => $provisioned['organisation'], 'user' => $provisioned['user']];
        });
    }

    /** Completes an ACTIVATE invitation: the admin chooses a password and can sign in. */
    public function activate(string $sessionToken, string $password): User
    {
        return DB::transaction(function () use ($sessionToken, $password) {
            $invitation = $this->liveSession($sessionToken, 'ACTIVATE');
            $user = User::findOrFail($invitation->user_id);
            $user->forceFill(['password' => $password, 'email_verified_at' => now(), 'is_active' => true])->save();
            $invitation->forceFill(['used_at' => now()])->save();
            AuditLog::record('TENANT_ADMIN_ACTIVATED', 'user', (string) $user->id, ['reference' => $user->email, 'user_id' => $user->id]);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $links
     */
    private function issue(string $purpose, string $email, array $links, ?int $creatorId): TenantInvitation
    {
        $token = Str::random(48);
        $invitation = TenantInvitation::create([
            'purpose' => $purpose,
            'email' => Str::lower($email),
            'token_hash' => TenantInvitation::hash($token),
            'expires_at' => now()->addDays(TenantInvitation::LINK_DAYS),
            'created_by' => $creatorId,
        ] + $links);

        $path = $purpose === 'REGISTER' ? '/register' : '/activate';
        Mail::to($invitation->email)->send(new TenantInvitationMail($invitation, url($path).'?token='.$token));

        return $invitation;
    }

    private function liveSession(string $sessionToken, string $purpose): TenantInvitation
    {
        $invitation = TenantInvitation::where('session_hash', TenantInvitation::hash($sessionToken))->lockForUpdate()->first();
        if (! $invitation || $invitation->purpose !== $purpose || $invitation->used_at || $invitation->revoked_at) {
            throw new InvitationUnusableException('This form is no longer valid. Ask for a new link.');
        }
        if (! $invitation->session_expires_at || $invitation->session_expires_at->isPast()) {
            throw new InvitationUnusableException('This form was open too long and has expired. Ask for a new link.');
        }

        return $invitation;
    }
}
