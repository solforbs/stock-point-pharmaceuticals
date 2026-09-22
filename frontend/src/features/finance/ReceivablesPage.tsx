import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { CustomerPicker } from '../../components/CustomerPicker'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Modal } from '../../components/ui/Modal'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { dEq, dSum, isValidDecimal } from '../../lib/decimal'
import { formatDate } from '../../lib/format'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import { PAYMENT_METHODS, type ArAgeing, type ArAgeingRow, type Customer, type Paginated, type Payment, type Sale } from '../../lib/types'

export default function ReceivablesPage() {
  const queryClient = useQueryClient()
  const canRecord = usePermission('payment.record')
  const [paying, setPaying] = useState<ArAgeingRow | null>(null)
  const ageing = useQuery({ queryKey: ['finance', 'ar-ageing'], queryFn: () => apiGet<ArAgeing>('/api/finance/ar-ageing') })

  const columns: Column<ArAgeingRow>[] = [
    { key: 'name', header: 'Customer', render: (r) => <><div className="font-semibold text-slate-900">{r.name}</div><div className="text-xs text-slate-500 font-mono">{r.code} · terms {r.payment_terms_days ?? 0} d</div></>, sortValue: (r) => r.name },
    { key: 'current', header: 'Current', align: 'right', render: (r) => <MoneyCell value={r.current} />, sortValue: (r) => Number(r.current) },
    { key: 'd1', header: '1–30', align: 'right', render: (r) => <MoneyCell value={r.d1_30} />, sortValue: (r) => Number(r.d1_30) },
    { key: 'd31', header: '31–60', align: 'right', render: (r) => <MoneyCell value={r.d31_60} className={Number(r.d31_60) > 0 ? 'text-amber-700 font-semibold' : ''} />, sortValue: (r) => Number(r.d31_60) },
    { key: 'd61', header: '61–90', align: 'right', render: (r) => <MoneyCell value={r.d61_90} className={Number(r.d61_90) > 0 ? 'text-amber-700 font-semibold' : ''} />, sortValue: (r) => Number(r.d61_90) },
    { key: 'd90', header: '90+', align: 'right', render: (r) => <MoneyCell value={r.d90_plus} className={Number(r.d90_plus) > 0 ? 'text-rose-600 font-bold' : ''} />, sortValue: (r) => Number(r.d90_plus) },
    { key: 'total', header: 'Total', align: 'right', render: (r) => <MoneyCell value={r.total} className="font-bold" />, sortValue: (r) => Number(r.total) },
    { key: 'limit', header: 'Limit / exposure', align: 'right', render: (r) => <span className="tabular text-xs text-slate-600">{formatMoney(r.credit_limit)} / {formatMoney(r.exposure)}</span> },
    { key: 'hold', header: '', render: (r) => (r.on_hold ? <StatusBadge status="ON_HOLD" label="Hold" /> : null) },
    { key: 'act', header: '', align: 'right', render: (r) => (canRecord ? <Button size="sm" onClick={() => setPaying(r)}>Receive</Button> : null) },
  ]
  const t = ageing.data?.totals

  return (
    <Page>
      <PageHeader parent="Finance" title="Receivables" subtitle={ageing.data ? `AR ageing as of ${formatDate(ageing.data.as_of)}` : 'AR ageing'} actions={canRecord ? <Button id="tour-receivables-record" variant="primary" onClick={() => setPaying({} as ArAgeingRow)}>Record customer payment</Button> : null} />
      <div id="tour-receivables-table" className="ui-card">
        <DataTable
          columns={columns}
          rows={ageing.data?.data}
          rowKey={(r) => r.customer_id}
          isLoading={ageing.isLoading}
          error={ageing.error}
          onRetry={() => ageing.refetch()}
          emptyTitle="Nothing outstanding"
          initialSort={{ key: 'total', dir: 'desc' }}
          footer={
            t ? (
              <tr className="font-bold bg-[var(--surface-2)]">
                <td>Totals</td>
                <td className="text-right"><MoneyCell value={t.current} /></td>
                <td className="text-right"><MoneyCell value={t.d1_30} /></td>
                <td className="text-right"><MoneyCell value={t.d31_60} /></td>
                <td className="text-right"><MoneyCell value={t.d61_90} /></td>
                <td className="text-right"><MoneyCell value={t.d90_plus} /></td>
                <td className="text-right"><MoneyCell value={t.total} /></td>
                <td colSpan={3} />
              </tr>
            ) : null
          }
        />
      </div>
      <ReceiptModal row={paying} onClose={() => setPaying(null)} onDone={() => { setPaying(null); queryClient.invalidateQueries({ queryKey: ['finance'] }); queryClient.invalidateQueries({ queryKey: ['customers'] }) }} />
    </Page>
  )
}

