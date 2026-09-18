import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { Field, Input } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import type { JournalEntry, Paginated } from '../../lib/types'

export default function JournalsPage() {
  const [filters, setFilters] = useState({ source_doc_type: '', from: '', to: '' })
  const [page, setPage] = useState(1)
  const list = useQuery({
    queryKey: ['finance', 'journals', filters, page],
    queryFn: () => apiGet<Paginated<JournalEntry>>('/api/finance/journals', { ...filters, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<JournalEntry>[] = [
    { key: 'doc', header: 'Journal', render: (j) => <span className="font-semibold tabular">{j.doc_number}</span>, sortValue: (j) => j.doc_number },
    { key: 'date', header: 'Entry date', render: (j) => formatDate(j.entry_date), sortValue: (j) => j.entry_date },
    { key: 'source', header: 'Source', render: (j) => <span>{titleCase(j.source_doc_type)} <span className="text-[var(--text-muted)] tabular">{j.source_doc_id?.slice(0, 8)}</span></span> },
    { key: 'narration', header: 'Narration', render: (j) => j.narration ?? '—' },
    { key: 'lines', header: 'Lines', align: 'right', render: (j) => <span className="tabular">{j.lines.length}</span> },
    { key: 'debit', header: 'Debit', align: 'right', render: (j) => <MoneyCell value={j.lines.reduce((s, l) => s + Number(l.debit_amount), 0).toFixed(4)} /> },
    { key: 'posted', header: 'Posted', render: (j) => formatDateTime(j.posted_at) },
  ]

  return (
    <Page>
      <PageHeader parent="Finance" title="Journals" subtitle="Every posted journal, append-only; expand a row for its lines." />
      <FilterBar>
        <Field label="Source type"><Input placeholder="sale, payment, goods_receipt…" value={filters.source_doc_type} onChange={(e) => setFilters({ ...filters, source_doc_type: e.target.value })} /></Field>
        <Field label="From"><Input type="date" value={filters.from} onChange={(e) => setFilters({ ...filters, from: e.target.value })} /></Field>
        <Field label="To"><Input type="date" value={filters.to} onChange={(e) => setFilters({ ...filters, to: e.target.value })} /></Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data?.data}
          rowKey={(j) => j.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          emptyTitle="No journals"
          renderExpanded={(j) => (
            <table className="ui-table">
              <thead><tr><th>#</th><th>Account</th><th>Narration</th><th className="text-right">Debit</th><th className="text-right">Credit</th></tr></thead>
              <tbody>
                {j.lines.map((l) => (
                  <tr key={l.id}>
                    <td className="tabular">{l.line_number}</td>
                    <td><span className="tabular font-semibold">{l.account?.code}</span> {l.account?.name}{l.account?.system_role && <span className="ml-1 text-[10px] text-[var(--text-muted)]">{l.account.system_role}</span>}</td>
                    <td>{l.narration ?? '—'}</td>
                    <td className="text-right"><MoneyCell value={Number(l.debit_amount) ? l.debit_amount : null} /></td>
                    <td className="text-right"><MoneyCell value={Number(l.credit_amount) ? l.credit_amount : null} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>
    </Page>
  )
}
