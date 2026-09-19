import { useQuery } from '@tanstack/react-query'
import { Printer } from 'lucide-react'
import { useState } from 'react'
import { CustomerPicker } from '../../components/CustomerPicker'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { EmptyState, ErrorState, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { Button, Field, Input } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet } from '../../lib/api'
import { formatDate, formatDateTime, titleCase, todayIso } from '../../lib/format'
import { formatKes } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { Customer } from '../../lib/types'

type StatementRow = { date: string; type: string; reference: string; debit: string; credit: string; balance: string }

type Ageing = { current: string; d1_30: string; d31_60: string; d61_90: string; d90_plus: string; total: string }

type CustomerStatement = {
  from: string
  to: string
  generated_at: string
  organisation: { id: string; name: string; legal_name: string | null; kra_pin: string | null } | null
  branch: { id: string; code: string; name: string; address: string | null }
  customer: { id: string; code: string; name: string; phone: string | null; email: string | null; address: string | null; payment_terms_days: number | null; credit_limit: string }
  rows: StatementRow[]
  opening_balance: string
  closing_balance: string
  total_debit: string
  total_credit: string
  ageing: Ageing
}

const AGEING_BUCKETS: { key: keyof Ageing; label: string }[] = [
  { key: 'current', label: 'Current' },
  { key: 'd1_30', label: '1–30 days' },
  { key: 'd31_60', label: '31–60 days' },
  { key: 'd61_90', label: '61–90 days' },
  { key: 'd90_plus', label: 'Over 90 days' },
  { key: 'total', label: 'Total due' },
]

/*
 * Print only the statement: everything else on the page is hidden and the
 * statement is laid out on a plain white sheet.
 */
const PRINT_CSS = `
@media print {
  body * { visibility: hidden !important; }
  #customer-statement-sheet, #customer-statement-sheet * { visibility: visible !important; }
  #customer-statement-sheet { position: absolute; left: 0; top: 0; width: 100%; padding: 0; margin: 0; box-shadow: none; border: none; background: #fff; color: #000; }
  #customer-statement-sheet .no-print { display: none !important; }
  #customer-statement-sheet table { font-size: 10.5pt; }
  #customer-statement-sheet tr { break-inside: avoid; }
  @page { size: A4; margin: 14mm; }
}
@media screen { #customer-statement-sheet .print-only { display: none; } }
`

function monthStartIso(): string {
  return `${todayIso().slice(0, 8)}01`
}