function ReceiptModal({ row, onClose, onDone }: { row: ArAgeingRow | null; onClose: () => void; onDone: () => void }) {
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [method, setMethod] = useState('MPESA')
  const [reference, setReference] = useState('')
  const [amount, setAmount] = useState('')
  const [allocate, setAllocate] = useState(false)
  const [allocations, setAllocations] = useState<Record<string, string>>({})
  const customerId = row?.customer_id ?? customer?.id

  // The customer's posted invoices, for explicit allocation. Left unallocated,
  // the server applies the receipt to the oldest invoices first.
  const invoices = useQuery({
    queryKey: ['sales', 'for-allocation', customerId],
    queryFn: () => apiGet<Paginated<Sale>>('/api/sales', { customer_id: customerId, status: 'POSTED', per_page: 100 }),
    enabled: !!customerId && allocate,
  })
  const allocationRows = Object.entries(allocations).filter(([, v]) => isValidDecimal(v) && Number(v) > 0)
  const allocatedTotal = dSum(allocationRows.map(([, v]) => v))
  const allocationOk = !allocate || (allocationRows.length > 0 && isValidDecimal(amount) && dEq(allocatedTotal, amount))

  const record = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<Payment>('/api/payments', {
        customer_id: customerId,
        method,
        reference: reference || null,
        amount,
        allocations: allocate ? allocationRows.map(([sale_id, amt]) => ({ sale_id, amount: amt })) : null,
      }),
    onSuccess: (p) => {
      toast.success(`Receipt of ${formatMoney(p.amount)} recorded`, p.allocations?.length ? `Allocated to ${p.allocations.length} invoice${p.allocations.length === 1 ? '' : 's'}.` : 'Applied to the oldest invoices first.')
      setAmount('')
      setReference('')
      setAllocations({})
      onDone()
    },
  })

  return (
    <Modal
      open={row !== null}
      onClose={onClose}
      title={row?.name ? `Receive from ${row.name}` : 'Record customer payment'}
      width={620}
      footer={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="primary" disabled={!customerId || !/^\d+(\.\d+)?$/.test(amount) || Number(amount) <= 0 || (method === 'MPESA' && !reference.trim()) || !allocationOk || record.isPending} onClick={() => record.mutate()}>
            {record.isPending ? 'Posting…' : 'Post receipt'}
          </Button>
        </>
      }
    >
      <div className="space-y-3">
        {!row?.customer_id && <Field label="Customer" required><CustomerPicker value={customer} onChange={setCustomer} /></Field>}
        {row?.total && <div className="text-xs tabular text-slate-700">Outstanding: <b>{formatMoney(row.total)}</b></div>}
        <Field label="Method" required>
          <Select value={method} onChange={(e) => setMethod(e.target.value)}>{PAYMENT_METHODS.map((m) => (<option key={m} value={m}>{m}</option>))}</Select>
        </Field>
        <Field label="Reference" required={method === 'MPESA'} hint={method === 'MPESA' ? 'An M-PESA receipt must carry the transaction code; a repeated reference is treated as the same receipt.' : undefined}>
          <Input value={reference} onChange={(e) => setReference(e.target.value)} />
        </Field>
        <Field label="Amount (KES)" required>
          <div className="flex gap-2">
            <Input inputMode="decimal" className="tabular text-right" value={amount} onChange={(e) => setAmount(e.target.value.replace(/[^\d.]/g, ''))} />
            {row?.total && <Button size="sm" onClick={() => setAmount(String(Number(row.total)))}>Full</Button>}
          </div>
        </Field>
        <label className="flex items-center gap-2 text-xs font-medium text-slate-700">
          <input type="checkbox" checked={allocate} disabled={!customerId} onChange={(e) => setAllocate(e.target.checked)} /> Allocate to specific invoices (otherwise oldest first)
        </label>
        {allocate && customerId && (
          <div className="ui-card max-h-56 overflow-y-auto">
            {invoices.isLoading ? (
              <LoadingSkeleton rows={3} />
            ) : (invoices.data?.data ?? []).length === 0 ? (
              <div className="p-3 text-xs text-slate-500">No posted invoices for this customer.</div>
            ) : (
              <table className="ui-table">
                <thead><tr><th>Invoice</th><th>Posted</th><th className="text-right">Invoice total</th><th className="text-right">Allocate</th></tr></thead>
                <tbody>
                  {(invoices.data?.data ?? []).map((s) => (
                    <tr key={s.id}>
                      <td className="tabular font-mono font-semibold">{s.doc_number}</td>
                      <td className="tabular">{formatDate(s.posted_at)}</td>
                      <td className="text-right"><MoneyCell value={s.grand_total} /></td>
                      <td className="text-right"><input value={allocations[s.id] ?? ''} onChange={(e) => setAllocations({ ...allocations, [s.id]: e.target.value.replace(/[^\d.]/g, '') })} className="ui-input h-7 w-28 tabular text-right text-sm" /></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
            <div className={`px-3 py-1.5 text-xs tabular border-t border-slate-200 ${allocationOk ? 'text-slate-500' : 'text-rose-600 font-medium'}`}>
              Allocated {formatMoney(allocatedTotal)} of {formatMoney(amount || '0')}{allocationOk ? '' : ' — allocations must equal the receipt amount'}. The server refuses an allocation above what an invoice still owes.
            </div>
          </div>
        )}
        {record.isError && <InlineError error={record.error} />}
      </div>
    </Modal>
  )
}
