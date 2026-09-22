export interface TourStep {
  targetId: string
  title: string
  description: string
  placement?: 'bottom' | 'top' | 'left' | 'right'
  route?: string
}

export interface TourDefinition {
  id: string
  route: string
  title: string
  subtitle: string
  steps: TourStep[]
}

export const DEFAULT_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-search',
    title: 'Universal Medicine & Action Search',
    description: 'Press Ctrl+K anytime to quickly look up medicines, batch stock, customers, or jump directly to any page across the entire system.',
    placement: 'bottom',
    route: '/dashboard',
  },
  {
    targetId: 'tour-pos-button',
    title: 'One-Tap Live POS Terminal',
    description: 'Launch the high-speed retail checkout counter with automated price-tiering, eTIMS compliance, and batch barcode scanning.',
    placement: 'bottom',
    route: '/dashboard',
  },
  {
    targetId: 'tour-branch-selector',
    title: 'Active Branch & Dispensary',
    description: 'Switch between retail stores and main warehouses. All inventory checks and sales transactions immediately bind to the selected active branch.',
    placement: 'right',
    route: '/dashboard',
  },
  {
    targetId: 'tour-sync-status',
    title: 'System Health & eTIMS Sync',
    description: 'Real-time status of your local database synchronization, background job queue, and Kenya Revenue Authority (eTIMS) transmissions.',
    placement: 'top',
    route: '/dashboard',
  },
  {
    targetId: 'tour-approvals-queue',
    title: 'Operational Approvals Hub',
    description: 'Review pending stock adjustments, supplier purchase orders, clinical quarantine releases, and customer credit over-limit approvals.',
    placement: 'top',
    route: '/dashboard',
  },
]

export const POS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-pos-mode-toggle',
    title: '1. Sale Mode (Retail vs Wholesale)',
    description: 'Switch between Retail for walk-in patients (cash/M-Pesa) and Wholesale for bulk clinic orders with special tier discounts.',
    placement: 'bottom',
    route: '/sell/pos',
  },
  {
    targetId: 'tour-pos-stock-room',
    title: '2. Physical Stock Room',
    description: 'Tells the POS which physical counter or storeroom to deduct medicine stock from. If stock is in the MAIN warehouse, switch rooms here.',
    placement: 'bottom',
    route: '/sell/pos',
  },
  {
    targetId: 'tour-pos-search-box',
    title: '3. Scan Barcode or Search Drug (F2)',
    description: 'Scan medicine barcodes directly with your barcode reader, search by drug name, or 1-tap any fast-moving medicine from the catalog.',
    placement: 'right',
    route: '/sell/pos',
  },
  {
    targetId: 'tour-pos-customer',
    title: '4. Patient & Customer Account (F5)',
    description: 'Walk-ins are set by default. For wholesale orders to clinics or hospitals, choose their customer account to apply custom prices and 30-day credit.',
    placement: 'bottom',
    route: '/sell/pos',
  },
  {
    targetId: 'tour-pos-payment-cta',
    title: '5. Instant Payment Checkout (F10)',
    description: 'Prices and taxes are automatically verified with a guaranteed price lock. Click Proceed to Payment or press F10 to take Cash, M-Pesa, or invoice on Credit.',
    placement: 'top',
    route: '/sell/pos',
  },
]

export const QUOTATIONS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-quotations-new',
    title: '1. New Quotation Drawer',
    description: 'Create a formal price quotation with automatic price tiering (HOSP, PHARM, NGO), bulk item additions, and a 7-day validity lock.',
    placement: 'bottom',
    route: '/sell/quotations',
  },
  {
    targetId: 'tour-quotations-filters',
    title: '2. Filter Bar & Customer Search',
    description: 'Quickly filter quotations by status (Draft, Sent, Accepted, Converted) or search by institutional customer name.',
    placement: 'bottom',
    route: '/sell/quotations',
  },
  {
    targetId: 'tour-quotations-table',
    title: '3. Quotations Ledger & 1-Click Conversion',
    description: 'Inspect quote status, validity dates, and download PDFs. Click any row to review line margins and 1-click convert into a confirmed sales order.',
    placement: 'top',
    route: '/sell/quotations',
  },
]

export const SALES_ORDERS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-sales-orders-new',
    title: '1. Create Sales Order',
    description: 'Place an institutional sales order to reserve batch stock under customer payment and credit terms.',
    placement: 'bottom',
    route: '/sell/sales-orders',
  },
  {
    targetId: 'tour-sales-orders-filters',
    title: '2. Order Status Pipeline',
    description: 'Filter orders through their operational lifecycle: DRAFT, CONFIRMED, IN_PROGRESS, PARTIALLY_FULFILLED, and FULFILLED.',
    placement: 'bottom',
    route: '/sell/sales-orders',
  },
  {
    targetId: 'tour-sales-orders-table',
    title: '3. Sales Orders Registry',
    description: 'Click any order to view ordered quantities, credit verification checks, line discounts, and warehouse pick list statuses.',
    placement: 'top',
    route: '/sell/sales-orders',
  },
]

export const INVOICES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-invoices-mode',
    title: '1. Sale Mode Filtering',
    description: 'Switch between Retail counter receipts, Wholesale commercial sales, and Clinic Dispensing invoices.',
    placement: 'bottom',
    route: '/sell/invoices',
  },
  {
    targetId: 'tour-invoices-filters',
    title: '2. Status & Date Range Filters',
    description: 'Filter POSTED sales or audit VOIDED transactions across custom date ranges for accurate daily and monthly reconciliations.',
    placement: 'bottom',
    route: '/sell/invoices',
  },
  {
    targetId: 'tour-invoices-table',
    title: '3. Invoices Ledger & eTIMS Receipts',
    description: 'Select any invoice row to inspect batch allocations, cashier signatures, payment splits, and download official eTIMS tax receipts with verified QR codes.',
    placement: 'top',
    route: '/sell/invoices',
  },
]

export const RETURNS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-returns-tabs',
    title: '1. Customer vs Supplier Returns',
    description: 'Toggle between Customer returns (credit notes & restock inspection) and Supplier returns (reversing goods receipts for debit notes).',
    placement: 'bottom',
    route: '/sell/returns',
  },
  {
    targetId: 'tour-returns-filter',
    title: '2. Return Status Filter',
    description: 'Filter returns across DRAFT, POSTED, and REJECTED states to manage pending quality inspections.',
    placement: 'bottom',
    route: '/sell/returns',
  },
  {
    targetId: 'tour-returns-table',
    title: '3. Returns Roster',
    description: 'View original invoice links, credit values, and refund methods (Cash, M-Pesa, or Account Credit balance).',
    placement: 'top',
    route: '/sell/returns',
  },
]

export const CUSTOMER_STATEMENTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-statements-customer',
    title: '1. Select Customer Account',
    description: 'Choose any hospital, clinic, or wholesale customer to instantly load their complete financial ledger and credit balance.',
    placement: 'bottom',
    route: '/sell/statements',
  },
  {
    targetId: 'tour-statements-date',
    title: '2. Statement Period Filter',
    description: 'Set custom date intervals (e.g. monthly billing cycle) to recalculate opening balance and running debit/credit entries.',
    placement: 'bottom',
    route: '/sell/statements',
  },
  {
    targetId: 'tour-statements-preview',
    title: '3. Ledger & 30/60/90+ Day Debt Aging',
    description: 'Generates an itemized running ledger of every invoice, receipt, and credit note with outstanding debt aging across Current, 1–30, 31–60, and 90+ days.',
    placement: 'top',
    route: '/sell/statements',
  },
  {
    targetId: 'tour-statements-print',
    title: '4. Print Official A4 Statement',
    description: '1-click print or export an official A4 customer statement complete with running balance and official pharmacy tax headers.',
    placement: 'left',
    route: '/sell/statements',
  },
]

export const PRODUCTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-products-new',
    title: '1. New Product Registration',
    description: 'Register new pharmaceutical SKUs with strength, therapeutic category, default pricing, and packaging UOM factors.',
    placement: 'bottom',
    route: '/inventory/products',
  },
  {
    targetId: 'tour-products-filters',
    title: '2. Search & Stock Filters',
    description: 'Look up drugs by name, generic formulation, exact barcode scan, or filter products below reorder point.',
    placement: 'bottom',
    route: '/inventory/products',
  },
  {
    targetId: 'tour-products-table',
    title: '3. Medication Catalog',
    description: 'Review on-hand quantities, free-to-sell stock, nearest batch expiries, and click any item to edit master data.',
    placement: 'top',
    route: '/inventory/products',
  },
]

