import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, CircleAlert, CircleMinus } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { EmptyState, InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Decimal, MatchResult, NamedRef, Paginated, SupplierInvoice } from '../../lib/types'

type ComparisonLine = {
  line_id: string
  product: { id: string; code: string | null; name: string | null }
  po_number: string | null
  grn_numbers: string[]
  qty_ordered: Decimal | null
  qty_accepted: Decimal | null
  qty_invoiced: Decimal
  po_unit_price: Decimal | null
  invoice_unit_price: Decimal
  price_variance_per_unit: Decimal | null
  price_variance_pct: string | null
  line_variance: Decimal | null
  checks: { po_linked: boolean; supplier: boolean | null; quantity: boolean | null; price: boolean | null }
  failures: string[]
}

type Comparison = {
  invoice: Pick<SupplierInvoice, 'id' | 'doc_number' | 'invoice_number' | 'invoice_date' | 'due_date' | 'match_status' | 'matched_at' | 'subtotal' | 'tax_total' | 'grand_total'> & { supplier: NamedRef | null }
  tolerances: { price_variance_pct: string; price_variance_abs: string }
  lines: ComparisonLine[]
  would_match: boolean
}

const QUEUES = [
  ['UNMATCHED', 'Waiting'],
  ['EXCEPTION', 'Exceptions'],
  ['MATCHED', 'Matched'],
] as const

/**
 * Part 9.4 — the purchase order, the goods received and the supplier's bill
 * laid side by side, line by line. Supplier Invoices is where a bill is
 * recorded; this is where it is checked before anything is owed.
 */
export default function ThreeWayMatchPage() {
  const [params, setParams] = useSearchParams()
  const [queue, setQueue] = useState<(typeof QUEUES)[number][0]>('UNMATCHED')
  const selectedId = params.get('invoice')

  const list = useQuery({
    queryKey: ['supplier-invoices', 'list', queue, '', 1],
    queryFn: () => apiGet<Paginated<SupplierInvoice>>('/api/supplier-invoices', { match_status: queue, page: 1, per_page: 100 }),
  })

  function select(id: string) {
    setParams((prev) => { const next = new URLSearchParams(prev); next.set('invoice', id); return next })
  }

  return (
    <Page>
      <PageHeader
        parent="Procurement"
        title="Three-Way Match"
        subtitle="Checks each supplier bill against its purchase order and what was actually received. Only a clean match becomes a payable."
      />
      <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
        <div className="ui-card p-3 space-y-3 self-start">
          <div className="flex gap-1">
            {QUEUES.map(([value, label]) => (
              <Button key={value} size="sm" variant={queue === value ? 'primary' : 'secondary'} onClick={() => setQueue(value)}>{label}</Button>
            ))}
          </div>
          {list.isLoading && <LoadingSkeleton rows={4} />}
          {list.isError && <InlineError error={list.error} />}
          {list.data && list.data.data.length === 0 && <p className="text-sm text-slate-500 px-1 py-4">Nothing in this queue.</p>}
          <ul className="space-y-1.5">
            {list.data?.data.map((inv) => (
              <li key={inv.id}>
                <button
                  type="button"
                  onClick={() => select(inv.id)}
                  className={`w-full text-left rounded-xl border px-3 py-2 transition-colors cursor-pointer ${selectedId === inv.id ? 'border-blue-400 bg-blue-50/60' : 'border-slate-200 hover:bg-slate-50'}`}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-semibold text-sm tabular">{inv.doc_number}</span>
                    <MoneyCell value={inv.grand_total} />
                  </div>
                  <div className="text-xs text-slate-500 truncate">{inv.supplier?.name ?? '—'} · {inv.invoice_number} · {formatDate(inv.invoice_date)}</div>
                </button>
              </li>
            ))}
          </ul>
        </div>
        {selectedId ? <ComparisonPanel id={selectedId} /> : (
          <div className="ui-card">
            <EmptyState title="Choose a supplier invoice" hint="Pick one from the queue to see the order, the receipt and the bill side by side." />
          </div>
        )}
      </div>
    </Page>
  )
}

