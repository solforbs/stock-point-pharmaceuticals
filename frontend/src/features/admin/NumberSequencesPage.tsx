import { useQuery } from '@tanstack/react-query'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { NoAccess } from '../../components/ui/States'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet } from '../../lib/api'
import { titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import type { NumberSequence } from '../../lib/types'

/** Part 21.1 — document numbering is gapless and issued inside the posting transaction, so there is nothing here to edit. */
export default function NumberSequencesPage() {
  const canView = usePermission('admin.settings')
  const { data: user } = useCurrentUser()
  const sequences = useQuery({ queryKey: ['admin', 'number-sequences'], queryFn: () => apiGet<NumberSequence[]>('/api/admin/number-sequences'), enabled: canView })
  const branchCode = (id: string | null) => (id ? (user?.branches.find((b) => b.id === id)?.code ?? id.slice(0, 8)) : 'All branches')

  const columns: Column<NumberSequence>[] = [
    { key: 'scope', header: 'Scope', render: (s) => <span className="font-semibold">{titleCase(s.scope)}</span>, sortValue: (s) => s.scope },
    { key: 'branch', header: 'Branch', render: (s) => <span className="tabular">{branchCode(s.branch_id)}</span>, sortValue: (s) => branchCode(s.branch_id) },
    { key: 'prefix', header: 'Prefix', render: (s) => <span className="tabular">{s.prefix}</span> },
    { key: 'year', header: 'Fiscal year', render: (s) => <span className="tabular">{s.fiscal_year ?? '—'}</span>, sortValue: (s) => s.fiscal_year ?? 0 },
    { key: 'next', header: 'Next number', align: 'right', render: (s) => <span className="tabular font-semibold">{s.prefix}{String(s.current_value + 1).padStart(s.padding, '0')}</span> },
    { key: 'current', header: 'Issued so far', align: 'right', render: (s) => <span className="tabular">{s.current_value.toLocaleString('en-KE')}</span>, sortValue: (s) => s.current_value },
    { key: 'padding', header: 'Padding', align: 'right', render: (s) => <span className="tabular">{s.padding}</span> },
    { key: 'reset', header: 'Reset', render: (s) => titleCase(s.reset_policy) },
  ]

  return (
    <Page>
      <PageHeader parent="Admin" title="Number Sequences" subtitle="Read-only. Numbering is gapless: a number is issued inside the same transaction as the document it names, so a rolled-back document never burns one (Part 21.1)." />
      {!canView ? (
        <div className="ui-card"><NoAccess permission="admin.settings" /></div>
      ) : (
        <div id="tour-sequences-table" className="ui-card">
          <div className="px-4 py-2 border-b border-slate-200 text-xs text-slate-500">
            Sequences cannot be edited or reset from the application. Annual sequences roll over automatically at fiscal year end.
          </div>
          <DataTable columns={columns} rows={sequences.data} rowKey={(s) => s.id} isLoading={sequences.isLoading} error={sequences.error} onRetry={() => sequences.refetch()} emptyTitle="No numbers issued yet" emptyHint="A sequence appears the first time a document of that type posts." />
        </div>
      )}
    </Page>
  )
}