export const STOCK_ON_HAND_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-stock-filters',
    title: '1. Store & Product Filters',
    description: 'Filter live inventory balances across main warehouses, dispensing rooms, and retail stores.',
    placement: 'bottom',
    route: '/inventory/stock-on-hand',
  },
  {
    targetId: 'tour-stock-table',
    title: '2. Authoritative 8-State Balance View',
    description: 'Inspect live balances categorized by Physical, Reserved, Quarantined, Free to Sell, and Landed Valuation.',
    placement: 'top',
    route: '/inventory/stock-on-hand',
  },
]

export const BATCHES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-batches-filters',
    title: '1. Expiry Horizon Filters',
    description: 'Monitor batches by critical expiry thresholds: Expired, 30 days, 90 days, or 180 days out.',
    placement: 'bottom',
    route: '/inventory/batches',
  },
  {
    targetId: 'tour-batches-table',
    title: '2. Batch Traceability Roster',
    description: 'Open any batch to trace complete movement genealogy, received supplier invoices, and patient dispensing history.',
    placement: 'top',
    route: '/inventory/batches',
  },
]

export const COUNTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-counts-new',
    title: '1. Initiate Stocktake Count',
    description: 'Plan a blind inventory count session for any store or selected medication list.',
    placement: 'bottom',
    route: '/inventory/counts',
  },
  {
    targetId: 'tour-counts-filters',
    title: '2. Count Session Pipeline',
    description: 'Filter counts through DRAFT, COUNTING, REVIEW, and POSTED phases.',
    placement: 'bottom',
    route: '/inventory/counts',
  },
  {
    targetId: 'tour-counts-table',
    title: '3. Counts Registry & Variance Review',
    description: 'Review physical counts against system balances, record variance explanation reasons, and dual-control approve adjustments.',
    placement: 'top',
    route: '/inventory/counts',
  },
]

export const TRANSFERS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-transfers-new',
    title: '1. New Stock Transfer',
    description: 'Initiate custody-controlled batch transfers between stores without generating fiscal invoices.',
    placement: 'bottom',
    route: '/inventory/transfers',
  },
  {
    targetId: 'tour-transfers-filters',
    title: '2. Transfer Lifecycle',
    description: 'Track transfers across DRAFT, DISPATCHED, and RECEIVED segregation of duties.',
    placement: 'bottom',
    route: '/inventory/transfers',
  },
  {
    targetId: 'tour-transfers-table',
    title: '3. Transfers Roster',
    description: 'Inspect dispatch quantities, carrier notes, and receiving verification timestamps.',
    placement: 'top',
    route: '/inventory/transfers',
  },
]

export const ADJUSTMENTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-adjustments-new',
    title: '1. New Stock Adjustment',
    description: 'Post inventory write-offs, breakages, or found stock with mandatory regulatory reason codes.',
    placement: 'bottom',
    route: '/inventory/adjustments',
  },
  {
    targetId: 'tour-adjustments-filters',
    title: '2. Approval Queue Filter',
    description: 'Separate routine adjustments from high-value variances waiting for supervisor dual-control approval.',
    placement: 'bottom',
    route: '/inventory/adjustments',
  },
  {
    targetId: 'tour-adjustments-table',
    title: '3. Adjustments Ledger',
    description: 'Review financial ledger impact, posted values, and management approval stamps.',
    placement: 'top',
    route: '/inventory/adjustments',
  },
]

export const STOCK_LEDGER_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-ledger-filters',
    title: '1. Stock Movement Filters & Running Balance',
    description: 'Filter immutable ledger movements by medication SKU, specific batch number, physical store room, and transaction type. Choosing a batch and store displays an authoritative running balance.',
    placement: 'bottom',
    route: '/inventory/stock-ledger',
  },
  {
    targetId: 'tour-ledger-table',
    title: '2. Append-Only Inventory Ledger',
    description: 'Inspect every chronological debit and credit transaction, unit costs, landed valuations, and linked source documents.',
    placement: 'top',
    route: '/inventory/stock-ledger',
  },
]

export const VALUATION_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-valuation-filter',
    title: '1. Store Valuation Scope',
    description: 'Switch between individual branch store rooms or calculate overall company-wide pharmacy stock valuation.',
    placement: 'bottom',
    route: '/inventory/valuation',
  },
  {
    targetId: 'tour-valuation-totals',
    title: '2. Total Inventory Valuation (WAC vs. Retail)',
    description: 'Real-time totals comparing total inventory value at weighted average cost (WAC) against potential yield at default retail prices.',
    placement: 'bottom',
    route: '/inventory/valuation',
  },
  {
    targetId: 'tour-valuation-table',
    title: '3. Itemized Stock Valuation Grid',
    description: 'Review product-by-product on-hand stock quantities, batch unit costs, and total extended valuations.',
    placement: 'top',
    route: '/inventory/valuation',
  },
]

export const OPENING_STOCK_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-opening-template',
    title: '1. Download CSV Stock Take Template',
    description: 'Download the standardized opening stock CSV template with required columns: product_code, batch_number, expiry_date, qty, and unit_cost.',
    placement: 'left',
    route: '/inventory/opening-stock',
  },
  {
    targetId: 'tour-opening-store-file',
    title: '2. Store Selection & CSV File Upload',
    description: 'Select the target pharmacy store room and upload your completed go-live stock take spreadsheet with drag-and-drop support.',
    placement: 'bottom',
    route: '/inventory/opening-stock',
  },
  {
    targetId: 'tour-opening-validation',
    title: '3. Pre-Commit Validation & Ledger Posting',
    description: 'Every row is validated against master product codes, unit costs, and expiry dates before any data is posted. Any error halts the entire batch to guarantee ledger integrity.',
    placement: 'top',
    route: '/inventory/opening-stock',
  },
]

export const REQUISITIONS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-requisitions-tabs',
    title: '1. Requisitions & Reorder Advisor',
    description: 'Toggle between raised departmental stock demands and the automated Reorder Suggestion algorithm based on historical run-rate and minimum safety stock.',
    placement: 'bottom',
    route: '/buy/requisitions',
  },
  {
    targetId: 'tour-requisitions-new',
    title: '2. Raise New Requisition',
    description: 'Draft internal demands for medicines, specify fulfillment deadlines, and submit for clinical management approval.',
    placement: 'bottom',
    route: '/buy/requisitions',
  },
  {
    targetId: 'tour-requisitions-status',
    title: '3. Approval Filter',
    description: 'Filter requisitions by Draft, Pending Approval, Approved, or Converted to Supplier PO.',
    placement: 'bottom',
    route: '/buy/requisitions',
  },
  {
    targetId: 'tour-requisitions-table',
    title: '4. Requisitions Ledger & PO Conversion',
    description: 'Click any requisition to review itemized lines, approve demands, or 1-click convert approved requisitions directly into supplier purchase orders.',
    placement: 'top',
    route: '/buy/requisitions',
  },
]

export const PURCHASE_ORDERS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-po-new',
    title: '1. Raise Purchase Order (PO)',
    description: 'Create supplier purchase orders with contracted vendor price lists, packaging units (packs/tins), and expected delivery dates.',
    placement: 'bottom',
    route: '/buy/purchase-orders',
  },
  {
    targetId: 'tour-po-status',
    title: '2. Order Lifecycle Filter',
    description: 'Monitor orders as they progress from Draft → Approved → Sent to Vendor → Partially Received → Closed.',
    placement: 'bottom',
    route: '/buy/purchase-orders',
  },
  {
    targetId: 'tour-po-table',
    title: '3. Orders Ledger & PDF Export',
    description: 'Track procurement commitments, download official purchase order PDFs to email vendors, and drill into delivery line progress.',
    placement: 'top',
    route: '/buy/purchase-orders',
  },
]

export const GOODS_RECEIPTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-grn-new',
    title: '1. New Goods Receipt (GRN)',
    description: 'Receive physical stock shipments against purchase orders or process emergency unscheduled stock intakes.',
    placement: 'bottom',
    route: '/buy/goods-receipts',
  },
  {
    targetId: 'tour-grn-filters',
    title: '2. Filter Deliveries & Vendors',
    description: 'Filter received goods by status (Draft vs Posted) or specific pharmaceutical supplier.',
    placement: 'bottom',
    route: '/buy/goods-receipts',
  },
  {
    targetId: 'tour-grn-table',
    title: '3. Batch Quality & Cold Chain Intake',
    description: 'All received items mandate batch number, expiry date, delivery temperature check, and enter PENDING_QC quarantine before release.',
    placement: 'top',
    route: '/buy/goods-receipts',
  },
]

export const SUPPLIER_INVOICES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-invoices-record',
    title: '1. Record Supplier Commercial Invoice',
    description: 'Capture invoice numbers, invoice dates, VAT breakdown, and credit due dates as billed by the distributor.',
    placement: 'bottom',
    route: '/buy/supplier-invoices',
  },
  {
    targetId: 'tour-invoices-match-filter',
    title: '2. 3-Way Match Status Filter',
    description: 'Switch between Unmatched invoices, Exceptions (price/quantity discrepancies), and fully Matched accounts payable.',
    placement: 'bottom',
    route: '/buy/supplier-invoices',
  },
  {
    targetId: 'tour-invoices-table',
    title: '3. Invoices Ledger & Payables Commitment',
    description: 'Click any invoice to run the 3-way match against the original PO and physical GRN. Only verified matches post to Accounts Payable.',
    placement: 'top',
    route: '/buy/supplier-invoices',
  },
]

