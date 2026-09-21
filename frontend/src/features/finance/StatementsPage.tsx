import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Field, Input } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { formatDate, todayIso } from '../../lib/format'
import type { TrialBalance } from '../../lib/types'

type Row = TrialBalance['accounts'][number]

export default function StatementsPage() {
  const [asOf, setAsOf] = useState(todayIso())
  const tb = useQuery({ queryKey: ['finance', 'trial-balance', asOf], queryFn: () => apiGet<TrialBalance>('/api/finance/trial-balance', { as_of: asOf }), placeholderData: (prev) => prev })

  const columns: Column<Row>[] = [
    { key: 'code', header: 'Account', render: (r) => <span className="font-semibold tabular font-mono">{r.code}</span>, sortValue: (r) => r.code },
    { key: 'name', header: 'Name', render: (r) => <>{r.name}{r.system_role && <span className="ml-1.5 text-xs text-slate-400 font-mono">({r.system_role})</span>}</>, sortValue: (r) => r.name },
    { key: 'type', header: 'Type', render: (r) => r.account_type, sortValue: (r) => r.account_type },
    { key: 'debit', header: 'Debit', align: 'right', render: (r) => <MoneyCell value={r.debit} />, sortValue: (r) => Number(r.debit) },
    { key: 'credit', header: 'Credit', align: 'right', render: (r) => <MoneyCell value={r.credit} />, sortValue: (r) => Number(r.credit) },
    { key: 'net', header: 'Net (Dr − Cr)', align: 'right', render: (r) => <MoneyCell value={r.net} className="font-semibold" />, sortValue: (r) => Number(r.net) },
  ]

  return (
    <Page>
      <PageHeader parent="Finance" title="Financial Statements" subtitle="Trial balance derived from posted journals only. Profit & loss and balance sheet are in Reports." actions={tb.data ? <StatusBadge status={tb.data.balanced ? 'OK' : 'FAILED'} label={tb.data.balanced ? 'Balanced' : 'Out of balance'} /> : null} />
      <div id="tour-statements-filter">
        <FilterBar>
          <Field label="As of"><Input type="date" value={asOf} onChange={(e) => setAsOf(e.target.value)} /></Field>
        </FilterBar>
      </div>
      <div id="tour-statements-table" className="ui-card">
        <DataTable
          columns={columns}
          rows={tb.data?.accounts.filter((a) => Number(a.debit) !== 0 || Number(a.credit) !== 0)}
          rowKey={(r) => r.code}
          isLoading={tb.isLoading}
          error={tb.error}
          onRetry={() => tb.refetch()}
          emptyTitle="No postings yet"
          footer={
            tb.data ? (
              <tr className="font-bold bg-[var(--surface-2)]">
                <td colSpan={3}>Totals as of {formatDate(tb.data.as_of)}</td>
                <td className="text-right"><MoneyCell value={tb.data.total_debit} /></td>
                <td className="text-right"><MoneyCell value={tb.data.total_credit} /></td>
                <td className="text-right"><MoneyCell value={(Number(tb.data.total_debit) - Number(tb.data.total_credit)).toFixed(4)} /></td>
              </tr>
            ) : null
          }
        />
      </div>
    </Page>
  )
}
