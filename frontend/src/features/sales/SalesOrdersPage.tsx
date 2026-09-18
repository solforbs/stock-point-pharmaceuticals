import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { CreditLimitResolver, type CreditResolution } from '../../components/CreditLimitResolver'
import { CustomerPicker } from '../../components/CustomerPicker'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError, newIdempotencyKey, withIdempotency } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { PAYMENT_TERMS_LABEL } from '../../lib/paymentTerms'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Customer, Paginated, SalesOrder } from '../../lib/types'
import { DocLinesEditor, linesPayload, type EditableLine } from './DocLinesEditor'

const STATUSES = ['DRAFT', 'CONFIRMED', 'IN_PROGRESS', 'PARTIALLY_FULFILLED', 'FULFILLED', 'CANCELLED']

export default function SalesOrdersPage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const canDiscount = usePermission('sale.discount.apply')
  const stores = useStores()
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [storeId, setStoreId] = useState('')
  const [requiredDate, setRequiredDate] = useState('')
  const [lines, setLines] = useState<EditableLine[]>([])
  const [attemptKey, setAttemptKey] = useState(() => newIdempotencyKey())
  const [paymentTerms, setPaymentTerms] = useState('')
  const selectedId = params.get('order')
  const effectiveStore = storeId || stores.data?.find((s) => s.is_sellable)?.id || ''

  const list = useQuery({
    queryKey: ['sales-orders', 'list', status, page],
    queryFn: () => apiGet<Paginated<SalesOrder>>('/api/sales-orders', { status, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<SalesOrder>(
        '/api/sales-orders',
        { customer_id: customer?.id, store_id: effectiveStore, required_date: requiredDate || null, payment_terms: paymentTerms || null, lines: linesPayload(lines) },
        withIdempotency(attemptKey),
      ),
    onSuccess: (order) => {
      toast.success(`Sales order ${order.doc_number} created`, order.approval_required ? 'Some discounts need approval.' : undefined)
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] })
      setCreating(false)
      setLines([])
      setAttemptKey(newIdempotencyKey())
      setParams({ order: order.id })
    },
  })

  const columns: Column<SalesOrder>[] = [
    { key: 'doc', header: 'Document', render: (o) => <span className="font-semibold tabular">{o.doc_number}</span>, sortValue: (o) => o.doc_number },
    { key: 'customer', header: 'Customer', render: (o) => o.customer?.name ?? '—', sortValue: (o) => o.customer?.name ?? '' },
    { key: 'status', header: 'Status', render: (o) => <StatusBadge status={o.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (o) => <span className="tabular">{o.lines_count ?? '—'}</span> },
    { key: 'required', header: 'Required', render: (o) => formatDate(o.required_date), sortValue: (o) => o.required_date ?? '' },
    { key: 'created', header: 'Created', render: (o) => formatDateTime(o.created_at), sortValue: (o) => o.created_at ?? '' },
    { key: 'total', header: 'Total', align: 'right', render: (o) => <MoneyCell value={o.grand_total} />, sortValue: (o) => Number(o.grand_total) },
  ]

  return (
    <Page>
      <PageHeader
        parent="Sell"
        title="Sales Orders"
        subtitle="Confirmed orders reserve stock; picking, dispatch and delivery happen in Warehouse."
        actions={
          <Button variant="primary" onClick={() => setCreating(true)}>
            New sales order
          </Button>
        }
      />
      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All</option>
            {STATUSES.map((s) => (
              <option key={s} value={s}>
                {s}
              </option>
            ))}
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(o) => o.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(o) => setParams({ order: o.id })} selectedKey={selectedId} emptyTitle="No sales orders" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer
        open={creating}
        onClose={() => setCreating(false)}
        title="New Sales Order"
        subtitle="Confirm sales order to reserve inventory under customer credit terms"
        width={880}
        footer={
          <>
            <div className="flex items-center gap-2 min-w-0">
              <span className="inline-flex items-center justify-center h-6 px-2 rounded-md bg-slate-100 text-slate-700 text-xs font-bold tabular">
                {lines.length} {lines.length === 1 ? 'item' : 'items'}
              </span>
              {customer ? (
                <span className="text-xs text-slate-600 truncate hidden sm:inline">
                  Client: <strong className="text-slate-900 font-bold">{customer.name}</strong>
                </span>
              ) : (
                <span className="text-xs font-semibold text-amber-600">
                  Select a customer to proceed
                </span>
              )}
            </div>
            <div className="flex items-center gap-2 shrink-0">
              <Button onClick={() => setCreating(false)} variant="secondary" size="md">
                Cancel
              </Button>
              <Button
                variant="primary"
                size="md"
                disabled={!customer || !effectiveStore || lines.length === 0 || create.isPending}
                onClick={() => create.mutate()}
                className="font-bold shadow-md shadow-blue-600/20"
              >
                {create.isPending ? 'Saving…' : 'Create order →'}
              </Button>
            </div>
          </>
        }
      >
        <div className="space-y-4">
          {/* Card 1: Client & Fulfillment */}
          <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xs space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center gap-2">
                <div className="h-7 w-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">
                  1
                </div>
                <div>
                  <h3 className="text-[13.5px] font-extrabold text-slate-900">Client & Delivery</h3>
                  <p className="text-[11.5px] text-slate-400 font-medium">Customer account, fulfillment store, and payment terms</p>
                </div>
              </div>
              <span className="text-[10.5px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">
                Required
              </span>
            </div>

            <div className="space-y-3.5">
              <Field label="Customer Account" required>
                <CustomerPicker value={customer} onChange={setCustomer} placeholder="Search customer by name, code, or phone number..." />
              </Field>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <Field label="Fulfillment Store" required>
                  <Select value={effectiveStore} onChange={(e) => setStoreId(e.target.value)}>
                    {(stores.data ?? []).map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.code} · {s.name}
                      </option>
                    ))}
                  </Select>
                </Field>

                <Field label="Required Date">
                  <Input type="date" value={requiredDate} onChange={(e) => setRequiredDate(e.target.value)} />
                </Field>
              </div>

              <Field label="Payment Terms" hint="Automatic: on account when customer has credit limit, otherwise cash on delivery.">
                <Select value={paymentTerms} onChange={(e) => setPaymentTerms(e.target.value)}>
                  <option value="">Automatic</option>
                  <option value="ACCOUNT">{PAYMENT_TERMS_LABEL.ACCOUNT}</option>
                  <option value="CASH_ON_DELIVERY">{PAYMENT_TERMS_LABEL.CASH_ON_DELIVERY}</option>
                </Select>
              </Field>
            </div>
          </div>

          {/* Card 2: Order Items */}
          <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xs space-y-3.5">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center gap-2">
                <div className="h-7 w-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs">
                  2
                </div>
                <div>
                  <h3 className="text-[13.5px] font-extrabold text-slate-900">Order Line Items</h3>
                  <p className="text-[11.5px] text-slate-400 font-medium">Add products, quantities, UOMs, and negotiated discounts</p>
                </div>
              </div>
              <span className="text-[11px] font-bold tabular px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                {lines.length} {lines.length === 1 ? 'line' : 'lines'}
              </span>
            </div>

            <DocLinesEditor lines={lines} onChange={setLines} canDiscount={canDiscount} />
          </div>

          {create.isError && <InlineError error={create.error} />}
        </div>
      </Drawer>

      <SalesOrderDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