export const SUPPLIERS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-suppliers-new',
    title: '1. Onboard New Supplier',
    description: 'Register licensed pharmaceutical manufacturers, wholesale distributors, and local agents with verified tax PINs.',
    placement: 'bottom',
    route: '/buy/suppliers',
  },
  {
    targetId: 'tour-suppliers-search',
    title: '2. Search & Regulatory Verification',
    description: 'Quickly find suppliers by trade name or vendor code with real-time fuzzy search.',
    placement: 'bottom',
    route: '/buy/suppliers',
  },
  {
    targetId: 'tour-suppliers-table',
    title: '3. PPB Licences, Terms & Payable Balances',
    description: 'Monitor Pharmacy & Poisons Board (PPB) operational licence validity, agreed credit terms (e.g. 30/60 days), and total outstanding payables.',
    placement: 'top',
    route: '/buy/suppliers',
  },
]

export const PICK_LISTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-picklist-pending',
    title: '1. Orders Awaiting Picking',
    description: 'Confirmed sales orders automatically queue here. Click any order to generate an itemized warehouse pick list with shelf locations.',
    placement: 'right',
    route: '/warehouse/pick-lists',
  },
  {
    targetId: 'tour-picklist-open',
    title: '2. Active Pick Lists',
    description: 'Track pick lists currently in progress on the warehouse floor. Resume anytime to record picked items.',
    placement: 'right',
    route: '/warehouse/pick-lists',
  },
  {
    targetId: 'tour-picklist-detail',
    title: '3. Batch Picking & Shelf Locations',
    description: 'Verify batch numbers, check expiry dates, record actual picked units, and 1-click complete the pick for packaging and dispatch.',
    placement: 'left',
    route: '/warehouse/pick-lists',
  },
]

export const PACKING_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-packing-ready',
    title: '1. Ready for Packing Filter',
    description: 'Filter orders that have finished picking and are awaiting carton packaging, sealing, or tote assignment.',
    placement: 'bottom',
    route: '/warehouse/packing',
  },
  {
    targetId: 'tour-packing-table',
    title: '2. Packing Queue & Parcel Bagging',
    description: 'Select an order to record parcel counts, seal numbers, and package dimensions before shipping.',
    placement: 'top',
    route: '/warehouse/packing',
  },
]

export const DISPATCH_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-dispatch-select',
    title: '1. Select Packed Order',
    description: 'Choose any order that has finished picking and packing to prepare its dispatch waybill.',
    placement: 'bottom',
    route: '/warehouse/dispatch',
  },
  {
    targetId: 'tour-dispatch-form',
    title: '2. Driver & Vehicle Registration',
    description: 'Log transport details including vehicle registration, driver full name, contact phone, and any on-delivery cash/M-Pesa tender.',
    placement: 'bottom',
    route: '/warehouse/dispatch',
  },
  {
    targetId: 'tour-dispatch-note',
    title: '3. Delivery Note & Invoice Posting',
    description: 'Confirming dispatch locks warehouse stock, generates the official delivery note, and posts the final sales invoice.',
    placement: 'left',
    route: '/warehouse/dispatch',
  },
]

export const DELIVERIES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-deliveries-status',
    title: '1. Delivery Status Filter',
    description: 'Filter delivery notes by Dispatched, In Transit, Delivered, or Cancelled.',
    placement: 'bottom',
    route: '/warehouse/deliveries',
  },
  {
    targetId: 'tour-deliveries-table',
    title: '2. Deliveries Ledger & Electronic POD',
    description: 'Click any delivery note to trace vehicle and driver details, inspect dispatched batch items, and record recipient proof of delivery (ePOD).',
    placement: 'top',
    route: '/warehouse/deliveries',
  },
]

export const LOCATIONS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-locations-new',
    title: '1. Create Storage Location',
    description: 'Configure new warehouse zones, temperature-controlled cold shelves, quarantine bays, and standard bin locations.',
    placement: 'bottom',
    route: '/warehouse/locations',
  },
  {
    targetId: 'tour-locations-store',
    title: '2. Storeroom & Branch Selector',
    description: 'Switch between retail dispensary shelves and main wholesale warehouse racks.',
    placement: 'bottom',
    route: '/warehouse/locations',
  },
  {
    targetId: 'tour-locations-table',
    title: '3. Aisle, Rack & Bin Hierarchy',
    description: 'Inspect bin storage capacity, on-hand item quantities, and shelf active/inactive status.',
    placement: 'top',
    route: '/warehouse/locations',
  },
]

export const CUSTOMERS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-customers-new',
    title: '1. Onboard Customer Account',
    description: 'Create individual walk-in profiles, wholesale hospitals, clinics, or retail pharmacy accounts.',
    placement: 'bottom',
    route: '/customers/list',
  },
  {
    targetId: 'tour-customers-search',
    title: '2. Instant Customer Search',
    description: 'Find accounts immediately by account code, trade name, phone number, or tax PIN.',
    placement: 'bottom',
    route: '/customers/list',
  },
  {
    targetId: 'tour-customers-table',
    title: '3. Customer Directory & Balances',
    description: 'View customer pricing tier, credit allowance, current ledger balance, and active/hold status.',
    placement: 'top',
    route: '/customers/list',
  },
]

export const TIERS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-tiers-card',
    title: '1. Pricing Tiers',
    description: 'Define customer pricing classes such as Wholesale A, Retail Standard, Government Tender, or Staff Discounts.',
    placement: 'bottom',
    route: '/customers/tiers',
  },
  {
    targetId: 'tour-pricelists-card',
    title: '2. Price Lists & Item Overrides',
    description: 'Set custom selling prices and special bulk contract pricing for specific medications.',
    placement: 'top',
    route: '/customers/tiers',
  },
]

export const CREDIT_CONTROL_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-credit-search',
    title: '1. Search Credit Accounts',
    description: 'Search accounts to assess credit risk, payment terms, or adjust credit limits.',
    placement: 'bottom',
    route: '/customers/credit-control',
  },
  {
    targetId: 'tour-credit-table',
    title: '2. Credit Exposure Ledger',
    description: 'Monitor real-time credit limit, current outstanding balance, and available remaining credit.',
    placement: 'top',
    route: '/customers/credit-control',
  },
  {
    targetId: 'tour-credit-editor',
    title: '3. Credit Review & Terms Adjustment',
    description: 'Update credit limit, extend payment grace days, or place high-risk accounts on credit stop.',
    placement: 'left',
    route: '/customers/credit-control',
  },
]

export const CONTACTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-contacts-tabs',
    title: '1. Contacts vs Interactions',
    description: 'Toggle between key stakeholder directory and the client communication audit log.',
    placement: 'bottom',
    route: '/customers/contacts',
  },
  {
    targetId: 'tour-contacts-customer',
    title: '2. Customer Filter',
    description: 'Focus communication logs and contact details on a specific hospital or pharmacy client.',
    placement: 'bottom',
    route: '/customers/contacts',
  },
  {
    targetId: 'tour-contacts-new',
    title: '3. Add Contact Person',
    description: 'Record buyers, chief pharmacists, procurement officers, and delivery receiving clerks.',
    placement: 'bottom',
    route: '/customers/contacts',
  },
  {
    targetId: 'tour-contacts-table',
    title: '4. Contact Details & Direct Actions',
    description: 'One-click call or email contacts directly from the directory.',
    placement: 'top',
    route: '/customers/contacts',
  },
]

export const RECEIVABLES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-receivables-record',
    title: '1. Record Customer Payment',
    description: 'Capture inbound client payments via M-PESA, Bank transfer, or Cheque, with automatic or line-by-line invoice allocation.',
    placement: 'bottom',
    route: '/finance/receivables',
  },
  {
    targetId: 'tour-receivables-table',
    title: '2. Accounts Receivable (AR) Ageing',
    description: 'Track outstanding balances across aging buckets (Current, 1-30, 31-60, 61-90, 90+ days), credit limits, and credit stops.',
    placement: 'top',
    route: '/finance/receivables',
  },
]

export const PAYABLES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-payables-table',
    title: '1. Outstanding Supplier Payables',
    description: 'View vendor balances generated from 3-way matched purchase orders and verified goods receipts.',
    placement: 'right',
    route: '/finance/payables',
  },
  {
    targetId: 'tour-payables-form',
    title: '2. Post Supplier Payment',
    description: 'Disburse supplier payments via Bank transfer, M-PESA, or Cheque with automatic Dr AP / Cr Bank general ledger posting.',
    placement: 'left',
    route: '/finance/payables',
  },
]

