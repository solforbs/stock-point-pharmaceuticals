<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdrReportController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ColdChainController;
use App\Http\Controllers\Api\CompanyProfileController;
use App\Http\Controllers\Api\ControlledDocumentController;
use App\Http\Controllers\Api\CustomerContactController;
use App\Http\Controllers\Api\CustomerStatementController;
use App\Http\Controllers\Api\DailyBriefingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeploymentController;
use App\Http\Controllers\Api\DocumentListController;
use App\Http\Controllers\Api\DocumentPdfController;
use App\Http\Controllers\Api\EtimsController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\LicenceController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\OfflineSaleController;
use App\Http\Controllers\Api\OperationsController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrganisationController;
use App\Http\Controllers\Api\PackingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayrollBandController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\PricingRuleController;
use App\Http\Controllers\Api\ProcurementController;
use App\Http\Controllers\Api\ProductCatalogueController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PublicOnboardingController;
use App\Http\Controllers\Api\QualityController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\RecordDeletionController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RequisitionController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\ScheduledReportController;
use App\Http\Controllers\Api\ScheduledReportRunController;
use App\Http\Controllers\Api\StockCountController;
use App\Http\Controllers\Api\StockTransferController;
use App\Http\Controllers\Api\TrainingController;
use App\Models\Branch;
use App\Services\Sales\SaleModes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'branch.context', 'tenant.access'])->get('/user', function (Request $request) {
    $user = $request->user()->load('roles');
    $activeBranchId = $request->attributes->get('active_branch_id');

    // Every branch the user holds a role in, so the SPA can offer a branch
    // switch (X-Branch-Id) and knows which sale modes each branch trades in.
    $branchIds = DB::table(config('permission.table_names.model_has_roles'))
        ->where('model_id', $user->getKey())->where('model_type', $user::class)
        ->whereNotNull('branch_id')->distinct()->pluck('branch_id');
    $branches = Branch::whereIn('id', $branchIds)->orderBy('code')->get()
        ->map(fn (Branch $b) => $b->only(['id', 'code', 'name', 'retail_enabled', 'wholesale_enabled', 'dispensing_enabled']) + [
            'sale_modes' => SaleModes::enabledFor($b),
            'default_sale_mode' => SaleModes::defaultFor($b),
        ]);
    $active = $branches->firstWhere('id', $activeBranchId);

    return $user->toArray() + [
        // Effective permissions (via roles, in the current branch context) —
        // not $user->permissions, which is direct grants only and is
        // normally empty since every permission here comes through a role.
        'permissions' => $user->getAllPermissions()->pluck('name'),
        'organisation' => $user->organisation?->only(['id', 'name', 'legal_name']),
        // Drives the trial / lapsed banner and read-only mode in the web app.
        'subscription' => $user->organisation ? (function ($organisation) {
            $current = $organisation->currentSubscription();

            return [
                'access_state' => $organisation->accessState(),
                'trial_ends_at' => $organisation->trial_ends_at?->toIso8601String(),
                'plan' => $current?->plan?->only(['id', 'code', 'name']),
                'current_period_end' => $current?->current_period_end?->toIso8601String(),
                'is_complimentary' => $organisation->is_complimentary,
            ];
        })($user->organisation) : null,
        'active_branch_id' => $activeBranchId,
        'active_branch' => $active,
        'branches' => $branches->values(),
        'sale_modes' => $active['sale_modes'] ?? [],
        'default_sale_mode' => $active['default_sale_mode'] ?? null,
    ];
});

