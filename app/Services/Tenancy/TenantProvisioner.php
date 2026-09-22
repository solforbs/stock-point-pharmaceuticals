<?php

namespace App\Services\Tenancy;

use App\Console\Commands\CreateAdminUser;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Organisation;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\DiscountAuthoritySeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxCodeSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a working institution in one step: the organisation with its
 * trial, the chart of accounts, VAT codes and standard roles, a first
 * branch and selling store, and the owner's account holding the
 * institution's Super Administrator role. Nothing is half-created: it all
 * happens in one transaction.
 */
class TenantProvisioner
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * @param  array{
     *     institution_name: string, legal_name?: ?string, kra_pin?: ?string, contact_email: string, contact_phone?: ?string,
     *     branch_name?: ?string, branch_code?: ?string, county?: ?string,
     *     admin_name: string, admin_email: string, admin_password?: ?string,
     *     trial?: bool, complimentary?: bool, created_by?: ?int,
     * }  $data  admin_password null leaves the account without a usable password until it is activated
     * @return array{organisation: Organisation, branch: Branch, store: Store, user: User}
     */
    public function provision(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $organisation = Organisation::create([
                'name' => $data['institution_name'],
                'legal_name' => $data['legal_name'] ?? $data['institution_name'],
                'kra_pin' => $data['kra_pin'] ?? null,
                'base_currency' => 'KES',
                'fiscal_year_start' => 1,
                'contact_email' => $data['contact_email'],
                'contact_phone' => $data['contact_phone'] ?? null,
            ]);
            $complimentary = (bool) ($data['complimentary'] ?? false);
            $trial = ! $complimentary && ($data['trial'] ?? true);
            $organisation->forceFill([
                'is_complimentary' => $complimentary,
                'trial_ends_at' => $trial ? now()->addDays(Organisation::TRIAL_DAYS) : null,
                'created_by' => $data['created_by'] ?? null,
            ])->save();
            $organisation->refreshSubscriptionStatus();

            ChartOfAccountsSeeder::provision($organisation->id);
            TaxCodeSeeder::provision($organisation->id);
            RoleSeeder::provision($organisation->id);
            DiscountAuthoritySeeder::provision($organisation->id);

            [$branch, $store, $user] = $this->tenant->run($organisation->id, function () use ($organisation, $data) {
                $branch = Branch::create([
                    'organisation_id' => $organisation->id,
                    'code' => Str::upper($data['branch_code'] ?? 'MAIN'),
                    'name' => $data['branch_name'] ?? 'Main branch',
                    'county' => $data['county'] ?? null,
                    'is_active' => true,
                    'retail_enabled' => true,
                    'wholesale_enabled' => true,
                ]);
                $store = Store::create([
                    'branch_id' => $branch->id, 'code' => 'MAIN', 'name' => 'Main store', 'store_type' => 'MAIN', 'is_sellable' => true,
                ]);

                $user = User::create([
                    'name' => $data['admin_name'],
                    'username' => $this->freeUsername($data['admin_email']),
                    'email' => Str::lower($data['admin_email']),
                    'password' => $data['admin_password'] ?? Str::random(64),
                ]);
                $user->forceFill([
                    'organisation_id' => $organisation->id,
                    'is_active' => true,
                    'email_verified_at' => isset($data['admin_password']) ? now() : null,
                ])->save();

                $registrar = app(PermissionRegistrar::class);
                $registrar->setPermissionsTeamId($branch->id);
                $user->assignRole(Role::where('name', CreateAdminUser::SUPER_ADMINISTRATOR)->firstOrFail());
                $registrar->setPermissionsTeamId(null);
                $registrar->forgetCachedPermissions();

                AuditLog::record('TENANT_PROVISIONED', 'organisation', $organisation->id, [
                    'user_id' => $data['created_by'] ?? $user->id,
                    'branch_id' => $branch->id,
                    'reference' => $organisation->name,
                    'after_json' => ['owner' => $user->email, 'trial_ends_at' => $organisation->trial_ends_at?->toIso8601String(), 'complimentary' => $organisation->is_complimentary],
                ]);

                return [$branch, $store, $user];
            });

            return ['organisation' => $organisation->fresh(), 'branch' => $branch, 'store' => $store, 'user' => $user];
        });
    }

    /** Usernames are platform-wide; the email's local part, numbered if taken. */
    private function freeUsername(string $email): string
    {
        $base = Str::limit(Str::slug(Str::before($email, '@'), '.'), 40, '') ?: 'admin';
        $candidate = $base;
        for ($n = 2; User::where('username', $candidate)->exists(); $n++) {
            $candidate = "{$base}{$n}";
        }

        return $candidate;
    }
}
