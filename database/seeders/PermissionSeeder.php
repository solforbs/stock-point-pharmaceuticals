<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    // The exact fine-grained permission catalogue from the blueprint, Part 18.3.
    public const PERMISSIONS = [
        'sale.view', 'sale.create', 'sale.void', 'sale.discount.apply', 'sale.discount.approve',
        'sale.mode.switch', 'sale.price.override',
        'stock.view', 'stock.adjust', 'stock.adjust.approve', 'stock.count.post',
        'stock.transfer.create', 'stock.transfer.approve', 'stock.transfer.dispatch', 'stock.transfer.receive',
        'stock.count.enter', 'stock.fefo.override',
        'product.view', 'product.create', 'product.edit', 'product.cost.view',
        'customer.manage', 'supplier.view', 'supplier.manage',
        'warehouse.pick', 'warehouse.dispatch',
        'location.manage', 'payment.reconcile',
        'finance.ar.view', 'finance.ap.view',
        'price.manage', 'price.simulate',
        'customer.credit.override',
        'requisition.view', 'requisition.create', 'requisition.approve',
        'po.create', 'po.approve', 'grn.create', 'grn.qc.release', 'invoice.match',
        'return.create', 'return.post', 'supplier.return',
        'payment.record',
        'journal.post', 'journal.reverse', 'period.close', 'tax.etims.manage',
        'quality.release', 'recall.initiate', 'waste.approve',
        'coldchain.record', 'coldchain.review', 'adr.report', 'adr.manage', 'licence.view', 'licence.manage', 'document.manage',
        'payroll.view', 'payroll.process', 'payroll.approve.own',
        'leave.request', 'leave.approve', 'report.schedule',
        'admin.users', 'admin.settings',
        'audit.view', 'report.view', 'report.financial.view',
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name) {
            // Plain Eloquent firstOrCreate rather than Spatie's findOrCreate()
            // helper — the latter reads through Spatie's in-memory permission
            // cache, which can be primed empty before this seeder runs and
            // then goes stale for the rest of the process.
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
