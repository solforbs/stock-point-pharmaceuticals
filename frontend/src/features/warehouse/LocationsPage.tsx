import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { MapPin } from 'lucide-react'
import { useMemo, useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { EmptyState, InlineError, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { titleCase } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import { ExpiryBadge } from '../inventory/StockOnHandPage'

const LOCATION_TYPES = ['BIN', 'SHELF', 'PALLET', 'COLD_SHELF'] as const
type LocationType = (typeof LOCATION_TYPES)[number]

export type WarehouseLocation = {
  id: string
  store_id: string
  code: string
  name: string | null
  location_type: LocationType
  aisle: string | null
  rack: string | null
  bin: string | null
  capacity: number | null
  is_active: boolean
  on_hand_base: string | null
  store?: { id: string; code: string; name: string } | null
}

type LocationStockRow = {
  product_id: string
  batch_id: string
  product_code: string
  product_name: string
  batch_number: string
  expiry_date: string | null
  batch_status: string
  qty_base: string
}

function slotPath(l: Pick<WarehouseLocation, 'aisle' | 'rack' | 'bin'>): string {
  const parts = [l.aisle && `Aisle ${l.aisle}`, l.rack && `Rack ${l.rack}`, l.bin && `Bin ${l.bin}`].filter(Boolean)
  return parts.length ? parts.join(' › ') : '—'
}

/** Part 10.6 — physical locations inside a store, and what the ledger says is put away at each. */
export default function LocationsPage() {
  const canView = usePermission('stock.view')
  const canManage = usePermission('location.manage')
  const stores = useStores()
  const [storeId, setStoreId] = useState('')
  const [showInactive, setShowInactive] = useState(false)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [editing, setEditing] = useState(false)
  const [creating, setCreating] = useState(false)
  const effectiveStoreId = storeId || stores.data?.[0]?.id || ''

  const list = useQuery({
    queryKey: ['locations', effectiveStoreId, showInactive],
    queryFn: () => apiGet<WarehouseLocation[]>('/api/locations', { store_id: effectiveStoreId, include_inactive: showInactive ? 1 : undefined }),
    enabled: canView && !!effectiveStoreId,
  })
  const selected = list.data?.find((l) => l.id === selectedId) ?? null

  const aisleCount = useMemo(() => new Set((list.data ?? []).map((l) => l.aisle ?? '')).size, [list.data])

  if (!canView) return <NoAccess permission="stock.view" />

  const columns: Column<WarehouseLocation>[] = [
    { key: 'code', header: 'Code', render: (l) => <span className="font-semibold tabular">{l.code}</span>, sortValue: (l) => l.code },
    { key: 'name', header: 'Name', render: (l) => l.name ?? <span className="text-[var(--text-muted)]">—</span>, sortValue: (l) => l.name ?? '' },
    { key: 'slot', header: 'Aisle › rack › bin', render: (l) => <span className="text-[var(--text-secondary)]">{slotPath(l)}</span>, sortValue: (l) => `${l.aisle ?? ''}|${l.rack ?? ''}|${l.bin ?? ''}` },
    { key: 'type', header: 'Type', render: (l) => <StatusBadge status={l.location_type} tone={l.location_type === 'COLD_SHELF' ? 'cold' : 'slate'} label={titleCase(l.location_type)} /> },
    { key: 'capacity', header: 'Capacity', align: 'right', render: (l) => <span className="tabular">{l.capacity ?? '—'}</span>, sortValue: (l) => l.capacity ?? 0 },
    { key: 'on_hand', header: 'On hand (base)', align: 'right', render: (l) => <QtyCell value={l.on_hand_base ?? '0'} />, sortValue: (l) => Number(l.on_hand_base ?? 0) },
    { key: 'status', header: 'Status', render: (l) => <StatusBadge status={l.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
  ]

  return (
    <Page>
      <PageHeader
        parent="Warehouse"
        title="Locations"
        subtitle="Aisles, racks and bins inside each store. Stock is attributed to a location when it is put away on receipt."
        actions={canManage ? <Button variant="primary" disabled={!effectiveStoreId} onClick={() => setCreating(true)}>New location</Button> : null}
      />
      <FilterBar>
        <Field label="Store" className="w-64">
          <Select value={effectiveStoreId} onChange={(e) => { setStoreId(e.target.value); setSelectedId(null) }}>
            {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
          </Select>
        </Field>
        <label className="flex items-center gap-2 text-xs font-medium text-slate-700 pb-2"><input type="checkbox" checked={showInactive} onChange={(e) => setShowInactive(e.target.checked)} /> Show deactivated</label>
        {list.data && <div className="text-xs text-slate-500 pb-2">{list.data.length} locations across {aisleCount} aisle{aisleCount === 1 ? '' : 's'}</div>}
      </FilterBar>
      <div className="ui-card">
        {stores.isSuccess && !stores.data.length ? (
          <EmptyState title="No stores in this branch" hint="Add a store under Administration › Branches first." />
        ) : (
          <DataTable
            columns={columns}
            rows={list.data}
            rowKey={(l) => l.id}
            isLoading={list.isLoading || stores.isLoading}
            error={list.error ?? stores.error}
            onRetry={() => list.refetch()}
            onRowClick={(l) => { setEditing(false); setSelectedId(l.id) }}
            selectedKey={selectedId}
            initialSort={{ key: 'slot', dir: 'asc' }}
            emptyTitle="No locations in this store"
            emptyHint={canManage ? 'Create aisles, racks and bins so received stock can be put away to a named slot.' : 'A storekeeper or operations manager sets up locations.'}
            rowClassName={(l) => (l.is_active ? '' : 'opacity-60')}
          />
        )}
      </div>

      <Drawer open={!!selected} onClose={() => { setSelectedId(null); setEditing(false) }} title={selected?.code ?? ''} subtitle={selected ? `${selected.store?.name ?? ''} · ${slotPath(selected)}` : undefined} width={680}>
        {selected && editing && <LocationForm storeId={selected.store_id} location={selected} onDone={() => setEditing(false)} onCancel={() => setEditing(false)} />}
        {selected && !editing && <LocationDetail location={selected} canManage={canManage} onEdit={() => setEditing(true)} />}
      </Drawer>
      <Drawer open={creating} onClose={() => setCreating(false)} title="New location" subtitle={stores.data?.find((s) => s.id === effectiveStoreId)?.name}>
        {creating && <LocationForm storeId={effectiveStoreId} onDone={(l) => { setCreating(false); setSelectedId(l.id) }} onCancel={() => setCreating(false)} />}
      </Drawer>
    </Page>
  )
}

function LocationDetail({ location, canManage, onEdit }: { location: WarehouseLocation; canManage: boolean; onEdit: () => void }) {
  const queryClient = useQueryClient()
  const [confirming, setConfirming] = useState(false)
  const stock = useQuery({
    queryKey: ['locations', 'stock', location.id],
    queryFn: () => apiGet<{ data: LocationStockRow[] }>(`/api/locations/${location.id}/stock`),
  })

  const toggle = useMutation({
    mutationFn: () => apiPatch<WarehouseLocation>(`/api/locations/${location.id}`, { is_active: !location.is_active }),
    onSuccess: (l) => {
      toast.success(l.is_active ? `${l.code} reactivated` : `${l.code} deactivated`)
      queryClient.invalidateQueries({ queryKey: ['locations'] })
      setConfirming(false)
    },
  })

  const stockColumns: Column<LocationStockRow>[] = [
    { key: 'product', header: 'Product', render: (r) => <><span className="font-semibold">{r.product_name}</span><div className="text-xs text-slate-500 font-mono tabular">{r.product_code}</div></>, sortValue: (r) => r.product_name },
    { key: 'batch', header: 'Batch', render: (r) => <span className="tabular">{r.batch_number}</span>, sortValue: (r) => r.batch_number },
    { key: 'expiry', header: 'Expiry', render: (r) => <ExpiryBadge date={r.expiry_date} />, sortValue: (r) => r.expiry_date ?? '' },
    { key: 'status', header: 'Batch status', render: (r) => <StatusBadge status={r.batch_status} /> },
    { key: 'qty', header: 'Qty (base)', align: 'right', render: (r) => <QtyCell value={r.qty_base} />, sortValue: (r) => Number(r.qty_base) },
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <StatusBadge status={location.is_active ? 'ACTIVE' : 'INACTIVE'} />
          <StatusBadge status={location.location_type} tone={location.location_type === 'COLD_SHELF' ? 'cold' : 'slate'} label={titleCase(location.location_type)} />
        </div>
        {canManage && (
          <div className="flex gap-2">
            <Button onClick={onEdit}>Edit</Button>
            <Button variant={location.is_active ? 'danger' : 'success'} onClick={() => setConfirming(true)}>{location.is_active ? 'Deactivate' : 'Reactivate'}</Button>
          </div>
        )}
      </div>
      <DescriptionList
        items={[
          { label: 'Name', value: location.name ?? '—' },
          { label: 'Store', value: location.store ? `${location.store.code} · ${location.store.name}` : '—' },
          { label: 'Slot', value: slotPath(location) },
          { label: 'Capacity', value: location.capacity ?? '—' },
        ]}
      />
      <div>
        <div className="text-sm font-bold text-slate-900 mb-2">Stock at this location</div>
        <div className="ui-card">
          <DataTable
            columns={stockColumns}
            rows={stock.data?.data}
            rowKey={(r) => `${r.product_id}:${r.batch_id}`}
            isLoading={stock.isLoading}
            error={stock.error}
            onRetry={() => stock.refetch()}
            emptyTitle="Nothing recorded here yet"
            emptyHint={<><MapPin size={12} className="inline -mt-0.5" /> Stock is attributed to a location when it is put away on receipt. Until goods are received or moved into this slot, it shows empty — store totals are on the Stock on hand page.</>}
          />
        </div>
      </div>
      <ConfirmDialog
        open={confirming}
        title={location.is_active ? `Deactivate ${location.code}?` : `Reactivate ${location.code}?`}
        message={location.is_active ? 'It disappears from put-away choices. Ledger history that references it is kept.' : 'It becomes available for put-away again.'}
        confirmLabel={location.is_active ? 'Deactivate' : 'Reactivate'}
        danger={location.is_active}
        isPending={toggle.isPending}
        onConfirm={() => toggle.mutate()}
        onCancel={() => setConfirming(false)}
      />
      {location.on_hand_base && Number(location.on_hand_base) !== 0 && location.is_active && (
        <p className="text-xs text-amber-700 font-medium">This location still holds stock ({location.on_hand_base} base units). Move it before deactivating.</p>
      )}
    </div>
  )
}

type LocationFormState = { code: string; name: string; location_type: LocationType; aisle: string; rack: string; bin: string; capacity: string }

function LocationForm({ storeId, location, onDone, onCancel }: { storeId: string; location?: WarehouseLocation; onDone: (l: WarehouseLocation) => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<LocationFormState>({
    code: location?.code ?? '',
    name: location?.name ?? '',
    location_type: location?.location_type ?? 'BIN',
    aisle: location?.aisle ?? '',
    rack: location?.rack ?? '',
    bin: location?.bin ?? '',
    capacity: location?.capacity != null ? String(location.capacity) : '',
  })
  const set = (patch: Partial<LocationFormState>) => setForm({ ...form, ...patch })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = {
        code: form.code,
        name: form.name || null,
        location_type: form.location_type,
        aisle: form.aisle || null,
        rack: form.rack || null,
        bin: form.bin || null,
        capacity: form.capacity === '' ? null : Number(form.capacity),
      }
      return location ? apiPatch<WarehouseLocation>(`/api/locations/${location.id}`, body) : apiPost<WarehouseLocation>('/api/locations', { ...body, store_id: storeId })
    },
    onSuccess: (l) => {
      toast.success(location ? `${l.code} updated` : `Location ${l.code} created`)
      queryClient.invalidateQueries({ queryKey: ['locations'] })
      onDone(l)
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Code" required hint="Unique within the store, e.g. A-01-03." error={err?.errors.code?.[0]}><Input value={form.code} onChange={(e) => set({ code: e.target.value.toUpperCase() })} /></Field>
        <Field label="Name" error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} placeholder="Fast movers" /></Field>
        <Field label="Type" error={err?.errors.location_type?.[0]}>
          <Select value={form.location_type} onChange={(e) => set({ location_type: e.target.value as LocationType })}>
            {LOCATION_TYPES.map((t) => (<option key={t} value={t}>{titleCase(t)}</option>))}
          </Select>
        </Field>
        <Field label="Capacity" hint="Optional, in base units or slots." error={err?.errors.capacity?.[0]}><Input inputMode="numeric" className="tabular" value={form.capacity} onChange={(e) => set({ capacity: e.target.value.replace(/\D/g, '') })} /></Field>
        <Field label="Aisle"><Input value={form.aisle} onChange={(e) => set({ aisle: e.target.value.toUpperCase() })} /></Field>
        <Field label="Rack"><Input value={form.rack} onChange={(e) => set({ rack: e.target.value })} /></Field>
        <Field label="Bin"><Input value={form.bin} onChange={(e) => set({ bin: e.target.value })} /></Field>
      </div>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={!form.code || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : location ? 'Save changes' : 'Create location'}</Button>
      </div>
    </div>
  )
}
