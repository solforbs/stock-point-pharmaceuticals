<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DocumentListController;
use App\Http\Controllers\Api\EtimsController;
use App\Http\Controllers\Api\FinanceController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PriceListController;
use App\Http\Controllers\Api\PricingController;
use App\Http\Controllers\Api\ProcurementController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\QualityController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RequisitionController;
use App\Http\Controllers\Api\ReturnController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\StockCountController;
use App\Http\Controllers\Api\StockTransferController;
use App\Models\Branch;
use App\Services\Sales\SaleModes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'branch.context'])->get('/user', function (Request $request) {
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
        'active_branch_id' => $activeBranchId,
        'active_branch' => $active,
        'branches' => $branches->values(),
        'sale_modes' => $active['sale_modes'] ?? [],
        'default_sale_mode' => $active['default_sale_mode'] ?? null,
    ];
});

// Part 21 — the API catalogue. Every route is authenticated, branch-scoped
// (Part 18.2) and permission-checked inside its controller.
Route::middleware(['auth:sanctum', 'branch.context'])->group(function () {
    // 21.3 Products and master data
    Route::get('/products', [ProductController::class, 'index']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::get('/products/{product}', [ProductController::class, 'show']);
    Route::patch('/products/{product}', [ProductController::class, 'update']);
    Route::get('/products/{product}/stock', [ProductController::class, 'stock']);
    Route::get('/uoms', [MasterDataController::class, 'uoms']);
    Route::get('/tax-codes', [MasterDataController::class, 'taxCodes']);
    Route::get('/stores', [MasterDataController::class, 'stores']);
    Route::get('/customers', [MasterDataController::class, 'customers']);
    Route::post('/customers', [MasterDataController::class, 'storeCustomer']);
    Route::get('/customers/{customer}', [MasterDataController::class, 'customer']);
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
    Route::get('/inventory/adjustments', [DocumentListController::class, 'adjustments']);
    Route::get('/inventory/adjustments/{adjustment}', [DocumentListController::class, 'adjustment']);
    Route::get('/delivery-notes', [DocumentListController::class, 'deliveryNotes']);
    Route::get('/delivery-notes/{note}', [DocumentListController::class, 'deliveryNote']);
    Route::get('/picking-lists', [DocumentListController::class, 'pickingLists']);
    Route::get('/picking-lists/{list}', [DocumentListController::class, 'pickingList']);
    Route::get('/users', [DocumentListController::class, 'users']);

    // 21.16 Dashboard and administration (Parts 16.3, 17, 18, 19)
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/admin/users', [AdminController::class, 'users']);
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
