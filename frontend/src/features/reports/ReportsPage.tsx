import { useMutation, useQuery } from '@tanstack/react-query'
import {
  AlertTriangle,
  BarChart3,
  Boxes,
  Calendar,
  ChevronRight,
  CreditCard,
  Download,
  FileText,
  Percent,
  Play,
  Search,
  ShieldCheck,
  TrendingUp,
  Truck,
  X,
} from 'lucide-react'
import { useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CustomerPicker } from '../../components/CustomerPicker'
import { ProductSearch } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { api, apiGet, cleanParams } from '../../lib/api'
import { addDaysIso, formatDate, formatDateTime, titleCase, todayIso } from '../../lib/format'
import { useProductCategories, useStores } from '../../lib/hooks'
import { toastApiError } from '../../lib/toast'
import type { Customer, Product, ReportDef, ReportResult } from '../../lib/types'
import { formatCell, isNumericType } from './reportCells'

type Filters = Record<string, string>

function getGroupIcon(groupName: string) {
  switch (groupName.toLowerCase()) {
    case 'sales':
      return TrendingUp
    case 'margin':
      return Percent
    case 'inventory':
      return Boxes
    case 'procurement':
      return Truck
    case 'finance':
      return CreditCard
    case 'quality':
      return ShieldCheck
    case 'management':
      return BarChart3
    case 'exceptions':
      return AlertTriangle
    default:
      return FileText
  }
}