function ComparisonPanel({ id }: { id: string }) {
  const queryClient = useQueryClient()
  const canMatch = usePermission('invoice.match')
  const comparison = useQuery({
    queryKey: ['supplier-invoices', 'comparison', id],
    queryFn: () => apiGet<Comparison>(`/api/supplier-invoices/${id}/comparison`),
  })

  const runMatch = useMutation({
    mutationFn: () => apiPost<MatchResult>(`/api/supplier-invoices/${id}/match`),
    onSuccess: (result) => {
      queryClient.invalidateQueries({ queryKey: ['supplier-invoices'] })
      queryClient.invalidateQueries({ queryKey: ['payables'] })
      if (result.matched) toast.success('Matched', 'The payable has been created.')
      else toast.warning('Match exception', 'Variances beyond tolerance; no payable yet.')
    },
  })

  if (comparison.isLoading) return <div className="ui-card p-4"><LoadingSkeleton rows={6} /></div>
  if (comparison.isError) return <div className="ui-card p-4"><InlineError error={comparison.error} /></div>
  const data = comparison.data
  if (!data) return null
  const inv = data.invoice

  return (
    <div className="ui-card p-4 space-y-4 min-w-0">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="flex items-center gap-2">
            <h2 className="text-lg font-bold text-slate-900">{inv.doc_number}</h2>
            <StatusBadge status={inv.match_status} />
          </div>
          <p className="text-sm text-slate-500">
            {inv.supplier?.name ?? '—'} · supplier invoice {inv.invoice_number} · dated {formatDate(inv.invoice_date)}
            {inv.matched_at && <> · matched {formatDateTime(inv.matched_at)}</>}
          </p>
          <Link to={`/buy/supplier-invoices?invoice=${inv.id}`} className="text-xs text-blue-600 font-semibold hover:underline">Open the invoice</Link>
        </div>
        {inv.match_status !== 'MATCHED' && canMatch && (
          <Button variant="primary" disabled={runMatch.isPending} onClick={() => runMatch.mutate()}>
            {runMatch.isPending ? 'Matching…' : data.would_match ? 'Approve match' : 'Run match (will raise an exception)'}
          </Button>
        )}
      </div>

      <div className={`rounded-xl border px-3.5 py-2.5 text-sm ${data.would_match ? 'border-emerald-300 bg-emerald-50/60 text-emerald-800' : 'border-amber-300 bg-amber-50/60 text-amber-800'}`}>
        {data.would_match
          ? 'Every line agrees with its order and receipt, within tolerance.'
          : `${data.lines.filter((l) => l.failures.length > 0).length} line(s) fall outside tolerance. Price tolerance: ${data.tolerances.price_variance_pct}% and KES ${Number(data.tolerances.price_variance_abs).toLocaleString()} per line (a line fails only when it exceeds both).`}
      </div>

      <div className="overflow-x-auto pos-scroll">
        <table className="w-full text-sm">
          <thead>
            <tr className="text-left text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200">
              <th className="py-2 pr-3">Product</th>
              <th className="py-2 pr-3 bg-slate-50/80 px-2" colSpan={2}>1 · Purchase order</th>
              <th className="py-2 pr-3 bg-blue-50/60 px-2">2 · Received</th>
              <th className="py-2 pr-3 bg-violet-50/60 px-2" colSpan={2}>3 · Invoiced</th>
              <th className="py-2 pr-3 text-right">Price variance</th>
              <th className="py-2">Result</th>
            </tr>
            <tr className="text-left text-[11px] text-slate-400 border-b border-slate-100">
              <th />
              <th className="px-2 bg-slate-50/80 text-right font-medium">Qty</th>
              <th className="px-2 bg-slate-50/80 text-right font-medium">Unit price</th>
              <th className="px-2 bg-blue-50/60 text-right font-medium">Qty accepted</th>
              <th className="px-2 bg-violet-50/60 text-right font-medium">Qty</th>
              <th className="px-2 bg-violet-50/60 text-right font-medium">Unit price</th>
              <th />
              <th />
            </tr>
          </thead>
          <tbody>
            {data.lines.map((line) => (
              <tr key={line.line_id} className="border-b border-slate-100 align-top">
                <td className="py-2 pr-3">
                  <div className="font-semibold text-slate-800">{line.product.name ?? line.product.id}</div>
                  <div className="text-xs text-slate-500">{line.product.code} {line.po_number && <>· {line.po_number}</>} {line.grn_numbers.length > 0 && <>· {line.grn_numbers.join(', ')}</>}</div>
                </td>
                <td className="px-2 py-2 bg-slate-50/80 text-right">{line.qty_ordered !== null ? <QtyCell value={line.qty_ordered} /> : '—'}</td>
                <td className="px-2 py-2 bg-slate-50/80 text-right">{line.po_unit_price !== null ? <MoneyCell value={line.po_unit_price} /> : '—'}</td>
                <td className={`px-2 py-2 bg-blue-50/60 text-right ${line.checks.quantity === false ? 'text-rose-700 font-semibold' : ''}`}>{line.qty_accepted !== null ? <QtyCell value={line.qty_accepted} /> : '—'}</td>
                <td className={`px-2 py-2 bg-violet-50/60 text-right ${line.checks.quantity === false ? 'text-rose-700 font-semibold' : ''}`}><QtyCell value={line.qty_invoiced} /></td>
                <td className={`px-2 py-2 bg-violet-50/60 text-right ${line.checks.price === false ? 'text-rose-700 font-semibold' : ''}`}><MoneyCell value={line.invoice_unit_price} /></td>
                <td className="py-2 pr-3 text-right tabular text-xs">
                  {line.price_variance_per_unit !== null ? <>{Number(line.price_variance_per_unit) > 0 ? '+' : ''}{Number(line.price_variance_per_unit).toFixed(2)} ({line.price_variance_pct}%)</> : '—'}
                </td>
                <td className="py-2">
                  <LineResult line={line} />
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {runMatch.isError && <InlineError error={runMatch.error} />}
    </div>
  )
}

function LineResult({ line }: { line: ComparisonLine }) {
  if (line.failures.length === 0) {
    return <span className="inline-flex items-center gap-1 text-emerald-700 text-xs font-semibold"><CheckCircle2 size={14} /> Agrees</span>
  }
  if (!line.checks.po_linked) {
    return <span className="inline-flex items-center gap-1 text-slate-600 text-xs font-semibold"><CircleMinus size={14} /> No PO line</span>
  }
  const problems = [
    line.checks.supplier === false && 'wrong supplier',
    line.checks.quantity === false && 'billed more than received',
    line.checks.price === false && 'price above tolerance',
  ].filter(Boolean)

  return (
    <span className="inline-flex items-start gap-1 text-rose-700 text-xs font-semibold" title={line.failures.join('\n')}>
      <CircleAlert size={14} className="shrink-0 mt-px" /> {problems.join(', ')}
    </span>
  )
}
