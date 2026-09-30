import { useQuery } from '@tanstack/react-query'
import { BookOpen } from 'lucide-react'
import { Link } from 'react-router-dom'
import { apiGet } from '../lib/api'
import { formatDate } from '../lib/format'
import { usePermission } from '../lib/permissions'
import type { JournalEntry, Paginated } from '../lib/types'
import { MoneyCell } from './ui/MoneyCell'

/** What each ledger role means to the person reading the document. */
const AFFECTS: Record<string, string> = {
  AR_CONTROL: 'Receivables (customer balance)',
  AP_CONTROL: 'Payables (supplier balance)',
  CASH: 'Cash in till',
  BANK: 'Bank',
  MPESA_CLEARING: 'M-PESA',
  PETTY_CASH: 'Petty cash',
  INVENTORY: 'Inventory value',
  COGS: 'Cost of goods sold',
  VAT_OUTPUT: 'VAT payable',
  VAT_INPUT: 'VAT input',
  SALES_RETAIL: 'Sales',
  SALES_WHOLESALE: 'Sales',
  SALES_DISPENSING: 'Sales',
  SALES_DISCOUNTS: 'Discounts',
  SALES_RETURNS: 'Sales returns',
  SUPPLIES_EXPENSE: 'Supplies expense',
  GRN_ACCRUAL: 'Goods received, not yet invoiced',
}

/**
 * "Which accounts did this document just move?" The journals a document
 * posted, line by line, with the sections of the system they touch. Shown to
 * people who may read journals; hidden from everyone else.
 */
export function PostingImpact({ sourceId, enabled = true }: { sourceId: string | null | undefined; enabled?: boolean }) {
  const canSee = usePermission('journal.post')
  const journals = useQuery({
    queryKey: ['finance', 'journals', 'for-document', sourceId],
    queryFn: () => apiGet<Paginated<JournalEntry>>('/api/finance/journals', { source_doc_id: sourceId, per_page: 20, sort_by: 'posted_at', sort_dir: 'asc' }),
    enabled: enabled && canSee && !!sourceId,
  })

  if (!canSee || !sourceId) return null
  const entries = journals.data?.data ?? []
  if (journals.isLoading || entries.length === 0) return null

  const touched = [...new Set(entries.flatMap((j) => j.lines.map((l) => (l.account?.system_role ? AFFECTS[l.account.system_role] : null)).filter((x): x is string => !!x)))]

  return (
    <section className="rounded-xl border border-slate-200 bg-slate-50/60 p-3.5 space-y-2.5" aria-label="Accounting entries">
      <div className="flex items-center justify-between gap-2">
        <h3 className="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-slate-600"><BookOpen size={13} /> Accounting entries posted</h3>
        {touched.length > 0 && <div className="text-xs text-slate-500">Updated: {touched.join(' · ')}</div>}
      </div>
      {entries.map((j) => (
        <div key={j.id} className="ui-card overflow-hidden">
          <div className="flex items-center justify-between px-3 py-1.5 bg-white border-b border-slate-100 text-xs">
            <Link to={`/finance/journals?q=${encodeURIComponent(j.doc_number)}`} className="tabular font-mono font-semibold text-blue-700 hover:underline">{j.doc_number}</Link>
            <span className="text-slate-500">{formatDate(j.entry_date)}</span>
          </div>
          <table className="ui-table">
            <thead><tr><th>Account</th><th className="text-right">Debit</th><th className="text-right">Credit</th></tr></thead>
            <tbody>
              {j.lines.map((l) => (
                <tr key={l.id}>
                  <td><span className="tabular font-mono text-slate-500">{l.account?.code}</span> {l.account?.name}</td>
                  <td className="text-right">{Number(l.debit_amount) > 0 ? <MoneyCell value={l.debit_amount} /> : ''}</td>
                  <td className="text-right">{Number(l.credit_amount) > 0 ? <MoneyCell value={l.credit_amount} /> : ''}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ))}
    </section>
  )
}
