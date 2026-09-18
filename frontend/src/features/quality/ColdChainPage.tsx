import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { EmptyState, InlineError, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { NamedRef, Paginated } from '../../lib/types'

type TempRange = { min: string; max: string; source: 'STORAGE_CONDITION' | 'COLD_DEFAULT' | 'ROOM_DEFAULT' }

type Reading = {
  id: string
  store_id: string
  recorded_at: string
  temperature_c: string
  humidity_pct: string | null
  source: 'MANUAL' | 'LOGGER'
  note: string | null
  is_excursion: boolean
  excursion_id: string | null
  store?: NamedRef | null
  recorder?: { id: number; name: string } | null
}

type StoreSummary = {
  store: { id: string; code: string; name: string; store_type: string }
  range: TempRange
  last_reading: Reading | null
  last_in_range: boolean | null
  open_excursions: number
}

type Excursion = {
  id: string
  store_id: string
  started_at: string
  ended_at: string | null
  min_temp: string
  max_temp: string
  range_min: string
  range_max: string
  status: 'OPEN' | 'UNDER_REVIEW' | 'CLOSED'
  impact_assessment: string | null
  action_taken: 'NO_IMPACT' | 'STOCK_QUARANTINED' | 'STOCK_DISPOSED' | null
  affected_batch_ids: string[] | null
  duration_minutes: number
  closed_at: string | null
  review_started_at: string | null
  readings_count?: number
  store?: NamedRef | null
  closer?: { id: number; name: string } | null
  reviewer?: { id: number; name: string } | null
  readings?: Reading[]
}

const ACTIONS = [
  { value: 'NO_IMPACT', label: 'No impact — stock remains released' },
  { value: 'STOCK_QUARANTINED', label: 'Quarantine every released batch in the store' },
  { value: 'STOCK_DISPOSED', label: 'Stock disposed (record the disposal under Waste)' },
] as const

type Tab = 'readings' | 'excursions'

function formatDuration(minutes: number): string {
  if (minutes < 60) return `${minutes} min`
  const h = Math.floor(minutes / 60)
  const m = minutes % 60
  return h < 48 ? `${h} h ${m} min` : `${Math.floor(h / 24)} d ${h % 24} h`
}

function temp(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—'
  return `${Number(value).toFixed(1)} °C`
}

/** Part 8.5 — cold chain: per-store windows, readings and excursion review. */
export default function ColdChainPage() {
  const canView = usePermission('stock.view')
  const canRecord = usePermission('coldchain.record')
  const [params, setParams] = useSearchParams()
  const tab: Tab = params.get('tab') === 'excursions' ? 'excursions' : 'readings'
  const selectedExcursion = params.get('excursion')

  const summary = useQuery({
    queryKey: ['cold-chain', 'summary'],
    queryFn: () => apiGet<StoreSummary[]>('/api/cold-chain/summary'),
    enabled: canView,
    refetchInterval: 60_000,
  })
  const [storeId, setStoreId] = useState('')
  const activeStoreId = storeId || summary.data?.[0]?.store.id || ''

  if (!canView) {
    return (
      <Page>
        <PageHeader parent="Quality & Compliance" title="Cold Chain" />
        <div className="ui-card"><NoAccess permission="stock.view" /></div>
      </Page>
    )
  }

  const setTab = (t: Tab) => setParams(t === 'readings' ? {} : { tab: t })

  return (
    <Page>
      <PageHeader
        parent="Quality & Compliance"
        title="Cold Chain"
        subtitle="Every store has a temperature window. A reading outside it opens an excursion that stays open until a pharmacist records the impact (Part 8.5)."
      />

      {summary.isLoading ? (
        <div className="ui-card mb-4"><LoadingSkeleton rows={3} /></div>
      ) : summary.error ? (
        <InlineError error={summary.error} className="mb-4" />
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
          {summary.data?.map((s) => (
            <StoreCard key={s.store.id} summary={s} active={s.store.id === activeStoreId} onClick={() => setStoreId(s.store.id)} />
          ))}
          {summary.data?.length === 0 && <div className="ui-card col-span-full"><EmptyState title="No stores in this branch" /></div>}
        </div>
      )}

      <div className="flex gap-1 mb-3">
        {(['readings', 'excursions'] as Tab[]).map((t) => (
          <button key={t} type="button" onClick={() => setTab(t)} className={`h-8 px-3.5 rounded-md text-[12.5px] font-semibold ${tab === t ? 'bg-[var(--color-navy)] text-white' : 'bg-[var(--surface-2)] text-[var(--text-secondary)] hover:bg-[var(--surface-3)]'}`}>
            {t === 'readings' ? 'Readings' : 'Excursions'}
          </button>
        ))}
      </div>

      {tab === 'readings' ? (
        <div className="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_320px] gap-4">
          <ReadingsPanel stores={summary.data ?? []} storeId={activeStoreId} onStore={setStoreId} />
          {canRecord ? <RecordReadingCard stores={summary.data ?? []} defaultStoreId={activeStoreId} /> : null}
        </div>
      ) : (
        <ExcursionsPanel stores={summary.data ?? []} onOpen={(id) => setParams({ tab: 'excursions', excursion: id })} selectedId={selectedExcursion} />
      )}

      <ExcursionDrawer id={selectedExcursion} onClose={() => setParams(tab === 'readings' ? {} : { tab })} />
    </Page>
  )
}

function StoreCard({ summary, active, onClick }: { summary: StoreSummary; active: boolean; onClick: () => void }) {
  const last = summary.last_reading
  const out = summary.last_in_range === false
  return (
    <button
      type="button"
      onClick={onClick}
      className={`ui-card text-left p-3.5 transition-shadow ${active ? 'ring-2 ring-[var(--color-navy)]' : 'hover:shadow-md'}`}
    >
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          <div className="text-[12.5px] font-bold text-[var(--text)] truncate">{summary.store.name}</div>
          <div className="text-[10.5px] text-[var(--text-muted)]">{summary.store.code} · {titleCase(summary.store.store_type)}</div>
        </div>
        {summary.open_excursions > 0 ? <StatusBadge status="EXCURSION" tone="red" label={`${summary.open_excursions} open`} /> : <StatusBadge status="OK" />}
      </div>
      <div className={`mt-2 text-[24px] font-extrabold tabular leading-none ${out ? 'text-[var(--status-red)]' : last ? 'text-[var(--status-green)]' : 'text-[var(--text-muted)]'}`}>
        {last ? temp(last.temperature_c) : 'No reading'}
      </div>
      <div className="mt-1.5 text-[11px] text-[var(--text-muted)] flex justify-between gap-2">
        <span className="tabular">Window {temp(summary.range.min)} – {temp(summary.range.max)}</span>
        <span>{last ? formatDateTime(last.recorded_at) : '—'}</span>
      </div>
      {out && <div className="mt-1 text-[11px] font-semibold text-[var(--status-red)]">Out of range</div>}
    </button>
  )
}

function RecordReadingCard({ stores, defaultStoreId }: { stores: StoreSummary[]; defaultStoreId: string }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ store_id: '', temperature_c: '', humidity_pct: '', source: 'MANUAL', note: '', recorded_at: '' })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const storeId = form.store_id || defaultStoreId
  const range = stores.find((s) => s.store.id === storeId)?.range
  const t = form.temperature_c === '' ? null : Number(form.temperature_c)
  const willBeOut = t !== null && range ? t < Number(range.min) || t > Number(range.max) : false

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<Reading>('/api/cold-chain/readings', {
        store_id: storeId,
        temperature_c: form.temperature_c,
        humidity_pct: form.humidity_pct || null,
        source: form.source,
        note: form.note || null,
        recorded_at: form.recorded_at ? form.recorded_at.replace('T', ' ') : null,
      }),
    onSuccess: (r) => {
      if (r.is_excursion) toast.warning('Reading out of range', 'An excursion is open for this store and needs a pharmacist review.')
      else toast.success(`Reading ${temp(r.temperature_c)} recorded`)
      setForm({ ...form, temperature_c: '', humidity_pct: '', note: '', recorded_at: '' })
      queryClient.invalidateQueries({ queryKey: ['cold-chain'] })
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <Card title="Record reading" className="self-start">
      <div className="p-4 space-y-3">
        <Field label="Store" required error={err?.errors.store_id?.[0]}>
          <Select value={storeId} onChange={(e) => set({ store_id: e.target.value })}>
            {stores.map((s) => (<option key={s.store.id} value={s.store.id}>{s.store.code} — {s.store.name}</option>))}
          </Select>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Temperature °C" required error={err?.errors.temperature_c?.[0]} hint={range ? `Window ${range.min} – ${range.max}` : undefined}>
            <Input inputMode="decimal" className={`tabular ${willBeOut ? 'border-[var(--status-red)]' : ''}`} value={form.temperature_c} onChange={(e) => set({ temperature_c: e.target.value.replace(/[^0-9.-]/g, '') })} />
          </Field>
          <Field label="Humidity %" error={err?.errors.humidity_pct?.[0]}>
            <Input inputMode="decimal" className="tabular" value={form.humidity_pct} onChange={(e) => set({ humidity_pct: e.target.value.replace(/[^0-9.]/g, '') })} />
          </Field>
        </div>
        {willBeOut && <div className="text-[11.5px] font-semibold text-[var(--status-red)]">This reading is outside the window and will open an excursion.</div>}
        <div className="grid grid-cols-2 gap-3">
          <Field label="Source">
            <Select value={form.source} onChange={(e) => set({ source: e.target.value })}>
              <option value="MANUAL">Manual</option>
              <option value="LOGGER">Data logger</option>
            </Select>
          </Field>
          <Field label="Taken at" hint="Blank = now" error={err?.errors.recorded_at?.[0]}>
            <Input type="datetime-local" value={form.recorded_at} onChange={(e) => set({ recorded_at: e.target.value })} />
          </Field>
        </div>
        <Field label="Note"><Input value={form.note} maxLength={500} onChange={(e) => set({ note: e.target.value })} /></Field>
        {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
        <Button variant="primary" className="w-full" disabled={!storeId || form.temperature_c === '' || save.isPending} onClick={() => save.mutate()}>
          {save.isPending ? 'Saving…' : 'Record reading'}
        </Button>
      </div>
    </Card>
  )
}