export const RECONCILIATION_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-reconciliation-filters',
    title: '1. Date & Channel Filters',
    description: 'Filter payments by date range, payment method (M-PESA, Bank, Cash), and reconciliation status.',
    placement: 'bottom',
    route: '/finance/reconciliation',
  },
  {
    targetId: 'tour-reconciliation-totals',
    title: '2. Method Totals & Variances',
    description: 'Monitor cleared receipts, verified reconciled totals, and outstanding unreconciled amounts per payment channel.',
    placement: 'bottom',
    route: '/finance/reconciliation',
  },
  {
    targetId: 'tour-reconciliation-action',
    title: '3. Bulk Reconcile Action',
    description: 'Select receipts matching your bank or M-PESA statement and click here to lock them with statement reference numbers.',
    placement: 'bottom',
    route: '/finance/reconciliation',
  },
  {
    targetId: 'tour-reconciliation-table',
    title: '4. Receipts Ledger & Audit Trail',
    description: 'Review receipt timestamps, customer codes, receiving cashier, and reconciler sign-offs.',
    placement: 'top',
    route: '/finance/reconciliation',
  },
]

export const JOURNALS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-journals-new',
    title: '1. Create Manual Journal Entry',
    description: 'Post custom double-entry adjusting journals with balanced debit and credit allocations across accounts.',
    placement: 'bottom',
    route: '/finance/journals',
  },
  {
    targetId: 'tour-journals-filters',
    title: '2. Search & Document Filters',
    description: 'Filter journal entries by reference number, source transaction type (sales, receipts, write-offs), or date range.',
    placement: 'bottom',
    route: '/finance/journals',
  },
  {
    targetId: 'tour-journals-table',
    title: '3. Append-Only General Ledger',
    description: 'Expand any journal entry to inspect line-item debit/credit splits and trigger authorized reversing journals.',
    placement: 'top',
    route: '/finance/journals',
  },
]

export const CHART_OF_ACCOUNTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-coa-filters',
    title: '1. Account Class Filters',
    description: 'Filter accounts by classification: Assets, Liabilities, Equity, Revenue, COGS, and Operating Expenses.',
    placement: 'bottom',
    route: '/finance/chart-of-accounts',
  },
  {
    targetId: 'tour-coa-table',
    title: '2. Account Hierarchy & Balances',
    description: 'View the nested chart of accounts, postable status, system roles (Bank, AR, AP, VAT), and live Dr/Cr balances.',
    placement: 'top',
    route: '/finance/chart-of-accounts',
  },
]

export const TAX_CENTRE_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-tax-status-cards',
    title: '1. eTIMS Transmission Status',
    description: 'Check real-time Kenya Revenue Authority (KRA) eTIMS status: Failed, Pending, and Successfully Submitted.',
    placement: 'bottom',
    route: '/finance/tax-centre',
  },
  {
    targetId: 'tour-tax-queue-table',
    title: '2. Fiscal Document Queue & Retry',
    description: 'Inspect tax control codes, KRA validation errors, and trigger manual one-click resubmissions for failed sales.',
    placement: 'top',
    route: '/finance/tax-centre',
  },
]

export const PERIODS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-periods-table',
    title: '1. Financial Periods & Month-End Close',
    description: 'Manage fiscal periods. Closing enforces the automated checklist: journals must balance and stock ledger must reconcile before locking.',
    placement: 'top',
    route: '/finance/periods',
  },
]

export const STATEMENTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-statements-filter',
    title: '1. As-Of Date Selector',
    description: 'Choose the reporting cut-off date to generate snapshot trial balance reports.',
    placement: 'bottom',
    route: '/finance/statements',
  },
  {
    targetId: 'tour-statements-table',
    title: '2. Trial Balance Verification',
    description: 'Confirm that total debits equal total credits across all posted accounts to guarantee balanced financial books.',
    placement: 'top',
    route: '/finance/statements',
  },
]

export const QUARANTINE_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-quarantine-tabs',
    title: '1. Holding States & QC Gates',
    description: 'Toggle between Quarantined stock (hard-blocked from dispensing) and Pending QC stock (new goods receipts awaiting release by a pharmacist).',
    placement: 'bottom',
    route: '/quality/quarantine',
  },
  {
    targetId: 'tour-quarantine-table',
    title: '2. Quarantined Batches & Audit Trail',
    description: 'Inspect held batches, expiry dates, supplier source, and landed cost. Releasing stock requires authorized quality permissions and writes an audit log.',
    placement: 'top',
    route: '/quality/quarantine',
  },
]

export const WASTE_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-waste-create',
    title: '1. Draft Destruction & Waste Batch',
    description: 'Initiate a formal disposal record for expired, damaged, recalled, or cold-chain excursion stock with destruction method and contractor.',
    placement: 'bottom',
    route: '/quality/waste',
  },
  {
    targetId: 'tour-waste-filters',
    title: '2. Disposal Reasons & Status Filter',
    description: 'Filter disposal manifests across Draft and Posted states and specific disposal reasons (Expired, Damaged, Recalled, Excursion).',
    placement: 'bottom',
    route: '/quality/waste',
  },
  {
    targetId: 'tour-waste-table',
    title: '3. Disposal Manifests & Two-Witness Sign-off',
    description: 'Review disposal batches and certificates. Posting requires two authorized staff witnesses and automatically posts inventory write-offs to financial ledgers.',
    placement: 'top',
    route: '/quality/waste',
  },
]

export const RECALLS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-recalls-initiate',
    title: '1. Initiate Pharmaceutical Recall',
    description: 'Initiate manufacturer, PPB, or internal drug recalls. Instantly locks affected batches across all stores and traces every distributed unit.',
    placement: 'bottom',
    route: '/quality/recalls',
  },
  {
    targetId: 'tour-recalls-filter',
    title: '2. Recall Lifecycle Stages',
    description: 'Track recall execution across the full pipeline: Initiated → Scoped → Blocked → Notified → Recovering → Reconciled → Dispositioned → Closed.',
    placement: 'bottom',
    route: '/quality/recalls',
  },
  {
    targetId: 'tour-recalls-table',
    title: '3. Recall Registry & Recovery Percentage',
    description: 'Review scoped customer rosters, total units distributed, quantity recovered, and real-time percentage effectiveness metrics.',
    placement: 'top',
    route: '/quality/recalls',
  },
]

export const COLD_CHAIN_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-coldchain-stores',
    title: '1. Live Temperature & Store Status',
    description: 'Monitor real-time temperatures across refrigerated store rooms and cold boxes, tracking accepted ranges (e.g. 2.0°C – 8.0°C) and open excursion alerts.',
    placement: 'bottom',
    route: '/quality/cold-chain',
  },
  {
    targetId: 'tour-coldchain-tabs',
    title: '2. Readings Log & Excursions Queue',
    description: 'Switch between the sequential temperature readings audit log and active temperature excursion incidents requiring pharmacist review.',
    placement: 'bottom',
    route: '/quality/cold-chain',
  },
  {
    targetId: 'tour-coldchain-record',
    title: '3. Record Temperature Log',
    description: 'Log manual thermometer checks or data logger telemetry with humidity levels and timestamps. Out-of-window entries automatically trigger excursion alerts.',
    placement: 'bottom',
    route: '/quality/cold-chain',
  },
  {
    targetId: 'tour-coldchain-table',
    title: '4. Visual Trends & Excursion Triage',
    description: 'Analyze time-series thermal charts, duration above threshold, and triage stock disposition (No Impact, Quarantine, or Waste Disposal).',
    placement: 'top',
    route: '/quality/cold-chain',
  },
]

export const PHARMACOVIGILANCE_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-adr-new',
    title: '1. Report Adverse Drug Reaction (ADR)',
    description: 'Log any suspected patient adverse drug reaction, linking symptoms, onset timeline, and suspect medication to identify safety patterns.',
    placement: 'bottom',
    route: '/quality/pharmacovigilance',
  },
  {
    targetId: 'tour-adr-filters',
    title: '2. Seriousness & Product Filters',
    description: 'Triage reported incidents by seriousness (Non-Serious, Serious, Life-Threatening, Fatal), submission status, and specific medication.',
    placement: 'bottom',
    route: '/quality/pharmacovigilance',
  },
  {
    targetId: 'tour-adr-table',
    title: '3. PPB PViMS Regulatory Escalation',
    description: 'Track clinical outcomes, link reports directly to batch numbers, and record Pharmacy and Poisons Board (PPB) regulatory transmission references.',
    placement: 'top',
    route: '/quality/pharmacovigilance',
  },
]

