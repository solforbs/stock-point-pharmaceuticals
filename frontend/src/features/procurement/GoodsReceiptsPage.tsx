import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { PackageCheck, Plus, Trash2, Truck } from 'lucide-react'
import { Fragment, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { ProductSearch } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, DrawerFooter, Field, FormSection, PrimaryAction, Select } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { usePurchaseOrder, useStores, useSuppliers, useTaxCodes } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { GoodsReceipt, Paginated, Product, PurchaseOrder } from '../../lib/types'
import { SellingPricesPanel } from './SellingPricesPanel'
import { decimalInput, netFromTrade, tradeTermsLabel } from './tradeTerms'

type GrnLine = {
  key: string
  purchase_order_line_id: string | null
  product_id: string
  product_label: string
  uom_id: string
  uom_options: { id: string; code: string }[]
  qty_ordered: string
  qty_delivered: string
  qty_accepted: string
  qty_rejected: string
  rejection_reason: string
  batch_number: string
  expiry_date: string
  manufacture_date: string
  unit_cost: string
  trade_price: string
  discount_pct: string
  /** VAT as charged on the supplier's invoice: 16% or zero-rated. Blank until the receiver answers. */
  vat: '' | 'STANDARD' | 'ZERO'
  temperature_on_arrival: string
  coa_received: boolean
  /** New selling price per price list id, in the received unit; blank means leave as is. */
  selling_prices: Record<string, string>
}

function blankLine(): Omit<GrnLine, 'key' | 'product_id' | 'product_label' | 'uom_id' | 'uom_options'> {
  return { purchase_order_line_id: null, qty_ordered: '', qty_delivered: '', qty_accepted: '', qty_rejected: '', rejection_reason: '', batch_number: '', expiry_date: '', manufacture_date: '', unit_cost: '', trade_price: '', discount_pct: '', vat: '', temperature_on_arrival: '', coa_received: false, selling_prices: {} }
}