function ReadingsPanel({ stores, storeId, onStore }: { stores: StoreSummary[]; storeId: string; onStore: (id: string) => void }) {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)
  const store = stores.find((s) => s.store.id === storeId)

  const chart = useQuery({
    queryKey: ['cold-chain', 'chart', storeId],
    queryFn: () => apiGet<Paginated<Reading>>('/api/cold-chain/readings', { store_id: storeId, per_page: 48 }),
    enabled: !!storeId,
  })
  const list = useQuery({
    queryKey: ['cold-chain', 'readings', storeId, from, to, page],
    queryFn: () => apiGet<Paginated<Reading>>('/api/cold-chain/readings', { store_id: storeId, from, to, page, per_page: 25 }),
    enabled: !!storeId,
    placeholderData: (prev) => prev,
  })

  const columns: Column<Reading>[] = [
    { key: 'at', header: 'Taken at', render: (r) => <span className="tabular">{formatDateTime(r.recorded_at)}</span>, sortValue: (r) => r.recorded_at },
    { key: 'temp', header: 'Temperature', align: 'right', render: (r) => <span className={`tabular font-semibold ${r.is_excursion ? 'text-[var(--status-red)]' : ''}`}>{temp(r.temperature_c)}</span>, sortValue: (r) => Number(r.temperature_c) },
    { key: 'hum', header: 'Humidity', align: 'right', render: (r) => <span className="tabular">{r.humidity_pct !== null ? `${Number(r.humidity_pct).toFixed(0)} %` : '—'}</span> },
    { key: 'state', header: 'State', render: (r) => (r.is_excursion ? <StatusBadge status="EXCURSION" tone="red" label="Out of range" /> : <StatusBadge status="OK" label="In range" />) },
    { key: 'source', header: 'Source', render: (r) => titleCase(r.source) },
    { key: 'by', header: 'Recorded by', render: (r) => r.recorder?.name ?? '—' },
    { key: 'note', header: 'Note', render: (r) => <span className="text-[var(--text-secondary)]">{r.note ?? ''}</span> },
  ]

  return (
    <div className="space-y-4 min-w-0">
      <Card title={store ? `${store.store.name} — last ${chart.data?.data.length ?? 0} readings` : 'Readings'}>
        <div className="p-4">
          {chart.isLoading ? <LoadingSkeleton rows={4} /> : chart.error ? <InlineError error={chart.error} /> : store ? <TemperatureChart readings={chart.data?.data ?? []} range={store.range} /> : null}
        </div>
      </Card>
      <FilterBar>
        <Field label="Store" className="w-56">
          <Select value={storeId} onChange={(e) => { onStore(e.target.value); setPage(1) }}>
            {stores.map((s) => (<option key={s.store.id} value={s.store.id}>{s.store.code} — {s.store.name}</option>))}
          </Select>
        </Field>
        <Field label="From"><Input type="date" value={from} onChange={(e) => { setFrom(e.target.value); setPage(1) }} /></Field>
        <Field label="To"><Input type="date" value={to} onChange={(e) => { setTo(e.target.value); setPage(1) }} /></Field>
        {(from || to) && <Button variant="ghost" onClick={() => { setFrom(''); setTo(''); setPage(1) }}>Clear dates</Button>}
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} emptyTitle="No readings" emptyHint="Record a reading to start the log for this store." rowClassName={(r) => (r.is_excursion ? 'bg-[color-mix(in_srgb,var(--status-red)_6%,transparent)]' : '')} />
        <Pagination page={list.data} onPage={setPage} />
      </div>
    </div>
  )
}

