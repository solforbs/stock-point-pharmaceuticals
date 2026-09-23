// Shapes mirror the Laravel API (routes/api.php + app/Http/Controllers/Api).
// Money and quantities arrive as decimal strings ("1000.0000") and stay
// strings: the server quote is authoritative; the SPA never does float
// arithmetic on anything that posts.

export type Decimal = string

export type Paginated<T> = {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export type SaleMode = 'RETAIL' | 'WHOLESALE' | 'DISPENSING'

export type Branch = {
  id: string
  code: string
  name: string
  retail_enabled: boolean
  wholesale_enabled: boolean
  dispensing_enabled: boolean
  sale_modes: SaleMode[]
  default_sale_mode: SaleMode | null
}

export type CurrentUser = {
  id: number
  name: string
  email: string
  username?: string | null
  mfa_required?: boolean
  roles?: { id: number; name: string }[]
  permissions: string[]
  /** The institution this account belongs to; every screen shows only its data. */
  organisation?: { id: string; name: string; legal_name: string | null } | null
  /** Runs the platform itself: backups, deployment, system health. */
  is_platform_admin?: boolean
  /** Drives the trial / lapsed banner; null for a platform-only account. */
  subscription?: SubscriptionSummary | null
  active_branch_id: string | null
  active_branch: Branch | null
  branches: Branch[]
  sale_modes: SaleMode[]
  default_sale_mode: SaleMode | null
}

export type Store = {
  id: string
  branch_id: string
  code: string
  name: string
  store_type: string
  is_sellable: boolean
}

export type Uom = { id: string; code: string; name: string; is_base_candidate?: boolean }

export type ProductUom = {
  id: string
  product_id: string
  uom_id: string
  factor_to_base: number
  is_base: boolean
  is_purchase: boolean
  is_sales: boolean
  is_default_sales: boolean
  barcode: string | null
  uom?: Uom
}

export type NamedRef = { id: string; code?: string; name: string }

/** A product as a document line names it: enough to tell the exact item apart. */
export type ItemRef = NamedRef & { generic_name?: string | null; strength?: string | null; description?: string | null; base_uom?: { id: string; code: string } | null }

export type ReturnReason =
  | 'DAMAGED' | 'EXPIRED' | 'SHORT_EXPIRY' | 'WRONG_ITEM' | 'EXCESS_QUANTITY' | 'QUALITY_COMPLAINT'
  | 'ADVERSE_REACTION' | 'RECALL' | 'NOT_REQUIRED' | 'NOT_ORDERED' | 'SLOW_MOVING' | 'OTHER'

export type ProductPrice = {
  id: string
  price_list_id: string
  uom_id: string | null
  factor_type: string
  unit_price: Decimal
  factor_value: Decimal | null
  effective_from: string | null
  effective_to: string | null
  price_list?: { id: string; code: string; name: string; sale_mode?: string } | null
}

export type Product = {
  id: string
  code: string
  sku: string | null
  gtin: string | null
  name: string
  generic_name: string | null
  strength: string | null
  /** Key features, for non-pharma items that have no strength. */
  description?: string | null
  is_discrete: boolean
  pack_integrity: boolean
  requires_batch: boolean
  reorder_point: Decimal
  safety_stock: Decimal
  lead_time_days: number
  default_price: Decimal | null
  is_active: boolean
  base_uom_id: string
  base_uom?: Uom | null
  uoms?: ProductUom[]
  category?: NamedRef | null
  manufacturer?: NamedRef | null
  dosage_form?: NamedRef | null
  storage_condition?: StorageCondition | null
  tax_code_id?: string | null
  tax_code?: NamedRef | null
  prices?: ProductPrice[]
  /** Active-branch stock, present when the user holds stock.view (GET /api/products). */
  stock?: {
    on_hand: Decimal
    reserved: Decimal
    free_to_sell: Decimal
    nearest_expiry: string | null
    by_store?: { store_id: string; store_code: string; is_sellable: boolean; on_hand: Decimal; free_to_sell: Decimal }[]
  }
  /** The distributor's last price, present only with product.cost.view. */
  last_purchase?: LastPurchase | null
}

export type LastPurchase = {
  unit_cost_per_base: Decimal
  unit_cost: Decimal
  /** The supplier's gross trade price and purchase discount, when captured on the receipt. */
  trade_price: Decimal | null
  /** The same trade price expressed per base unit, for the pricing model table. */
  trade_price_per_base?: Decimal | null
  discount_pct: Decimal | null
  uom_code: string | null
  supplier: string | null
  received_at: string | null
  grn_number: string
}

export type InsightPrice = { unit_price: Decimal; gross_price: Decimal; floor_price: Decimal | null; source: string }

export type UsualPrice = { unit_price: Decimal; uom_id: string; uom_code: string | null; times: number; last_price: Decimal; last_sold_at: string }

/** GET /api/products/{id}/insight — everything a cashier needs beside one cart line. */
export type ProductInsight = {
  product: { id: string; code: string; name: string; generic_name: string | null; strength: string | null }
  tax: { code: string | null; name: string | null; rate_pct: Decimal; treatment: 'STANDARD' | 'ZERO_RATED' | 'EXEMPT' | 'NOT_SET' }
  min_margin_pct: Decimal | null
  uoms: {
    uom_id: string
    uom_code: string | null
    factor_to_base: number
    is_default_sales: boolean
    retail: InsightPrice | null
    trade: InsightPrice | null
    unit_cost: Decimal | null
    retail_markup_pct: Decimal | null
  }[]
  buying: { wac_per_base: Decimal | null; last_purchase: LastPurchase | null } | null
  usual_price: { customer: UsualPrice | null; everyone: UsualPrice | null }
  alternatives: { match: 'SAME_GENERIC' | 'SAME_CATEGORY'; free_in_store: Decimal; free_in_branch: Decimal; product: Product }[]
}

export type Alert = {
  id: string
  branch_id: string
  alert_key: string
  category: 'RECEIVABLE' | 'PAYABLE' | 'EXPIRY' | 'LICENCE'
  type: string
  severity: 'INFO' | 'WARNING' | 'CRITICAL'
  title: string
  detail: string | null
  entity_type: string | null
  entity_id: string | null
  due_date: string | null
  days_to_due: number | null
  amount: Decimal | null
  link: string | null
  permission: string
  first_seen_at: string
  last_seen_at: string
  resolved_at: string | null
  acknowledged_by: number | null
  acknowledged_at: string | null
}

export type AlertSummary = {
  total: number
  critical: number
  by_category: Record<Alert['category'], { total: number; critical: number; warning: number; info: number }>
  last_scan_at: string | null
}

export type DosageForm = { id: string; code: string; name: string }

export type StorageCondition = {
  id: string
  code: string
  name: string
  min_temp_c: Decimal | null
  max_temp_c: Decimal | null
  max_excursion_minutes: number | null
  requires_cold_chain: boolean
}

export type StockBatchRow = {
  batch_id: string
  batch_number: string
  expiry_date: string
  status: string
  on_hand: Decimal
  reserved: Decimal
  wac?: Decimal
}

export type StockStateRow = {
  product_id: string
  product_code: string
  product_name: string
  category_id: string | null
  category_name: string | null
  store_id: string
  store_code: string
  store_name: string
  on_hand: Decimal
  reserved: Decimal
  free_to_sell: Decimal
  in_transit: Decimal
  pending_qc: Decimal
  quarantined: Decimal
  expired: Decimal
  recalled: Decimal
  on_order: Decimal
  value_at_cost?: Decimal
  nearest_expiry: string | null
  reorder_point: Decimal
  batches: StockBatchRow[]
}

export type ProductStock = { product: { id: string; code: string; name: string }; stores: StockStateRow[] }

export type LedgerRow = {
  id: string
  txn_type: string
  product_id: string
  batch_id: string
  store_id: string
  qty_base: Decimal
  unit_cost?: Decimal
  total_cost?: Decimal
  source_doc_type: string | null
  source_doc_id: string | null
  txn_datetime: string
  user_id: number | null
  running_balance?: Decimal
  product?: NamedRef | null
  batch?: { id: string; batch_number: string; expiry_date: string } | null
  store?: { id: string; code: string } | null
}

export type ProductBatch = {
  id: string
  product_id: string
  batch_number: string
  manufacturer_batch_ref: string | null
  expiry_date: string
  manufacture_date: string | null
  supplier_id: string | null
  unit_cost?: Decimal
  landed_unit_cost?: Decimal
  status: string
  qc_released_at: string | null
  qty_on_hand?: Decimal | null
  product?: NamedRef | null
  supplier?: NamedRef | null
  balances?: { id: string; store_id: string; qty_on_hand: Decimal; qty_reserved: Decimal; wac?: Decimal; store?: NamedRef | null }[]
  movements?: LedgerRow[]
  recipients?: BatchRecipient[]
  distributed_base?: Decimal
}

export type BatchRecipient = {
  sale_id: string
  doc_number: string
  posted_at: string
  sale_mode: SaleMode
  customer_id: string | null
  customer_code: string | null
  customer_name: string | null
  qty_base: Decimal
  is_bonus: boolean
}

export type CustomerTier = {
  id: string
  code: string
  name: string
  default_discount_pct?: Decimal
  max_discount_pct?: Decimal
  credit_terms_days?: number
}

export type CustomerCredit = {
  customer_id: string
  credit_limit: Decimal
  current_balance: Decimal
  unallocated_receipts: Decimal
  on_hold: boolean
  hold_reason: string | null
  reviewed_at: string | null
}

export type Customer = {
  id: string
  code: string
  name: string
  customer_type: string
  tier_id: string | null
  tax_status: string | null
  payment_terms_days: number | null
  fulfilment_policy: string | null
  phone: string | null
  email: string | null
  address: string | null
  is_active: boolean
  tier?: CustomerTier | null
  credit?: CustomerCredit | null
  contacts?: { id: string; name: string; role: string | null; phone: string | null; email: string | null; is_primary: boolean }[]
  available_credit?: Decimal
  open_order_exposure?: Decimal
}

export type QuoteLine = {
  line_ref: string
  product_id: string
  product_code: string
  product_name: string
  uom_id: string
  uom_code: string
  factor_to_base: number
  quantity: Decimal
  qty_base: Decimal
  list_price: Decimal
  break_price: Decimal
  price_source: string
  requested_discount_pct: Decimal | null
  unit_price: Decimal
  discount_per_unit: Decimal
  discount_pct: Decimal
  discount_amount: Decimal
  discount_source: string
  discount_capped_by: string | null
  discount_reason: string | null
  line_subtotal: Decimal
  net_amount: Decimal
  tax_code_id: string | null
  tax_code: string | null
  tax_rate: Decimal
  tax_amount: Decimal
  line_total: Decimal
  bonus_qty: Decimal
  bonus_product_id: string | null
  bonus_funded_by: string | null
  unit_cost: Decimal | null
  line_cost: Decimal | null
  gross_profit: Decimal | null
  margin_pct: Decimal | null
  effective_margin_pct: Decimal | null
  floor_price: Decimal | null
  floor_breached: boolean
  approval_required: boolean
  batch_id: string | null
  explain: string[]
}

export type QuoteTotals = {
  subtotal: Decimal
  discount: Decimal
  net: Decimal
  tax: Decimal
  grand_total: Decimal
  total_cost: Decimal | null
  gross_profit: Decimal | null
  margin_pct: Decimal | null
}

export type Quote = {
  quote_id: string | null
  expires_at: string | null
  currency: string
  sale_mode: SaleMode
  customer_id: string | null
  min_shelf_life_days?: number | null
  lines: QuoteLine[]
  header_discount: string[]
  totals: QuoteTotals
  approval_required: boolean
  floor_breached: boolean
  quoted_at: string
}

export type PaymentMethod = 'CASH' | 'MPESA' | 'BANK' | 'CARD' | 'CHEQUE'
export const PAYMENT_METHODS: PaymentMethod[] = ['CASH', 'MPESA', 'BANK', 'CARD', 'CHEQUE']

export type TenderLine = { method: PaymentMethod; amount: Decimal; reference?: string }

export type BatchAllocation = {
  id: string
  batch_id: string
  qty_base: Decimal
  is_bonus?: boolean
  unit_cost?: Decimal
  batch?: { id: string; batch_number: string; expiry_date: string } | null
}

export type SaleLine = {
  id: string
  line_number: number
  product_id: string
  uom_id: string
  qty: Decimal
  qty_base: Decimal
  list_price: Decimal
  unit_price: Decimal
  discount_amount: Decimal
  discount_pct: Decimal
  discount_source: string | null
  tax_rate: Decimal
  tax_amount: Decimal
  line_total: Decimal
  unit_cost?: Decimal
  line_cost?: Decimal
  is_bonus: boolean
  product?: ItemRef | null
  uom?: { id: string; code: string } | null
  batch_allocations?: BatchAllocation[]
}

export type Sale = {
  id: string
  doc_number: string
  sale_mode: SaleMode
  sub_type: string | null
  status: 'DRAFT' | 'POSTED' | 'VOIDED'
  customer_id: string | null
  store_id: string
  quote_id: string | null
  terminal_id: string | null
  subtotal: Decimal
  discount_total: Decimal
  tax_total: Decimal
  grand_total: Decimal
  cost_total?: Decimal
  void_reason: string | null
  voided_at: string | null
  posted_at: string | null
  created_at?: string
  customer?: (NamedRef & { customer_type?: string }) | null
  lines?: SaleLine[]
}

export type DocLine = {
  id: string
  line_number: number
  product_id: string
  uom_id: string
  qty: Decimal
  qty_base: Decimal
  list_price: Decimal
  unit_price: Decimal
  discount_amount: Decimal
  discount_pct: Decimal
  tax_rate: Decimal
  tax_amount: Decimal
  line_total: Decimal
  product?: NamedRef | null
}

export type Quotation = {
  id: string
  doc_number: string
  status: string
  customer_id: string
  store_id: string
  valid_until: string
  subtotal: Decimal
  discount_total: Decimal
  tax_total: Decimal
  grand_total: Decimal
  notes: string | null
  converted_sales_order_id: string | null
  created_at?: string
  lines_count?: number
  customer?: NamedRef | null
  lines?: DocLine[]
}

export type SalesOrderLine = DocLine & {
  qty_reserved_base: Decimal
  qty_picked_base: Decimal
  qty_dispatched_base: Decimal
}

export type SalesOrder = {
  id: string
  doc_number: string
  status: string
  customer_id: string
  store_id: string
  quotation_id: string | null
  required_date: string | null
  payment_terms?: 'ACCOUNT' | 'CASH_ON_DELIVERY'
  credit_override_reason?: string | null
  subtotal: Decimal
  discount_total: Decimal
  tax_total: Decimal
  grand_total: Decimal
  cancel_reason: string | null
  created_at?: string
  lines_count?: number
  customer?: NamedRef | null
  lines?: SalesOrderLine[]
  quote_id?: string
  approval_required?: boolean
}

export type PickingListLine = {
  id: string
  sales_order_line_id: string
  product_id: string
  batch_id: string
  store_id: string
  qty_to_pick_base: Decimal
  qty_picked_base: Decimal
  status: 'PENDING' | 'PICKED' | 'SHORT'
  picked_at: string | null
  product?: NamedRef | null
  batch?: { id: string; batch_number: string; expiry_date: string } | null
}

export type OrderRef = { id: string; doc_number: string; customer_id: string; status?: string; customer?: NamedRef | null }

export type PickingList = {
  id: string
  doc_number: string
  status: string
  sales_order_id: string
  store_id: string
  started_at: string | null
  completed_at: string | null
  created_at?: string
  lines_count?: number
  sales_order?: OrderRef | null
  lines: PickingListLine[]
  delivery_notes?: { id: string; picking_list_id: string; doc_number: string; status: string }[]
}

export type DeliveryNoteLine = {
  id: string
  product_id: string
  qty_base?: Decimal
  qty_dispatched_base?: Decimal
  product?: NamedRef | null
  batch_allocations?: BatchAllocation[]
}

export const DELIVERY_MODES = ['VEHICLE', 'MOTORBIKE', 'HAND', 'CUSTOMER_PICKUP'] as const
export type DeliveryMode = (typeof DELIVERY_MODES)[number]
export const DELIVERY_MODE_LABELS: Record<DeliveryMode, string> = {
  VEHICLE: 'Vehicle',
  MOTORBIKE: 'Motorbike',
  HAND: 'Hand delivery',
  CUSTOMER_PICKUP: 'Customer pickup',
}

export type DeliveryNote = {
  id: string
  doc_number: string
  status: string
  sales_order_id: string
  picking_list_id: string
  customer_id: string
  delivery_mode: DeliveryMode | null
  vehicle_reg: string | null
  driver_name: string | null
  driver_phone: string | null
  dispatched_at: string | null
  delivered_at: string | null
  received_by_name: string | null
  sale_id: string | null
  created_at?: string
  lines_count?: number
  sales_order?: OrderRef | null
  lines?: DeliveryNoteLine[]
}

export type Supplier = {
  id: string
  code: string
  name: string
  contact_name: string | null
  email: string | null
  phone: string | null
  licence_number: string | null
  licence_expiry: string | null
  payment_terms_days: number | null
  lead_time_days: number | null
  status: string
  is_active: boolean
  payable_balance?: Decimal
}

export type PurchaseOrderLine = {
  id: string
  product_id: string
  uom_id: string
  qty_ordered: Decimal
  unit_price: Decimal
  trade_price: Decimal | null
  discount_pct: Decimal | null
  tax_code_id: string | null
  /** tax_code_id comes with it so a goods receipt can show the product's VAT treatment. */
  product?: (NamedRef & { tax_code_id?: string | null }) | null
  uom?: Uom | null
}

export type PurchaseOrder = {
  id: string
  doc_number: string
  supplier_id: string
  status: string
  expected_date: string | null
  sent_at: string | null
  created_at?: string
  /** Set when the order was raised by awarding a request for quotation. */
  rfq_id?: string | null
  lines_count?: number
  supplier?: (NamedRef & { licence_expiry?: string | null; status?: string }) | null
  lines?: PurchaseOrderLine[]
  goods_receipts?: { id: string; purchase_order_id: string; doc_number: string; status: string; received_at: string | null }[]
}

export type UserRef = { id: number; name: string; username: string | null }

export type ProductCategory = { id: string; code: string; name: string; parent_id: string | null }

export type TaxCode = { id: string; code: string; name: string; tax_type: string; is_recoverable: boolean; rate_pct: Decimal | null }

export type GoodsReceiptLine = {
  id: string
  purchase_order_line_id: string | null
  product_id: string
  uom_id: string
  qty_ordered: Decimal
  qty_delivered: Decimal
  qty_accepted: Decimal
  qty_rejected: Decimal
  rejection_reason: string | null
  batch_number: string
  expiry_date: string
  unit_cost: Decimal
  trade_price: Decimal | null
  discount_pct: Decimal | null
  landed_unit_cost: Decimal | null
  batch?: ProductBatch | null
}

export type GoodsReceipt = {
  id: string
  doc_number: string
  purchase_order_id: string | null
  supplier_id: string
  store_id: string
  status: string
  is_emergency: boolean
  received_at: string | null
  created_at?: string
  lines_count?: number
  lines: GoodsReceiptLine[]
  purchase_order?: { id: string; doc_number: string; status?: string } | null
  supplier?: NamedRef | null
  store?: NamedRef | null
}

export type SupplierInvoiceLine = {
  id: string
  purchase_order_line_id: string
  product_id: string
  qty: Decimal
  unit_price: Decimal
  line_total: Decimal
  product?: NamedRef | null
  purchase_order_line?: { id: string; purchase_order_id: string; qty_ordered: Decimal; unit_price: Decimal } | null
}

export type SupplierInvoice = {
  id: string
  doc_number: string
  supplier_id: string
  invoice_number: string
  invoice_date: string
  due_date: string | null
  subtotal: Decimal
  tax_total: Decimal
  grand_total: Decimal
  match_status: 'UNMATCHED' | 'MATCHED' | 'EXCEPTION'
  matched_at: string | null
  lines_count?: number
  supplier?: NamedRef | null
  lines?: SupplierInvoiceLine[]
}

export type MatchResult = { matched: boolean; failures: unknown[]; invoice: SupplierInvoice }

export type StockAdjustmentLine = {
  id: string
  product_id: string
  batch_id: string
  qty_base: Decimal
  unit_cost?: Decimal
  line_value?: Decimal
  product?: NamedRef | null
  batch?: BatchRef | null
}

export type StockAdjustment = {
  id: string
  doc_number: string
  store_id: string
  reason_code: string
  approval_status: 'PENDING' | 'APPROVED' | 'REJECTED'
  total_value?: Decimal
  notes?: string | null
  created_at?: string
  lines_count?: number
  store?: NamedRef | null
  lines?: StockAdjustmentLine[]
}

export type Payment = {
  id: string
  customer_id: string
  method: PaymentMethod
  reference: string | null
  amount: Decimal
  status: string
  received_at: string | null
  allocations?: { id: string; sale_id: string; amount: Decimal }[]
}

export type ArAgeingRow = {
  customer_id: string
  code: string
  name: string
  customer_type: string
  payment_terms_days: number | null
  credit_limit: Decimal
  exposure: Decimal
  on_hold: boolean
  current: Decimal
  d1_30: Decimal
  d31_60: Decimal
  d61_90: Decimal
  d90_plus: Decimal
  total: Decimal
}

export type ArAgeingTotals = Pick<ArAgeingRow, 'current' | 'd1_30' | 'd31_60' | 'd61_90' | 'd90_plus' | 'total'>

export type ArAgeing = { data: ArAgeingRow[]; totals: ArAgeingTotals; as_of: string }

export type JournalLine = {
  id: string
  line_number: number
  account_id: string
  debit_amount: Decimal
  credit_amount: Decimal
  narration: string | null
  account?: { id: string; code: string; name: string; system_role: string | null } | null
}

export type JournalEntry = {
  id: string
  doc_number: string
  entry_date: string
  source_doc_type: string | null
  source_doc_id: string | null
  narration: string | null
  reverses_journal_id?: string | null
  posted_at: string | null
  lines: JournalLine[]
}

export type TrialBalance = {
  as_of: string
  accounts: { code: string; name: string; account_type: string; system_role: string | null; debit: Decimal; credit: Decimal; net: Decimal }[]
  total_debit: Decimal
  total_credit: Decimal
  balanced: boolean
}

export type FinancialPeriod = {
  id: string
  fiscal_year: number
  period_no: number
  start_date: string
  end_date: string
  status: 'OPEN' | 'CLOSING' | 'CLOSED' | 'LOCKED'
  closed_at: string | null
}

// ---- Transfers, counts, requisitions -----------------------------------

export type BatchRef = { id: string; batch_number: string; expiry_date: string; status?: string }

export type StockTransferLine = {
  id: string
  product_id: string
  batch_id: string
  qty_dispatched: Decimal
  qty_received: Decimal | null
  product?: NamedRef | null
  batch?: BatchRef | null
}

export type StockTransfer = {
  id: string
  doc_number: string
  from_store_id: string
  to_store_id: string
  status: 'DRAFT' | 'APPROVED' | 'DISPATCHED' | 'RECEIVED' | 'DISCREPANCY'
  requested_by?: number | null
  approved_by?: number | null
  dispatched_at: string | null
  received_at: string | null
  created_at?: string
  lines_count?: number
  from_store?: NamedRef | null
  to_store?: NamedRef | null
  lines?: StockTransferLine[]
}

export type StockCountLine = {
  id: string
  product_id: string
  batch_id: string
  system_qty: Decimal
  counted_qty: Decimal | null
  variance_qty: Decimal | null
  variance_value?: Decimal | null
  reason_code: string | null
  product?: NamedRef | null
  batch?: BatchRef | null
}

export type StockCount = {
  id: string
  doc_number: string
  store_id: string
  status: 'PLANNED' | 'COUNTING' | 'REVIEW' | 'APPROVED' | 'CLOSED'
  approved_at: string | null
  created_at?: string
  lines_count?: number
  store?: NamedRef | null
  lines?: StockCountLine[]
  variance_reasons?: string[]
}

export type RequisitionLine = {
  id: string
  product_id: string
  qty_requested: Decimal
  notes: string | null
  product?: (NamedRef & { base_uom_id?: string; base_uom?: Uom | null }) | null
}

export type Requisition = {
  id: string
  doc_number: string
  status: 'DRAFT' | 'PENDING_APPROVAL' | 'APPROVED' | 'REJECTED' | 'CONVERTED'
  needed_by: string | null
  notes: string | null
  created_at?: string
  lines_count?: number
  lines?: RequisitionLine[]
}

export type ReorderSuggestion = {
  product_id: string
  product_code: string
  product_name: string
  base_uom: string | null
  reorder_point: Decimal
  free_to_sell: Decimal
  on_order: Decimal
  coverage: Decimal | null
  suggested_qty_base: Decimal
  lead_time_days: number
  required_by: string
  nearest_expiry: string | null
  supplier_id: string | null
  supplier_code: string | null
  supplier_name: string | null
  formula: string
}

// ---- Returns, waste, recalls -------------------------------------------

export type Disposition = 'RESALEABLE' | 'QUARANTINE' | 'DESTROY' | 'REJECT'

export type CustomerReturnLine = {
  id: string
  sale_line_id: string
  product_id: string
  batch_id: string
  qty_base: Decimal
  disposition: Disposition
  unit_price: Decimal
  line_net: Decimal
  tax_amount: Decimal
  line_total: Decimal
  unit_cost?: Decimal
  line_cost?: Decimal
  inspection_notes: string | null
  return_reason: ReturnReason | null
  remarks: string | null
  product?: ItemRef | null
  batch?: BatchRef | null
}

export type CustomerReturn = {
  id: string
  doc_number: string
  credit_note_number: string | null
  status: 'DRAFT' | 'POSTED' | 'REJECTED'
  sale_id: string
  customer_id: string | null
  recall_id: string | null
  reason: string
  refund_method: 'CASH' | 'MPESA' | 'BANK' | 'CUSTOMER_ACCOUNT' | null
  refund_reference: string | null
  subtotal: Decimal
  tax_total: Decimal
  grand_total: Decimal
  cost_total?: Decimal
  posted_at: string | null
  created_at?: string
  lines_count?: number
  etims_status?: string | null
  customer?: NamedRef | null
  sale?: { id: string; doc_number: string; sale_mode: SaleMode; posted_at?: string | null } | null
  lines?: CustomerReturnLine[]
}

export type SupplierReturn = {
  id: string
  doc_number: string
  supplier_id: string
  store_id: string
  status: 'DRAFT' | 'POSTED'
  reason: string
  total_value?: Decimal
  created_at?: string
  lines_count?: number
  supplier?: NamedRef | null
  store?: NamedRef | null
  lines?: { id: string; product_id: string; batch_id: string; qty_base: Decimal; unit_cost: Decimal; return_reason: ReturnReason | null; remarks: string | null; product?: ItemRef | null; batch?: BatchRef | null }[]
}

export type WasteDisposal = {
  id: string
  doc_number: string
  store_id: string
  status: 'DRAFT' | 'POSTED'
  reason: 'EXPIRED' | 'DAMAGED' | 'RECALLED' | 'EXCURSION' | 'CONTAMINATED'
  recall_id: string | null
  disposal_method: string | null
  disposal_contractor: string | null
  certificate_reference: string | null
  ppb_reference: string | null
  witnessed_by_1: number | null
  witnessed_by_2: number | null
  notes: string | null
  total_value: Decimal
  posted_at: string | null
  created_at?: string
  lines_count?: number
  store?: NamedRef | null
  lines?: { id: string; product_id: string; batch_id: string; qty_base: Decimal; unit_cost: Decimal; line_value: Decimal; product?: NamedRef | null; batch?: BatchRef | null }[]
}

export type Recall = {
  id: string
  doc_number: string
  status: 'INITIATED' | 'SCOPED' | 'BLOCKED' | 'NOTIFIED' | 'RECOVERING' | 'RECONCILED' | 'DISPOSITIONED' | 'CLOSED'
  source: 'MANUFACTURER' | 'PPB' | 'INTERNAL'
  external_reference: string | null
  reason: string
  disposition: 'RETURN_TO_SUPPLIER' | 'DESTROY' | null
  effectiveness_pct: Decimal | null
  initiated_at: string | null
  blocked_at: string | null
  closed_at: string | null
  batches_count?: number
  customers_count?: number
}

export type RecallBatch = {
  id: string
  product_id: string
  batch_id: string
  status_before: string | null
  on_hand_at_scope: Decimal
  in_transit_at_scope: Decimal
  distributed_qty: Decimal
  recovered_qty: Decimal
  disposed_qty: Decimal
  stock_by_store: { store_id: string; store_code: string | null; on_hand: Decimal }[]
  on_hand_now: Decimal
  outstanding_qty: Decimal
  batch?: BatchRef | null
  product?: NamedRef | null
}

export type RecallCustomer = {
  id: string
  customer_id: string | null
  customer_name_snapshot: string
  contact_snapshot: string | null
  qty_distributed: Decimal
  qty_recovered: Decimal
  notified_at: string | null
  notification_reference: string | null
}

export type RecallTrace = {
  recall: Recall
  batches: RecallBatch[]
  customers: RecallCustomer[]
  distributed_qty: Decimal
  recovered_qty: Decimal
  disposed_qty: Decimal
  effectiveness_pct: Decimal | null
}

// ---- eTIMS, reports, payroll -------------------------------------------

export type EtimsQueueRow = {
  type: 'sale' | 'credit_note'
  id: string
  doc_number: string
  credit_note_number?: string | null
  sale_mode?: SaleMode
  customer_id: string | null
  grand_total: Decimal
  posted_at: string | null
  etims_status: string
  etims_control_code: string | null
  etims_invoice_number: string | null
  etims_submitted_at: string | null
  etims_error: string | null
  customer?: NamedRef | null
}

export type EtimsQueue = {
  summary: { pending: number; submitted: number; failed: number; not_configured: number; enabled: boolean; driver: string }
  status: string
  data: EtimsQueueRow[]
}

export type ReportDef = { key: string; title: string; group: string; description: string; filters: string[] }
export type ReportColumn = { key: string; label: string; type?: string }
export type ReportResult = {
  key: string
  title: string
  group: string
  generated_at: string
  from: string
  to: string
  filters: Record<string, unknown>
  columns: ReportColumn[]
  rows: Record<string, unknown>[]
  totals: Record<string, unknown>
}

export type ReportReviewStatus = 'UNREVIEWED' | 'VERIFIED' | 'FLAGGED'

/** Part 20.3 — one archived scheduled-report run in the Report inbox. */
export type ScheduledReportRun = {
  id: string
  scheduled_report_id: string | null
  report_key: string
  report_title: string
  period_from: string
  period_to: string
  trigger: 'SCHEDULED' | 'MANUAL'
  triggered_by: number | null
  requester?: { id: number; name: string } | null
  generated_at: string
  row_count: number | null
  columns_json: ReportColumn[] | null
  totals_json: Record<string, unknown> | null
  csv_filename: string | null
  emailed_to: string[] | null
  status: 'SUCCESS' | 'FAILED'
  error: string | null
  review_status: ReportReviewStatus
  reviewer: { id: number; name: string } | null
  reviewed_at: string | null
  review_notes: string | null
}

export type ScheduledReportRunPage = Paginated<ScheduledReportRun> & { counts: Record<ReportReviewStatus, number> }

export type ScheduledReportRunDetail = ScheduledReportRun & {
  has_file: boolean
  preview: { columns: ReportColumn[]; rows: Record<string, unknown>[]; totals: Record<string, unknown>; truncated: boolean } | null
}

export type Employee = {
  id: string
  employee_no: string
  name: string
  branch_id: string | null
  user_id: number | null
  national_id: string | null
  kra_pin: string | null
  nssf_no: string | null
  shif_no: string | null
  job_title: string | null
  department: string | null
  employment_type: 'PERMANENT' | 'CONTRACT' | 'CASUAL' | null
  date_joined: string | null
  date_left: string | null
  basic_salary: Decimal
  regular_allowances: Decimal | null
  pension_contribution: Decimal | null
  bank_name: string | null
  is_active: boolean
}

export type PayrollBand = {
  id: string
  band_type: string
  sequence: number
  effective_from: string
  effective_to: string | null
  lower: Decimal | null
  upper: Decimal | null
  rate_pct: Decimal | null
  fixed_amount: Decimal | null
  meta_json: Record<string, unknown> | null
  source: string | null
}

export type PayrollRunLine = {
  id: string
  employee_id: string
  basic: Decimal
  allowances: Decimal
  overtime: Decimal
  gross: Decimal
  pension_contribution: Decimal
  taxable: Decimal
  paye: Decimal
  nssf_employee: Decimal
  nssf_employer: Decimal
  shif: Decimal
  housing_levy_employee: Decimal
  housing_levy_employer: Decimal
  other_deductions: Decimal
  net: Decimal
  breakdown_json: {
    gross_tax_before_relief?: Decimal
    personal_relief?: Decimal
    paye_bands?: { band: number; lower: Decimal; upper: Decimal | null; rate_pct: Decimal; amount_in_band: Decimal; tax: Decimal }[]
    nssf_tiers?: { tier: number; pensionable: Decimal; rate_pct: Decimal; contribution: Decimal }[]
  } | null
  employee?: Pick<Employee, 'id' | 'employee_no' | 'name' | 'job_title' | 'department'> | null
}

export type PayrollRun = {
  id: string
  doc_number: string
  period_year: number
  period_month: number
  status: 'DRAFT' | 'COMPUTED' | 'APPROVED' | 'POSTED' | 'PAID'
  bands_as_of: string | null
  total_gross: Decimal
  total_paye: Decimal
  total_nssf_employee: Decimal
  total_nssf_employer: Decimal
  total_shif: Decimal
  total_housing_levy_employee: Decimal
  total_housing_levy_employer: Decimal
  total_other_deductions: Decimal
  total_net: Decimal
  journal_id: string | null
  payment_journal_id: string | null
  computed_at: string | null
  approved_at: string | null
  posted_at: string | null
  paid_at: string | null
  lines_count?: number
  lines?: PayrollRunLine[]
}

export type Payslip = {
  run: Pick<PayrollRun, 'id' | 'doc_number' | 'period_year' | 'period_month' | 'status' | 'bands_as_of'>
  employee: Pick<Employee, 'id' | 'employee_no' | 'name' | 'job_title' | 'department' | 'kra_pin' | 'nssf_no' | 'shif_no' | 'bank_name'> | null
  line: PayrollRunLine
}

// ---- Dashboard, admin, price lists, chart of accounts ------------------

export type ApprovalQueueKey = 'requisitions' | 'purchase_orders' | 'adjustments' | 'transfers' | 'counts' | 'customer_returns'

export type DashboardSummary = {
  as_of: string
  sales_today: null | { count: number; total: Decimal; by_mode: Partial<Record<SaleMode, { count: number; total: Decimal }>>; voided_today: number }
  approvals: null | Partial<Record<ApprovalQueueKey, number>>
  inventory: null | {
    pending_qc_batches: number
    pending_qc_qty?: Decimal
    quarantined_batches: number
    expiring_90d_batches: number
    expiring_90d_qty?: Decimal
    expired_batches_on_hand: number
    low_stock_count?: number
    transfers_in_transit: number
  }
  procurement: null | { open_purchase_orders: number; suppliers_licence_expired: number; suppliers_licence_expiring_30d: number }
  compliance: null | { etims_failed: number; etims_pending: number }
  quality: null | Partial<{ cold_chain_open_excursions: number; adr_draft_reports: number; licences_expired: number; licences_expiring_60d: number; documents_to_acknowledge: number }>
  people: null | Partial<{ leave_pending: number }>
  finance: null | { open_period: null | Pick<FinancialPeriod, 'id' | 'fiscal_year' | 'period_no' | 'start_date' | 'end_date'>; period_open_for_today: boolean }
  ar?: null | { total: Decimal; customers_count: number; d90_plus: Decimal }
}

export type UserAssignment = { branch_id: string; branch_code: string | null; role: string }

export type AdminUser = {
  id: number
  name: string
  username: string | null
  email: string
  phone: string | null
  is_active: boolean
  mfa_required: boolean
  must_change_password: boolean
  failed_login_attempts: number
  locked_until: string | null
  last_login_at: string | null
  created_at: string | null
  assignments: UserAssignment[]
}

export type AdminRole = { id: number; name: string; permissions: string[]; users_count: number }

export type PermissionGroup = { group: string; permissions: string[] }

export type StoreType = 'MAIN' | 'COLD' | 'QUARANTINE' | 'RETAIL' | 'TRANSIT' | 'DISPENSARY'
export const STORE_TYPES: StoreType[] = ['MAIN', 'COLD', 'QUARANTINE', 'RETAIL', 'TRANSIT', 'DISPENSARY']

export type AdminStore = { id: string; code: string; name: string; store_type: string; is_sellable: boolean; storage_condition_id?: string | null }

export type AdminBranch = {
  id: string
  code: string
  name: string
  address: string | null
  county: string | null
  is_active: boolean
  retail_enabled: boolean
  wholesale_enabled: boolean
  dispensing_enabled: boolean
  stores: AdminStore[]
}

export type SettingType = 'string' | 'integer' | 'decimal'
export type SettingValue = string | number | null

export type SettingRow = {
  scope: string
  key: string
  type: SettingType
  default: SettingValue
  description: string
  value: SettingValue
  source: 'default' | 'organisation' | 'branch'
  organisation_value: SettingValue
  branch_value: SettingValue
  set_at: string | null
  set_by: number | null
}

export type SettingsResponse = { branch_id: string; data: SettingRow[] }

export type NumberSequence = {
  id: string
  scope: string
  branch_id: string | null
  prefix: string
  fiscal_year: number | null
  current_value: number
  padding: number
  reset_policy: string
}

export type AuditLogRow = {
  id: string
  occurred_at: string
  user_id: number | null
  username_snapshot: string | null
  branch_id: string | null
  action: string
  entity_type: string
  entity_id: string | null
  reference: string | null
  before_json: unknown
  after_json: unknown
  changed_fields: string[] | null
  reason: string | null
}

export type AuditLogPage = Paginated<AuditLogRow> & { actions: string[] }

export type PriceList = {
  id: string
  code: string
  name: string
  sale_mode: SaleMode | null
  tier_id: string | null
  branch_id: string | null
  currency: string
  prices_include_tax: boolean
  effective_from: string | null
  effective_to: string | null
  is_active: boolean
  priority: number
  product_prices_count?: number
  tier?: { id: string; code: string; name: string } | null
  branch?: { id: string; code: string; name: string } | null
}

/** GET /api/products/{id}/selling-prices — the product's current row on each active list, in one unit. */
export type SellingPrices = {
  product_id: string
  uom_id: string
  tax_rate_pct: Decimal
  lists: {
    price_list: Pick<PriceList, 'id' | 'code' | 'name' | 'sale_mode' | 'prices_include_tax' | 'tier' | 'branch'>
    current: { id: string; factor_type: PriceFactorType; unit_price: Decimal; factor_value: Decimal | null; effective_from: string | null } | null
  }[]
}

export type PriceFactorType = 'FIXED' | 'COST_PLUS_MARKUP' | 'TARGET_MARGIN' | 'LIST_RELATIVE'
export const PRICE_FACTOR_TYPES: PriceFactorType[] = ['FIXED', 'COST_PLUS_MARKUP', 'TARGET_MARGIN', 'LIST_RELATIVE']

export type PriceListItem = {
  id: string
  product_id: string
  uom_id: string | null
  factor_type: PriceFactorType
  unit_price: Decimal
  factor_value: Decimal | null
  effective_from: string | null
  effective_to: string | null
  product?: { id: string; code: string; name: string; default_price: Decimal | null } | null
  uom?: Uom | null
}

export type ChartAccount = {
  id: string
  code: string
  name: string
  account_type: string
  parent_id: string | null
  is_postable: boolean
  system_role: string | null
  currency: string | null
  is_active: boolean
  balance: Decimal
}

// ---------------------------------------------------------------- Platform, onboarding and billing

export type AccessState = 'ACTIVE' | 'TRIAL' | 'LAPSED' | 'SUSPENDED'

export type SubscriptionSummary = {
  access_state: AccessState
  trial_ends_at: string | null
  plan: { id: string; code: string; name: string } | null
  current_period_end: string | null
  is_complimentary: boolean
}

export type Plan = {
  id: string
  code: string
  name: string
  description: string | null
  currency: string
  price_monthly: Decimal
  price_yearly: Decimal | null
  max_branches: number | null
  max_users: number | null
  features: string[] | null
  is_active?: boolean
  sort_order?: number
  paystack_plan_monthly?: string | null
  paystack_plan_yearly?: string | null
}

export type BillingInterval = 'MONTHLY' | 'YEARLY'

export type SubscriptionPaymentRow = {
  id: string
  reference: string
  amount: Decimal
  currency: string
  status: 'PENDING' | 'SUCCESS' | 'FAILED'
  channel: string | null
  billing_interval: BillingInterval | null
  paid_at: string | null
  created_at: string
  plan?: { id: string; name: string } | null
  organisation?: { id: string; name: string } | null
}

export type BillingOverview = {
  institution: { id: string; name: string; contact_email: string | null }
  access_state: AccessState
  is_complimentary: boolean
  trial_ends_at: string | null
  suspension_reason: string | null
  subscription: { plan: { id: string; code: string; name: string } | null; billing_interval: BillingInterval; current_period_end: string | null; renews_automatically: boolean } | null
  plans: Plan[]
  payments: SubscriptionPaymentRow[]
  online_payment_available: boolean
}

export type TenantRequestRow = {
  id: string
  institution_name: string
  contact_name: string
  email: string
  phone: string | null
  town: string | null
  branches_count: number | null
  users_count: number | null
  message: string | null
  status: 'PENDING' | 'APPROVED' | 'REJECTED' | 'REGISTERED'
  rejection_reason: string | null
  created_at: string
  plan?: { id: string; code: string; name: string } | null
  organisation?: { id: string; name: string } | null
}

export type InvitationRow = {
  id: string
  purpose: 'REGISTER' | 'ACTIVATE'
  email: string
  state: 'SENT' | 'OPENED' | 'USED' | 'REVOKED' | 'EXPIRED'
  expires_at: string
  opened_at: string | null
  used_at: string | null
}

export type TenantRow = {
  id: string
  name: string
  legal_name: string | null
  kra_pin: string | null
  contact_email: string | null
  contact_phone: string | null
  is_complimentary: boolean
  access_state: AccessState
  trial_ends_at: string | null
  suspended_at: string | null
  suspension_reason: string | null
  branches_count: number
  users_count: number
  plan: { id: string; code: string; name: string } | null
  current_period_end: string | null
  created_at: string
}

/** Supplier quotes and competitive bid analysis (client item 19). */
export type RfqStatus = 'DRAFT' | 'SENT' | 'CLOSED' | 'AWARDED' | 'CANCELLED'

export type RfqLine = {
  id: string
  product_id: string
  uom_id: string
  qty: Decimal
  notes: string | null
  sort_order: number
  awarded_supplier_id: string | null
  recommended_supplier_id: string | null
  product?: (NamedRef & { strength?: string | null }) | null
  uom?: { id: string; code: string } | null
}

export type RfqQuoteLine = {
  id: string
  rfq_line_id: string
  unit_price: Decimal
  qty_available: Decimal | null
  lead_time_days: number
  shelf_life_months: number | null
  notes: string | null
}

export type RfqInvite = {
  id: string
  supplier_id: string
  quote_status: 'AWAITING' | 'RECEIVED' | 'DECLINED'
  quote_reference: string | null
  quote_date: string | null
  valid_until: string | null
  payment_terms_days: number | null
  delivery_charge: Decimal
  notes: string | null
  received_at: string | null
  supplier?: (NamedRef & { email?: string | null; status?: string; is_active?: boolean; licence_expiry?: string | null; payment_terms_days?: number | null; lead_time_days?: number | null }) | null
  quote_lines?: RfqQuoteLine[]
}

export type Rfq = {
  id: string
  doc_number: string
  title: string
  needed_by: string | null
  notes: string | null
  status: RfqStatus
  weight_price: Decimal
  weight_lead_time: Decimal
  weight_payment_terms: Decimal
  weight_supplier_record: Decimal
  sent_at: string | null
  closed_at: string | null
  award_mode: 'SINGLE' | 'SPLIT' | null
  award_followed_recommendation: boolean | null
  award_justification: string | null
  awarded_at: string | null
  cancel_reason: string | null
  created_at?: string
  lines_count?: number
  suppliers_count?: number
  quotes_received_count?: number
  lines?: RfqLine[]
  suppliers?: RfqInvite[]
  purchase_orders?: { id: string; doc_number: string; supplier_id: string; status: string; expected_date: string | null; supplier?: NamedRef | null }[]
  awarder?: { id: number; name: string } | null
  creator?: { id: number; name: string } | null
  created_purchase_orders?: PurchaseOrder[]
}

export type CbaRisk = { code: string; message: string }

export type CbaScores = { price: number; lead_time: number; payment_terms: number; supplier_record: number; total: number }

export type CbaQuote = {
  supplier_id: string
  supplier_name: string
  unit_price: Decimal
  qty_available: Decimal | null
  qty_to_order: Decimal
  full_quantity: boolean
  line_total: Decimal
  lead_time_days: number
  shelf_life_months: number | null
  payment_terms_days: number | null
  notes: string | null
  is_lowest: boolean
  eligible: boolean
  quote_valid: boolean
  scores: CbaScores
  risks: CbaRisk[]
}

export type CbaSupplier = {
  supplier_id: string
  code: string
  name: string
  quote_status: RfqInvite['quote_status']
  quote_reference: string | null
  valid_until: string | null
  quote_valid: boolean
  payment_terms_days: number | null
  delivery_charge: Decimal
  eligible: boolean
  lines_quoted: number
  total: Decimal
  total_with_delivery: Decimal
  record: {
    score: number
    licence_status: 'VALID' | 'EXPIRING' | 'EXPIRED' | 'MISSING'
    licence_expiry: string | null
    invoices_matched: number
    invoices_exception: number
    deliveries: number
    deliveries_timed: number
    deliveries_on_time: number
    rejected_share_pct: number | null
    summary: string
  }
  risks: CbaRisk[]
}

export type CbaLine = {
  rfq_line_id: string
  product: { id: string; code: string | null; name: string | null; strength: string | null }
  uom: { id: string; code: string | null }
  qty: Decimal
  notes: string | null
  lowest_unit_price: Decimal | null
  quotes: CbaQuote[]
  recommendation: {
    supplier_id: string
    supplier_name: string
    unit_price: Decimal
    qty_to_order: Decimal
    line_total: Decimal
    score: number
    reasons: string[]
    summary: string
    risks: CbaRisk[]
  } | null
  no_recommendation_reason: string | null
}

export type CbaAnalysis = {
  rfq: { id: string; doc_number: string; title: string; status: RfqStatus; needed_by: string | null }
  weights: { price: number; lead_time: number; payment_terms: number; supplier_record: number }
  suppliers: CbaSupplier[]
  lines: CbaLine[]
  overall: {
    mode: 'SINGLE' | 'SPLIT' | 'NONE'
    supplier_id: string | null
    supplier_name: string | null
    /** rfq_line_id → supplier_id; an empty plan may arrive as []. */
    plan: Record<string, string> | []
    total: Decimal
    reasons: string[]
    summary: string
    risks: CbaRisk[]
    unawarded_lines: number
  }
  generated_at: string
}

export type TenantDetail = TenantRow & {
  subscriptions: { id: string; billing_interval: BillingInterval; status: string; current_period_end: string | null; plan?: { name: string } | null }[]
  payments: SubscriptionPaymentRow[]
  invitations: InvitationRow[]
  admins: { id: number; name: string; email: string; is_active: boolean; last_login_at: string | null }[]
}

/** What opening a one-time link returns. */
export type OpenedInvitation = {
  purpose: 'REGISTER' | 'ACTIVATE'
  email: string
  session_token: string
  session_expires_at: string
  institution_name: string | null
  contact_name: string | null
  contact_phone: string | null
  town: string | null
}

/** Client item 14 — the company profile behind the letterhead and the company profile PDF. */
export type CompanyProfileImageKind = 'logo' | 'stamp' | 'signature'

export type CompanyProfile = {
  id: string
  name: string
  legal_name: string | null
  tagline: string | null
  about: string | null
  mission: string | null
  vision: string | null
  core_values: string[]
  services: string[]
  website: string | null
  contact_email: string | null
  contact_phone: string | null
  physical_address: string | null
  postal_address: string | null
  signatory_name: string | null
  signatory_title: string | null
  images: Record<CompanyProfileImageKind, boolean>
  images_version: number | null
}

/* Training centre (client item 17). Knowledge-check answers never reach the browser. */
export type TrainingModuleProgress = {
  lessons_viewed: number
  lessons_total: number
  tasks_completed: number
  tasks_verified: number
  tasks_total: number
  attempts: number
  best_score: number | null
  passed: boolean
  feedback_rating: number | null
  started: boolean
  completed: boolean
  completed_at: string | null
}

export type TrainingModuleCard = {
  key: string
  title: string
  summary: string
  audience: string
  recommended: boolean
  lesson_count: number
  task_count: number
  question_count: number
  progress: TrainingModuleProgress
}

export type TrainingOverview = {
  pass_mark: number
  can_manage: boolean
  roles: string[]
  modules: TrainingModuleCard[]
}

export type TrainingLesson = {
  key: string
  title: string
  summary: string
  body: string[]
  steps: string[]
  tips: string[]
  route: string | null
  route_label: string | null
  tour: string | null
}

export type TrainingTask = {
  key: string
  title: string
  instructions: string
  checklist: string[]
  route: string | null
  auto_verified: boolean
}

export type TrainingQuestion = { id: string; question: string; options: string[] }

export type TrainingTaskState = {
  task: string
  started_at: string | null
  completed_at: string | null
  is_verified: boolean
  verification_detail: string | null
  note: string | null
}

export type TrainingAttempt = { id: string; correct: number; total: number; score_pct: number; passed: boolean; created_at: string }

export type TrainingModuleDetail = {
  pass_mark: number
  module: {
    key: string
    title: string
    summary: string
    audience: string
    lessons: TrainingLesson[]
    tasks: TrainingTask[]
    quiz: TrainingQuestion[]
  }
  progress: TrainingModuleProgress
  lessons_viewed: Record<string, string>
  tasks: Record<string, TrainingTaskState>
  attempts: TrainingAttempt[]
  feedback: { rating: number; comments: string | null; updated_at: string } | null
}

export type TrainingQuizResult = {
  attempt: TrainingAttempt
  best_score: number
  pass_mark: number
  review: { question_id: string; lesson: string | null }[]
}

export type TrainingReport = {
  pass_mark: number
  modules: { key: string; title: string; audience: string }[]
  staff: { id: number; name: string; username: string | null; is_active: boolean; roles: string[]; modules: Record<string, TrainingModuleProgress> }[]
}

export type TrainingFeedbackRow = {
  id: string
  module_key: string
  module_title: string
  rating: number
  comments: string | null
  user: { id: number; name: string; username: string | null } | null
  updated_at: string
}

/* The assistant on the sign-in page (read-only, verified by an emailed code). */
export type AssistantCommand = { command: string; title: string; hint: string }

export type AssistantVerified = {
  session_token: string
  expires_at: string | null
  user: { name: string; email: string }
}

export type AssistantAnswer = {
  command: string
  title: string
  summary: string
  columns: { key: string; label: string }[]
  rows: Record<string, string | number | null>[]
}

