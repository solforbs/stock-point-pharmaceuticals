<?php

namespace Database\Seeders;

use App\Console\Commands\CreateAdminUser;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * [NEW-RECOMMENDATION] The blueprint (Part 18.3) names these ten roles and
     * the permission catalogue, plus the separation-of-duties principle, but
     * leaves the exact role -> permission matrix as a business decision for
     * the owner. This mapping is a reasonable starting point, not a source
     * fact — review and adjust per Part 32's "discount authority matrix" step.
     */
    public const ROLE_PERMISSIONS = [
        'Director' => [
            'sale.view', 'product.view', 'stock.view', 'supplier.view', 'finance.ar.view', 'finance.ap.view',
            'report.view', 'report.financial.view', 'admin.settings', 'sale.discount.approve',
            'stock.adjust.approve', 'po.approve', 'period.close', 'recall.initiate',
            'waste.approve', 'customer.credit.override', 'payroll.view', 'audit.view',
            'licence.view', 'licence.manage', 'document.manage',
            // A small business's owner prepares and approves payroll alone (decision 2026-09-18).
            'payroll.process', 'payroll.approve.own',
            'payment.reconcile',
            'leave.request', 'leave.approve', 'report.schedule', 'price.manage', 'price.simulate',
            // Supplier quotes: the director awards; buyers prepare (client item 19).
            'rfq.view', 'rfq.award',
        ],
        'Operations Manager' => [
            'sale.view', 'product.view', 'product.edit', 'customer.manage', 'supplier.view', 'supplier.manage',
            'warehouse.pick', 'warehouse.dispatch', 'finance.ar.view',
            'location.manage',
            'sale.create', 'sale.void', 'sale.discount.approve', 'sale.mode.switch',
            'stock.view', 'stock.adjust', 'stock.adjust.approve', 'stock.count.post',
            'stock.transfer.create', 'stock.transfer.approve', 'stock.transfer.dispatch', 'stock.transfer.receive',
            'stock.count.enter', 'stock.fefo.override', 'requisition.view', 'requisition.create', 'requisition.approve', 'po.create', 'po.approve',
            'grn.create', 'quality.release', 'recall.initiate', 'waste.approve', 'return.create', 'return.post', 'supplier.return',
            'coldchain.record', 'coldchain.review', 'adr.report', 'adr.manage', 'licence.view', 'licence.manage', 'document.manage',
            'report.view', 'report.financial.view',
            'leave.approve', 'report.schedule', 'price.manage', 'price.simulate',
            'rfq.view', 'rfq.manage', 'rfq.award',
        ],
        'Pharmacist' => [
            'sale.view', 'product.view', 'requisition.view', 'requisition.create', 'sale.create', 'stock.view', 'stock.fefo.override', 'quality.release',
            'grn.qc.release', 'recall.initiate', 'waste.approve', 'return.post', 'stock.adjust',
            'coldchain.record', 'coldchain.review', 'adr.report', 'adr.manage', 'licence.view',
            'leave.request',
        ],
        'Senior Cashier' => [
            'sale.view', 'product.view', 'payment.record', 'return.create', 'sale.create', 'sale.void', 'sale.discount.apply', 'sale.mode.switch', 'stock.view',
            'adr.report',
            'leave.request', 'price.simulate',
        ],
        'Cashier' => [
            'sale.view', 'product.view', 'sale.create', 'sale.discount.apply', 'stock.view',
            'leave.request',
        ],
        'Storekeeper' => [
            'product.view', 'warehouse.pick', 'warehouse.dispatch', 'stock.view', 'stock.adjust', 'stock.count.post', 'stock.count.enter',
            'stock.transfer.create', 'stock.transfer.dispatch', 'stock.transfer.receive',
            'requisition.view', 'requisition.create', 'grn.create', 'product.create', 'return.create', 'supplier.return',
            'coldchain.record',
            'location.manage',
            'leave.request',
        ],
        'Procurement Officer' => [
            'product.view', 'supplier.view', 'supplier.manage', 'stock.view', 'requisition.view', 'requisition.approve', 'supplier.return', 'report.view', 'po.create', 'grn.create', 'invoice.match', 'product.create',
            'rfq.view', 'rfq.manage',
        ],
        'Finance Officer' => [
            'sale.view', 'product.view', 'supplier.view', 'finance.ar.view', 'finance.ap.view', 'customer.credit.override', 'report.view',
            'payment.record', 'journal.post', 'journal.reverse', 'invoice.match', 'tax.etims.manage', 'payroll.view', 'payroll.process',
            'report.financial.view', 'product.cost.view',
            'payment.reconcile',
            'leave.request', 'leave.approve', 'report.schedule',
            'rfq.view',
        ],
        'Auditor' => [
            'sale.view', 'product.view', 'supplier.view', 'finance.ar.view', 'finance.ap.view', 'audit.view', 'report.view', 'report.financial.view', 'stock.view', 'product.cost.view', 'payroll.view',
            'licence.view', 'rfq.view',
            'leave.request',
        ],
        'System Administrator' => [
            'admin.users', 'admin.settings', 'audit.view', 'record.delete',
            'licence.view', 'licence.manage', 'document.manage',
        ],
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

        // A database seeded before a permission was added lacks it; make sure
        // every permission a standard role names exists before assigning it.
        foreach (array_unique(array_merge(...array_values(self::ROLE_PERMISSIONS))) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(TenantContext::class)->run($organisationId, function () use ($organisationId) {
            foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
                Role::firstOrCreate(['organisation_id' => $organisationId, 'name' => $roleName, 'guard_name' => 'web', 'branch_id' => null])
                    ->syncPermissions($permissions);
            }

            // The institution's owner role. It carries every permission there
            // is, including ones added by later releases; the platform-level
            // operations (backups, deployment) additionally need a platform
            // administrator, so owning an institution never reaches them.
            Role::firstOrCreate(['organisation_id' => $organisationId, 'name' => CreateAdminUser::SUPER_ADMINISTRATOR, 'guard_name' => 'web', 'branch_id' => null])
                ->syncPermissions(Permission::where('guard_name', 'web')->get());
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