/** Part 20 — Clean, light-themed, professional report catalogue with clear typography and quick report switching. */
export default function ReportsPage() {
  const [params, setParams] = useSearchParams()
  const stores = useStores()
  const categories = useProductCategories()
  const catalogue = useQuery({
    queryKey: ['reports', 'catalogue'],
    queryFn: () => apiGet<{ data: ReportDef[] }>('/api/reports'),
    staleTime: 5 * 60_000,
  })

  const defs = catalogue.data?.data ?? []
  const groups = useMemo(() => [...new Set(defs.map((d) => d.group))], [defs])
  const [chosenGroup, setGroup] = useState<string>('')
  const [search, setSearch] = useState<string>('')

  // Always select the specified report, or default cleanly to the first available report
  const rawKey = params.get('report')
  const activeKey = rawKey || (defs[0]?.key ?? '')
  const def = defs.find((d) => d.key === activeKey) ?? defs[0] ?? null

  const [filters, setFilters] = useState<Filters>({ from: addDaysIso(-30), to: todayIso() })
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [product, setProduct] = useState<Product | null>(null)
  const [result, setResult] = useState<ReportResult | null>(null)

  const effective = cleanParams({ ...filters, customer_id: customer?.id, product_id: product?.id }) ?? {}

  const run = useMutation({
    meta: { silent: true },
    mutationFn: () => apiGet<ReportResult>(`/api/reports/${activeKey}`, effective),
    onSuccess: setResult,
  })

  const exportCsv = useMutation({
    mutationFn: async () => {
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

  // Filter definitions based on category and search query
  const filteredDefs = useMemo(() => {
    const q = search.trim().toLowerCase()
    return defs.filter((d) => {
      const matchesGroup = !chosenGroup || d.group === chosenGroup
      const matchesQuery =
        !q ||
        d.title.toLowerCase().includes(q) ||
        d.description.toLowerCase().includes(q) ||
        d.key.toLowerCase().includes(q) ||
        d.group.toLowerCase().includes(q)
      return matchesGroup && matchesQuery
    })
  }, [defs, chosenGroup, search])

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

  const ActiveGroupIcon = def ? getGroupIcon(def.group) : FileText

  return (
    <Page>
      <PageHeader
        parent="Reports"
        title="Report Catalogue"
        subtitle="Every report reads live posted transactions and is permission-gated individually. Exports are cryptographically audited."
      />

      {catalogue.isLoading && <LoadingSkeleton rows={8} />}
      {catalogue.isError && <InlineError error={catalogue.error} />}

      {defs.length > 0 && (
        <div className="grid gap-5 lg:grid-cols-[300px_1fr] items-start">
          {/* LEFT SIDEBAR: Clean Report Navigation */}
          <div id="tour-reports-catalogue" className="space-y-3">
            {/* Simple Search Input */}
            <div className="relative">
              <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none" />
              <input
                type="text"
                placeholder="Search reports…"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full h-9 pl-9 pr-8 text-xs font-semibold text-slate-900 bg-white border border-slate-300 rounded-lg shadow-2xs placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-600"
              />
              {search && (
                <button
                  type="button"
                  onClick={() => setSearch('')}
                  aria-label="Clear search"
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 p-0.5 rounded-full hover:bg-slate-100"
                >
                  <X size={13} />
                </button>
              )}
            </div>

            {/* Category Filter Chips */}
            <div className="flex flex-wrap gap-1">
              <button
                type="button"
                onClick={() => setGroup('')}
                className={`h-7 px-2.5 rounded-md text-xs font-bold transition-colors ${
                  chosenGroup === ''
                    ? 'bg-blue-600 text-white'
                    : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                }`}
              >
                All ({defs.length})
              </button>
              {groups.map((g) => {
                const isSelected = chosenGroup === g
                const count = defs.filter((d) => d.group === g).length
                return (
                  <button
                    key={g}
                    type="button"
                    onClick={() => setGroup(isSelected ? '' : g)}
                    className={`h-7 px-2.5 rounded-md text-xs font-semibold transition-colors ${
                      isSelected
                        ? 'bg-blue-600 text-white font-bold'
                        : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                    }`}
                  >
                    {titleCase(g)} ({count})
                  </button>
                )
              })}
            </div>

            {/* Reports List */}
            <div className="ui-card divide-y divide-slate-100 max-h-[calc(100vh-230px)] overflow-y-auto">
              {filteredDefs.length === 0 ? (
                <div className="p-4 text-center text-xs text-slate-600">No reports match "{search}"</div>
              ) : (
                filteredDefs.map((d) => {
                  const isSelected = activeKey === d.key
                  const Icon = getGroupIcon(d.group)
                  return (
                    <button
                      key={d.key}
                      type="button"
                      onClick={() => choose(d)}
                      className={`w-full text-left p-3 transition-colors flex items-start justify-between gap-2 group ${
                        isSelected
                          ? 'bg-blue-50 border-l-4 border-l-blue-600 text-slate-900'
                          : 'hover:bg-slate-50 text-slate-800'
                      }`}
                    >
                      <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-1.5">
                          <Icon size={13} className={isSelected ? 'text-blue-600 shrink-0' : 'text-slate-500 shrink-0'} />
                          <div className={`text-xs leading-snug truncate ${isSelected ? 'font-bold text-blue-950' : 'font-bold text-slate-900'}`}>
                            {d.title}
                          </div>
                        </div>
                        <div className="text-xs text-slate-700 font-medium line-clamp-2 mt-1 leading-relaxed">
                          {d.description}
                        </div>
                      </div>
                      <ChevronRight size={14} className={`shrink-0 mt-0.5 ${isSelected ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-600'}`} />
                    </button>
                  )
                })
              )}
            </div>
          </div>

          {/* RIGHT WORKSPACE: Current Report Header, Filters & Data Table */}
          {def && (
            <div className="min-w-0 space-y-4">
              {/* Clean Light Report Header */}
              <div className="ui-card p-4 sm:p-5 bg-white border border-slate-200">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                  <div className="flex items-start gap-3">
                    <div className="w-10 h-10 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center shrink-0 mt-0.5">
                      <ActiveGroupIcon size={20} />
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <h2 className="text-lg font-bold text-slate-900 leading-tight">{def.title}</h2>
                        <span className="px-2 py-0.5 rounded text-[11px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700 border border-slate-200">
                          {def.group}
                        </span>
                      </div>
                      <p className="text-xs text-slate-700 font-medium mt-1 max-w-3xl leading-relaxed">
                        {def.description}
                      </p>
                    </div>
                  </div>

                  <div className="flex items-center gap-2 shrink-0 self-end sm:self-center">
                    <Button
                      variant="primary"
                      onClick={() => run.mutate()}
                      disabled={run.isPending}
                      className="font-bold gap-1.5"
                    >
                      <Play size={13} className={run.isPending ? 'animate-spin' : ''} />
                      <span>{run.isPending ? 'Running…' : 'Run Report'}</span>
                    </Button>
                    <Button
                      onClick={() => exportCsv.mutate()}
                      disabled={exportCsv.isPending}
                      className="gap-1.5 font-semibold"
                    >
                      <Download size={13} />
                      <span>Export CSV</span>
                    </Button>
                  </div>
                </div>
              </div>

              {/* Clean Filters Bar */}
              <div id="tour-reports-filters">
                <FilterBar>
                  {def.filters.includes('from') && (
                    <Field label="From">
                      <Input
                        type="date"
                        value={filters.from ?? ''}
                        onChange={(e) => setFilters({ ...filters, from: e.target.value })}
                      />
                    </Field>
                  )}
                  {def.filters.includes('to') && (
                    <Field label="To">
                      <Input
                        type="date"
                        value={filters.to ?? ''}
                        onChange={(e) => setFilters({ ...filters, to: e.target.value })}
                      />
                    </Field>
                  )}
                  {def.filters.includes('as_of') && (
                    <Field label="As of">
                      <Input
                        type="date"
                        value={filters.as_of ?? ''}
                        onChange={(e) => setFilters({ ...filters, as_of: e.target.value })}
                      />
                    </Field>
                  )}
                  {def.filters.includes('customer_id') && (
                    <Field label="Customer" className="w-56">
                      <CustomerPicker value={customer} onChange={setCustomer} />
                    </Field>
                  )}
                  {def.filters.includes('product_id') && (
                    <Field label="Product" className="w-56">
                      <ProductSearch onSelect={setProduct} placeholder={product ? product.name : 'Choose product…'} />
                    </Field>
                  )}
                  {def.filters.includes('store_id') && (
                    <Field label="Store">
                      <Select value={filters.store_id ?? ''} onChange={(e) => setFilters({ ...filters, store_id: e.target.value })}>
                        <option value="">All stores</option>
                        {(stores.data ?? []).map((s) => (
                          <option key={s.id} value={s.id}>
                            {s.code} · {s.name}
                          </option>
                        ))}
                      </Select>
                    </Field>
                  )}
                  {def.filters.includes('category_id') && (
                    <Field label="Category">
                      <Select value={filters.category_id ?? ''} onChange={(e) => setFilters({ ...filters, category_id: e.target.value })}>
                        <option value="">All</option>
                        {(categories.data ?? []).map((c) => (
                          <option key={c.id} value={c.id}>
                            {c.code} · {c.name}
                          </option>
                        ))}
                      </Select>
                    </Field>
                  )}
                  {['threshold_pct', 'threshold', 'dead_days', 'open_hour', 'close_hour']
                    .filter((f) => def.filters.includes(f))
                    .map((f) => (
                      <Field key={f} label={titleCase(f)}>
                        <Input
                          inputMode="decimal"
                          className="tabular w-28"
                          value={filters[f] ?? ''}
                          onChange={(e) => setFilters({ ...filters, [f]: e.target.value.replace(/[^\d.]/g, '') })}
                        />
                      </Field>
                    ))}
                </FilterBar>
              </div>

              {run.isError && <InlineError error={run.error} />}

              {/* Data Table Container */}
              {result && result.key === def.key && (
                <div id="tour-reports-table" className="ui-card overflow-hidden">
                  <div className="px-4 py-2.5 text-xs text-slate-700 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2 font-semibold">
                    <div className="flex items-center gap-2">
                      <Calendar size={13} className="text-slate-500" />
                      <span className="tabular">
                        {formatDate(result.from)} → {formatDate(result.to)}
                      </span>
                      <span className="text-slate-400">·</span>
                      <span className="text-slate-900 font-bold">{result.rows.length} rows</span>
                    </div>
                    <div className="text-slate-600 font-normal">
                      Generated {formatDateTime(result.generated_at)}
                    </div>
                  </div>

                  <DataTable
                    columns={columns}
                    rows={result.rows}
                    rowKey={(row) => JSON.stringify(row).slice(0, 200) + String(result.rows.indexOf(row))}
                    emptyTitle="No rows for this period"
                    emptyHint="Try adjusting your dates or filters."
                    maxHeight="65vh"
                    footer={
                      hasTotals ? (
                        <tr className="font-bold bg-slate-100 text-slate-900 border-t border-slate-300">
                          {result.columns.map((c, i) => (
                            <td key={c.key} className={isNumericType(c.type) ? 'text-right px-3 py-2.5' : 'px-3 py-2.5'}>
                              {c.key in result.totals ? formatCell(result.totals[c.key], c) : i === 0 ? 'Totals' : ''}
                            </td>
                          ))}
                        </tr>
                      ) : null
                    }
                  />

                  {hasTotals && Object.keys(result.totals).some((k) => !result.columns.find((c) => c.key === k)) && (
                    <div className="px-4 py-2.5 border-t border-slate-200 bg-slate-50 flex flex-wrap gap-x-5 gap-y-1 text-xs tabular">
                      {Object.entries(result.totals)
                        .filter(([k]) => !result.columns.find((c) => c.key === k))
                        .map(([k, v]) => (
                          <span key={k}>
                            <span className="text-slate-600 font-medium">{titleCase(k)}: </span>
                            <b className="text-slate-900 font-bold">
                              {typeof v === 'boolean' ? (v ? 'Yes' : 'No') : typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v)}
                            </b>
                          </span>
                        ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </Page>
  )
}