export const LICENCES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-licences-new',
    title: '1. Register Compliance Licence',
    description: 'Record PPB premises licences, pharmacist and pharmtech annual practice licences, single business permits, fire safety, and KRA TCC.',
    placement: 'bottom',
    route: '/quality/licences',
  },
  {
    targetId: 'tour-licences-kpis',
    title: '2. Expiry Warning Gates (60 Days)',
    description: 'Interactive compliance tiles categorizing licences as Expired, Expiring within 60 days, and Valid to prevent regulatory non-compliance.',
    placement: 'bottom',
    route: '/quality/licences',
  },
  {
    targetId: 'tour-licences-filters',
    title: '3. Holder & Category Filters',
    description: 'Filter credentials by Organization, Branch, Employee, or Supplier credentials with search by certificate reference.',
    placement: 'bottom',
    route: '/quality/licences',
  },
  {
    targetId: 'tour-licences-table',
    title: '4. Licence Register & Enforcement',
    description: 'Inspect document attachments and verify active status. The procurement engine automatically refuses purchase orders to suppliers with expired licences.',
    placement: 'top',
    route: '/quality/licences',
  },
]

export const SOPS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-sops-new',
    title: '1. New Controlled Document',
    description: 'Upload and register controlled standard operating procedures (SOPs), clinical policies, forms, and work instructions with automated versioning.',
    placement: 'bottom',
    route: '/quality/sops',
  },
  {
    targetId: 'tour-sops-filters',
    title: '2. Categories & Unread Filter',
    description: 'Browse documents by category and toggle "Only ones I have not acknowledged" to see procedures awaiting your compliance sign-off.',
    placement: 'bottom',
    route: '/quality/sops',
  },
  {
    targetId: 'tour-sops-table',
    title: '3. Staff Acknowledgement & Audit Log',
    description: 'Read controlled versions and submit binding electronic sign-offs. When an SOP is updated, staff acknowledgement is automatically reset for the new version.',
    placement: 'top',
    route: '/quality/sops',
  },
]

export const EMPLOYEES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-employees-new',
    title: '1. Register Employee',
    description: 'Add employees with job titles, departments, permanent/contract status, salary, and statutory details (KRA PIN, NSSF, SHIF, bank account).',
    placement: 'bottom',
    route: '/people/employees',
  },
  {
    targetId: 'tour-employees-filter',
    title: '2. Active Payroll Filter',
    description: 'Filter active payroll staff or inspect historical employee records. Bank details are write-only through secure API channels.',
    placement: 'bottom',
    route: '/people/employees',
  },
  {
    targetId: 'tour-employees-table',
    title: '3. Employee Master Directory',
    description: 'View employment dates, basic salaries, active status, and click any row to update employee profiles with full audit logging.',
    placement: 'top',
    route: '/people/employees',
  },
]

export const PAYROLL_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-payroll-bands',
    title: '1. Statutory Tax & Relief Bands',
    description: 'View active statutory calculation brackets for PAYE, NSSF, SHIF, Affordable Housing Levy, and personal relief caps in force.',
    placement: 'bottom',
    route: '/people/payroll',
  },
  {
    targetId: 'tour-payroll-open',
    title: '2. Open Monthly Payroll Run',
    description: 'Initialize a new payroll run for any month/year. Each run stamps the precise statutory band rules in effect for that period.',
    placement: 'bottom',
    route: '/people/payroll',
  },
  {
    targetId: 'tour-payroll-table',
    title: '3. Runs Roster & 4-Stage Workflow',
    description: 'Manage payroll runs through Compute (variable inputs) → Approve (segregation of duties) → Post Journal → Bank Payout.',
    placement: 'top',
    route: '/people/payroll',
  },
]

export const LEAVE_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-leave-tabs',
    title: '1. Requests & Entitlement Balances',
    description: 'Switch between individual leave requests and the annual entitlement balances matrix (Annual, Sick, Maternity, Paternity, Compassionate).',
    placement: 'bottom',
    route: '/people/leave',
  },
  {
    targetId: 'tour-leave-new',
    title: '2. New Leave Request',
    description: 'Submit leave applications with automatic Monday–Friday working day calculation. Staff cannot approve their own requests.',
    placement: 'bottom',
    route: '/people/leave',
  },
  {
    targetId: 'tour-leave-filters',
    title: '3. Request Status & Department Filter',
    description: 'Filter pending manager approvals, approved leaves, and past requests by employee, leave type, and calendar year.',
    placement: 'bottom',
    route: '/people/leave',
  },
  {
    targetId: 'tour-leave-table',
    title: '4. Leave Requests & Approval Pipeline',
    description: 'Review request dates, working days, supporting documents, and click any row to approve, reject with reason, or cancel.',
    placement: 'top',
    route: '/people/leave',
  },
]

export const PAYROLL_BANDS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-payroll-bands-add',
    title: '1. Add New Statutory Band',
    description: 'Configure new tax brackets, contribution tiers, or relief limits when tax authorities (KRA, NSSF, SHIF) enact new legislation.',
    placement: 'bottom',
    route: '/admin/payroll-bands',
  },
  {
    targetId: 'tour-payroll-bands-list',
    title: '2. Active Tax & Levy Brackets',
    description: 'Inspect rate percentages, upper/lower bounds, and effective dates. Past processed payroll runs preserve their historical rate snapshots.',
    placement: 'top',
    route: '/admin/payroll-bands',
  },
]

export const REPORTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-reports-catalogue',
    title: '1. Report Categories & Catalogue',
    description: 'Explore pre-built financial, sales, inventory, procurement, and tax compliance reports. Each report reads authoritative posted transactions.',
    placement: 'bottom',
    route: '/reports/catalogue',
  },
  {
    targetId: 'tour-reports-filters',
    title: '2. Dynamic Report Parameters',
    description: 'Customize date intervals, customer accounts, medication items, stores, or categories. Run queries on-demand or download clean CSV exports.',
    placement: 'bottom',
    route: '/reports/catalogue',
  },
  {
    targetId: 'tour-reports-table',
    title: '3. Data Grid & Grand Totals',
    description: 'Inspect typed tabular rows with sorting and totals. Financial and cost data are strictly restricted by user permissions.',
    placement: 'top',
    route: '/reports/catalogue',
  },
]

export const ANALYTICS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-analytics-filters',
    title: '1. Time Window & Quick Presets',
    description: 'Set custom reporting date ranges or select 7-day, 30-day, or 90-day presets to update all analytics charts synchronously.',
    placement: 'bottom',
    route: '/reports/analytics',
  },
  {
    targetId: 'tour-analytics-charts',
    title: '2. Executive KPI Charts & Breakdown',
    description: 'Interactive visualizations for Net Sales & Gross Profit, Sales by Mode (Retail vs. Wholesale), Top 10 Medications, Inventory Valuation, Expiry Risk, and AR Debt Aging.',
    placement: 'top',
    route: '/reports/analytics',
  },
]

export const SCHEDULED_REPORTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-scheduled-new',
    title: '1. Schedule Automated Report',
    description: 'Configure automated email dispatches of any catalogue report (Daily, Weekly, or Monthly) to executive or finance team members.',
    placement: 'bottom',
    route: '/reports/scheduled',
  },
  {
    targetId: 'tour-scheduled-filter',
    title: '2. Status & Execution Triage',
    description: 'Monitor active schedules, paused dispatches, and check error logs if email delivery fails.',
    placement: 'bottom',
    route: '/reports/scheduled',
  },
  {
    targetId: 'tour-scheduled-table',
    title: '3. Schedules Registry & Run Now',
    description: 'Review next execution timestamps, recipient rosters, and click any row to edit or immediately trigger an on-demand delivery test.',
    placement: 'top',
    route: '/reports/scheduled',
  },
]

export const USERS_ROLES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-users-tabs',
    title: '1. Users & Global Roles',
    description: 'Switch between individual user accounts and global role permission definitions. Role definitions are organisation-wide while assignments are per-branch.',
    placement: 'bottom',
    route: '/admin/users-roles',
  },
  {
    targetId: 'tour-users-filters',
    title: '2. Search & Account Status',
    description: 'Search staff by name, email or username, and filter by active/locked status.',
    placement: 'bottom',
    route: '/admin/users-roles',
  },
  {
    targetId: 'tour-users-new',
    title: '3. Onboard Staff Account',
    description: 'Create a new employee user account, enforce strong passwords, require MFA, and allocate branch-specific security roles.',
    placement: 'left',
    route: '/admin/users-roles',
  },
  {
    targetId: 'tour-users-table',
    title: '4. Staff Accounts & Security Triage',
    description: 'Inspect assigned branch roles, active MFA status, and click any user to manage password resets, unlock locked accounts, or edit roles.',
    placement: 'top',
    route: '/admin/users-roles',
  },
]

