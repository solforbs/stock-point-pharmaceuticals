import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { EmptyState, ErrorState, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { StatusBadge, type StatusTone } from '../../components/ui/StatusBadge'
import { Field, Input, Select } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { titleCase } from '../../lib/format'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { ChartAccount } from '../../lib/types'

const TYPE_TONE: Record<string, StatusTone> = { ASSET: 'blue', LIABILITY: 'amber', EQUITY: 'purple', REVENUE: 'green', INCOME: 'green', EXPENSE: 'red', COGS: 'red' }

type TreeRow = ChartAccount & { depth: number }

/** Dr positive / Cr negative, the way an accountant reads a trial balance. */
function formatBalance(value: string): string {
  const n = Number(value)
  if (!n) return `${formatMoney('0')}`
  return `${formatMoney(Math.abs(n))} ${n > 0 ? 'Dr' : 'Cr'}`
}

/** Flattens the parent_id tree into indented rows, children in code order, keeping an ancestor whenever a descendant matches the filter. */
function buildTree(accounts: ChartAccount[], matches: (a: ChartAccount) => boolean): TreeRow[] {
  const byParent = new Map<string | null, ChartAccount[]>()
  const byId = new Map(accounts.map((a) => [a.id, a]))
  for (const a of accounts) {
    const parent = a.parent_id && byId.has(a.parent_id) ? a.parent_id : null
    const list = byParent.get(parent) ?? []
    list.push(a)
    byParent.set(parent, list)
  }
  for (const list of byParent.values()) list.sort((x, y) => x.code.localeCompare(y.code, undefined, { numeric: true }))

  const out: TreeRow[] = []
  function walk(parent: string | null, depth: number): boolean {
    let kept = false
    for (const a of byParent.get(parent) ?? []) {
      const start = out.length
      out.push({ ...a, depth })
      const childKept = walk(a.id, depth + 1)
      if (matches(a) || childKept) kept = true
      else out.splice(start, 1)
    }
    return kept
  }
  walk(null, 0)
  return out
}

/** Part 12.1 — the ledger's account tree with the posted balance of every account. */
export default function ChartOfAccountsPage() {
  const canView = usePermission('report.financial.view')
  const [type, setType] = useState('')
  const [q, setQ] = useState('')
  const accounts = useQuery({ queryKey: ['finance', 'chart-of-accounts'], queryFn: () => apiGet<{ data: ChartAccount[] }>('/api/finance/chart-of-accounts'), enabled: canView, staleTime: 60_000 })

  const all = useMemo(() => accounts.data?.data ?? [], [accounts.data])
  const types = useMemo(() => [...new Set(all.map((a) => a.account_type))].sort(), [all])
  const rows = useMemo(() => {
    const term = q.trim().toLowerCase()
    return buildTree(all, (a) => (!type || a.account_type === type) && (!term || a.code.toLowerCase().includes(term) || a.name.toLowerCase().includes(term) || (a.system_role ?? '').toLowerCase().includes(term)))
  }, [all, type, q])

  return (
    <Page>
      <PageHeader parent="Finance" title="Chart of Accounts" subtitle="Balances are the sum of posted journal lines: debit positive, credit negative. Only postable leaf accounts take journal lines." />
      {!canView ? (
        <div className="ui-card"><NoAccess permission="report.financial.view" /></div>
      ) : (
        <>
          <FilterBar>
            <Field label="Type">
              <Select value={type} onChange={(e) => setType(e.target.value)}>
                <option value="">All</option>
                {types.map((t) => (<option key={t} value={t}>{titleCase(t)}</option>))}
              </Select>
            </Field>
            <Field label="Search" className="w-72"><Input placeholder="Code, name or system role" value={q} onChange={(e) => setQ(e.target.value)} /></Field>
          </FilterBar>
          <div className="ui-card">
            {accounts.isLoading && <LoadingSkeleton rows={8} />}
            {accounts.isError && <ErrorState error={accounts.error} onRetry={() => accounts.refetch()} />}
            {accounts.data && rows.length === 0 && <EmptyState title="No accounts match" />}
            {accounts.data && rows.length > 0 && (
              <div className="overflow-auto max-h-[75vh]">
                <table className="ui-table">
                  <thead>
                    <tr><th>Code</th><th>Account</th><th>Type</th><th>System role</th><th>Postable</th><th className="text-right">Balance</th></tr>
                  </thead>
                  <tbody>
                    {rows.map((a) => (
                      <tr key={a.id} className={!a.is_active ? 'opacity-60' : ''}>
                        <td className="tabular font-semibold whitespace-nowrap">{a.code}</td>
                        <td>
                          <span style={{ paddingLeft: a.depth * 18 }} className={a.is_postable ? '' : 'font-bold'}>{a.name}</span>
                          {!a.is_active && <StatusBadge status="INACTIVE" className="ml-2" />}
                        </td>
                        <td><StatusBadge status={a.account_type} tone={TYPE_TONE[a.account_type] ?? 'slate'} /></td>
                        <td className="text-[11px] text-[var(--text-secondary)] tabular">{a.system_role ?? '—'}</td>
                        <td>{a.is_postable ? 'Yes' : <span className="text-[var(--text-muted)]">Header</span>}</td>
                        <td className="text-right tabular whitespace-nowrap">{formatBalance(a.balance)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </>
      )}
    </Page>
  )
}
