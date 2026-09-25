<?php

namespace Database\Seeders;

use App\Console\Commands\CreateAdminUser;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Governance decision (client, 2026-09-24): a deliberately small,
     * two-level role model to keep the system healthy.
     *
     * PLATFORM level — "us", the system owners: the platform administrator
     * account (users.is_platform_admin) approves/suspends institutions,
     * manages plans and billing, and holds NO operational role inside any
     * institution.
     *
     * INSTITUTION level — exactly these standard roles:
     *   Director   — owns the institution: every institution permission,
     *                all three modules, users & roles, approvals.
     *   Clinician  — the hospital: patients, reception work, consultations,
     *                diagnoses, referrals, ordering tests, prescribing.
     *   Laboratory Technician — the bench: the full order workflow through
     *                to signed-off results.
     *   Pharmacist — runs the pharmacy day to day: selling and dispensing,
     *                inventory, procurement and the pre-existing workflow.
     *                Approvals (PO, adjustments, transfers, discounts,
     *                supplier awards) stay with the Director.
     *   Patient    — reserved for the future patient portal; carries no
     *                permissions yet, so such an account opens no module.
     *
     * A custom role is still possible in Users & Roles, but the standard
     * provisioning creates nothing beyond this list.
     */
    public const ROLE_PERMISSIONS = [
        'Clinician' => [
            'hospital.view', 'hospital.patient.view', 'hospital.patient.manage',
            'hospital.encounter.manage', 'hospital.consultation.manage',
            'hospital.diagnosis.manage', 'hospital.referral.manage',
            'hospital.prescription.create',
            'laboratory.order.create', 'laboratory.order.view',
        ],
        'Laboratory Technician' => [
            'laboratory.view',
            'laboratory.order.accept', 'laboratory.order.cancel',
            'laboratory.sample.collect',
            'laboratory.result.enter', 'laboratory.result.approve',
            'laboratory.report.view',
        ],
        'Pharmacist' => [
            // Selling and dispensing
            'sale.view', 'sale.create', 'sale.void', 'sale.discount.apply', 'sale.mode.switch', 'sale.price.override',
            'prescription.view', 'prescription.dispense',
            'payment.record',
            // Inventory
            'stock.view', 'stock.adjust', 'stock.count.enter', 'stock.count.post',
            'stock.transfer.create', 'stock.transfer.dispatch', 'stock.transfer.receive',
            'stock.fefo.override',
            'product.view', 'product.create', 'product.edit',
            'location.manage', 'warehouse.pick', 'warehouse.dispatch',
            // Procurement
            'requisition.view', 'requisition.create', 'po.create', 'grn.create', 'grn.qc.release',
            'invoice.match', 'supplier.view', 'supplier.manage', 'rfq.view', 'rfq.manage',
            // Returns
            'return.create', 'return.post', 'supplier.return',
            // Quality and compliance, as before
            'quality.release', 'recall.initiate', 'waste.approve',
            'coldchain.record', 'coldchain.review', 'adr.report', 'adr.manage', 'licence.view',
            // Everyday extras
            'report.view', 'price.simulate', 'leave.request',
        ],
        'Patient' => [],
        // Part 18.3 — the bootstrap/IT role `user:create-admin` grants:
        // users, settings and the audit log, no clinical or financial
        // posting rights. It sat in RETIRED_ROLES for a while, which broke
        // the console command that every fresh install starts from.
        'System Administrator' => [
            'admin.users', 'admin.settings', 'audit.view', 'record.delete',
            'licence.view', 'licence.manage', 'document.manage',
            'training.manage',
        ],
    ];

    /**
     * Roles the platform no longer provisions. A retired role that nobody
     * holds is deleted; one still assigned is left alone (reassign its
     * people in Users & Roles, then reseed to complete the clean-up).
     */
    public const RETIRED_ROLES = [
        'Super Administrator',
        'Operations Manager', 'Senior Cashier', 'Cashier', 'Storekeeper', 'Wholesale Rep',
        'Procurement Officer', 'Finance Officer', 'Auditor',
        'Hospital Administrator', 'Receptionist', 'Nurse',
        'Laboratory Doctor', 'Laboratory Manager',
    ];

    /** Every institution gets its own copy of the standard roles. */
    public function run(): void
    {
        foreach (Organisation::pluck('id') as $organisationId) {
            self::provision((string) $organisationId);
        }
    }

    /**
     * Creates (or re-syncs) one institution's standard roles. Role
     * *definitions* carry no branch — only the later assignment of a role to
     * a user needs a concrete branch.
     */
    public static function provision(string $organisationId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        // A database seeded before a permission was added lacks it; heal the
        // whole catalogue, because the Director role carries every
        // permission — not only the ones the smaller roles name.
        foreach (PermissionSeeder::all() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(TenantContext::class)->run($organisationId, function () use ($organisationId) {
            foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
                Role::firstOrCreate(['organisation_id' => $organisationId, 'name' => $roleName, 'guard_name' => 'web', 'branch_id' => null])
                    ->syncPermissions($permissions);
            }

            // The institution's owner role — the Director. It carries every
            // permission there is, including ones added by later releases.
            // Platform-level operations (approving institutions, billing,
            // backups, deployment) need a platform administrator account and
            // never come from owning an institution.
            Role::firstOrCreate(['organisation_id' => $organisationId, 'name' => CreateAdminUser::SUPER_ADMINISTRATOR, 'guard_name' => 'web', 'branch_id' => null])
                ->syncPermissions(Permission::where('guard_name', 'web')->get());

            // Retire the old role zoo: delete retired roles nobody holds.
            foreach (self::RETIRED_ROLES as $retired) {
                $role = Role::where('organisation_id', $organisationId)->where('name', $retired)->first();
                if ($role && ! DB::table(config('permission.table_names.model_has_roles'))->where('role_id', $role->id)->exists()) {
                    $role->delete();
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