export const BRANCHES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-branches-new',
    title: '1. Register Branch Facility',
    description: 'Create a new physical pharmacy branch or regional warehouse facility with county location and operational settings.',
    placement: 'left',
    route: '/admin/branches',
  },
  {
    targetId: 'tour-branches-table',
    title: '2. Branch & Store Inventory Hierarchy',
    description: 'Every stock balance and sale belongs to a store room inside a branch. Click any branch to configure Retail, Wholesale, and Dispensing modes, and manage internal stores (Main, Cold Room, Quarantine).',
    placement: 'top',
    route: '/admin/branches',
  },
]

export const PERMISSIONS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-permissions-filter',
    title: '1. Filter Permission Capabilities',
    description: 'Search granular security permissions (e.g. stock.adjust, price.manage, audit.view) across the entire system catalogue.',
    placement: 'bottom',
    route: '/admin/permissions',
  },
  {
    targetId: 'tour-permissions-matrix',
    title: '2. Role × Permission Security Matrix',
    description: 'Audit which system roles possess specific operational and financial capabilities. To adjust a role permissions set, click "Edit roles" at the top.',
    placement: 'top',
    route: '/admin/permissions',
  },
]

export const SETTINGS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-settings-org',
    title: '1. Organisation Identity & Regulatory Profile',
    description: 'Inspect your registered pharmaceutical company name, corporate tax PIN, and headquarters contact details.',
    placement: 'bottom',
    route: '/admin/settings',
  },
  {
    targetId: 'tour-settings-scopes',
    title: '2. Scoped Configuration Parameters',
    description: 'Settings are strictly versioned. A branch setting overrides the organisation default for that location only. Configure POS modes, rounding rules, and system timeouts.',
    placement: 'top',
    route: '/admin/settings',
  },
]

export const SECURITY_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-security-mfa',
    title: '1. Two-Factor Authentication (TOTP)',
    description: 'Enforce multi-factor authentication (MFA) via Google Authenticator or Microsoft Authenticator for finance, admin, and clinical audit access.',
    placement: 'bottom',
    route: '/admin/security',
  },
]

export const NUMBER_SEQUENCES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-sequences-table',
    title: '1. Gapless Document Numbering',
    description: 'Inspect authoritative sequence counters for Invoices, Goods Receipts, Requisitions, and Adjustments. Document numbers are issued inside posting transactions so rolled-back operations never burn a number.',
    placement: 'top',
    route: '/admin/number-sequences',
  },
]

export const AUDIT_LOG_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-audit-filters',
    title: '1. Audit Trail Filter & Search',
    description: 'Search immutable audit events by user, action, entity type (sale, user, batch, payment), reference ID, or date range.',
    placement: 'bottom',
    route: '/admin/audit-log',
  },
  {
    targetId: 'tour-audit-table',
    title: '2. Immutable Change Log & Diff Viewer',
    description: 'Select any row to inspect the full Before and After JSON payloads, exact changed fields, user IP address, and timestamp.',
    placement: 'top',
    route: '/admin/audit-log',
  },
]

export const SYSTEM_HEALTH_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-health-kpis',
    title: '1. Health Heartbeats & Subsystems',
    description: 'Live status indicators for Database latency, asynchronous Queue backlogs, Scheduler cron heartbeats, Disk space, and automated Backups.',
    placement: 'bottom',
    route: '/admin/system-health',
  },
  {
    targetId: 'tour-health-reconcile',
    title: '2. Stock Ledger vs. General Ledger Reconciliation',
    description: 'Automated drift monitoring between the physical inventory valuation and general ledger inventory accounts. Any mismatch is flagged immediately.',
    placement: 'top',
    route: '/admin/system-health',
  },
  {
    targetId: 'tour-health-jobs',
    title: '3. Failed Background Jobs & Queue Recovery',
    description: 'Inspect any background job errors (eTIMS transmission, mail, nightly scans) with 1-click retry and discard controls.',
    placement: 'top',
    route: '/admin/system-health',
  },
]

export const BACKUP_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-backup-run',
    title: '1. On-Demand Database & File Backups',
    description: 'Trigger immediate compressed MySQL database dumps or full system archive zips before software updates or major audits.',
    placement: 'bottom',
    route: '/admin/backup',
  },
  {
    targetId: 'tour-backup-stats',
    title: '2. Backup Metrics & Retention Policy',
    description: 'Review the latest backup timestamp, total storage consumed, and automated retention window.',
    placement: 'bottom',
    route: '/admin/backup',
  },
  {
    targetId: 'tour-backup-table',
    title: '3. Downloadable Backup Archives',
    description: 'Download recent database snapshots to store securely off-site on encrypted media or dedicated cloud cold storage.',
    placement: 'top',
    route: '/admin/backup',
  },
]

export const SYNC_CENTRE_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-sync-connectivity',
    title: '1. Network & Server Connectivity',
    description: 'Real-time telemetry showing browser network status, server round-trip latency (ping), and offline selling queue status.',
    placement: 'bottom',
    route: '/admin/sync-centre',
  },
  {
    targetId: 'tour-sync-etims',
    title: '2. KRA eTIMS Fiscal Submission Queue',
    description: 'Monitor tax transmission metrics: Pending, Submitted, Failed, and Unconfigured documents.',
    placement: 'bottom',
    route: '/admin/sync-centre',
  },
  {
    targetId: 'tour-sync-terminals',
    title: '3. POS Terminal Activity (Last 24 Hours)',
    description: 'Track active point-of-sale terminals, transaction volume, void counts, and last posted timestamps across branch tills.',
    placement: 'top',
    route: '/admin/sync-centre',
  },
]

export const ALERTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-alerts-categories',
    title: '1. Standing Risk Categories',
    description: 'High-priority risk summaries: Overdue customer receivables, unpaid supplier payables, and expiring medication shelf-life risk.',
    placement: 'bottom',
    route: '/admin/alerts',
  },
  {
    targetId: 'tour-alerts-filters',
    title: '2. Triage & Severity Filters',
    description: 'Filter alerts by Critical, Warning, or Info, and toggle acknowledged items.',
    placement: 'bottom',
    route: '/admin/alerts',
  },
  {
    targetId: 'tour-alerts-table',
    title: '3. Actionable Alert Registry',
    description: 'Jump directly to the affected invoice or medication batch, or mark alerts as seen once addressed.',
    placement: 'top',
    route: '/admin/alerts',
  },
]

export const DEPLOYMENTS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-deploy-actions',
    title: '1. Update Checks & Deployment Actions',
    description: 'Check remote GitHub repository for new commits, tags, or patches, and execute zero-downtime deployment pipelines.',
    placement: 'left',
    route: '/admin/deployments',
  },
  {
    targetId: 'tour-deploy-status',
    title: '2. Active Code Commit & Release Status',
    description: 'Inspect currently deployed git commit hash, branch, release tags, and uncommitted server modifications.',
    placement: 'top',
    route: '/admin/deployments',
  },
]

export const PRICING_RULES_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-pricing-tabs',
    title: '1. Pricing Engine Rule Tiers',
    description: 'Configure Promotions, Quantity Price Breaks, Discount Policies, Cashier Discount Authority, and Customer Contract Prices.',
    placement: 'bottom',
    route: '/admin/pricing-rules',
  },
  {
    targetId: 'tour-pricing-content',
    title: '2. Rule Editor & Price Simulator',
    description: 'Manage prioritized discount rules (cheapest valid rule wins) and test calculations with the built-in pricing simulator.',
    placement: 'top',
    route: '/admin/pricing-rules',
  },
]

/**
 * Global Central Tour Registry
 * To add a tour to ANY page in the application, just add a single entry here!
 * AppLayout automatically detects the route and renders the white prompt card and spotlight tour.
 */
