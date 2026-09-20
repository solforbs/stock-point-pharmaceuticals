import { useQuery, type UseQueryResult } from '@tanstack/react-query'
import { Table2, BarChart3 } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { BarChart, DonutChart, LineChart, StackedBarChart } from '../../components/charts'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { EmptyState, ErrorState, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { Button, Field, Input } from '../../components/ui/primitives'
import { apiGet, getApiError } from '../../lib/api'
import { addDaysIso, formatDate, todayIso } from '../../lib/format'
import { formatKes, formatPct } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { ReportResult } from '../../lib/types'

type Range = { from: string; to: string }
type ArAgeing = { data: { customer_id: string; name: string }[]; totals: Record<'current' | 'd1_30' | 'd31_60' | 'd61_90' | 'd90_plus' | 'total', string>; as_of: string }

const PRESETS = [
  { label: '7 days', days: 7 },
  { label: '30 days', days: 30 },
  { label: '90 days', days: 90 },
]

const num = (v: unknown) => {
  const n = Number(v ?? 0)
  return Number.isFinite(n) ? n : 0
}
const kes = (v: number) => formatKes(v)

function useReport(key: string, params: Record<string, string>, enabled = true) {
  return useQuery({
    queryKey: ['analytics', key, params],
    queryFn: () => apiGet<ReportResult>(`/api/reports/${key}`, params),
    enabled,
    retry: (count, err) => getApiError(err).status !== 403 && count < 2,
    staleTime: 60_000,
  })
}

/** Every day in the range, so a quiet day shows as zero instead of vanishing from the axis. */
function daysBetween(range: Range): string[] {
  const out: string[] = []
  const d = new Date(`${range.from}T00:00:00`)
  const end = new Date(`${range.to}T00:00:00`)
  while (d <= end && out.length < 400) {
    out.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`)
    d.setDate(d.getDate() + 1)
  }
  return out
}

const shortDay = (iso: string) => new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short' }).format(new Date(`${iso}T00:00:00`))

/** Part 20 — the analytics dashboard: the catalogue's reports drawn as charts over one date range. */
export default function AnalyticsPage() {
  const canView = usePermission('report.view')
  const canCost = usePermission('product.cost.view')
  const canAr = usePermission('finance.ar.view')
  const [draft, setDraft] = useState<Range>({ from: addDaysIso(-29), to: todayIso() })
  const [range, setRange] = useState<Range>(draft)

  const daily = useReport('sales.daily_summary', range, canView)
  const byProduct = useReport('sales.by_product', range, canView)
  const expiry = useReport('inventory.expiry_risk', range, canView)
  const valuation = useReport('inventory.valuation', { to: range.to }, canView && canCost)
  const ar = useQuery({
    queryKey: ['analytics', 'ar-ageing'],
    queryFn: () => apiGet<ArAgeing>('/api/finance/ar-ageing'),
    enabled: canView && canAr,
    retry: (count, err) => getApiError(err).status !== 403 && count < 2,
    staleTime: 60_000,
  })

  if (!canView) return <NoAccess permission="report.view" />

  const days = daysBetween(range)
  const dailyRows = new Map((daily.data?.rows ?? []).map((r) => [String(r.day).slice(0, 10), r]))
  const trend = days.map((day) => {
    const r = dailyRows.get(day)
    return {
      key: day,
      label: shortDay(day),
      values: {
        net: num(r?.net_sales),
        profit: num(r?.gross_profit),
        retail: num(r?.retail_net),
        wholesale: num(r?.wholesale_net),
        dispensing: num(r?.dispensing_net),
      },
    }
  })
  const totals = daily.data?.totals ?? {}
  const hasDispensing = trend.some((t) => t.values.dispensing > 0)

  const products = [...(byProduct.data?.rows ?? [])].sort((a, b) => num(b.net_sales) - num(a.net_sales))
  const topProducts = products.slice(0, 10).map((r) => ({
    key: String(r.product_id ?? r.code),
    label: String(r.name ?? r.code),
    value: num(r.net_sales),
    detail: [{ label: 'Gross profit', value: kes(num(r.gross_profit)) }, { label: 'Margin', value: formatPct(r.margin_pct as string | null) }],
  }))

  const categoryMap = new Map<string, { net: number; cogs: number }>()
  for (const r of products) {
    const name = String(r.category ?? '') || 'Uncategorised'
    const c = categoryMap.get(name) ?? { net: 0, cogs: 0 }
    c.net += num(r.net_sales)
    c.cogs += num(r.cogs)
    categoryMap.set(name, c)
  }
  const categories = [...categoryMap.entries()]
    .map(([name, c]) => ({ key: name, label: name, value: c.net - c.cogs, margin: c.net > 0 ? ((c.net - c.cogs) / c.net) * 100 : 0, net: c.net }))
    .sort((a, b) => b.value - a.value)
    .slice(0, 10)

  const storeMap = new Map<string, { cost: number; retail: number }>()
  for (const r of valuation.data?.rows ?? []) {
    const store = String(r.store ?? '—')
    const s = storeMap.get(store) ?? { cost: 0, retail: 0 }
    s.cost += num(r.value_at_cost)
    s.retail += num(r.value_at_retail)
    storeMap.set(store, s)
  }
  const stores = [...storeMap.entries()].map(([store, s]) => ({ key: store, label: store, value: s.cost, detail: [{ label: 'At retail', value: kes(s.retail) }] })).sort((a, b) => b.value - a.value)

  const expiryTotals = expiry.data?.totals ?? {}
  const expirySlices = [
    { key: 'expired', label: 'Expired', value: num(expiryTotals.value_expired), color: 'var(--status-red)' },
    { key: 'd30', label: 'Within 30 days', value: num(expiryTotals.value_critical_30), color: 'var(--viz-2)' },
    { key: 'd90', label: '31–90 days', value: num(expiryTotals.value_warning_90), color: 'var(--viz-4)' },
    { key: 'd180', label: '91–180 days', value: num(expiryTotals.value_watch_180), color: 'var(--viz-1)' },
  ]

  const arBuckets = ar.data
    ? ([
        ['current', 'Current'],
        ['d1_30', '1–30 d'],
        ['d31_60', '31–60 d'],
        ['d61_90', '61–90 d'],
        ['d90_plus', '90+ d'],
      ] as const).map(([k, label]) => ({ key: k, label, values: { amount: num(ar.data.totals[k]) } }))
    : []

  const hideValuation = !canCost || getApiError(valuation.error).status === 403
  const hideAr = !canAr || getApiError(ar.error).status === 403

  return (
    <Page>
      <PageHeader parent="Reports" title="Analytics" subtitle="Posted transactions only, for the active branch. Hover any chart for exact figures; switch a card to its table for the numbers." />
      <FilterBar>
        <Field label="From"><Input type="date" value={draft.from} max={draft.to} onChange={(e) => setDraft({ ...draft, from: e.target.value })} /></Field>
        <Field label="To"><Input type="date" value={draft.to} min={draft.from} onChange={(e) => setDraft({ ...draft, to: e.target.value })} /></Field>
        <Button variant="primary" disabled={!draft.from || !draft.to || draft.from > draft.to} onClick={() => setRange(draft)}>Apply</Button>
        <div className="flex gap-1 pb-0.5">
          {PRESETS.map((p) => {
            const preset = { from: addDaysIso(-(p.days - 1)), to: todayIso() }
            const on = range.from === preset.from && range.to === preset.to
            return (
              <Button key={p.days} size="sm" variant={on ? 'primary' : 'ghost'} onClick={() => { setDraft(preset); setRange(preset) }}>{p.label}</Button>
            )
          })}
        </div>
      </FilterBar>

      <div className="grid gap-4 xl:grid-cols-2">
        <ChartCard
          title="Net sales and gross profit"
          subtitle={`${formatDate(range.from)} – ${formatDate(range.to)} · net ${kes(num(totals.net_sales))} · profit ${kes(num(totals.gross_profit))}`}
          query={daily}
          isEmpty={!daily.data?.rows.length}
          emptyHint="No posted sales in this period."
          table={<SimpleTable head={['Day', 'Net sales', 'Gross profit']} rows={trend.map((t) => [formatDate(t.key), kes(t.values.net), kes(t.values.profit)])} />}
        >
          <LineChart ariaLabel="Net sales and gross profit per day" data={trend} series={[{ key: 'net', label: 'Net sales' }, { key: 'profit', label: 'Gross profit' }]} />
        </ChartCard>

        <ChartCard
          title="Sales by mode"
          subtitle="Net sales per day, retail and wholesale stacked"
          query={daily}
          isEmpty={!daily.data?.rows.length}
          emptyHint="No posted sales in this period."
          table={<SimpleTable head={['Day', 'Retail', 'Wholesale', ...(hasDispensing ? ['Dispensing'] : [])]} rows={trend.map((t) => [formatDate(t.key), kes(t.values.retail), kes(t.values.wholesale), ...(hasDispensing ? [kes(t.values.dispensing)] : [])])} />}
        >
          <StackedBarChart
            ariaLabel="Net sales per day by sale mode"
            data={trend}
            series={[{ key: 'retail', label: 'Retail' }, { key: 'wholesale', label: 'Wholesale' }, ...(hasDispensing ? [{ key: 'dispensing', label: 'Dispensing' }] : [])]}
          />
        </ChartCard>

        <ChartCard
          title="Top products"
          subtitle="Ten best sellers by net sales"
          query={byProduct}
          isEmpty={!topProducts.length}
          emptyHint="No product sales in this period."
          table={<SimpleTable head={['Product', 'Net sales', 'Gross profit', 'Margin']} rows={products.slice(0, 10).map((r) => [String(r.name), kes(num(r.net_sales)), kes(num(r.gross_profit)), formatPct(r.margin_pct as string | null)])} />}
        >
          <BarChart ariaLabel="Top ten products by net sales" data={topProducts} />
        </ChartCard>

        <ChartCard
          title="Gross profit by category"
          subtitle="Net sales less cost of goods, grouped by product category"
          query={byProduct}
          isEmpty={!categories.length}
          emptyHint="No product sales in this period."
          table={<SimpleTable head={['Category', 'Net sales', 'Gross profit', 'Margin']} rows={categories.map((c) => [c.label, kes(c.net), kes(c.value), formatPct(c.margin.toFixed(2))])} />}
        >
          <BarChart
            ariaLabel="Gross profit by product category"
            data={categories.map((c) => ({ key: c.key, label: c.label, value: c.value, detail: [{ label: 'Net sales', value: kes(c.net) }, { label: 'Margin', value: formatPct(c.margin.toFixed(2)) }] }))}
          />
        </ChartCard>

        {!hideValuation && (
          <ChartCard
            title="Stock value by store"
            subtitle={`On hand at weighted average cost, as at ${formatDate(range.to)}`}
            query={valuation}
            isEmpty={!stores.length}
            emptyHint="No stock on hand."
            table={<SimpleTable head={['Store', 'At cost', 'At retail']} rows={[...storeMap.entries()].map(([s, v]) => [s, kes(v.cost), kes(v.retail)])} />}
          >
            <BarChart ariaLabel="Stock value at cost by store" data={stores} labelWidth={110} />
          </ChartCard>
        )}

        <ChartCard
          title="Expiry risk"
          subtitle="Value at cost of batches expired or expiring within 180 days"
          query={expiry}
          isEmpty={expirySlices.every((s) => s.value === 0)}
          emptyHint="Nothing expired or expiring within 180 days."
          table={<SimpleTable head={['Tier', 'Value at cost']} rows={expirySlices.map((s) => [s.label, kes(s.value)])} />}
        >
          <DonutChart ariaLabel="Stock value by expiry tier" data={expirySlices} centerLabel="At risk" />
        </ChartCard>

        {!hideAr && (
          <ChartCard
            title="Receivables ageing"
            subtitle={ar.data ? `Outstanding customer balances as at ${formatDate(ar.data.as_of)} · total ${kes(num(ar.data.totals.total))}` : 'Outstanding customer balances by age'}
            query={ar}
            isEmpty={!ar.data || num(ar.data.totals.total) === 0}
            emptyHint="No customer owes anything."
            table={<SimpleTable head={['Bucket', 'Amount']} rows={arBuckets.map((b) => [b.label, kes(b.values.amount)])} />}
          >
            <StackedBarChart ariaLabel="Receivables by age bucket" data={arBuckets} series={[{ key: 'amount', label: 'Outstanding' }]} height={200} />
          </ChartCard>
        )}
      </div>
    </Page>
  )
}

/** A dashboard card: loading, a 403 or other error, empty, or the chart, with a table view for exact numbers. */
function ChartCard({
  title,
  subtitle,
  query,
  isEmpty,
  emptyHint,
  table,
  children,
}: {
  title: string
  subtitle?: ReactNode
  query: UseQueryResult<unknown>
  isEmpty: boolean
  emptyHint: string
  table: ReactNode
  children: ReactNode
}) {
  const [asTable, setAsTable] = useState(false)
  const error = query.error ? getApiError(query.error) : null
  let body: ReactNode
  if (query.isLoading) body = <LoadingSkeleton rows={5} />
  else if (error?.status === 403) body = <EmptyState title="Not available to your role" hint={error.message} />
  else if (query.isError) body = <ErrorState error={query.error} onRetry={() => query.refetch()} compact />
  else if (isEmpty) body = <EmptyState title="Nothing to chart" hint={emptyHint} />
  else body = asTable ? table : children

  return (
    <section className="ui-card min-w-0">
      <header className="flex items-start justify-between gap-3 px-4 pt-3 pb-2">
        <div className="min-w-0">
          <h2 className="text-sm font-semibold text-slate-900">{title}</h2>
          {subtitle && <div className="text-xs text-slate-500 mt-0.5">{subtitle}</div>}
        </div>
        <button
          type="button"
          onClick={() => setAsTable(!asTable)}
          className="shrink-0 inline-flex items-center gap-1 text-xs text-slate-600 hover:text-slate-900 px-2 py-1 rounded-md hover:bg-slate-100 transition-colors"
          aria-pressed={asTable}
        >
          {asTable ? <BarChart3 size={12} /> : <Table2 size={12} />} {asTable ? 'Chart' : 'Table'}
        </button>
      </header>
      <div className="px-4 pb-4">{body}</div>
    </section>
  )
}

function SimpleTable({ head, rows }: { head: string[]; rows: string[][] }) {
  return (
    <div className="overflow-auto max-h-[260px]">
      <table className="ui-table">
        <thead>
          <tr>{head.map((h, i) => <th key={h} className={i === 0 ? 'text-left' : 'text-right'}>{h}</th>)}</tr>
        </thead>
        <tbody>
          {rows.map((r, ri) => (
            <tr key={`${r[0]}-${ri}`}>{r.map((c, ci) => <td key={ci} className={ci === 0 ? '' : 'text-right tabular'}>{c}</td>)}</tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