export function SalesOrderDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [cancelling, setCancelling] = useState(false)
  const order = useQuery({ queryKey: ['sales-orders', id], queryFn: () => apiGet<SalesOrder>(`/api/sales-orders/${id}`), enabled: !!id })

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['sales-orders'] })
    queryClient.invalidateQueries({ queryKey: ['inventory'] })
    queryClient.invalidateQueries({ queryKey: ['customers'] })
  }

  const confirm = useMutation({
    meta: { silent: true },
    mutationFn: (resolution: CreditResolution = {}) => apiPost<SalesOrder>(`/api/sales-orders/${id}/confirm`, resolution),
    onSuccess: (o) => {
      toast.success(`${o.doc_number} confirmed`, 'Stock reserved.')
      invalidate()
    },
  })
  const cancel = useMutation({
    mutationFn: (reason: string) => apiPost<SalesOrder>(`/api/sales-orders/${id}/cancel`, { reason }),
    onSuccess: (o) => {
      toast.success(`${o.doc_number} cancelled`)
      setCancelling(false)
      invalidate()
    },
  })

  const o = order.data
  const cancellable = o && !['FULFILLED', 'CANCELLED'].includes(o.status)
  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={o?.doc_number ?? 'Sales order'}
      subtitle={o ? `${o.customer?.name ?? ''} · created ${formatDateTime(o.created_at)}` : undefined}
      width={800}
      actions={
        o ? (
          <div className="flex gap-2">
            {o.status === 'DRAFT' && (
              <Button variant="success" size="sm" onClick={() => confirm.mutate({})} disabled={confirm.isPending}>
                Confirm & reserve
              </Button>
            )}
            {(o.status === 'CONFIRMED' || o.status === 'IN_PROGRESS' || o.status === 'PARTIALLY_FULFILLED') && (
              <Link to={`/warehouse/pick-lists?order=${o.id}`} className="inline-flex items-center h-7 px-2.5 rounded-md border border-[var(--border-strong)] text-[11.5px] font-semibold">
                Pick
              </Link>
            )}
            {cancellable && (
              <Button variant="danger" size="sm" onClick={() => setCancelling(true)}>
                Cancel order
              </Button>
            )}
          </div>
        ) : null
      }
    >
      {order.isLoading && <LoadingSkeleton />}
      {order.isError && <InlineError error={order.error} />}
      {confirm.isError && (
        <div className="mb-3 space-y-2">
          <CreditLimitResolver error={confirm.error} pending={confirm.isPending} onResolve={(r) => confirm.mutate(r)} />
          {getApiError(confirm.error).code !== 'CREDIT_LIMIT_EXCEEDED' && <InlineError error={confirm.error} />}
        </div>
      )}
      {o && (
        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <StatusBadge status={o.status} />
            {o.cancel_reason && <span className="text-[11.5px] text-[var(--status-red)]">Cancelled: {o.cancel_reason}</span>}
          </div>
          <DescriptionList
            items={[
              { label: 'Required', value: formatDate(o.required_date) },
              { label: 'Payment terms', value: `${PAYMENT_TERMS_LABEL[o.payment_terms ?? 'ACCOUNT']}${o.credit_override_reason ? ` · limit overridden: ${o.credit_override_reason}` : ''}` },
              { label: 'Quotation', value: o.quotation_id ?? '—' },
            ]}
          />
          <div className="overflow-x-auto rounded-xl border border-slate-200 shadow-2xs">
            <table className="ui-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Product</th>
                  <th className="text-right">Qty</th>
                  <th className="text-right">Reserved</th>
                  <th className="text-right">Picked</th>
                  <th className="text-right">Dispatched</th>
                  <th className="text-right">Unit price</th>
                  <th className="text-right">Total</th>
                </tr>
              </thead>
              <tbody>
                {(o.lines ?? []).map((line) => (
                  <tr key={line.id}>
                    <td className="tabular font-medium text-slate-500">{line.line_number}</td>
                    <td className="font-semibold text-slate-900">{line.product?.name ?? line.product_id}</td>
                    <td className="text-right">
                      <QtyCell value={line.qty} />
                    </td>
                    <td className="text-right">
                      <QtyCell value={line.qty_reserved_base} />
                    </td>
                    <td className="text-right">
                      <QtyCell value={line.qty_picked_base} />
                    </td>
                    <td className="text-right">
                      <QtyCell value={line.qty_dispatched_base} />
                    </td>
                    <td className="text-right">
                      <MoneyCell value={line.unit_price} />
                    </td>
                    <td className="text-right font-bold">
                      <MoneyCell value={line.line_total} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="ml-auto w-full sm:w-80 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs space-y-2 text-[13px] tabular">
            <div className="flex justify-between text-slate-500 font-medium"><span>Subtotal</span><MoneyCell value={o.subtotal} /></div>
            <div className="flex justify-between text-slate-500 font-medium"><span>Discount</span><MoneyCell value={`-${o.discount_total}`} /></div>
            <div className="flex justify-between text-slate-500 font-medium"><span>Tax</span><MoneyCell value={o.tax_total} /></div>
            <div className="flex justify-between pt-2 border-t border-slate-200 text-slate-900 font-black text-sm">
              <span>Grand Total</span>
              <MoneyCell value={o.grand_total} className="font-black text-base text-blue-600" />
            </div>
          </div>
        </div>
      )}
      <ConfirmDialog
        open={cancelling}
        title={`Cancel ${o?.doc_number ?? ''}?`}
        message="Reservations are released. The order stays on record as CANCELLED."
        confirmLabel="Cancel order"
        danger
        requireReason="Reason"
        isPending={cancel.isPending}
        onCancel={() => setCancelling(false)}
        onConfirm={(reason) => cancel.mutate(reason)}
      />
    </Drawer>
  )
}
