<?php

namespace App\Services\Reports;

/**
 * Part 20.2 — the report catalogue. Every report reads posted
 * transactions, is permission-gated individually, and can be exported.
 * Each entry: title, group, the query class and method, the permissions
 * required, and the filters it understands beyond from/to.
 */
class ReportCatalogue
{
    /**
     * @return array<string, array{title: string, group: string, class: class-string, method: string, permissions: list<string>, filters: list<string>, description: string}>
     */
    public static function all(): array
    {
        $sales = fn (string $m) => [SalesReports::class, $m];
        $margin = fn (string $m) => [MarginReports::class, $m];
        $inv = fn (string $m) => [InventoryReports::class, $m];
        $proc = fn (string $m) => [ProcurementReports::class, $m];
        $fin = fn (string $m) => [FinanceReports::class, $m];
        $qual = fn (string $m) => [QualityReports::class, $m];
        $mgmt = fn (string $m) => [ManagementReports::class, $m];
        $exc = fn (string $m) => [ExceptionReports::class, $m];

        $def = fn (string $title, string $group, array $target, array $permissions, array $filters, string $description) => [
            'title' => $title, 'group' => $group, 'class' => $target[0], 'method' => $target[1],
            'permissions' => $permissions, 'filters' => $filters, 'description' => $description,
        ];

        $view = ['report.view'];
        $cost = ['report.view', 'product.cost.view'];
        $finance = ['report.financial.view'];
        $audit = ['audit.view'];

        return [
            // Sales
            'sales.daily_summary' => $def('Daily sales summary', 'sales', $sales('dailySummary'), $view, [], 'Per day, with the retail / wholesale / dispensing split, discounts, tax and gross profit.'),
            'sales.by_customer' => $def('Sales by customer', 'sales', $sales('byCustomer'), $view, [], 'Net sales, cost and margin per customer.'),
            'sales.by_product' => $def('Sales by product', 'sales', $sales('byProduct'), $view, ['category_id'], 'Quantity, net sales, cost and margin per product.'),
            'sales.by_cashier' => $def('Sales by cashier / rep', 'sales', $sales('byCashier'), $view, [], 'Transactions, net sales and discounts given per user.'),
            'sales.hourly_profile' => $def('Hourly sales profile', 'sales', $sales('hourlyProfile'), $view, [], 'Transactions and net sales by hour of day.'),
            'sales.discount_analysis' => $def('Discount analysis', 'sales', $sales('discountAnalysis'), $view, [], 'Discounts by user and product, with how many needed approval.'),
            'sales.bonus_goods_cost' => $def('Bonus goods cost', 'sales', $sales('bonusGoodsCost'), $cost, [], 'Free goods issued per product at batch cost.'),
            'sales.quotation_conversion' => $def('Quotation conversion rate', 'sales', $sales('quotationConversion'), $view, [], 'Quotations issued versus converted to orders.'),
            'sales.void_log' => $def('Void log', 'sales', $sales('voidLog'), $view, [], 'Voided sales by user and day, with reasons.'),
            // Margin and cost
            'margin.by_product' => $def('Gross profit by product', 'margin', $margin('byProduct'), $cost, [], 'Revenue, COGS at batch cost, gross profit, margin and markup per product.'),
            'margin.by_customer' => $def('Gross profit by customer', 'margin', $margin('byCustomer'), $cost, [], 'Revenue, COGS, gross profit and margin per customer.'),
            'margin.below_floor' => $def('Margin below floor exceptions', 'margin', $margin('belowFloor'), $cost, [], 'Sale lines whose realised margin fell under the product\'s minimum margin.'),
            'margin.effective_after_bonus' => $def('Effective margin after bonus', 'margin', $margin('effectiveAfterBonus'), $cost, [], 'Margin once the cost of free goods is included.'),
            'margin.wac_history' => $def('WAC movement history', 'margin', $margin('wacHistory'), $cost, ['product_id'], 'Every receipt cost and the resulting weighted average cost.'),
            // Inventory
            'inventory.valuation' => $def('Stock valuation', 'inventory', $inv('valuation'), $cost, ['store_id'], 'On-hand value at weighted average cost and at retail price, per product and store.'),
            'inventory.expiry_risk' => $def('Expiry risk by tier', 'inventory', $inv('expiryRisk'), $view, [], 'Quantity and value at risk in the expired / 30 / 90 / 180-day tiers.'),
            'inventory.movement_classes' => $def('Fast / slow / dead stock', 'inventory', $inv('movementClasses'), $view, ['dead_days'], 'Sales velocity per product with days of stock and a dead-stock value total.'),
            'inventory.turnover' => $def('Stock turnover and days of stock', 'inventory', $inv('turnover'), $cost, [], 'COGS for the period against inventory value.'),
            'inventory.adjustments' => $def('Adjustment analysis', 'inventory', $inv('adjustments'), $view, [], 'Stock adjustments by reason and user with value.'),
            'inventory.count_variance' => $def('Count variance history', 'inventory', $inv('countVariance'), $view, [], 'Approved stock counts with their variance value.'),
            'inventory.in_transit' => $def('Transfer in-transit ageing', 'inventory', $inv('inTransit'), $view, [], 'Dispatched transfers not yet received, by days in transit.'),
            'inventory.negative_stock' => $def('Negative stock incidents', 'inventory', $inv('negativeStock'), $view, [], 'Balances below zero, which should never happen outside offline sync.'),
            'inventory.fefo_compliance' => $def('FEFO compliance', 'inventory', $inv('fefoCompliance'), $view, [], 'Share of allocations that followed first-expired-first-out, and the overrides.'),
            // Procurement
            'procurement.open_pos' => $def('Open purchase orders and ageing', 'procurement', $proc('openPurchaseOrders'), $view, [], 'Approved, sent and partially received orders by age.'),
            'procurement.outstanding' => $def('Outstanding quantities', 'procurement', $proc('outstandingQuantities'), $view, [], 'Ordered minus accepted per purchase order line.'),
            'procurement.supplier_performance' => $def('Supplier performance scorecard', 'procurement', $proc('supplierPerformance'), $view, [], 'On-time delivery, rejection rate and lead time per supplier.'),
            'procurement.spend' => $def('Procurement spend by supplier', 'procurement', $proc('spendBySupplier'), $cost, [], 'Value received per supplier in the period.'),
            'procurement.price_variance' => $def('Price variance report', 'procurement', $proc('priceVariance'), $cost, [], 'Invoice price against purchase order price per line.'),
            'procurement.matching_exceptions' => $def('GRN-to-invoice matching exceptions', 'procurement', $proc('matchingExceptions'), $view, [], 'Supplier invoices that did not match, with the reasons.'),
            'procurement.supplier_licences' => $def('Supplier licence expiry', 'procurement', $proc('supplierLicences'), $view, [], 'Supplier licences expiring within 90 days or missing.'),
            // Finance
            'finance.profit_and_loss' => $def('Profit and loss', 'finance', $fin('profitAndLoss'), $finance, [], 'Revenue, cost of sales, gross profit, expenses and result from posted journals.'),
            'finance.balance_sheet' => $def('Balance sheet', 'finance', $fin('balanceSheet'), $finance, [], 'Assets, liabilities and equity as at the end date; must balance.'),
            'finance.ap_ageing' => $def('AP ageing', 'finance', $fin('apAgeing'), $finance, [], 'What is owed to each supplier by age of invoice.'),
            'finance.customer_statement' => $def('Customer statement', 'finance', $fin('customerStatement'), $finance, ['customer_id'], 'Every invoice, receipt and credit note with a running balance.'),
            'finance.unallocated_receipts' => $def('Unallocated receipts', 'finance', $fin('unallocatedReceipts'), $finance, [], 'Receipts not yet applied to an invoice.'),
            'finance.vat_return' => $def('VAT return', 'finance', $fin('vatReturn'), $finance, [], 'Output VAT less input VAT for the period.'),
            'finance.till_summary' => $def('Till and M-PESA summary', 'finance', $fin('tillSummary'), $finance, [], 'Receipts by method and day, with reconciliation status.'),
            'finance.period_close_checklist' => $def('Period close checklist', 'finance', $fin('periodCloseChecklist'), $finance, [], 'The checks that must pass before the open period closes.'),
            // Quality and compliance
            'quality.quarantine_ageing' => $def('Quarantine ageing', 'quality', $qual('quarantineAgeing'), $view, [], 'Batches held in quarantine or awaiting QC, by days held.'),
            'quality.recall_effectiveness' => $def('Recall effectiveness', 'quality', $qual('recallEffectiveness'), $view, [], 'Distributed, recovered and disposed quantities per recall.'),
            'quality.waste_by_reason' => $def('Waste and disposal by reason', 'quality', $qual('wasteByReason'), $view, [], 'Posted disposals grouped by reason with value written off.'),
            'quality.licence_calendar' => $def('Licence and certificate expiry calendar', 'quality', $qual('licenceCalendar'), $view, [], 'Supplier licences and customer exemption certificates by expiry date.'),
            // Management
            'management.branch_comparison' => $def('Branch performance comparison', 'management', $mgmt('branchComparison'), $cost, [], 'Sales, gross profit and margin per branch, organisation-wide.'),
            'management.abc_analysis' => $def('ABC analysis', 'management', $mgmt('abcAnalysis'), $cost, [], 'Which products make 80% of the gross profit.'),
            'management.kpi_scorecard' => $def('KPI scorecard', 'management', $mgmt('kpiScorecard'), $cost, [], 'The headline numbers for the period on one row.'),
            // Exception reports auditors ask for (Part 19.3)
            'exceptions.voids' => $def('Voids by user and day', 'exceptions', $exc('voids'), $audit, [], 'Every void with who, when and why.'),
            'exceptions.discounts' => $def('Discounts above threshold', 'exceptions', $exc('discountsAboveThreshold'), $audit, ['threshold_pct'], 'Lines discounted beyond the threshold (default 5%) and who approved them.'),
            'exceptions.fefo_overrides' => $def('FEFO overrides', 'exceptions', $exc('fefoOverrides'), $audit, [], 'Every allocation that skipped the first-expiring batch, with the reason.'),
            'exceptions.large_adjustments' => $def('Adjustments above threshold', 'exceptions', $exc('largeAdjustments'), $audit, ['threshold'], 'Stock adjustments whose value exceeded the approval threshold.'),
            'exceptions.out_of_hours' => $def('Out-of-hours transactions', 'exceptions', $exc('outOfHours'), $audit, ['open_hour', 'close_hour'], 'Sales posted outside business hours.'),
            'exceptions.sequence_gaps' => $def('Document sequence gaps', 'exceptions', $exc('sequenceGaps'), $audit, [], 'Missing numbers in the gapless document sequences; should always be empty.'),
            'exceptions.manual_journals' => $def('Manual journals', 'exceptions', $exc('manualJournals'), $audit, [], 'Journals not generated by a transaction.'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