// Part 21 — the API catalogue. Every route is authenticated, branch-scoped
// (Part 18.2) and permission-checked inside its controller.
Route::middleware(['auth:sanctum', 'branch.context', 'tenant.access'])->group(function () {
    // 21.3 Products and master data
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{product}', [ProductController::class, 'show'])->whereUuid('product');
    Route::patch('/products/{product}', [ProductController::class, 'update']);
    Route::get('/products/{product}/stock', [ProductController::class, 'stock']);
    Route::get('/products/{product}/insight', [ProductController::class, 'insight']);
    Route::get('/products/{product}/selling-prices', [ProductController::class, 'sellingPrices']);
    Route::get('/uoms', [MasterDataController::class, 'uoms']);
    Route::get('/tax-codes', [MasterDataController::class, 'taxCodes']);
    Route::get('/dosage-forms', [MasterDataController::class, 'dosageForms']);
    Route::get('/storage-conditions', [MasterDataController::class, 'storageConditions']);
    Route::get('/stores', [MasterDataController::class, 'stores']);
    Route::get('/customers', [MasterDataController::class, 'customers']);
    Route::post('/customers', [MasterDataController::class, 'storeCustomer']);
    Route::get('/customers/{customer}', [MasterDataController::class, 'customer']);
    Route::patch('/customers/{customer}', [MasterDataController::class, 'updateCustomer'])->whereUuid('customer');
    Route::patch('/customers/{customer}/credit', [MasterDataController::class, 'updateCredit']);
    Route::get('/customer-tiers', [MasterDataController::class, 'tiers']);
    Route::post('/customer-tiers', [PriceListController::class, 'storeTier']);
    Route::get('/price-lists', [PriceListController::class, 'index']);
    Route::post('/price-lists', [PriceListController::class, 'store']);
    Route::patch('/price-lists/{list}', [PriceListController::class, 'update']);
    Route::get('/price-lists/{list}/items', [PriceListController::class, 'items']);
    Route::post('/price-lists/{list}/items', [PriceListController::class, 'storeItem']);

    // 21.4 Pricing
    Route::post('/pricing/quote', [PricingController::class, 'quote']);
    Route::post('/pricing/simulate', [PricingController::class, 'simulate']);

    // 21.6 POS and sales
    Route::get('/sales', [SaleController::class, 'index']);
    Route::post('/sales/checkout', [SaleController::class, 'checkout']);

    // Part 16.7 / 17.5 — selling through an outage: price pack, outbox replay, conflicts.
    Route::get('/pos/offline-pack', [OfflineSaleController::class, 'pack']);
    Route::get('/pos/offline-sales', [OfflineSaleController::class, 'index']);
    Route::post('/pos/offline-sales', [OfflineSaleController::class, 'store']);
    Route::post('/pos/offline-sales/{offlineSale}/retry', [OfflineSaleController::class, 'retry']);
    Route::post('/pos/offline-sales/{offlineSale}/dismiss', [OfflineSaleController::class, 'dismiss']);
    Route::get('/sales/{sale}', [SaleController::class, 'show']);
    Route::post('/sales/{sale}/void', [SaleController::class, 'void']);

    // 21.7 Wholesale orders
    Route::post('/quotations', [OrderController::class, 'storeQuotation']);
    Route::get('/quotations/{quotation}', [OrderController::class, 'quotation']);
    Route::post('/quotations/{quotation}/accept', [OrderController::class, 'acceptQuotation']);
    Route::get('/sales-orders', [OrderController::class, 'salesOrders']);
    Route::post('/sales-orders', [OrderController::class, 'storeSalesOrder']);
    Route::get('/sales-orders/{order}', [OrderController::class, 'salesOrder']);
    Route::post('/sales-orders/{order}/confirm', [OrderController::class, 'confirmSalesOrder']);
    Route::post('/sales-orders/{order}/cancel', [OrderController::class, 'cancelSalesOrder']);
    Route::get('/sales-orders/{order}/updates', [OrderController::class, 'salesOrderUpdates']);

    // Part 16.6 — documents as PDFs, rendered from the posted record.
    Route::get('/sales/{sale}/pdf', [DocumentPdfController::class, 'invoice']);
    Route::get('/delivery-notes/{note}/pdf', [DocumentPdfController::class, 'deliveryNote']);
    Route::get('/quotations/{quotation}/pdf', [DocumentPdfController::class, 'quotation']);
    Route::get('/purchase-orders/{po}/pdf', [DocumentPdfController::class, 'purchaseOrder']);
    Route::get('/goods-receipts/{receipt}/pdf', [DocumentPdfController::class, 'goodsReceipt']);
    Route::get('/customers/{customer}/statement/pdf', [CustomerStatementController::class, 'pdf']);
    Route::post('/sales-orders/{order}/pick', [OrderController::class, 'pick']);
    Route::post('/picking-lists/{list}/lines/{line}/pick', [OrderController::class, 'pickLine']);
    Route::post('/picking-lists/{list}/complete', [OrderController::class, 'completePicking']);
    Route::post('/sales-orders/{order}/dispatch', [OrderController::class, 'dispatch']);
    Route::post('/delivery-notes/{note}/pod', [OrderController::class, 'proofOfDelivery']);

    // 21.8 Inventory
    Route::get('/inventory/stock', [InventoryController::class, 'stock']);
    Route::get('/inventory/ledger', [InventoryController::class, 'ledger']);
    Route::post('/inventory/adjustments', [InventoryController::class, 'storeAdjustment']);
    Route::post('/inventory/opening-stock', [InventoryController::class, 'openingStock']);
    Route::post('/inventory/adjustments/{adjustment}/approve', [InventoryController::class, 'approveAdjustment']);
    Route::post('/inventory/adjustments/{adjustment}/reject', [InventoryController::class, 'rejectAdjustment']);
    Route::get('/batches', [InventoryController::class, 'batches']);
    Route::get('/batches/{batch}', [InventoryController::class, 'batch']);
    Route::post('/batches/{batch}/release', [InventoryController::class, 'releaseBatch']);
    Route::post('/batches/{batch}/quarantine', [InventoryController::class, 'quarantineBatch']);
    Route::get('/inventory/transfers', [StockTransferController::class, 'index']);
    Route::post('/inventory/transfers', [StockTransferController::class, 'store']);
    Route::get('/inventory/transfers/{transfer}', [StockTransferController::class, 'show']);
    Route::post('/inventory/transfers/{transfer}/approve', [StockTransferController::class, 'approve']);
    Route::post('/inventory/transfers/{transfer}/dispatch', [StockTransferController::class, 'dispatch']);
    Route::post('/inventory/transfers/{transfer}/receive', [StockTransferController::class, 'receive']);
    Route::post('/inventory/transfers/{transfer}/resolve', [StockTransferController::class, 'resolve']);
    Route::get('/inventory/counts', [StockCountController::class, 'index']);
    Route::post('/inventory/counts', [StockCountController::class, 'store']);
    Route::get('/inventory/counts/{count}', [StockCountController::class, 'show']);
    Route::post('/inventory/counts/{count}/start', [StockCountController::class, 'start']);
    Route::post('/inventory/counts/{count}/lines/{line}', [StockCountController::class, 'enter']);
    Route::post('/inventory/counts/{count}/review', [StockCountController::class, 'review']);
    Route::post('/inventory/counts/{count}/approve', [StockCountController::class, 'approve']);
    Route::post('/inventory/counts/{count}/close', [StockCountController::class, 'close']);

    // 21.9 Procurement
    Route::get('/procurement/reorder-suggestions', [RequisitionController::class, 'reorderSuggestions']);
    Route::get('/requisitions', [RequisitionController::class, 'index']);
    Route::post('/requisitions', [RequisitionController::class, 'store']);
    Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show']);
    Route::post('/requisitions/{requisition}/submit', [RequisitionController::class, 'submit']);
    Route::post('/requisitions/{requisition}/approve', [RequisitionController::class, 'approve']);
    Route::post('/requisitions/{requisition}/reject', [RequisitionController::class, 'reject']);
    Route::post('/requisitions/{requisition}/convert', [RequisitionController::class, 'convert']);
    Route::get('/suppliers', [ProcurementController::class, 'suppliers']);
    Route::get('/suppliers/{supplier}', [ProcurementController::class, 'supplier'])->whereUuid('supplier');
    Route::post('/suppliers', [ProcurementController::class, 'storeSupplier']);
    Route::patch('/suppliers/{supplier}', [ProcurementController::class, 'updateSupplier']);
    Route::get('/purchase-orders', [ProcurementController::class, 'purchaseOrders']);
    Route::post('/purchase-orders', [ProcurementController::class, 'storePurchaseOrder']);
    Route::post('/purchase-orders/{po}/approve', [ProcurementController::class, 'approvePurchaseOrder']);
    Route::post('/purchase-orders/{po}/send', [ProcurementController::class, 'sendPurchaseOrder']);
    Route::post('/goods-receipts', [ProcurementController::class, 'storeGoodsReceipt']);
    Route::get('/goods-receipts/{receipt}', [ProcurementController::class, 'goodsReceipt']);
    Route::post('/supplier-invoices', [ProcurementController::class, 'storeSupplierInvoice']);
    Route::post('/supplier-invoices/{invoice}/match', [ProcurementController::class, 'matchSupplierInvoice']);
    Route::post('/supplier-payments', [ProcurementController::class, 'storeSupplierPayment']);

    // Part 11 — returns, waste; Part 8.4 — recalls
    Route::get('/customer-returns', [ReturnController::class, 'index']);
    Route::post('/customer-returns', [ReturnController::class, 'store']);
    Route::get('/customer-returns/{return}', [ReturnController::class, 'show']);
    Route::post('/customer-returns/{return}/lines/{line}/disposition', [ReturnController::class, 'disposition']);
    Route::post('/customer-returns/{return}/post', [ReturnController::class, 'post']);
    Route::post('/customer-returns/{return}/reject', [ReturnController::class, 'reject']);
    Route::get('/supplier-returns', [ReturnController::class, 'supplierReturns']);
    Route::post('/supplier-returns', [ReturnController::class, 'storeSupplierReturn']);
    Route::get('/supplier-returns/{return}', [ReturnController::class, 'supplierReturn']);
    Route::get('/waste-disposals', [QualityController::class, 'wasteDisposals']);
    Route::post('/waste-disposals', [QualityController::class, 'storeWasteDisposal']);
    Route::get('/waste-disposals/{disposal}', [QualityController::class, 'wasteDisposal']);
    Route::post('/waste-disposals/{disposal}/post', [QualityController::class, 'postWasteDisposal']);
    Route::get('/recalls', [QualityController::class, 'recalls']);
    Route::post('/recalls', [QualityController::class, 'initiateRecall']);
    Route::get('/recalls/{recall}', [QualityController::class, 'recall']);
    Route::post('/recalls/{recall}/block', [QualityController::class, 'blockRecall']);
    Route::post('/recalls/{recall}/notify', [QualityController::class, 'notifyRecall']);
    Route::post('/recalls/{recall}/reconcile', [QualityController::class, 'reconcileRecall']);
    Route::post('/recalls/{recall}/disposition', [QualityController::class, 'dispositionRecall']);
    Route::post('/recalls/{recall}/close', [QualityController::class, 'closeRecall']);

    // 21.10 Finance
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::post('/payments/{payment}/void', [PaymentController::class, 'void']);
    Route::get('/finance/ar-ageing', [PaymentController::class, 'arAgeing']);
    Route::get('/finance/trial-balance', [FinanceController::class, 'trialBalance']);
    Route::get('/finance/journals', [FinanceController::class, 'journals']);
    Route::post('/finance/journals', [FinanceController::class, 'storeJournal']);
    Route::get('/finance/journals/{journal}', [FinanceController::class, 'showJournal']);
    Route::post('/finance/journals/{journal}/reverse', [FinanceController::class, 'reverseJournal']);
    Route::get('/finance/chart-of-accounts', [FinanceController::class, 'chartOfAccounts']);
    Route::get('/finance/periods', [FinanceController::class, 'periods']);
    Route::post('/finance/periods/{period}/close', [FinanceController::class, 'closePeriod']);

    // Part 21.16 — payroll
    Route::get('/payroll/employees', [PayrollController::class, 'employees']);
    Route::post('/payroll/employees', [PayrollController::class, 'storeEmployee']);
    Route::patch('/payroll/employees/{employee}', [PayrollController::class, 'updateEmployee']);
    Route::get('/payroll/bands', [PayrollController::class, 'bands']);
    Route::get('/payroll/runs', [PayrollController::class, 'runs']);
    Route::post('/payroll/runs', [PayrollController::class, 'open']);
    Route::get('/payroll/runs/{run}', [PayrollController::class, 'run']);
    Route::post('/payroll/runs/{run}/compute', [PayrollController::class, 'compute']);
    Route::post('/payroll/runs/{run}/approve', [PayrollController::class, 'approve']);
    Route::post('/payroll/runs/{run}/post', [PayrollController::class, 'post']);
    Route::post('/payroll/runs/{run}/pay', [PayrollController::class, 'pay']);
    Route::get('/payroll/runs/{run}/payslips/{employee}', [PayrollController::class, 'payslip']);

    // Read side for the screens: lists and single documents, lookups
    Route::get('/purchase-orders/{po}', [DocumentListController::class, 'purchaseOrder']);
    Route::get('/quotations', [DocumentListController::class, 'quotations']);
    Route::get('/goods-receipts', [DocumentListController::class, 'goodsReceipts']);
    Route::get('/supplier-invoices', [DocumentListController::class, 'supplierInvoices']);
    Route::get('/supplier-invoices/{invoice}', [DocumentListController::class, 'supplierInvoice']);
    Route::get('/supplier-invoices/{invoice}/comparison', [ProcurementController::class, 'supplierInvoiceComparison']);
    Route::get('/inventory/adjustments', [DocumentListController::class, 'adjustments']);
    Route::get('/inventory/adjustments/{adjustment}', [DocumentListController::class, 'adjustment']);
    Route::get('/delivery-notes', [DocumentListController::class, 'deliveryNotes']);
    Route::get('/delivery-notes/{note}', [DocumentListController::class, 'deliveryNote']);
    Route::get('/picking-lists', [DocumentListController::class, 'pickingLists']);
    Route::get('/picking-lists/{list}', [DocumentListController::class, 'pickingList']);
    Route::get('/users', [DocumentListController::class, 'users']);

    // Warehouse locations & packing, customer contacts, reconciliation, statements (Parts 10.6, 12.4, 12.5)
    Route::get('/locations', [LocationController::class, 'index']);
    Route::post('/locations', [LocationController::class, 'store']);
    Route::patch('/locations/{location}', [LocationController::class, 'update']);
    Route::get('/locations/{location}/stock', [LocationController::class, 'stock']);
    Route::get('/packing/queue', [PackingController::class, 'queue']);
    Route::post('/picking-lists/{list}/pack', [PackingController::class, 'pack']);
    Route::get('/customer-contacts', [CustomerContactController::class, 'index']);
    Route::post('/customer-contacts', [CustomerContactController::class, 'store']);
    Route::patch('/customer-contacts/{contact}', [CustomerContactController::class, 'update']);
    Route::get('/customer-interactions', [CustomerContactController::class, 'interactions']);
    Route::post('/customer-interactions', [CustomerContactController::class, 'storeInteraction']);
    Route::patch('/customer-interactions/{interaction}', [CustomerContactController::class, 'updateInteraction']);
    Route::get('/finance/reconciliation', [ReconciliationController::class, 'index']);
    Route::post('/finance/reconciliation/reconcile', [ReconciliationController::class, 'reconcile']);
    Route::post('/finance/reconciliation/{payment}/unreconcile', [ReconciliationController::class, 'unreconcile']);
    Route::get('/customers/{customer}/statement', [CustomerStatementController::class, 'show']);

    // Quality & Compliance — cold chain (Part 8.5), ADR (Part 11.4), licences and SOPs (V6 Parts 16.3, 16.4)
    Route::get('/cold-chain/summary', [ColdChainController::class, 'summary']);
    Route::get('/cold-chain/readings', [ColdChainController::class, 'readings']);
    Route::post('/cold-chain/readings', [ColdChainController::class, 'storeReading']);
    Route::get('/cold-chain/excursions', [ColdChainController::class, 'excursions']);
    Route::get('/cold-chain/excursions/{excursion}', [ColdChainController::class, 'excursion']);
    Route::post('/cold-chain/excursions/{excursion}/review', [ColdChainController::class, 'reviewExcursion']);
    Route::post('/cold-chain/excursions/{excursion}/close', [ColdChainController::class, 'closeExcursion']);
    Route::get('/adr-reports', [AdrReportController::class, 'index']);
    Route::post('/adr-reports', [AdrReportController::class, 'store']);
    Route::get('/adr-reports/{report}', [AdrReportController::class, 'show']);
    Route::patch('/adr-reports/{report}', [AdrReportController::class, 'update']);
    Route::post('/adr-reports/{report}/submit', [AdrReportController::class, 'submit']);
    Route::post('/adr-reports/{report}/close', [AdrReportController::class, 'close']);
    Route::get('/licences', [LicenceController::class, 'index']);
    // Before {licence}, which also answers synthetic ids such as supplier:<uuid>.
    Route::get('/licences/holders', [LicenceController::class, 'holders']);
    Route::get('/licences/{licence}', [LicenceController::class, 'show']);
    Route::post('/licences', [LicenceController::class, 'store']);
    Route::match(['post', 'patch'], '/licences/{licence}', [LicenceController::class, 'update']);
    Route::get('/licences/{licence}/document', [LicenceController::class, 'document']);
    Route::get('/documents', [ControlledDocumentController::class, 'index']);
    Route::get('/documents/templates', [ControlledDocumentController::class, 'templates']);
    Route::post('/documents/from-template', [ControlledDocumentController::class, 'storeFromTemplate']);
    Route::post('/documents', [ControlledDocumentController::class, 'store']);
    Route::get('/documents/{document}', [ControlledDocumentController::class, 'show']);
    Route::patch('/documents/{document}', [ControlledDocumentController::class, 'update']);
    Route::post('/documents/{document}/versions', [ControlledDocumentController::class, 'storeVersion']);
    Route::post('/documents/{document}/versions/from-text', [ControlledDocumentController::class, 'storeVersionFromText']);
    Route::get('/documents/{document}/versions/{version}/download', [ControlledDocumentController::class, 'download']);
    Route::post('/documents/{document}/activate', [ControlledDocumentController::class, 'activate']);
    Route::post('/documents/{document}/retire', [ControlledDocumentController::class, 'retire']);
    Route::post('/documents/{document}/acknowledge', [ControlledDocumentController::class, 'acknowledge']);
    Route::get('/documents/{document}/acknowledgements', [ControlledDocumentController::class, 'acknowledgements']);

    // Admin operations and catalogue maintenance (Parts 5, 17) — system health, backups, sync centre, categories, product import/export
    Route::get('/admin/system-health', [OperationsController::class, 'systemHealth']);
    Route::post('/admin/system-health/retry-failed-jobs', [OperationsController::class, 'retryFailedJobs']);
    Route::post('/admin/system-health/forget-failed-job/{id}', [OperationsController::class, 'forgetFailedJob']);
    // Part 18.3 — deleting master data (never transactions).
    // Part 15 — statutory payroll rates, kept current by the System Administrator.
    Route::get('/payroll-bands', [PayrollBandController::class, 'index']);
    Route::post('/payroll-bands', [PayrollBandController::class, 'store']);
    Route::patch('/payroll-bands/{band}', [PayrollBandController::class, 'update']);
    Route::delete('/payroll-bands/{band}', [PayrollBandController::class, 'destroy']);

    Route::get('/admin/deletable', [RecordDeletionController::class, 'types']);
    Route::get('/admin/records/{type}/{id}/references', [RecordDeletionController::class, 'references']);
    Route::delete('/admin/records/{type}/{id}', [RecordDeletionController::class, 'destroy']);

    // Part 17 — deployments: pull the latest code and run the deploy script.
    Route::get('/admin/deployments', [DeploymentController::class, 'status']);
    Route::post('/admin/deployments/check', [DeploymentController::class, 'check']);
    Route::post('/admin/deployments', [DeploymentController::class, 'deploy']);

    Route::get('/admin/backups', [OperationsController::class, 'backups']);
    Route::post('/admin/backups', [OperationsController::class, 'createBackup']);
    Route::get('/admin/backups/{name}/download', [OperationsController::class, 'downloadBackup']);
    Route::get('/admin/sync-status', [OperationsController::class, 'syncStatus']);
    Route::get('/product-categories/all', [ProductCatalogueController::class, 'categories']);
    Route::post('/product-categories', [ProductCatalogueController::class, 'storeCategory']);
    Route::patch('/product-categories/{category}', [ProductCatalogueController::class, 'updateCategory']);
    Route::post('/products/import', [ProductCatalogueController::class, 'import']);
    Route::get('/products/export', [ProductCatalogueController::class, 'export']);

    // People, reports and pricing rules — leave (21.16), scheduled reports (20.3), pricing rules (Part 6)
    Route::get('/leave/types', [LeaveController::class, 'types']);
    Route::get('/leave/employees', [LeaveController::class, 'employees']);
    Route::get('/leave/balances', [LeaveController::class, 'balances']);
    Route::get('/leave/requests', [LeaveController::class, 'index']);
    Route::post('/leave/requests', [LeaveController::class, 'store']);
    Route::post('/leave/requests/{leave}/approve', [LeaveController::class, 'approve']);
    Route::post('/leave/requests/{leave}/reject', [LeaveController::class, 'reject']);
    Route::post('/leave/requests/{leave}/cancel', [LeaveController::class, 'cancel']);
    Route::get('/scheduled-reports', [ScheduledReportController::class, 'index']);
    Route::post('/scheduled-reports', [ScheduledReportController::class, 'store']);
    Route::get('/scheduled-reports/{schedule}', [ScheduledReportController::class, 'show']);
    Route::patch('/scheduled-reports/{schedule}', [ScheduledReportController::class, 'update']);
    Route::delete('/scheduled-reports/{schedule}', [ScheduledReportController::class, 'destroy']);
    Route::post('/scheduled-reports/{schedule}/run-now', [ScheduledReportController::class, 'runNow']);
    Route::get('/scheduled-report-runs', [ScheduledReportRunController::class, 'index']);
    Route::get('/scheduled-report-runs/{run}', [ScheduledReportRunController::class, 'show']);
    Route::get('/scheduled-report-runs/{run}/download', [ScheduledReportRunController::class, 'download']);
    Route::post('/scheduled-report-runs/{run}/review', [ScheduledReportRunController::class, 'review']);
    Route::get('/pricing-rules/promotions', [PricingRuleController::class, 'promotions']);
    Route::post('/pricing-rules/promotions', [PricingRuleController::class, 'storePromotion']);
    Route::get('/pricing-rules/promotions/{promotion}', [PricingRuleController::class, 'promotion']);
    Route::patch('/pricing-rules/promotions/{promotion}', [PricingRuleController::class, 'updatePromotion']);
    Route::post('/pricing-rules/promotions/{promotion}/activate', [PricingRuleController::class, 'activatePromotion']);
    Route::post('/pricing-rules/promotions/{promotion}/deactivate', [PricingRuleController::class, 'deactivatePromotion']);
    Route::get('/pricing-rules/price-breaks', [PricingRuleController::class, 'priceBreaks']);
    Route::post('/pricing-rules/price-breaks', [PricingRuleController::class, 'storePriceBreak']);
    Route::patch('/pricing-rules/price-breaks/{break}', [PricingRuleController::class, 'updatePriceBreak']);
    Route::delete('/pricing-rules/price-breaks/{break}', [PricingRuleController::class, 'destroyPriceBreak']);
    Route::get('/pricing-rules/discount-policies', [PricingRuleController::class, 'discountPolicies']);
    Route::post('/pricing-rules/discount-policies', [PricingRuleController::class, 'storeDiscountPolicy']);
    Route::patch('/pricing-rules/discount-policies/{policy}', [PricingRuleController::class, 'updateDiscountPolicy']);
    Route::delete('/pricing-rules/discount-policies/{policy}', [PricingRuleController::class, 'destroyDiscountPolicy']);
    Route::get('/pricing-rules/discount-authorities', [PricingRuleController::class, 'discountAuthorities']);
    Route::put('/pricing-rules/discount-authorities/{role}', [PricingRuleController::class, 'upsertDiscountAuthority']);
    Route::delete('/pricing-rules/discount-authorities/{role}', [PricingRuleController::class, 'destroyDiscountAuthority']);
    Route::get('/pricing-rules/customer-prices', [PricingRuleController::class, 'customerPrices']);
    Route::post('/pricing-rules/customer-prices', [PricingRuleController::class, 'storeCustomerPrice']);
    Route::patch('/pricing-rules/customer-prices/{price}', [PricingRuleController::class, 'updateCustomerPrice']);
    Route::delete('/pricing-rules/customer-prices/{price}', [PricingRuleController::class, 'destroyCustomerPrice']);
    Route::post('/pricing-rules/test', [PricingRuleController::class, 'test']);

    // 21.16 Dashboard and administration (Parts 16.3, 17, 18, 19)
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);

    // Part 17 — standing alerts: payment deadlines and shelf-life risk.
    // Part 17 — messages between people, delivered live over the websocket.
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount']);
    Route::get('/messages/recipients', [MessageController::class, 'recipients']);
    Route::post('/messages', [MessageController::class, 'store']);
    Route::post('/messages/read-all', [MessageController::class, 'markAllRead']);
    Route::post('/messages/{message}/read', [MessageController::class, 'markRead']);

    // Client item 17 — the training centre: lessons, practice tasks, knowledge checks, feedback.
    Route::get('/training', [TrainingController::class, 'index']);
    Route::get('/training/report', [TrainingController::class, 'report']);
    Route::get('/training/feedback', [TrainingController::class, 'feedbackIndex']);
    Route::get('/training/certificates/{user}/{module}', [TrainingController::class, 'certificate'])->whereNumber('user');
    Route::get('/training/modules/{module}', [TrainingController::class, 'show']);
    Route::post('/training/modules/{module}/lessons/{lesson}/viewed', [TrainingController::class, 'viewLesson']);
    Route::post('/training/modules/{module}/tasks/{task}/start', [TrainingController::class, 'startTask']);
    Route::post('/training/modules/{module}/tasks/{task}/complete', [TrainingController::class, 'completeTask']);
    Route::post('/training/modules/{module}/quiz', [TrainingController::class, 'submitQuiz']);
    Route::post('/training/modules/{module}/feedback', [TrainingController::class, 'feedback']);

    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/alerts/summary', [AlertController::class, 'summary']);
    Route::get('/reminders/today', [DailyBriefingController::class, 'today']);
    Route::post('/alerts/scan', [AlertController::class, 'scan']);
    Route::post('/alerts/acknowledge-all', [AlertController::class, 'acknowledgeAll']);
    Route::post('/alerts/{alert}/acknowledge', [AlertController::class, 'acknowledge']);
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::get('/admin/users/{user}', [AdminController::class, 'user'])->whereNumber('user');
    Route::post('/admin/users', [AdminController::class, 'storeUser']);
    Route::patch('/admin/users/{user}', [AdminController::class, 'updateUser']);
    Route::post('/admin/users/{user}/unlock', [AdminController::class, 'unlockUser']);
    Route::get('/admin/roles', [AdminController::class, 'roles']);
    Route::post('/admin/roles', [AdminController::class, 'storeRole']);
    Route::patch('/admin/roles/{role}', [AdminController::class, 'updateRole']);
    Route::get('/admin/permissions', [AdminController::class, 'permissions']);
    Route::get('/admin/branches', [AdminController::class, 'branches']);
    Route::post('/admin/branches', [AdminController::class, 'storeBranch']);
    Route::patch('/admin/branches/{branch}', [AdminController::class, 'updateBranch']);
    Route::post('/admin/branches/{branch}/stores', [AdminController::class, 'storeStore']);
    Route::patch('/admin/branches/{branch}/stores/{store}', [AdminController::class, 'updateStore']);
    Route::get('/admin/organisation', [OrganisationController::class, 'show']);
    Route::patch('/admin/organisation', [OrganisationController::class, 'update']);
    Route::get('/admin/company-profile', [CompanyProfileController::class, 'show']);
    Route::patch('/admin/company-profile', [CompanyProfileController::class, 'update']);
    Route::get('/admin/company-profile/pdf', [CompanyProfileController::class, 'pdf']);
    Route::get('/admin/company-profile/images/{kind}', [CompanyProfileController::class, 'image'])->whereIn('kind', ['logo', 'stamp', 'signature']);
    Route::post('/admin/company-profile/images/{kind}', [CompanyProfileController::class, 'uploadImage'])->whereIn('kind', ['logo', 'stamp', 'signature']);
    Route::delete('/admin/company-profile/images/{kind}', [CompanyProfileController::class, 'deleteImage'])->whereIn('kind', ['logo', 'stamp', 'signature']);
    Route::get('/admin/settings', [AdminController::class, 'settings']);
    Route::put('/admin/settings', [AdminController::class, 'putSetting']);
    Route::get('/admin/number-sequences', [AdminController::class, 'numberSequences']);
    Route::get('/admin/audit-log', [AdminController::class, 'auditLog']);
    Route::get('/product-categories', [DocumentListController::class, 'productCategories']);

    // Part 20 — reports
    Route::get('/reports', [ReportController::class, 'catalogue']);
    Route::get('/reports/{report}', [ReportController::class, 'run']);

    // Part 13.6 — the eTIMS queue
    Route::get('/etims/queue', [EtimsController::class, 'queue']);
    Route::post('/etims/sales/{sale}/retry', [EtimsController::class, 'retrySale']);
    Route::post('/etims/credit-notes/{return}/retry', [EtimsController::class, 'retryCreditNote']);
});

