import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost, newIdempotencyKey, withIdempotency } from '../../lib/api'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import { PAYMENT_METHODS, type DeliveryNote, type Paginated, type PaymentMethod, type SalesOrder, type TenderLine } from '../../lib/types'

/** Part 10.2 — dispatch posts stock OUT, COGS, revenue and AR in one transaction. */
export default function DispatchPage() {
  const [params] = useSearchParams()
  const queryClient = useQueryClient()
  const canDispatch = usePermission('warehouse.dispatch')
  const [orderId, setOrderId] = useState(params.get('order') ?? '')
  const [vehicle, setVehicle] = useState('')
  const [driver, setDriver] = useState('')
  const [phone, setPhone] = useState('')
  const [payments, setPayments] = useState<TenderLine[]>([])
  const [attemptKey, setAttemptKey] = useState(() => newIdempotencyKey())
  const [note, setNote] = useState<DeliveryNote | null>(null)

  const orders = useQuery({ queryKey: ['sales-orders', 'dispatchable'], queryFn: () => apiGet<Paginated<SalesOrder>>('/api/sales-orders', { per_page: 200 }) })
  const candidates = (orders.data?.data ?? []).filter((o) => ['CONFIRMED', 'IN_PROGRESS', 'PARTIALLY_FULFILLED'].includes(o.status))

  const dispatch = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<DeliveryNote>(
        `/api/sales-orders/${orderId}/dispatch`,
        { vehicle_reg: vehicle || null, driver_name: driver || null, driver_phone: phone || null, payments: payments.map((p) => ({ method: p.method, amount: p.amount, reference: p.reference || null })) },
        withIdempotency(attemptKey),
      ),
    onSuccess: (dn) => {
      setNote(dn)
      setAttemptKey(newIdempotencyKey())
      queryClient.invalidateQueries({ queryKey: ['delivery-notes'] })
      queryClient.invalidateQueries({ queryKey: ['picking-lists'] })
      toast.success(`Delivery note ${dn.doc_number} dispatched`, 'Stock, revenue and receivable posted.')
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] })
      queryClient.invalidateQueries({ queryKey: ['sales'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['customers'] })
    },
  })

  return (
    <Page>
      <PageHeader parent="Warehouse" title="Dispatch" subtitle="Dispatching a completed pick list creates the delivery note and posts the invoice. Anything unpaid goes to the customer's account." />
      <div className="grid gap-4 lg:grid-cols-[1fr_380px]">
        <div id="tour-dispatch-form">
          <Card title="Dispatch an order">
            <div className="p-4 sm:p-5 space-y-4">
              <div id="tour-dispatch-select">
                <Field label="Sales order" required>
                  <Select value={orderId} onChange={(e) => setOrderId(e.target.value)}>
                    <option value="">Choose an order to dispatch…</option>
                    {candidates.map((o) => (<option key={o.id} value={o.id}>{o.doc_number} · {o.customer?.name} · {o.status} · {formatMoney(o.grand_total)}</option>))}
                  </Select>
                </Field>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
                <Field label="Vehicle registration"><Input value={vehicle} onChange={(e) => setVehicle(e.target.value.toUpperCase())} placeholder="KDA 123A" /></Field>
                <Field label="Driver"><Input value={driver} onChange={(e) => setDriver(e.target.value)} placeholder="Driver full name" /></Field>
                <Field label="Driver phone"><Input value={phone} onChange={(e) => setPhone(e.target.value)} placeholder="0712 345 678" /></Field>
              </div>
              <Field label="Payments collected on dispatch (optional)" hint="Leave empty to invoice on the customer's credit account.">
                <div className="space-y-2">
                  {payments.map((p, i) => (
                    <div key={i} className="grid grid-cols-1 sm:grid-cols-[110px_1fr_1fr_auto] gap-2">
                      <Select value={p.method} onChange={(e) => setPayments(payments.map((x, j) => (j === i ? { ...x, method: e.target.value as PaymentMethod } : x)))}>
                        {PAYMENT_METHODS.map((m) => (<option key={m} value={m}>{m}</option>))}
                      </Select>
                      <Input inputMode="decimal" className="tabular text-right" placeholder="Amount" value={p.amount} onChange={(e) => setPayments(payments.map((x, j) => (j === i ? { ...x, amount: e.target.value.replace(/[^\d.]/g, '') } : x)))} />
                      <Input placeholder="Reference" value={p.reference ?? ''} onChange={(e) => setPayments(payments.map((x, j) => (j === i ? { ...x, reference: e.target.value } : x)))} />
                      <Button size="sm" variant="ghost" onClick={() => setPayments(payments.filter((_, j) => j !== i))} aria-label="Remove"><Trash2 size={13} /></Button>
                    </div>
                  ))}
                  <Button size="sm" onClick={() => setPayments([...payments, { method: 'MPESA', amount: '', reference: '' }])}><Plus size={12} /> Add payment</Button>
                </div>
              </Field>
              {dispatch.isError && <InlineError error={dispatch.error} />}
              <div className="flex justify-end pt-2">
                <Button variant="primary" disabled={!orderId || !canDispatch || dispatch.isPending} onClick={() => dispatch.mutate()} title={canDispatch ? undefined : 'Needs warehouse.dispatch'}>
                  {dispatch.isPending ? 'Dispatching…' : 'Confirm dispatch'}
                </Button>
              </div>
              <p className="text-xs text-slate-500">Note: The order must have a completed pick list before it can be dispatched.</p>
            </div>
          </Card>
        </div>
        <div id="tour-dispatch-note">
          <Card title="Delivery note">
            {!note ? (
              <div className="p-6 text-sm text-slate-500">The delivery note appears here after dispatch.</div>
            ) : (
              <div className="p-4 space-y-2 text-sm">
                <div className="flex items-center gap-2"><span className="font-bold tabular">{note.doc_number}</span><StatusBadge status={note.status} /></div>
                <div>Vehicle {note.vehicle_reg ?? '—'} · Driver {note.driver_name ?? '—'} {note.driver_phone ? `(${note.driver_phone})` : ''}</div>
                {note.sale_id && <div><Link to={`/sell/invoices?sale=${note.sale_id}`} className="text-blue-600 hover:text-blue-700 hover:underline font-medium">Open the posted invoice</Link></div>}
                <div><Link to={`/warehouse/deliveries?note=${note.id}`} className="text-blue-600 hover:text-blue-700 hover:underline font-medium">Record proof of delivery</Link></div>
              </div>
            )}
          </Card>
        </div>
      </div>
    </Page>
  )
}
