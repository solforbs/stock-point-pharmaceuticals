<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
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
        ],
        'Operations Manager' => [
            'sale.view', 'product.view', 'product.edit', 'customer.manage', 'supplier.view', 'supplier.manage',
            'warehouse.pick', 'warehouse.dispatch', 'finance.ar.view',
            'sale.create', 'sale.void', 'sale.discount.approve', 'sale.mode.switch',
            'stock.view', 'stock.adjust', 'stock.adjust.approve', 'stock.count.post',
            'stock.transfer.create', 'stock.transfer.approve', 'stock.transfer.dispatch', 'stock.transfer.receive',
            'stock.count.enter', 'stock.fefo.override', 'requisition.view', 'requisition.create', 'requisition.approve', 'po.create', 'po.approve',
            'grn.create', 'quality.release', 'recall.initiate', 'waste.approve', 'return.create', 'return.post', 'supplier.return',
            'report.view', 'report.financial.view',
        ],
        'Pharmacist' => [
            'sale.view', 'product.view', 'requisition.view', 'requisition.create', 'sale.create', 'stock.view', 'stock.fefo.override', 'quality.release',
            'grn.qc.release', 'recall.initiate', 'waste.approve', 'return.post', 'stock.adjust',
        ],
        'Senior Cashier' => [
            'sale.view', 'product.view', 'payment.record', 'return.create', 'sale.create', 'sale.void', 'sale.discount.apply', 'sale.mode.switch', 'stock.view',
        ],
        'Cashier' => [
            'sale.view', 'product.view', 'sale.create', 'sale.discount.apply', 'stock.view',
        ],
        'Storekeeper' => [
            'product.view', 'warehouse.pick', 'warehouse.dispatch', 'stock.view', 'stock.adjust', 'stock.count.post', 'stock.count.enter',
            'stock.transfer.create', 'stock.transfer.dispatch', 'stock.transfer.receive',
            'requisition.view', 'requisition.create', 'grn.create', 'product.create', 'return.create', 'supplier.return',
        ],
        'Procurement Officer' => [
            'product.view', 'supplier.view', 'supplier.manage', 'stock.view', 'requisition.view', 'requisition.approve', 'supplier.return', 'report.view', 'po.create', 'grn.create', 'invoice.match', 'product.create',
        ],
        'Finance Officer' => [
            'sale.view', 'product.view', 'supplier.view', 'finance.ar.view', 'finance.ap.view', 'customer.credit.override', 'report.view',
            'payment.record', 'journal.post', 'journal.reverse', 'invoice.match', 'tax.etims.manage', 'payroll.view', 'payroll.process',
            'report.financial.view', 'product.cost.view',
        ],
        'Auditor' => [
            'sale.view', 'product.view', 'supplier.view', 'finance.ar.view', 'finance.ap.view', 'audit.view', 'report.view', 'report.financial.view', 'stock.view', 'product.cost.view', 'payroll.view',
        ],
        'System Administrator' => [
            'admin.users', 'admin.settings', 'audit.view',
        ],
    ];

    public function run(): void
    {
        // Role *definitions* are global (branch_id null) — only the later
        // assignment of a role to a user needs a concrete branch.
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web', 'branch_id' => null]
            );
            $role->syncPermissions($permissions);
        }
    }
}