/** Part 12.4 — a customer's statement: every invoice, receipt and credit note with a running balance, and the debt by age. */
export default function CustomerStatementsPage() {
  const canSales = usePermission('sale.view')
  const canAr = usePermission('finance.ar.view')
  const { data: user } = useCurrentUser()
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [from, setFrom] = useState(monthStartIso)
  const [to, setTo] = useState(todayIso)

  const statement = useQuery({
    queryKey: ['customers', 'statement', customer?.id, from, to],
    queryFn: () => apiGet<CustomerStatement>(`/api/customers/${customer?.id}/statement`, { from, to }),
    enabled: !!customer && canSales && canAr,
  })

  if (!canSales) return <NoAccess permission="sale.view" />
  if (!canAr) return <NoAccess permission="finance.ar.view" />

  const s = statement.data
  const columns: Column<StatementRow>[] = [
    { key: 'date', header: 'Date', render: (r) => <span className="tabular">{r.type === 'OPENING_BALANCE' ? formatDate(r.date) : formatDateTime(r.date)}</span> },
    { key: 'type', header: 'Type', render: (r) => (r.type === 'OPENING_BALANCE' ? <span className="font-semibold">Balance brought forward</span> : titleCase(r.type)) },
    { key: 'reference', header: 'Reference', render: (r) => <span className="tabular">{r.reference || '—'}</span> },
    { key: 'debit', header: 'Debit', align: 'right', render: (r) => (Number(r.debit) ? <MoneyCell value={r.debit} /> : null) },
    { key: 'credit', header: 'Credit', align: 'right', render: (r) => (Number(r.credit) ? <MoneyCell value={r.credit} /> : null) },
    { key: 'balance', header: 'Balance', align: 'right', render: (r) => <MoneyCell value={r.balance} className="font-semibold" /> },
  ]

  return (
    <Page>
      <style>{PRINT_CSS}</style>
      <PageHeader
        parent="Sell"
        title="Customer Statements"
        subtitle="Every invoice, receipt and credit note for a customer with a running balance, plus what is owed by age."
        actions={<Button variant="primary" disabled={!s} onClick={() => window.print()}><Printer size={13} /> Print</Button>}
      />
      <FilterBar>
        <Field label="Customer" className="w-96"><CustomerPicker value={customer} onChange={setCustomer} /></Field>
        <Field label="From" className="w-40"><Input type="date" value={from} max={to} onChange={(e) => setFrom(e.target.value)} /></Field>
        <Field label="To" className="w-40"><Input type="date" value={to} min={from} onChange={(e) => setTo(e.target.value)} /></Field>
      </FilterBar>

      {!customer ? (
        <div className="ui-card"><EmptyState title="Choose a customer" hint="Pick a customer and a date range to see their statement." /></div>
      ) : statement.isLoading ? (
        <div className="ui-card"><LoadingSkeleton rows={8} /></div>
      ) : statement.error || !s ? (
        <div className="ui-card"><ErrorState error={statement.error} onRetry={() => statement.refetch()} /></div>
      ) : (
        <div id="customer-statement-sheet" className="ui-card p-5 space-y-4">
          <header className="flex flex-wrap items-start justify-between gap-4 border-b border-slate-200 pb-3">
            <div>
              <div className="text-base font-bold text-slate-900">{s.organisation?.legal_name || s.organisation?.name || user?.active_branch?.name}</div>
              <div className="text-xs text-slate-500">{s.branch.name} ({s.branch.code}){s.branch.address ? ` · ${s.branch.address}` : ''}</div>
              {s.organisation?.kra_pin && <div className="text-xs text-slate-500">KRA PIN {s.organisation.kra_pin}</div>}
            </div>
            <div className="text-right">
              <div className="text-sm font-bold uppercase tracking-wider text-slate-800">Statement of account</div>
              <div className="text-xs text-slate-500 tabular">{formatDate(s.from)} – {formatDate(s.to)}</div>
              <div className="text-xs text-slate-400 print-only">Printed {formatDateTime(s.generated_at)}</div>
            </div>
          </header>

          <section className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <div className="text-xs uppercase tracking-wider text-slate-400 font-semibold mb-1">Customer</div>
              <div className="font-bold text-sm text-slate-900">{s.customer.name}</div>
              <div className="tabular text-slate-600 font-mono">{s.customer.code}</div>
              {s.customer.address && <div className="text-slate-600">{s.customer.address}</div>}
              {(s.customer.phone || s.customer.email) && <div className="text-slate-500">{[s.customer.phone, s.customer.email].filter(Boolean).join(' · ')}</div>}
            </div>
            <div className="text-right space-y-1">
              <div>Payment terms: <span className="font-semibold text-slate-800">{s.customer.payment_terms_days ?? 0} days</span></div>
              <div>Credit limit: <span className="font-semibold tabular text-slate-800">{formatKes(s.customer.credit_limit)}</span></div>
              <div>Opening balance: <span className="font-semibold tabular text-slate-800">{formatKes(s.opening_balance)}</span></div>
              <div className="text-sm pt-1 border-t border-slate-100">Balance due: <span className="font-bold tabular text-slate-900">{formatKes(s.closing_balance)}</span></div>
            </div>
          </section>

          <DataTable
            columns={columns}
            rows={s.rows}
            rowKey={(r) => `${r.date}-${r.type}-${r.reference}-${r.balance}`}
            emptyTitle="No transactions in this period"
            footer={
              <tr className="font-bold">
                <td colSpan={3} className="px-3 py-2 text-right">Totals for the period</td>
                <td className="px-3 py-2 text-right"><MoneyCell value={s.total_debit} /></td>
                <td className="px-3 py-2 text-right"><MoneyCell value={s.total_credit} /></td>
                <td className="px-3 py-2 text-right"><MoneyCell value={s.closing_balance} /></td>
              </tr>
            }
          />
          {s.rows.length <= 1 && <p className="text-xs text-slate-500">No invoices, receipts or credit notes between these dates — only the balance brought forward.</p>}

          <section>
            <div className="text-xs uppercase tracking-wider text-slate-500 font-semibold mb-1.5">Ageing of the balance due (as of today)</div>
            <table className="w-full text-xs border border-slate-200 rounded-xl overflow-hidden">
              <thead>
                <tr className="bg-slate-50">{AGEING_BUCKETS.map((b) => (<th key={b.key} className="px-3 py-2 text-right font-semibold text-slate-600 border-b border-slate-200">{b.label}</th>))}</tr>
              </thead>
              <tbody>
                <tr>{AGEING_BUCKETS.map((b) => (<td key={b.key} className={`px-3 py-2 text-right tabular ${b.key === 'total' ? 'font-bold text-slate-900' : 'text-slate-700'} ${b.key === 'd90_plus' && Number(s.ageing.d90_plus) > 0 ? 'text-rose-700 font-bold' : ''}`}>{formatKes(s.ageing[b.key], { symbol: false })}</td>))}</tr>
              </tbody>
            </table>
          </section>

          {Number(s.ageing.d90_plus) > 0 || Number(s.ageing.d61_90) > 0 ? (
            <div className="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs space-y-1">
              <div className="font-bold flex items-center gap-1.5">
                <span>⚠️ OVERDUE PAYMENT NOTICE:</span>
                <span>Immediate settlement required</span>
              </div>
              <p className="text-xs leading-relaxed text-rose-800">
                Invoices exceeding the agreed {s.customer.payment_terms_days ?? 30}-day credit terms attract a contractual late payment penalty interest of 2% per month in accordance with the credit facility agreement. Please remit payment promptly to avoid temporary credit hold.
              </p>
            </div>
          ) : null}

          <footer className="text-xs text-slate-500 border-t border-slate-200 pt-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
            <span>Please quote your customer code <strong className="text-slate-800">{s.customer.code}</strong> with every payment.</span>
            <span>Queries on this statement should be raised within 14 days of receipt.</span>
          </footer>
        </div>
      )}
    </Page>
  )
}
