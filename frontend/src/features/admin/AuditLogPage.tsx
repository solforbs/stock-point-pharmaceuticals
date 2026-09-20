import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Card, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import type { AuditLogPage as AuditLogPageData, AuditLogRow } from '../../lib/types'

function pretty(value: unknown): string {
  if (value === null || value === undefined) return '—'
  return JSON.stringify(value, null, 2)
}

/** Part 19 — append-only audit trail. Rows are never edited or deleted; this screen only reads. */
export default function AuditLogPage() {
  const canView = usePermission('audit.view')
  const { data: user } = useCurrentUser()
  const [action, setAction] = useState('')
  const [entityType, setEntityType] = useState('')
  const [q, setQ] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [allBranches, setAllBranches] = useState(false)
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<AuditLogRow | null>(null)
  const dq = useDebounced(q, 250)
  const dEntity = useDebounced(entityType, 250)

  const list = useQuery({
    queryKey: ['admin', 'audit-log', { action, entity_type: dEntity, q: dq, from, to, allBranches, page }],
    queryFn: () => apiGet<AuditLogPageData>('/api/admin/audit-log', { action, entity_type: dEntity, q: dq, from, to, all_branches: allBranches ? 1 : '', page, per_page: 50 }),
    enabled: canView,
    placeholderData: (prev) => prev,
  })
  const branchCode = (id: string | null) => (id ? (user?.branches.find((b) => b.id === id)?.code ?? id.slice(0, 8)) : '—')

  const columns: Column<AuditLogRow>[] = [
    { key: 'when', header: 'When', render: (r) => <span className="tabular whitespace-nowrap">{formatDateTime(r.occurred_at)}</span>, sortValue: (r) => r.occurred_at },
    { key: 'user', header: 'User', render: (r) => r.username_snapshot ?? (r.user_id !== null ? `#${r.user_id}` : 'system') },
    { key: 'branch', header: 'Branch', render: (r) => <span className="tabular">{branchCode(r.branch_id)}</span> },
    { key: 'action', header: 'Action', render: (r) => <StatusBadge status={r.action} tone="slate" label={r.action} /> },
    { key: 'entity', header: 'Entity', render: (r) => <>{titleCase(r.entity_type)}<div className="text-xs text-slate-500 font-mono">{r.entity_id?.slice(0, 8) ?? ''}</div></> },
    { key: 'reference', header: 'Reference', render: (r) => <span className="tabular">{r.reference ?? '—'}</span> },
    { key: 'changed', header: 'Changed', render: (r) => <span className="text-xs text-slate-500">{(r.changed_fields ?? []).join(', ') || '—'}</span> },
    { key: 'reason', header: 'Reason', render: (r) => <span className="text-slate-600">{r.reason ?? '—'}</span> },
  ]

  const reset = () => setPage(1)

  return (
    <Page>
      <PageHeader parent="Admin" title="Audit Log" subtitle="Who did what, when, with the before and after state. Rows are written once and never changed (Part 19.1)." />
      {!canView ? (
        <div className="ui-card"><NoAccess permission="audit.view" /></div>
      ) : (
        <>
          <FilterBar>
            <Field label="Action">
              <Select value={action} onChange={(e) => { setAction(e.target.value); reset() }}>
                <option value="">All</option>
                {(list.data?.actions ?? (action ? [action] : [])).map((a) => (<option key={a} value={a}>{a}</option>))}
              </Select>
            </Field>
            <Field label="Entity type"><Input className="w-40" placeholder="e.g. sale, user" value={entityType} onChange={(e) => { setEntityType(e.target.value); reset() }} /></Field>
            <Field label="Search" className="w-64"><Input placeholder="Reference, reason or username" value={q} onChange={(e) => { setQ(e.target.value); reset() }} /></Field>
            <Field label="From"><Input type="date" value={from} onChange={(e) => { setFrom(e.target.value); reset() }} /></Field>
            <Field label="To"><Input type="date" value={to} onChange={(e) => { setTo(e.target.value); reset() }} /></Field>
            <label className="flex items-center gap-2 text-xs text-slate-600 h-8 cursor-pointer"><input type="checkbox" checked={allBranches} onChange={(e) => { setAllBranches(e.target.checked); reset() }} /> All branches</label>
          </FilterBar>
          <div className="ui-card">
            <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={setSelected} selectedKey={selected?.id ?? null} emptyTitle="No audit entries match" maxHeight="70vh" />
            <Pagination page={list.data} onPage={setPage} />
          </div>
        </>
      )}
      <Drawer open={!!selected} onClose={() => setSelected(null)} title={selected?.action ?? ''} subtitle={selected ? `${titleCase(selected.entity_type)} · ${formatDateTime(selected.occurred_at)}` : undefined} width={760}>
        {selected && (
          <div className="space-y-4">
            <DescriptionList
              items={[
                { label: 'User', value: selected.username_snapshot ?? (selected.user_id !== null ? `#${selected.user_id}` : 'system') },
                { label: 'Branch', value: branchCode(selected.branch_id) },
                { label: 'Entity', value: <span className="tabular">{selected.entity_type} · {selected.entity_id ?? '—'}</span> },
                { label: 'Reference', value: selected.reference ?? '—' },
                { label: 'Reason', value: selected.reason ?? '—' },
                { label: 'Changed fields', value: (selected.changed_fields ?? []).length ? <div className="flex flex-wrap gap-1">{(selected.changed_fields ?? []).map((f) => <span key={f} className="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-xs font-mono text-slate-600">{f}</span>)}</div> : '—' },
              ]}
            />
            <div className="grid gap-3 md:grid-cols-2">
              <Card title="Before">
                <pre className="p-3 text-xs font-mono text-slate-700 whitespace-pre-wrap break-all max-h-[50vh] overflow-auto">{pretty(selected.before_json)}</pre>
              </Card>
              <Card title="After">
                <pre className="p-3 text-xs font-mono text-slate-700 whitespace-pre-wrap break-all max-h-[50vh] overflow-auto">{pretty(selected.after_json)}</pre>
              </Card>
            </div>
          </div>
        )}
      </Drawer>
    </Page>
  )
}