// An institution's own plan and Paystack checkout. tenant.access leaves these
// open even when the institution has lapsed, so it can always pay.
Route::middleware(['auth:sanctum', 'branch.context', 'tenant.access'])->group(function () {
    Route::get('/billing', [BillingController::class, 'show']);
    Route::post('/billing/checkout', [BillingController::class, 'checkout']);
    Route::post('/billing/verify', [BillingController::class, 'verify']);
});

// The public edge: plans, quote requests, the one-time links, Paystack's webhook.
Route::prefix('public')->group(function () {
    Route::get('/plans', [PublicOnboardingController::class, 'plans'])->middleware('throttle:60,1');
    Route::post('/quote-requests', [PublicOnboardingController::class, 'requestQuote'])->middleware('throttle:5,1');
    Route::post('/invitations/open', [PublicOnboardingController::class, 'openInvitation'])->middleware('throttle:10,1');
    Route::post('/register', [PublicOnboardingController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/activate', [PublicOnboardingController::class, 'activate'])->middleware('throttle:10,1');
    Route::post('/paystack/webhook', [PublicOnboardingController::class, 'paystackWebhook']);
});

// The platform console: institutions, quote requests, plans and payments.
Route::middleware(['auth:sanctum', 'platform'])->prefix('platform')->group(function () {
    Route::get('/quote-requests', [PlatformController::class, 'quoteRequests']);
    Route::post('/quote-requests/{tenantRequest}/approve', [PlatformController::class, 'approveRequest']);
    Route::post('/quote-requests/{tenantRequest}/reject', [PlatformController::class, 'rejectRequest']);
    Route::get('/tenants', [PlatformController::class, 'tenants']);
    Route::post('/tenants', [PlatformController::class, 'storeTenant']);
    Route::get('/tenants/{organisation}', [PlatformController::class, 'tenant']);
    Route::post('/tenants/{organisation}/suspend', [PlatformController::class, 'suspend']);
    Route::post('/tenants/{organisation}/reactivate', [PlatformController::class, 'reactivate']);
    Route::post('/tenants/{organisation}/extend-trial', [PlatformController::class, 'extendTrial']);
    Route::post('/tenants/{organisation}/complimentary', [PlatformController::class, 'setComplimentary']);
    Route::get('/tenants/{organisation}/transactions-since', [PlatformController::class, 'purgePreview']);
    Route::post('/tenants/{organisation}/clear-transactions', [PlatformController::class, 'purgeTransactions']);
    Route::post('/invitations/{invitation}/resend', [PlatformController::class, 'resendInvitation']);
    Route::get('/plans', [PlatformController::class, 'plans']);
    Route::post('/plans', [PlatformController::class, 'storePlan']);
    Route::patch('/plans/{plan}', [PlatformController::class, 'updatePlan']);
    Route::get('/payments', [PlatformController::class, 'payments']);
});
