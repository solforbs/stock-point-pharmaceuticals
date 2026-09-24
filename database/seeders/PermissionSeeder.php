<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    // The exact fine-grained permission catalogue from the blueprint, Part 18.3.
    // Any of these grants access to the Pharmacy module (see ModuleCatalogue).
    public const PHARMACY_PERMISSIONS = [
        'sale.view', 'sale.create', 'sale.void', 'sale.discount.apply', 'sale.discount.approve',
        'sale.mode.switch', 'sale.price.override',
        'stock.view', 'stock.adjust', 'stock.adjust.approve', 'stock.count.post',
        'stock.transfer.create', 'stock.transfer.approve', 'stock.transfer.approve.own', 'stock.transfer.dispatch', 'stock.transfer.receive',
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
        'rfq.view', 'rfq.manage', 'rfq.award',
        'return.create', 'return.post', 'supplier.return',
        'payment.record',
        'journal.post', 'journal.reverse', 'period.close', 'tax.etims.manage',
        'quality.release', 'recall.initiate', 'waste.approve',
        'coldchain.record', 'coldchain.review', 'adr.report', 'adr.manage', 'licence.view', 'licence.manage', 'document.manage',
        'payroll.view', 'payroll.process', 'payroll.approve.own',
        'leave.request', 'leave.approve', 'report.schedule', 'training.manage',
        'admin.users', 'admin.settings', 'record.delete', 'deploy.run',
        'audit.view', 'report.view', 'report.financial.view', 'report.review',
        // Prescription dispensing is pharmacy work: the pharmacist sees and
        // fills prescriptions sent over from the hospital module.
        'prescription.view', 'prescription.dispense',
    ];

    /**
     * Hospital module catalogue. `laboratory.order.create` / `.view` sit here,
     * not in the laboratory list: ordering tests and reading results is
     * clinician work done from inside an encounter, and holding only those
     * two must not open the Laboratory module itself (a clinician sees the
     * Hospital module alone — spec examples in Part 1/20 of the brief).
     */
    public const HOSPITAL_PERMISSIONS = [
        'hospital.view', 'hospital.manage',
        'hospital.patient.view', 'hospital.patient.manage',
        'hospital.encounter.manage',
        'hospital.consultation.manage',
        'hospital.diagnosis.manage',
        'hospital.referral.manage',
        'hospital.prescription.create',
        'laboratory.order.create', 'laboratory.order.view',
    ];

    // Laboratory module catalogue: the bench-side workflow.
    public const LABORATORY_PERMISSIONS = [
        'laboratory.view', 'laboratory.manage',
        'laboratory.category.manage', 'laboratory.test.manage',
        'laboratory.order.accept', 'laboratory.order.cancel',
        'laboratory.sample.collect',
        'laboratory.result.enter', 'laboratory.result.approve',
        'laboratory.report.view',
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_values(array_unique(array_merge(
            self::PHARMACY_PERMISSIONS,
            self::HOSPITAL_PERMISSIONS,
            self::LABORATORY_PERMISSIONS,
        )));
    }

    public function run(): void
    {
        foreach (self::all() as $name) {
            // Plain Eloquent firstOrCreate rather than Spatie's findOrCreate()
            // helper — the latter reads through Spatie's in-memory permission
            // cache, which can be primed empty before this seeder runs and
            // then goes stale for the rest of the process.
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