export const REGISTERED_TOURS: TourDefinition[] = [
  {
    id: 'dashboard',
    route: '/dashboard',
    title: 'Dashboard Overview',
    subtitle: 'Learn the core navigation, universal search, branch switching, and operations queue',
    steps: DEFAULT_TOUR_STEPS,
  },
  {
    id: 'pos',
    route: '/sell/pos',
    title: 'PharmaPoint POS Terminal',
    subtitle: 'Master fast drug barcode scanning, stock room selection, patient accounts, and payment checkout',
    steps: POS_TOUR_STEPS,
  },
  {
    id: 'quotations',
    route: '/sell/quotations',
    title: 'Wholesale Quotations',
    subtitle: 'Learn how to create tier-priced quotes, download PDFs, and 1-click convert to sales orders',
    steps: QUOTATIONS_TOUR_STEPS,
  },
  {
    id: 'sales-orders',
    route: '/sell/sales-orders',
    title: 'Wholesale Sales Orders',
    subtitle: 'Learn how to manage order fulfillment pipelines, credit limits, and warehouse inventory reservations',
    steps: SALES_ORDERS_TOUR_STEPS,
  },
  {
    id: 'invoices',
    route: '/sell/invoices',
    title: 'Sales Invoices & Receipts',
    subtitle: 'Learn how to inspect posted retail receipts, wholesale invoices, and eTIMS tax certificates',
    steps: INVOICES_TOUR_STEPS,
  },
  {
    id: 'returns',
    route: '/sell/returns',
    title: 'Returns & Reverse Logistics',
    subtitle: 'Learn how customer return inspections, quarantine dispositions, and credit notes work',
    steps: RETURNS_TOUR_STEPS,
  },
  {
    id: 'customer-statements',
    route: '/sell/statements',
    title: 'Customer Statements & Debt Aging',
    subtitle: 'Learn how to review customer accounts, trace 30/60/90+ day debt aging, and print statements',
    steps: CUSTOMER_STATEMENTS_TOUR_STEPS,
  },
  {
    id: 'products',
    route: '/inventory/products',
    title: 'Medication & Product Catalogue',
    subtitle: 'Learn how to register pharmaceutical SKUs, packaging UOM factors, and reorder points',
    steps: PRODUCTS_TOUR_STEPS,
  },
  {
    id: 'stock-on-hand',
    route: '/inventory/stock-on-hand',
    title: 'Stock on Hand',
    subtitle: 'Learn how to inspect authoritative 8-state live balances across all store rooms',
    steps: STOCK_ON_HAND_TOUR_STEPS,
  },
  {
    id: 'batches',
    route: '/inventory/batches',
    title: 'Batches & Expiry Tracking',
    subtitle: 'Learn how to monitor 30/90/180-day expiry horizons and batch movement history',
    steps: BATCHES_TOUR_STEPS,
  },
  {
    id: 'counts',
    route: '/inventory/counts',
    title: 'Physical Stock Counts',
    subtitle: 'Learn how to run blind counting, variance reviews, and supervisor sign-offs',
    steps: COUNTS_TOUR_STEPS,
  },
  {
    id: 'transfers',
    route: '/inventory/transfers',
    title: 'Inter-Store Transfers',
    subtitle: 'Learn how to dispatch and receive custody-tracked stock transfers between stores',
    steps: TRANSFERS_TOUR_STEPS,
  },
  {
    id: 'adjustments',
    route: '/inventory/adjustments',
    title: 'Stock Adjustments',
    subtitle: 'Learn how to submit reason-coded stock write-offs and approve value adjustments',
    steps: ADJUSTMENTS_TOUR_STEPS,
  },
  {
    id: 'stock-ledger',
    route: '/inventory/stock-ledger',
    title: 'Stock Ledger & Running Balances',
    subtitle: 'Learn how to trace append-only stock transactions, movement types, and batch running balances',
    steps: STOCK_LEDGER_TOUR_STEPS,
  },
  {
    id: 'valuation',
    route: '/inventory/valuation',
    title: 'Stock Valuation (Cost & Retail)',
    subtitle: 'Learn how inventory on-hand balances are valued at weighted average cost and retail prices',
    steps: VALUATION_TOUR_STEPS,
  },
  {
    id: 'opening-stock',
    route: '/inventory/opening-stock',
    title: 'Opening Stock Go-Live Import',
    subtitle: 'Learn how initial stock balances are uploaded via CSV, validated against master records, and posted',
    steps: OPENING_STOCK_TOUR_STEPS,
  },
  {
    id: 'requisitions',
    route: '/buy/requisitions',
    title: 'Purchase Requisitions & Demand',
    subtitle: 'Learn how to raise departmental medicine demands, review approvals, and use the reorder advisor',
    steps: REQUISITIONS_TOUR_STEPS,
  },
  {
    id: 'purchase-orders',
    route: '/buy/purchase-orders',
    title: 'Purchase Orders (PO)',
    subtitle: 'Learn how to raise supplier POs, manage approval workflows, track deliveries, and print official order PDFs',
    steps: PURCHASE_ORDERS_TOUR_STEPS,
  },
  {
    id: 'goods-receipts',
    route: '/buy/goods-receipts',
    title: 'Goods Receipts (GRN)',
    subtitle: 'Learn how to verify incoming supplier shipments, log cold chain temperatures, and intake batches into quarantine',
    steps: GOODS_RECEIPTS_TOUR_STEPS,
  },
  {
    id: 'supplier-invoices',
    route: '/buy/supplier-invoices',
    title: 'Supplier Invoices & 3-Way Match',
    subtitle: 'Learn how to record vendor commercial invoices and verify PO vs GRN vs Invoice to commit payables',
    steps: SUPPLIER_INVOICES_TOUR_STEPS,
  },
  {
    id: 'suppliers',
    route: '/buy/suppliers',
    title: 'Suppliers & Vendor Compliance',
    subtitle: 'Learn how to onboard licensed distributors, track PPB regulatory license expiries, and manage payment terms',
    steps: SUPPLIERS_TOUR_STEPS,
  },
  {
    id: 'pick-lists',
    route: '/warehouse/pick-lists',
    title: 'Warehouse Pick Lists',
    subtitle: 'Learn how to generate warehouse pick lists, fulfill order lines from bin shelves, and complete orders for dispatch',
    steps: PICK_LISTS_TOUR_STEPS,
  },
  {
    id: 'packing',
    route: '/warehouse/packing',
    title: 'Order Packing & Parcels',
    subtitle: 'Learn how to pack picked medications into parcels, record parcel quantities, and prepare totes for dispatch',
    steps: PACKING_TOUR_STEPS,
  },
  {
    id: 'dispatch',
    route: '/warehouse/dispatch',
    title: 'Dispatch & Vehicle Waybills',
    subtitle: 'Learn how to assign drivers, log vehicle registration numbers, collect on-delivery tender, and post official delivery notes',
    steps: DISPATCH_TOUR_STEPS,
  },
  {
    id: 'deliveries',
    route: '/warehouse/deliveries',
    title: 'Deliveries & Proof of Delivery (ePOD)',
    subtitle: 'Learn how to track shipment dispatches, monitor delivery status, and capture electronic recipient sign-offs',
    steps: DELIVERIES_TOUR_STEPS,
  },
  {
    id: 'locations',
    route: '/warehouse/locations',
    title: 'Warehouse Storage Locations',
    subtitle: 'Learn how to organize pharmacy storerooms into aisles, racks, and cold-shelf bins for precise item tracking',
    steps: LOCATIONS_TOUR_STEPS,
  },
  {
    id: 'customers',
    route: '/customers/list',
    title: 'Customer Directory & Accounts',
    subtitle: 'Learn how to manage hospital, clinic, pharmacy and patient accounts, credit terms, and pricing tiers',
    steps: CUSTOMERS_TOUR_STEPS,
  },
  {
    id: 'tiers',
    route: '/customers/tiers',
    title: 'Pricing Tiers & Custom Price Lists',
    subtitle: 'Learn how to configure wholesale, clinic, and tender tier pricing and item-specific contract rates',
    steps: TIERS_TOUR_STEPS,
  },
  {
    id: 'credit-control',
    route: '/customers/credit-control',
    title: 'Credit Control & Debt Risk Management',
    subtitle: 'Learn how to manage credit limits, payment grace periods, credit holds, and outstanding balances',
    steps: CREDIT_CONTROL_TOUR_STEPS,
  },
  {
    id: 'contacts',
    route: '/customers/contacts',
    title: 'Contacts & Communication Logs',
    subtitle: 'Learn how to track customer contacts, roles, direct phone lines, and log customer communications',
    steps: CONTACTS_TOUR_STEPS,
  },
  {
    id: 'receivables',
    route: '/finance/receivables',
    title: 'Accounts Receivable & Ageing Matrix',
    subtitle: 'Learn how to manage customer debt aging, credit exposures, and record incoming patient payments',
    steps: RECEIVABLES_TOUR_STEPS,
  },
  {
    id: 'payables',
    route: '/finance/payables',
    title: 'Accounts Payable & Supplier Settlements',
    subtitle: 'Learn how to review supplier payables resulting from 3-way matched GRNs and disburse payments',
    steps: PAYABLES_TOUR_STEPS,
  },
  {
    id: 'reconciliation',
    route: '/finance/reconciliation',
    title: 'Bank & M-PESA Reconciliation',
    subtitle: 'Learn how to match customer receipts with bank/M-PESA statement lines and keep clear audit trails',
    steps: RECONCILIATION_TOUR_STEPS,
  },
  {
    id: 'journals',
    route: '/finance/journals',
    title: 'General Journal Entries',
    subtitle: 'Learn how to inspect the append-only double-entry general ledger and post balanced manual journal adjustments',
    steps: JOURNALS_TOUR_STEPS,
  },
  {
    id: 'chart-of-accounts',
    route: '/finance/chart-of-accounts',
    title: 'Chart of Accounts (COA)',
    subtitle: 'Learn how the nested chart of accounts, system roles, and posted debit/credit balances work',
    steps: CHART_OF_ACCOUNTS_TOUR_STEPS,
  },
  {
    id: 'tax-centre',
    route: '/finance/tax-centre',
    title: 'eTIMS Tax & KRA Compliance Centre',
    subtitle: 'Learn how to monitor real-time KRA fiscal submission queues, inspect control codes, and retry transmissions',
    steps: TAX_CENTRE_TOUR_STEPS,
  },
  {
    id: 'periods',
    route: '/finance/periods',
    title: 'Fiscal Periods & Month-End Closing',
    subtitle: 'Learn how fiscal periods are monitored and how automated integrity checklists validate balanced ledgers before locking',
    steps: PERIODS_TOUR_STEPS,
  },
  {
    id: 'statements',
    route: '/finance/statements',
    title: 'Trial Balance & Financial Statements',
    subtitle: 'Learn how to verify total debits and credits across all accounts to guarantee balanced books',
    steps: STATEMENTS_TOUR_STEPS,
  },
  {
    id: 'quarantine',
    route: '/quality/quarantine',
    title: 'Quality Quarantine & QC Gates',
    subtitle: 'Learn how to manage holding states, incoming goods QC inspection, and pharmacist release audits',
    steps: QUARANTINE_TOUR_STEPS,
  },
  {
    id: 'waste',
    route: '/quality/waste',
    title: 'Waste & Pharmaceutical Destruction',
    subtitle: 'Learn how to draft disposal batches, execute two-witness approvals, and post write-offs',
    steps: WASTE_TOUR_STEPS,
  },
  {
    id: 'recalls',
    route: '/quality/recalls',
    title: 'Batch Recalls & Traceability',
    subtitle: 'Learn how to initiate drug recalls, lock suspect batches, trace customers, and track recovery rates',
    steps: RECALLS_TOUR_STEPS,
  },
  {
    id: 'cold-chain',
    route: '/quality/cold-chain',
    title: 'Cold Chain & Temperature Excursions',
    subtitle: 'Learn how to monitor thermal ranges, log manual/logger readings, and triage temperature excursions',
    steps: COLD_CHAIN_TOUR_STEPS,
  },
  {
    id: 'pharmacovigilance',
    route: '/quality/pharmacovigilance',
    title: 'Pharmacovigilance & ADR Reporting',
    subtitle: 'Learn how to capture adverse drug reactions, trace suspect batches, and record PPB references',
    steps: PHARMACOVIGILANCE_TOUR_STEPS,
  },
  {
    id: 'licences',
    route: '/quality/licences',
    title: 'Licences & Regulatory Compliance',
    subtitle: 'Learn how to monitor premises, practising, and supplier licences with automated 60-day alerts',
    steps: LICENCES_TOUR_STEPS,
  },
  {
    id: 'sops',
    route: '/quality/sops',
    title: 'Controlled SOPs & Digital Acknowledgement',
    subtitle: 'Learn how to maintain versioned standard operating procedures and track staff compliance sign-offs',
    steps: SOPS_TOUR_STEPS,
  },
  {
    id: 'employees',
    route: '/people/employees',
    title: 'Employee Master Directory',
    subtitle: 'Learn how to manage employee master records, statutory IDs, job titles, and audited payroll profiles',
    steps: EMPLOYEES_TOUR_STEPS,
  },
  {
    id: 'payroll',
    route: '/people/payroll',
    title: 'Monthly Payroll Processing',
    subtitle: 'Learn how to open payroll runs, compute statutory deductions, enforce dual approvals, and post journals',
    steps: PAYROLL_TOUR_STEPS,
  },
  {
    id: 'leave',
    route: '/people/leave',
    title: 'Leave & Attendance Management',
    subtitle: 'Learn how working-day leave requests, balance tracking, and segregated managerial approvals work',
    steps: LEAVE_TOUR_STEPS,
  },
  {
    id: 'payroll-bands',
    route: '/admin/payroll-bands',
    title: 'Statutory Payroll Bands & Tax Rates',
    subtitle: 'Learn how statutory PAYE, NSSF, SHIF, and Housing Levy tiers are configured and version-locked',
    steps: PAYROLL_BANDS_TOUR_STEPS,
  },
  {
    id: 'reports-catalogue',
    route: '/reports/catalogue',
    title: 'Central Report Catalogue',
    subtitle: 'Learn how to generate dynamic pharmaceutical reports, apply filters, and export verified CSV data',
    steps: REPORTS_TOUR_STEPS,
  },
  {
    id: 'reports-analytics',
    route: '/reports/analytics',
    title: 'Executive Analytics & BI Visualizations',
    subtitle: 'Learn how to monitor revenue trends, margins, retail vs. wholesale performance, and inventory aging',
    steps: ANALYTICS_TOUR_STEPS,
  },
  {
    id: 'reports-scheduled',
    route: '/reports/scheduled',
    title: 'Scheduled Email Report Dispatches',
    subtitle: 'Learn how to set up automated daily, weekly, or monthly report emails to stakeholders and management',
    steps: SCHEDULED_REPORTS_TOUR_STEPS,
  },
  {
    id: 'users-roles',
    route: '/admin/users-roles',
    title: 'Users & Roles Management',
    subtitle: 'Learn how to manage user accounts, assign branch-specific roles, enforce MFA, and unlock accounts',
    steps: USERS_ROLES_TOUR_STEPS,
  },
  {
    id: 'branches',
    route: '/admin/branches',
    title: 'Branches & Internal Stores',
    subtitle: 'Learn how branch facilities, operational sale modes, and store inventory rooms (Main, Cold, Quarantine) are configured',
    steps: BRANCHES_TOUR_STEPS,
  },
  {
    id: 'permissions',
    route: '/admin/permissions',
    title: 'Role × Permission Security Matrix',
    subtitle: 'Audit which system roles possess specific operational, financial, and clinical capabilities',
    steps: PERMISSIONS_TOUR_STEPS,
  },
  {
    id: 'settings',
    route: '/admin/settings',
    title: 'Application & Branch Settings',
    subtitle: 'Learn how versioned configuration parameters and branch overrides are managed without in-place edits',
    steps: SETTINGS_TOUR_STEPS,
  },
  {
    id: 'security',
    route: '/admin/security',
    title: 'Account Security & Two-Factor Auth',
    subtitle: 'Enrol and manage authenticator TOTP protections for privileged administrative and finance actions',
    steps: SECURITY_TOUR_STEPS,
  },
  {
    id: 'number-sequences',
    route: '/admin/number-sequences',
    title: 'Gapless Number Sequences',
    subtitle: 'Inspect authoritative transaction counters and gapless document numbering sequences',
    steps: NUMBER_SEQUENCES_TOUR_STEPS,
  },
  {
    id: 'audit-log',
    route: '/admin/audit-log',
    title: 'Immutable System Audit Trail',
    subtitle: 'Track who did what, when, with complete before-and-after JSON snapshots and IP logs',
    steps: AUDIT_LOG_TOUR_STEPS,
  },
  {
    id: 'system-health',
    route: '/admin/system-health',
    title: 'System Health & Subsystem Telemetry',
    subtitle: 'Monitor database latency, queue backlogs, ledger reconciliation drift, and failed background jobs',
    steps: SYSTEM_HEALTH_TOUR_STEPS,
  },
  {
    id: 'backup',
    route: '/admin/backup',
    title: 'Database & System Backups',
    subtitle: 'Generate on-demand compressed MySQL database snapshots and manage off-site disaster recovery',
    steps: BACKUP_TOUR_STEPS,
  },
  {
    id: 'sync-centre',
    route: '/admin/sync-centre',
    title: 'Sync Centre & Telemetry',
    subtitle: 'Check device-to-server connectivity, round-trip latency, eTIMS queues, and 24h POS terminal transactions',
    steps: SYNC_CENTRE_TOUR_STEPS,
  },
  {
    id: 'alerts',
    route: '/admin/alerts',
    title: 'Standing Alerts & Operational Triage',
    subtitle: 'Review overdue customer debt, supplier invoice deadlines, and expiring drug shelf-life risk',
    steps: ALERTS_TOUR_STEPS,
  },
  {
    id: 'deployments',
    route: '/admin/deployments',
    title: 'Deployments & Version Management',
    subtitle: 'Check GitHub for remote updates, inspect git commit statuses, and run zero-downtime deploys',
    steps: DEPLOYMENTS_TOUR_STEPS,
  },
  {
    id: 'pricing-rules',
    route: '/admin/pricing-rules',
    title: 'Pricing Engine Rules & Simulator',
    subtitle: 'Configure promotions, quantity breaks, discount authority, and test calculations in the pricing simulator',
    steps: PRICING_RULES_TOUR_STEPS,
  },
]

export function getTourForRoute(pathname: string): TourDefinition | null {
  return REGISTERED_TOURS.find((t) => pathname === t.route || pathname.startsWith(t.route + '/')) ?? null
}
