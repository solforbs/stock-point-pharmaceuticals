import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { NoAccess } from '../../components/ui/States'
import { Field, Select } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { formatKes } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { ReportResult } from '../../lib/types'
import { formatCell, isNumericType } from '../reports/reportCells'

const REPORT_KEY = 'inventory.valuation'

/** Part 20 — the stock valuation report as its own screen; the catalogue entry needs both report.view and product.cost.view. */
export default function ValuationPage() {
  const canReport = usePermission('report.view')
  const canCost = usePermission('product.cost.view')
  const canView = canReport && canCost
  const stores = useStores()
  const [storeId, setStoreId] = useState('')

  const result = useQuery({
    queryKey: ['reports', REPORT_KEY, { store_id: storeId }],
    queryFn: () => apiGet<ReportResult>(`/api/reports/${REPORT_KEY}`, { store_id: storeId }),
    enabled: canView,
    placeholderData: (prev) => prev,
  })
  const r = result.data

  const columns: Column<Record<string, unknown>>[] = (r?.columns ?? []).map((c) => ({
    key: c.key,
    header: c.label,
    align: isNumericType(c.type) ? 'right' : 'left',
    render: (row) => formatCell(row[c.key], c),
    sortValue: (row) => {
      const v = row[c.key]
      return isNumericType(c.type) ? Number(v ?? 0) : v === null || v === undefined ? null : String(v)
    },
  }))
  const totals = r?.totals ?? {}
  const totalCost = totals.value_at_cost
  const totalRetail = totals.value_at_retail

  return (
    <Page>
      <PageHeader parent="Inventory" title="Valuation" subtitle="On-hand value at weighted average cost and at default retail price, per product and store. Reads posted stock balances only." />
      {!canView ? (
        <div className="ui-card"><NoAccess permission={canReport ? 'product.cost.view' : 'report.view'} /></div>
      ) : (
        <>
          <FilterBar>
            <Field label="Store">
              <Select value={storeId} onChange={(e) => setStoreId(e.target.value)}>
                <option value="">All stores in branch</option>
                {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
              </Select>
            </Field>
          </FilterBar>
          <div className="grid gap-3 sm:grid-cols-2 mb-4 max-w-2xl">
            <div className="ui-card p-4">
              <div className="ui-label !mb-1 text-slate-500 font-semibold">Total at cost (WAC)</div>
              <div className="text-2xl font-bold tracking-tight text-slate-900 tabular leading-tight">{result.isLoading ? '…' : formatKes(totalCost === undefined ? null : String(totalCost))}</div>
            </div>
            <div className="ui-card p-4">
              <div className="ui-label !mb-1 text-slate-500 font-semibold">Total at retail</div>
              <div className="text-2xl font-bold tracking-tight text-slate-900 tabular leading-tight">{result.isLoading ? '…' : formatKes(totalRetail === undefined ? null : String(totalRetail))}</div>
            </div>
          </div>
          <div className="ui-card">
            {r && (
              <div className="px-4 py-2.5 text-xs text-slate-500 border-b border-slate-200 tabular font-medium">
                {r.rows.length} rows · generated {formatDateTime(r.generated_at)}
              </div>
            )}
            <DataTable
              columns={columns}
              rows={r?.rows}
              rowKey={(row) => `${String(row.code)}|${String(row.store)}`}
              isLoading={result.isLoading}
              error={result.error}
              onRetry={() => result.refetch()}
              emptyTitle="No stock on hand"
              maxHeight="65vh"
              footer={
                r && Object.keys(totals).length > 0 ? (
                  <tr className="font-bold bg-slate-50">
                    {r.columns.map((c, i) => (
                      <td key={c.key} className={isNumericType(c.type) ? 'text-right' : ''}>
                        {c.key in totals ? formatCell(totals[c.key], c) : i === 0 ? 'Totals' : ''}
                      </td>
                    ))}
                  </tr>
                ) : null
              }
            />
          </div>
        </>
      )}
    </Page>
  )
}