/** Part 9.2 — PO-backed receipt; batch + expiry mandatory; creates the batch as PENDING_QC. */
export default function GoodsReceiptsPage() {
  const [params, setParams] = useSearchParams()
  const canCreate = usePermission('grn.create')
  const suppliers = useSuppliers()
  const [status, setStatus] = useState('')
  const [supplierFilter, setSupplierFilter] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(!!params.get('po'))
  const selectedId = params.get('receipt')

  const list = useQuery({
    queryKey: ['goods-receipts', 'list', status, supplierFilter, page],
    queryFn: () => apiGet<Paginated<GoodsReceipt>>('/api/goods-receipts', { status, supplier_id: supplierFilter, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<GoodsReceipt>[] = [
    { key: 'doc', header: 'Document', render: (g) => <span className="font-semibold tabular">{g.doc_number}</span>, sortValue: (g) => g.doc_number },
    { key: 'po', header: 'Purchase order', render: (g) => (g.purchase_order ? <span className="tabular">{g.purchase_order.doc_number}</span> : <StatusBadge status="EMERGENCY" tone="amber" label="Emergency" />) },
    { key: 'supplier', header: 'Supplier', render: (g) => g.supplier?.name ?? '—', sortValue: (g) => g.supplier?.name ?? '' },
    { key: 'store', header: 'Store', render: (g) => g.store?.code ?? '—' },
    { key: 'status', header: 'Status', render: (g) => <StatusBadge status={g.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (g) => <span className="tabular">{g.lines_count ?? '—'}</span> },
    { key: 'received', header: 'Received', render: (g) => formatDateTime(g.received_at ?? g.created_at), sortValue: (g) => g.received_at ?? g.created_at ?? '' },
    { key: 'pdf', header: '', render: (g) => <PdfDownloadButton url={`/api/goods-receipts/${g.id}/pdf`} filename={g.doc_number} label="PDF" /> },
  ]

  return (
    <Page>
      <PageHeader
        parent="Procurement & Supply"
        title="Goods Receipts (GRN)"
        subtitle="Mandatory batch & expiry verification, cold chain temperature logging, and PENDING QC quarantine creation"
        actions={
          canCreate ? (
            <div id="tour-grn-new">
              <PrimaryAction icon={Plus} onClick={() => setCreating(true)}>
                New goods receipt
              </PrimaryAction>
            </div>
          ) : null
        }
      />
      <div id="tour-grn-filters">
        <FilterBar>
          <Field label="Status"><Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}><option value="">All Statuses</option><option value="DRAFT">Draft</option><option value="POSTED">Posted</option></Select></Field>
          <Field label="Supplier"><Select value={supplierFilter} onChange={(e) => { setSupplierFilter(e.target.value); setPage(1) }}><option value="">All Suppliers</option>{(suppliers.data?.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.name}</option>))}</Select></Field>
        </FilterBar>
      </div>
      <div id="tour-grn-table" className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(g) => g.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(g) => setParams({ receipt: g.id })} selectedKey={selectedId} emptyTitle="No goods receipts" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <NewReceiptDrawer open={creating} initialPo={params.get('po')} onClose={() => { setCreating(false); if (params.get('po')) setParams({}) }} onCreated={(g) => setParams({ receipt: g.id })} />
      <ReceiptDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function NewReceiptDrawer({ open, initialPo, onClose, onCreated }: { open: boolean; initialPo: string | null; onClose: () => void; onCreated: (g: GoodsReceipt) => void }) {
  const queryClient = useQueryClient()
  const stores = useStores()
  const suppliers = useSuppliers()
  const canSetPrices = usePermission('price.manage')
  const taxCodes = useTaxCodes()
  // The two answers the receiving bay can give, mapped to this organisation's VAT codes.
  const vatOf = (taxCodeId: string | null | undefined): GrnLine['vat'] => {
    const code = (taxCodes.data ?? []).find((t) => t.id === taxCodeId)?.code
    return code === 'VAT_STD' ? 'STANDARD' : code === 'VAT_ZERO' ? 'ZERO' : ''
  }
  const [pricesOpen, setPricesOpen] = useState<string | null>(null)
  const [poId, setPoId] = useState(initialPo ?? '')
  const [supplierId, setSupplierId] = useState('')
  const [storeId, setStoreId] = useState('')
  const [emergency, setEmergency] = useState(false)
  const [lines, setLines] = useState<GrnLine[]>([])
  const effectiveStore = storeId || stores.data?.find((s) => s.store_type === 'MAIN')?.id || stores.data?.[0]?.id || ''

  const pos = useQuery({ queryKey: ['purchase-orders', 'receivable'], queryFn: () => apiGet<Paginated<PurchaseOrder>>('/api/purchase-orders', { per_page: 200 }), enabled: open })
  const receivable = (pos.data?.data ?? []).filter((po) => ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED'].includes(po.status))
  const po = usePurchaseOrder(emergency ? null : poId || null)

  // Prefill every line from the PO (GET /api/purchase-orders/{id}): product, purchase UOM, ordered qty and price.
  useEffect(() => {
    const detail = po.data
    if (!detail) return
    setSupplierId(detail.supplier_id)
    setLines(
      (detail.lines ?? []).map((l) => ({
        key: l.id,
        ...blankLine(),
        purchase_order_line_id: l.id,
        product_id: l.product_id,
        product_label: l.product ? `${l.product.name} · ${l.product.code ?? ''}` : l.product_id.slice(0, 8),
        uom_id: l.uom_id,
        uom_options: [{ id: l.uom_id, code: l.uom?.code ?? l.uom_id.slice(0, 8) }],
        qty_ordered: l.qty_ordered,
        qty_delivered: String(Number(l.qty_ordered)),
        qty_accepted: String(Number(l.qty_ordered)),
        unit_cost: String(Number(l.unit_price)),
        trade_price: l.trade_price != null ? String(Number(l.trade_price)) : '',
        discount_pct: l.discount_pct != null ? String(Number(l.discount_pct)) : '',
        vat: vatOf(l.product?.tax_code_id),
      })),
    )
  }, [po.data])

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<GoodsReceipt>('/api/goods-receipts', {
        purchase_order_id: emergency ? null : poId || null,
        supplier_id: supplierId,
        store_id: effectiveStore,
        is_emergency: emergency,
        lines: lines.map((l) => ({
          purchase_order_line_id: emergency ? null : l.purchase_order_line_id,
          product_id: l.product_id,
          uom_id: l.uom_id,
          qty_delivered: l.qty_delivered,
          qty_accepted: l.qty_accepted,
          qty_rejected: l.qty_rejected || '0',
          rejection_reason: l.rejection_reason || null,
          batch_number: l.batch_number,
          expiry_date: l.expiry_date,
          manufacture_date: l.manufacture_date || null,
          unit_cost: l.unit_cost,
          trade_price: l.trade_price || null,
          discount_pct: l.trade_price ? l.discount_pct || '0' : null,
          vat: l.vat || null,
          temperature_on_arrival: l.temperature_on_arrival || null,
          coa_received: l.coa_received,
          ...(canSetPrices ? { selling_prices: sellingPriceEntries(l) } : {}),
        })),
      }),
    onSuccess: (grn) => {
      queryClient.setQueryData(['goods-receipts', grn.id], grn)
      queryClient.invalidateQueries({ queryKey: ['goods-receipts'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['batches'] })
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
      queryClient.invalidateQueries({ queryKey: ['selling-prices'] })
      queryClient.invalidateQueries({ queryKey: ['price-lists'] })
      toast.success(`Goods receipt ${grn.doc_number} posted`, 'Batches created as PENDING QC; release them in Batches & Expiry.')
      setLines([])
      setPoId('')
      onClose()
      onCreated(grn)
    },
  })

  function addManualLine(p: Product) {
    const uoms = (p.uoms ?? []).filter((u) => u.is_purchase || u.is_base)
    setLines([...lines, { key: `${p.id}-${Date.now()}`, ...blankLine(), product_id: p.id, product_label: `${p.name} · ${p.code}`, uom_id: uoms[0]?.uom_id ?? p.base_uom_id, uom_options: uoms.map((u) => ({ id: u.uom_id, code: u.uom?.code ?? '' })), vat: vatOf(p.tax_code_id) }])
  }
  function update(key: string, patch: Partial<GrnLine>) {
    setLines(lines.map((l) => (l.key === key ? { ...l, ...patch } : l)))
  }
  /** Trade price or discount changed: the unit cost follows; the receiver can still key their own. */
  function updateTerms(line: GrnLine, patch: Pick<Partial<GrnLine>, 'trade_price' | 'discount_pct'>) {
    const next = { ...line, ...patch }
    update(line.key, { ...patch, unit_cost: netFromTrade(next.trade_price, next.discount_pct) || line.unit_cost })
  }

  const valid =
    !!supplierId && !!effectiveStore && lines.length > 0 && (emergency || !!poId) &&
    lines.every((l) => l.product_id && l.uom_id && l.batch_number && l.expiry_date && !!l.vat && /^\d+(\.\d+)?$/.test(l.qty_delivered) && /^\d+(\.\d+)?$/.test(l.qty_accepted) && /^\d+(\.\d+)?$/.test(l.unit_cost) && Number(l.discount_pct || 0) <= 100 && (emergency || l.purchase_order_line_id) && Object.values(l.selling_prices).every((v) => v === '' || /^\d+(\.\d+)?$/.test(v)))

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="New Goods Receipt (GRN)"
      subtitle="Verify delivered quantities, batch numbers, expiry dates and temperatures"
      width={1180}
      footer={
        <DrawerFooter
          badge={`${lines.length} lines`}
          onCancel={onClose}
          onSubmit={() => create.mutate()}
          submitLabel="Post goods receipt"
          disabled={!valid || create.isPending}
          isPending={create.isPending}
        />
      }
    >
      <div className="space-y-4">
        <FormSection
          title="Procurement Source & Receiving Store"
          description="Link to an approved purchase order or initiate an emergency audited receipt"
          icon={Truck}
          badge="Required"
        >
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <Field label="Purchase Order" required={!emergency} className="sm:col-span-2">
              <Select value={poId} disabled={emergency} onChange={(e) => { setPoId(e.target.value); setLines([]) }}>
                <option value="">Choose approved purchase order…</option>
                {receivable.map((p) => (<option key={p.id} value={p.id}>{p.doc_number} · {p.supplier?.name} · {p.status}</option>))}
              </Select>
            </Field>
            <Field label="Receiving Store" required>
              <Select value={effectiveStore} onChange={(e) => setStoreId(e.target.value)}>
                {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
              </Select>
            </Field>
            {emergency && (
              <Field label="Supplier" required className="sm:col-span-3">
                <Select value={supplierId} onChange={(e) => setSupplierId(e.target.value)}>
                  <option value="">Choose supplier…</option>
                  {(suppliers.data?.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.name}</option>))}
                </Select>
              </Field>
            )}
          </div>
          <label className="flex items-center gap-2 text-xs font-semibold text-slate-700 mt-2 cursor-pointer">
            <input type="checkbox" checked={emergency} onChange={(e) => { setEmergency(e.target.checked); if (e.target.checked) { setPoId(''); setLines([]) } }} className="rounded border-slate-300 text-blue-600 focus:ring-blue-500" />
            <span>Emergency receipt without a purchase order (audited log entry created)</span>
          </label>
        </FormSection>

        {po.isLoading && <LoadingSkeleton rows={3} />}
        {po.isError && <InlineError error={po.error} />}
        {po.data && (po.data.goods_receipts ?? []).length > 0 && (
          <div className="p-3 bg-blue-50/60 border border-blue-200/60 rounded-xl text-xs text-blue-800">
            <strong>Previous Receipts:</strong> {po.data.goods_receipts!.map((g) => `${g.doc_number} (${g.status})`).join(', ')}. Adjust delivered quantities to what arrived today.
          </div>
        )}
        {emergency && <Field label="Add Products"><ProductSearch onSelect={addManualLine} /></Field>}

        <FormSection
          title="Received Line Items & Batch Verification"
          description="Verify delivered, accepted, and rejected units with batch numbers and cold-chain temperature"
          icon={PackageCheck}
          badge={`${lines.length} lines`}
        >
          {lines.length > 0 ? (
            <div className="overflow-x-auto rounded-xl border border-slate-200">
              <table className="ui-table min-w-[1260px]">
                <thead><tr><th>Product</th><th>UOM</th><th className="text-right">Ordered</th><th>Delivered</th><th>Accepted</th><th>Rejected</th><th>Batch no.</th><th>Expiry</th><th>Mfg date</th><th>Trade price</th><th>Disc. %</th><th>Unit cost</th><th>VAT on invoice</th><th>Temp °C</th><th>COA</th><th /></tr></thead>
                <tbody>
                  {lines.map((l) => (
                    <Fragment key={l.key}>
                    <tr>
                      <td className="text-xs font-semibold text-slate-900">
                        {l.product_label}
                        {canSetPrices && (
                          <button type="button" onClick={() => setPricesOpen(pricesOpen === l.key ? null : l.key)} className="block mt-1 text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline">
                            {pricesOpen === l.key ? 'Hide selling prices' : `Selling prices${sellingPriceEntries(l).length ? ` (${sellingPriceEntries(l).length} new)` : ''}`}
                          </button>
                        )}
                      </td>
                      <td>
                        {l.uom_options.length > 1 ? (
                          <select value={l.uom_id} onChange={(e) => update(l.key, { uom_id: e.target.value })} className="ui-input h-7 w-auto text-xs">{l.uom_options.map((u) => (<option key={u.id} value={u.id}>{u.code}</option>))}</select>
                        ) : (
                          <span className="tabular text-xs font-bold text-slate-600">{l.uom_options[0]?.code ?? l.uom_id.slice(0, 8)}</span>
                        )}
                      </td>
                      <td className="text-right"><QtyCell value={l.qty_ordered || null} /></td>
                      <td><input value={l.qty_delivered} onChange={(e) => update(l.key, { qty_delivered: e.target.value.replace(/[^\d.]/g, '') })} className="ui-input h-7 w-16 tabular text-right font-bold" /></td>
                      <td><input value={l.qty_accepted} onChange={(e) => update(l.key, { qty_accepted: e.target.value.replace(/[^\d.]/g, '') })} className="ui-input h-7 w-16 tabular text-right font-bold text-emerald-600" /></td>
                      <td>
                        <input value={l.qty_rejected} onChange={(e) => update(l.key, { qty_rejected: e.target.value.replace(/[^\d.]/g, '') })} className="ui-input h-7 w-14 tabular text-right text-rose-600" />
                        {Number(l.qty_rejected) > 0 && <input value={l.rejection_reason} placeholder="Reason" onChange={(e) => update(l.key, { rejection_reason: e.target.value })} className="ui-input h-7 w-28 mt-1" />}
                      </td>
                      <td><input value={l.batch_number} onChange={(e) => update(l.key, { batch_number: e.target.value })} className="ui-input h-7 w-28 tabular font-semibold" placeholder="BATCH-001" /></td>
                      <td><input type="date" value={l.expiry_date} onChange={(e) => update(l.key, { expiry_date: e.target.value })} className="ui-input h-7 w-36" /></td>
                      <td><input type="date" value={l.manufacture_date} onChange={(e) => update(l.key, { manufacture_date: e.target.value })} className="ui-input h-7 w-36" /></td>
                      <td><input value={l.trade_price} placeholder="Optional" onChange={(e) => updateTerms(l, { trade_price: decimalInput(e.target.value) })} className="ui-input h-7 w-20 tabular text-right" /></td>
                      <td><input value={l.discount_pct} placeholder="0" onChange={(e) => updateTerms(l, { discount_pct: decimalInput(e.target.value) })} className="ui-input h-7 w-14 tabular text-right" /></td>
                      <td><input value={l.unit_cost} title={l.trade_price ? 'Filled from trade price less discount. Change it to match the invoice.' : undefined} onChange={(e) => update(l.key, { unit_cost: decimalInput(e.target.value) })} className="ui-input h-7 w-20 tabular text-right font-bold" /></td>
                      <td>
                        <select
                          value={l.vat}
                          onChange={(e) => update(l.key, { vat: e.target.value as GrnLine['vat'] })}
                          title="As charged on the supplier's invoice. This sets what the till charges the customer."
                          className={`ui-input h-7 w-28 text-xs ${l.vat ? '' : 'border-amber-400 bg-amber-50'}`}
                        >
                          <option value="">Choose…</option>
                          <option value="ZERO">No · zero-rated</option>
                          <option value="STANDARD">Yes · 16%</option>
                        </select>
                      </td>
                      <td><input value={l.temperature_on_arrival} placeholder="°C" onChange={(e) => update(l.key, { temperature_on_arrival: e.target.value.replace(/[^-\d.]/g, '') })} className="ui-input h-7 w-14 tabular text-right" /></td>
                      <td><input type="checkbox" checked={l.coa_received} onChange={(e) => update(l.key, { coa_received: e.target.checked })} /></td>
                      <td><Button size="sm" variant="ghost" onClick={() => setLines(lines.filter((x) => x.key !== l.key))} aria-label="Remove"><Trash2 size={13} /></Button></td>
                    </tr>
                    {canSetPrices && pricesOpen === l.key && (
                      <tr>
                        <td colSpan={16} className="bg-slate-50/70">
                          <SellingPricesPanel
                            productId={l.product_id}
                            uomId={l.uom_id}
                            uomCode={l.uom_options.find((u) => u.id === l.uom_id)?.code ?? ''}
                            buyingCost={l.unit_cost}
                            values={l.selling_prices}
                            onChange={(selling_prices) => update(l.key, { selling_prices })}
                          />
                        </td>
                      </tr>
                    )}
                    </Fragment>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p className="text-xs text-slate-500 py-3 text-center">Select a purchase order above to load expected products and quantities.</p>
          )}
        </FormSection>

        {create.isError && <InlineError error={create.error} />}
      </div>
    </Drawer>
  )
}

/** The prices keyed on a line, ready to post. */
function sellingPriceEntries(line: GrnLine): { price_list_id: string; unit_price: string }[] {
  return Object.entries(line.selling_prices)
    .filter(([, price]) => price !== '')
    .map(([price_list_id, unit_price]) => ({ price_list_id, unit_price }))
}

function ReceiptDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const receipt = useQuery({ queryKey: ['goods-receipts', id], queryFn: () => apiGet<GoodsReceipt>(`/api/goods-receipts/${id}`), enabled: !!id })
  const g = receipt.data
  return (
    <Drawer open={!!id} onClose={onClose} title={g?.doc_number ?? 'Goods receipt'} subtitle={g ? `${g.purchase_order ? `PO ${g.purchase_order.doc_number}` : 'Emergency receipt'} · received ${formatDateTime(g.received_at)}` : undefined} width={900}>
      {receipt.isLoading && <LoadingSkeleton />}
      {receipt.isError && <InlineError error={receipt.error} />}
      {g && (
        <div className="space-y-4">
          <div className="flex items-center gap-2"><StatusBadge status={g.status} />{g.is_emergency && <StatusBadge status="EMERGENCY" tone="amber" label="Emergency" />}{g.purchase_order && <Link to={`/buy/purchase-orders?po=${g.purchase_order.id}`} className="text-xs text-blue-600 hover:text-blue-700 hover:underline font-semibold">Open purchase order</Link>}<span className="ml-auto"><PdfDownloadButton url={`/api/goods-receipts/${g.id}/pdf`} filename={g.doc_number} label="Download GRN" /></span></div>
          <DescriptionList items={[{ label: 'Supplier', value: g.supplier?.name ?? g.supplier_id.slice(0, 8) }, { label: 'Store', value: g.store?.code ?? g.store_id.slice(0, 8) }]} />
          <table className="ui-table">
            <thead><tr><th>Batch</th><th>Expiry</th><th>Batch status</th><th className="text-right">Ordered</th><th className="text-right">Delivered</th><th className="text-right">Accepted</th><th className="text-right">Rejected</th><th className="text-right">Unit cost</th><th className="text-right">Landed cost</th></tr></thead>
            <tbody>
              {g.lines.map((l) => (
                <tr key={l.id}>
                  <td className="tabular font-mono font-semibold">{l.batch_number}{l.batch && <Link to={`/inventory/batches?batch=${l.batch.id}`} className="ml-1.5 text-xs text-blue-600 hover:text-blue-700 hover:underline font-medium">open</Link>}</td>
                  <td className="tabular">{formatDate(l.expiry_date)}</td>
                  <td>{l.batch ? <StatusBadge status={l.batch.status} /> : '—'}</td>
                  <td className="text-right"><QtyCell value={l.qty_ordered} /></td>
                  <td className="text-right"><QtyCell value={l.qty_delivered} /></td>
                  <td className="text-right"><QtyCell value={l.qty_accepted} /></td>
                  <td className="text-right"><QtyCell value={l.qty_rejected} />{l.rejection_reason && <div className="text-xs text-slate-500">{l.rejection_reason}</div>}</td>
                  <td className="text-right">
                    <MoneyCell value={l.unit_cost} />
                    {tradeTermsLabel(l.trade_price, l.discount_pct) && <div className="text-xs text-slate-500 tabular">{tradeTermsLabel(l.trade_price, l.discount_pct)}</div>}
                  </td>
                  <td className="text-right"><MoneyCell value={l.batch?.landed_unit_cost ?? l.landed_unit_cost} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Drawer>
  )
}
