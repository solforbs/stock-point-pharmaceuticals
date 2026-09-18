import { useMutation, useQuery } from '@tanstack/react-query'
import { Download, Play } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CustomerPicker } from '../../components/CustomerPicker'
import { ProductSearch } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { EmptyState, InlineError, LoadingSkeleton } from '../../components/ui/States'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { api, apiGet, cleanParams } from '../../lib/api'
import { addDaysIso, formatDate, formatDateTime, titleCase, todayIso } from '../../lib/format'
import { useProductCategories, useStores } from '../../lib/hooks'
import { toastApiError } from '../../lib/toast'
import type { Customer, Product, ReportDef, ReportResult } from '../../lib/types'
import { formatCell, isNumericType } from './reportCells'

type Filters = Record<string, string>

/** Part 20 — one generic viewer for the whole catalogue: group tabs, a filter bar driven by each report's filters, typed columns, totals, CSV export. */
export default function ReportsPage() {
  const [params, setParams] = useSearchParams()
  const stores = useStores()
  const categories = useProductCategories()
  const catalogue = useQuery({ queryKey: ['reports', 'catalogue'], queryFn: () => apiGet<{ data: ReportDef[] }>('/api/reports'), staleTime: 5 * 60_000 })
  const defs = catalogue.data?.data ?? []
  const groups = [...new Set(defs.map((d) => d.group))]
  const [chosenGroup, setGroup] = useState<string>('')
  const activeKey = params.get('report') ?? ''
  const def = defs.find((d) => d.key === activeKey) ?? null
  const [filters, setFilters] = useState<Filters>({ from: addDaysIso(-30), to: todayIso() })
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [product, setProduct] = useState<Product | null>(null)
  const [result, setResult] = useState<ReportResult | null>(null)
  // The active tab is the one chosen, else the selected report's group, else the first group — derived, not synchronised.
  const group = chosenGroup || def?.group || groups[0] || ''

  const effective = cleanParams({ ...filters, customer_id: customer?.id, product_id: product?.id }) ?? {}

  const run = useMutation({
    meta: { silent: true },
    mutationFn: () => apiGet<ReportResult>(`/api/reports/${activeKey}`, effective),
    onSuccess: setResult,
  })

  const exportCsv = useMutation({
    mutationFn: async () => {
      // Fetched with the API client (not a bare link) so X-Branch-Id and the session travel with it.
      const res = await api.get(`/api/reports/${activeKey}`, { params: { ...effective, format: 'csv' }, responseType: 'blob' })
      const url = URL.createObjectURL(res.data as Blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `${activeKey.replace(/\./g, '-')}-${effective.from ?? ''}-${effective.to ?? ''}.csv`
      a.click()
      URL.revokeObjectURL(url)
    },
    onError: (e) => toastApiError(e, 'Export failed'),
  })

  function choose(d: ReportDef) {
    setParams({ report: d.key })
    setResult(null)
  }

  const columns: Column<Record<string, unknown>>[] = (result?.columns ?? []).map((c) => ({
    key: c.key,
    header: c.label,
    align: isNumericType(c.type) ? 'right' : 'left',
    render: (row) => formatCell(row[c.key], c),
    sortValue: (row) => {
      const v = row[c.key]
      return isNumericType(c.type) ? Number(v ?? 0) : v === null || v === undefined ? null : String(v)
    },
  }))
  const hasTotals = !!result && Object.keys(result.totals ?? {}).length > 0

  return (
    <Page>
      <PageHeader parent="Reports" title="Report Catalogue" subtitle="Every report reads posted transactions and is permission-gated individually. Exports are audited." />
      {catalogue.isLoading && <LoadingSkeleton />}
      {catalogue.isError && <InlineError error={catalogue.error} />}
      {defs.length > 0 && (
        <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
          <div className="space-y-2">
            <div className="flex flex-wrap gap-1">
              {groups.map((g) => (
                <button key={g} type="button" onClick={() => setGroup(g)} className={`h-7 px-2.5 rounded-md text-[11.5px] font-semibold ${group === g ? 'bg-[var(--color-navy)] text-white' : 'bg-[var(--surface-2)] text-[var(--text-secondary)]'}`}>{titleCase(g)}</button>
              ))}
            </div>
            <div className="ui-card max-h-[70vh] overflow-y-auto">
              {defs.filter((d) => d.group === group).map((d) => (
                <button key={d.key} type="button" onClick={() => choose(d)} className={`w-full text-left px-3 py-2 border-b border-[var(--border)] last:border-b-0 ${activeKey === d.key ? 'bg-[color-mix(in_srgb,var(--color-navy)_10%,var(--card))]' : 'hover:bg-[var(--surface-2)]'}`}>
                  <div className="text-[12.5px] font-semibold">{d.title}</div>
                  <div className="text-[10.5px] text-[var(--text-muted)] leading-snug">{d.description}</div>
                </button>
              ))}
            </div>
          </div>

          <div className="min-w-0">
            {!def ? (
              <EmptyState title="Choose a report" hint="Pick a report on the left, set its filters, then run it." />
            ) : (
              <div className="space-y-3">
                <div>
                  <h2 className="text-[15px] font-bold">{def.title}</h2>
                  <p className="text-[11.5px] text-[var(--text-muted)]">{def.description}</p>
                </div>
                <FilterBar>
                  {def.filters.includes('from') && <Field label="From"><Input type="date" value={filters.from ?? ''} onChange={(e) => setFilters({ ...filters, from: e.target.value })} /></Field>}
                  {def.filters.includes('to') && <Field label="To"><Input type="date" value={filters.to ?? ''} onChange={(e) => setFilters({ ...filters, to: e.target.value })} /></Field>}
                  {def.filters.includes('customer_id') && <Field label="Customer" className="w-72"><CustomerPicker value={customer} onChange={setCustomer} /></Field>}
                  {def.filters.includes('product_id') && (
                    <Field label="Product" className="w-72">
                      {product ? (
                        <div className="ui-input flex items-center gap-2"><span className="flex-1 truncate">{product.name}</span><button type="button" onClick={() => setProduct(null)} className="text-[var(--text-muted)]">×</button></div>
                      ) : (
                        <ProductSearch onSelect={setProduct} placeholder="Search product…" />
                      )}
                    </Field>
                  )}
                  {def.filters.includes('store_id') && (
                    <Field label="Store"><Select value={filters.store_id ?? ''} onChange={(e) => setFilters({ ...filters, store_id: e.target.value })}><option value="">All</option>{(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code}</option>))}</Select></Field>
                  )}
                  {def.filters.includes('category_id') && (
                    <Field label="Category"><Select value={filters.category_id ?? ''} onChange={(e) => setFilters({ ...filters, category_id: e.target.value })}><option value="">All</option>{(categories.data ?? []).map((c) => (<option key={c.id} value={c.id}>{c.code} · {c.name}</option>))}</Select></Field>
                  )}
                  {['threshold_pct', 'threshold', 'dead_days', 'open_hour', 'close_hour'].filter((f) => def.filters.includes(f)).map((f) => (
                    <Field key={f} label={titleCase(f)}><Input inputMode="decimal" className="tabular w-28" value={filters[f] ?? ''} onChange={(e) => setFilters({ ...filters, [f]: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
                  ))}
                  <Button variant="primary" onClick={() => run.mutate()} disabled={run.isPending}><Play size={12} /> {run.isPending ? 'Running…' : 'Run'}</Button>
                  <Button onClick={() => exportCsv.mutate()} disabled={exportCsv.isPending}><Download size={12} /> Export CSV</Button>
                </FilterBar>
                {run.isError && <InlineError error={run.error} />}
                {result && result.key === def.key && (
                  <div className="ui-card">
                    <div className="px-3 py-2 text-[11px] text-[var(--text-muted)] border-b border-[var(--border)] tabular">
                      {formatDate(result.from)} → {formatDate(result.to)} · {result.rows.length} rows · generated {formatDateTime(result.generated_at)}
                    </div>
                    <DataTable
                      columns={columns}
                      rows={result.rows}
                      rowKey={(row, ) => JSON.stringify(row).slice(0, 200) + String(result.rows.indexOf(row))}
                      emptyTitle="No rows for this period"
                      maxHeight="65vh"
                      footer={
                        hasTotals ? (
                          <tr className="font-bold bg-[var(--surface-2)]">
                            {result.columns.map((c, i) => (
                              <td key={c.key} className={isNumericType(c.type) ? 'text-right' : ''}>
                                {c.key in result.totals ? formatCell(result.totals[c.key], c) : i === 0 ? 'Totals' : ''}
                              </td>
                            ))}
                          </tr>
                        ) : null
                      }
                    />
                    {hasTotals && Object.keys(result.totals).some((k) => !result.columns.find((c) => c.key === k)) && (
                      <div className="px-3 py-2 border-t border-[var(--border)] flex flex-wrap gap-x-5 gap-y-1 text-[11.5px] tabular">
                        {Object.entries(result.totals).filter(([k]) => !result.columns.find((c) => c.key === k)).map(([k, v]) => (
                          <span key={k}><span className="text-[var(--text-muted)]">{titleCase(k)}</span> <b>{typeof v === 'boolean' ? (v ? 'Yes' : 'No') : typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v)}</b></span>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      )}
    </Page>
  )
}