/** A small inline SVG line chart — the window is shaded; out-of-range points are red. */
function TemperatureChart({ readings, range }: { readings: Reading[]; range: TempRange }) {
  if (readings.length === 0) return <EmptyState title="No readings yet" hint="The chart shows the last 48 readings for the selected store." />
  const points = [...readings].reverse()
  const values = points.map((r) => Number(r.temperature_c))
  const lo = Math.min(Number(range.min), ...values) - 1
  const hi = Math.max(Number(range.max), ...values) + 1
  const W = 640
  const H = 180
  const pad = { l: 36, r: 10, t: 10, b: 22 }
  const x = (i: number) => pad.l + (points.length === 1 ? (W - pad.l - pad.r) / 2 : (i * (W - pad.l - pad.r)) / (points.length - 1))
  const y = (v: number) => pad.t + ((hi - v) * (H - pad.t - pad.b)) / (hi - lo)
  const path = points.map((r, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(Number(r.temperature_c)).toFixed(1)}`).join(' ')
  const ticks = [Number(range.min), Number(range.max), lo + 1, hi - 1].filter((v, i, a) => a.indexOf(v) === i)

  return (
    <svg viewBox={`0 0 ${W} ${H}`} className="w-full h-auto" role="img" aria-label={`Temperature over the last ${points.length} readings`}>
      <rect x={pad.l} width={W - pad.l - pad.r} y={y(Number(range.max))} height={Math.max(0, y(Number(range.min)) - y(Number(range.max)))} fill="color-mix(in srgb, var(--status-green) 10%, transparent)" />
      {ticks.map((v) => (
        <g key={v}>
          <line x1={pad.l} x2={W - pad.r} y1={y(v)} y2={y(v)} stroke="var(--border)" strokeDasharray={v === Number(range.min) || v === Number(range.max) ? '4 3' : undefined} />
          <text x={pad.l - 4} y={y(v) + 3} textAnchor="end" fontSize="9.5" fill="var(--text-muted)">{v.toFixed(0)}°</text>
        </g>
      ))}
      <path d={path} fill="none" stroke="var(--color-navy)" strokeWidth={1.6} strokeLinejoin="round" />
      {points.map((r, i) => (
        <circle key={r.id} cx={x(i)} cy={y(Number(r.temperature_c))} r={r.is_excursion ? 3.2 : 2.2} fill={r.is_excursion ? 'var(--status-red)' : 'var(--color-navy)'}>
          <title>{`${formatDateTime(r.recorded_at)} — ${temp(r.temperature_c)}`}</title>
        </circle>
      ))}
      <text x={pad.l} y={H - 6} fontSize="9.5" fill="var(--text-muted)">{formatDateTime(points[0].recorded_at)}</text>
      <text x={W - pad.r} y={H - 6} fontSize="9.5" textAnchor="end" fill="var(--text-muted)">{formatDateTime(points[points.length - 1].recorded_at)}</text>
    </svg>
  )
}

function ExcursionsPanel({ stores, onOpen, selectedId }: { stores: StoreSummary[]; onOpen: (id: string) => void; selectedId: string | null }) {
  const [status, setStatus] = useState('UNRESOLVED')
  const [storeId, setStoreId] = useState('')
  const [page, setPage] = useState(1)
  const list = useQuery({
    queryKey: ['cold-chain', 'excursions', status, storeId, page],
    queryFn: () => apiGet<Paginated<Excursion>>('/api/cold-chain/excursions', { status, store_id: storeId, page }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<Excursion>[] = [
    { key: 'store', header: 'Store', render: (e) => e.store?.name ?? '—', sortValue: (e) => e.store?.name ?? '' },
    { key: 'start', header: 'Started', render: (e) => <span className="tabular">{formatDateTime(e.started_at)}</span>, sortValue: (e) => e.started_at },
    { key: 'end', header: 'Back in range', render: (e) => (e.ended_at ? <span className="tabular">{formatDateTime(e.ended_at)}</span> : <StatusBadge status="EXCURSION" tone="red" label="Still out" />) },
    { key: 'dur', header: 'Duration', align: 'right', render: (e) => <span className="tabular">{formatDuration(e.duration_minutes)}</span>, sortValue: (e) => e.duration_minutes },
    { key: 'minmax', header: 'Min / max', align: 'right', render: (e) => <span className="tabular text-[var(--status-red)]">{temp(e.min_temp)} / {temp(e.max_temp)}</span> },
    { key: 'window', header: 'Window', render: (e) => <span className="tabular text-[var(--text-muted)]">{temp(e.range_min)} – {temp(e.range_max)}</span> },
    { key: 'status', header: 'Status', render: (e) => <StatusBadge status={e.status} tone={e.status === 'OPEN' ? 'red' : e.status === 'UNDER_REVIEW' ? 'amber' : undefined} /> },
    { key: 'action', header: 'Decision', render: (e) => (e.action_taken ? titleCase(e.action_taken) : '—') },
  ]

  return (
    <>
      <FilterBar>
        <Field label="Status" className="w-48">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="UNRESOLVED">Open or under review</option>
            <option value="OPEN">Open</option>
            <option value="UNDER_REVIEW">Under review</option>
            <option value="CLOSED">Closed</option>
            <option value="">All</option>
          </Select>
        </Field>
        <Field label="Store" className="w-56">
          <Select value={storeId} onChange={(e) => { setStoreId(e.target.value); setPage(1) }}>
            <option value="">All stores</option>
            {stores.map((s) => (<option key={s.store.id} value={s.store.id}>{s.store.code} — {s.store.name}</option>))}
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(e) => e.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(e) => onOpen(e.id)} selectedKey={selectedId} emptyTitle={status === 'UNRESOLVED' ? 'No unresolved excursions' : 'No excursions'} emptyHint="Excursions open automatically when a reading falls outside the store's window." />
        <Pagination page={list.data} onPage={setPage} />
      </div>
    </>
  )
}

function ExcursionDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const canReview = usePermission('coldchain.review')
  const [impact, setImpact] = useState('')
  const [action, setAction] = useState<string>('NO_IMPACT')
  const detail = useQuery({
    queryKey: ['cold-chain', 'excursion', id],
    queryFn: () => apiGet<Excursion>(`/api/cold-chain/excursions/${id}`),
    enabled: !!id,
  })
  const e = detail.data

  const done = (message: string) => {
    toast.success(message)
    queryClient.invalidateQueries({ queryKey: ['cold-chain'] })
    queryClient.invalidateQueries({ queryKey: ['batches'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
  }
  const review = useMutation({
    mutationFn: () => apiPost<Excursion>(`/api/cold-chain/excursions/${id}/review`),
    onSuccess: () => done('Excursion taken under review'),
  })
  const close = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<Excursion>(`/api/cold-chain/excursions/${id}/close`, { impact_assessment: impact, action_taken: action }),
    onSuccess: (x) => {
      done(x.action_taken === 'STOCK_QUARANTINED' ? `Excursion closed — ${x.affected_batch_ids?.length ?? 0} batch(es) quarantined` : 'Excursion closed')
      setImpact('')
    },
  })
  const closeErr = close.isError ? getApiError(close.error) : null

  return (
    <Drawer open={!!id} onClose={onClose} title={e ? `Excursion — ${e.store?.name ?? ''}` : 'Excursion'} subtitle={e ? `Started ${formatDateTime(e.started_at)}` : undefined} width={640}>
      {detail.isLoading && <LoadingSkeleton rows={6} />}
      {detail.error && <InlineError error={detail.error} />}
      {e && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <StatusBadge status={e.status} tone={e.status === 'OPEN' ? 'red' : e.status === 'UNDER_REVIEW' ? 'amber' : undefined} />
            {canReview && e.status === 'OPEN' && <Button size="sm" disabled={review.isPending} onClick={() => review.mutate()}>Take under review</Button>}
          </div>
          <DescriptionList
            items={[
              { label: 'Window', value: `${temp(e.range_min)} – ${temp(e.range_max)}` },
              { label: 'Recorded min / max', value: <span className="text-[var(--status-red)] font-semibold tabular">{temp(e.min_temp)} / {temp(e.max_temp)}</span> },
              { label: 'Back in range', value: e.ended_at ? formatDateTime(e.ended_at) : 'Not yet — the store is still out of range' },
              { label: 'Duration', value: formatDuration(e.duration_minutes) },
              { label: 'Under review', value: e.review_started_at ? `${e.reviewer?.name ?? '—'} · ${formatDateTime(e.review_started_at)}` : '—' },
              ...(e.status === 'CLOSED'
                ? [
                    { label: 'Decision', value: titleCase(e.action_taken) },
                    { label: 'Batches quarantined', value: String(e.affected_batch_ids?.length ?? 0) },
                    { label: 'Closed', value: `${e.closer?.name ?? '—'} · ${formatDateTime(e.closed_at)}` },
                    { label: 'Impact assessment', value: <span className="whitespace-pre-wrap">{e.impact_assessment}</span> },
                  ]
                : []),
            ]}
          />
          <div>
            <div className="text-[12px] font-bold mb-1.5">Readings during the excursion ({e.readings?.length ?? 0})</div>
            <div className="border border-[var(--border)] rounded-md divide-y divide-[var(--border)] max-h-56 overflow-y-auto">
              {(e.readings ?? []).map((r) => (
                <div key={r.id} className="flex justify-between px-3 py-1.5 text-[12px]">
                  <span className="tabular">{formatDateTime(r.recorded_at)}</span>
                  <span className="tabular font-semibold text-[var(--status-red)]">{temp(r.temperature_c)}</span>
                  <span className="text-[var(--text-muted)]">{r.recorder?.name ?? titleCase(r.source)}</span>
                </div>
              ))}
            </div>
          </div>
          {e.status !== 'CLOSED' && canReview && (
            <div className="border-t border-[var(--border)] pt-4 space-y-3">
              <div className="text-[13px] font-bold">Close with a decision</div>
              <Field label="Impact assessment" required hint="What was exposed, for how long, and why the decision is safe. Recorded in the audit log." error={closeErr?.errors.impact_assessment?.[0]}>
                <Textarea rows={4} value={impact} onChange={(ev) => setImpact(ev.target.value)} />
              </Field>
              <Field label="Action taken" required error={closeErr?.errors.action_taken?.[0]}>
                <Select value={action} onChange={(ev) => setAction(ev.target.value)}>
                  {ACTIONS.map((a) => (<option key={a.value} value={a.value}>{a.label}</option>))}
                </Select>
              </Field>
              {closeErr && !Object.keys(closeErr.errors).length && <InlineError error={close.error} />}
              <div className="flex justify-end">
                <Button variant={action === 'NO_IMPACT' ? 'primary' : 'danger'} disabled={impact.trim().length < 10 || close.isPending} onClick={() => close.mutate()}>
                  {close.isPending ? 'Closing…' : 'Close excursion'}
                </Button>
              </div>
            </div>
          )}
        </div>
      )}
    </Drawer>
  )
}
