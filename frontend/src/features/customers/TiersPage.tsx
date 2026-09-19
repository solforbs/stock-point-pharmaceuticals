import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Receipt, Tag } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ProductSearch, useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, DrawerFooter, Field, FormSection, Input, Select } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDate, titleCase } from '../../lib/format'
import { useCustomerTiers, usePriceLists, useProduct } from '../../lib/hooks'
import { formatPct } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import { PRICE_FACTOR_TYPES, type CustomerTier, type Paginated, type PriceFactorType, type PriceList, type PriceListItem, type Product } from '../../lib/types'

const FACTOR_LABEL: Record<PriceFactorType, string> = {
  FIXED: 'Fixed price',
  COST_PLUS_MARKUP: 'Cost + markup',
  TARGET_MARGIN: 'Target margin',
  LIST_RELATIVE: 'Relative to list price',
}

function factorSummary(item: PriceListItem) {
  if (item.factor_type === 'FIXED') return <MoneyCell value={item.unit_price} />
  const pct = item.factor_value === null ? null : String(Number(item.factor_value) * 100)
  return <span className="tabular">{FACTOR_LABEL[item.factor_type]} {formatPct(pct)}</span>
}

/** Part 6.2 — customer tiers and the effective-dated price lists that price them. */
export default function TiersPage() {
  const canView = usePermission('sale.view')
  const canManage = usePermission('price.manage')
  const [params, setParams] = useSearchParams()
  const [creatingTier, setCreatingTier] = useState(false)
  const [creatingList, setCreatingList] = useState(false)
  const tiers = useCustomerTiers()
  const lists = usePriceLists(canView)
  const selectedId = params.get('list')
  const selected = lists.data?.find((l) => l.id === selectedId) ?? null

  const tierColumns: Column<CustomerTier>[] = [
    { key: 'code', header: 'Code', render: (t) => <span className="font-semibold tabular">{t.code}</span>, sortValue: (t) => t.code },
    { key: 'name', header: 'Name', render: (t) => t.name, sortValue: (t) => t.name },
    { key: 'disc', header: 'Default disc.', align: 'right', render: (t) => <span className="tabular">{formatPct(t.default_discount_pct ?? null)}</span> },
    { key: 'max', header: 'Max disc.', align: 'right', render: (t) => <span className="tabular">{formatPct(t.max_discount_pct ?? null)}</span> },
    { key: 'terms', header: 'Terms', align: 'right', render: (t) => <span className="tabular">{t.credit_terms_days ?? 0} d</span> },
  ]

  const listColumns: Column<PriceList>[] = [
    { key: 'code', header: 'Code', render: (l) => <span className="font-semibold tabular">{l.code}</span>, sortValue: (l) => l.code },
    { key: 'name', header: 'Name', render: (l) => l.name, sortValue: (l) => l.name },
    { key: 'mode', header: 'Mode', render: (l) => (l.sale_mode ? <StatusBadge status={l.sale_mode} /> : <span className="text-[var(--text-muted)]">Any</span>) },
    { key: 'tier', header: 'Tier', render: (l) => l.tier?.code ?? '—' },
    { key: 'branch', header: 'Branch', render: (l) => l.branch?.code ?? 'All' },
    { key: 'priority', header: 'Priority', align: 'right', render: (l) => <span className="tabular">{l.priority}</span>, sortValue: (l) => l.priority },
    { key: 'effective', header: 'Effective', render: (l) => <span className="tabular">{formatDate(l.effective_from)} → {l.effective_to ? formatDate(l.effective_to) : 'open'}</span> },
    { key: 'count', header: 'Prices', align: 'right', render: (l) => <span className="tabular">{l.product_prices_count ?? 0}</span>, sortValue: (l) => l.product_prices_count ?? 0 },
    { key: 'status', header: 'Status', render: (l) => <StatusBadge status={l.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
  ]

  return (
    <Page>
      <PageHeader parent="Customers" title="Tiers & Price Lists" subtitle="A tier names the default price list and discount policy; the pricing engine picks the highest-priority active list that matches mode, tier and branch (Part 6.2)." />
      {!canView ? (
        <div className="ui-card"><NoAccess permission="sale.view" /></div>
      ) : (
        <div className="grid gap-4 xl:grid-cols-[minmax(320px,2fr)_3fr]">
          <Card title="Customer tiers" actions={canManage ? <Button size="sm" variant="primary" onClick={() => setCreatingTier(true)}>New tier</Button> : null}>
            <DataTable columns={tierColumns} rows={tiers.data} rowKey={(t) => t.id} isLoading={tiers.isLoading} error={tiers.error} onRetry={() => tiers.refetch()} emptyTitle="No tiers" />
          </Card>
          <Card title="Price lists" actions={canManage ? <Button size="sm" variant="primary" onClick={() => setCreatingList(true)}>New price list</Button> : null}>
            <DataTable columns={listColumns} rows={lists.data} rowKey={(l) => l.id} isLoading={lists.isLoading} error={lists.error} onRetry={() => lists.refetch()} onRowClick={(l) => setParams({ list: l.id })} selectedKey={selectedId} emptyTitle="No price lists" />
          </Card>
        </div>
      )}
      <Drawer open={!!selected} onClose={() => setParams({})} title={selected?.name ?? ''} subtitle={selected ? `${selected.code} · ${selected.currency} · ${selected.prices_include_tax ? 'prices include tax' : 'prices exclude tax'}` : undefined} width={900}>
        {selected && <PriceListDrawer key={selected.id} list={selected} canManage={canManage} />}
      </Drawer>
      <Drawer open={creatingTier} onClose={() => setCreatingTier(false)} title="New tier" width={560}>
        {creatingTier && <TierForm lists={lists.data ?? []} onDone={() => setCreatingTier(false)} onCancel={() => setCreatingTier(false)} />}
      </Drawer>
      <Drawer open={creatingList} onClose={() => setCreatingList(false)} title="New price list" width={640}>
        {creatingList && <PriceListForm tiers={tiers.data ?? []} onDone={(l) => { setCreatingList(false); setParams({ list: l.id }) }} onCancel={() => setCreatingList(false)} />}
      </Drawer>
    </Page>
  )
}

// ---- Price list detail ---------------------------------------------------

function PriceListDrawer({ list, canManage }: { list: PriceList; canManage: boolean }) {
  const queryClient = useQueryClient()
  const [q, setQ] = useState('')
  const [history, setHistory] = useState(false)
  const [page, setPage] = useState(1)
  const [adding, setAdding] = useState(false)
  const [editingHeader, setEditingHeader] = useState(false)
  const dq = useDebounced(q, 250)

  const items = useQuery({
    queryKey: ['price-lists', list.id, 'items', dq, history, page],
    queryFn: () => apiGet<Paginated<PriceListItem>>(`/api/price-lists/${list.id}/items`, { q: dq, include_history: history ? 1 : '', page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const today = new Date().toISOString().slice(0, 10)
  const isClosed = (i: PriceListItem) => !!i.effective_to && i.effective_to.slice(0, 10) < today

  const columns: Column<PriceListItem>[] = [
    { key: 'product', header: 'Product', render: (i) => <><div className="font-semibold">{i.product?.name ?? i.product_id.slice(0, 8)}</div><div className="text-[10.5px] text-[var(--text-muted)] tabular">{i.product?.code ?? ''}</div></>, sortValue: (i) => i.product?.name ?? '' },
    { key: 'uom', header: 'Unit', render: (i) => i.uom?.code ?? '—' },
    { key: 'type', header: 'Basis', render: (i) => FACTOR_LABEL[i.factor_type] },
    { key: 'price', header: 'Price / factor', align: 'right', render: (i) => factorSummary(i) },
    { key: 'list', header: 'Default price', align: 'right', render: (i) => <MoneyCell value={i.product?.default_price} muted /> },
    { key: 'from', header: 'From', render: (i) => <span className="tabular">{formatDate(i.effective_from)}</span>, sortValue: (i) => i.effective_from ?? '' },
    { key: 'to', header: 'To', render: (i) => <span className="tabular">{i.effective_to ? formatDate(i.effective_to) : 'open'}</span> },
  ]

  return (
    <div className="space-y-4">
      {editingHeader ? (
        <PriceListHeaderForm list={list} onDone={() => setEditingHeader(false)} onCancel={() => setEditingHeader(false)} />
      ) : (
        <div className="flex items-start justify-between gap-3">
          <DescriptionList
            className="grid-cols-[auto_1fr_auto_1fr]"
            items={[
              { label: 'Mode', value: list.sale_mode ? titleCase(list.sale_mode) : 'Any' },
              { label: 'Tier', value: list.tier ? `${list.tier.code} · ${list.tier.name}` : 'Any' },
              { label: 'Branch', value: list.branch ? `${list.branch.code} · ${list.branch.name}` : 'All branches' },
              { label: 'Priority', value: <span className="tabular">{list.priority}</span> },
              { label: 'Effective', value: <span className="tabular">{formatDate(list.effective_from)} → {list.effective_to ? formatDate(list.effective_to) : 'open'}</span> },
              { label: 'Status', value: <StatusBadge status={list.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
            ]}
          />
          {canManage && <Button size="sm" onClick={() => setEditingHeader(true)}>Edit list</Button>}
        </div>
      )}

      {canManage && adding && (
        <Card title="Add price">
          <div className="p-4"><PriceItemForm list={list} onDone={() => { setAdding(false); queryClient.invalidateQueries({ queryKey: ['price-lists'] }) }} onCancel={() => setAdding(false)} /></div>
        </Card>
      )}

      <FilterBar>
        <Field label="Search" className="w-64"><Input placeholder="Product name or code" value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} /></Field>
        <label className="flex items-center gap-2 text-[12px] h-8"><input type="checkbox" checked={history} onChange={(e) => { setHistory(e.target.checked); setPage(1) }} /> Show history</label>
        {canManage && !adding && <div className="ml-auto"><Button variant="primary" size="sm" onClick={() => setAdding(true)}>Add price</Button></div>}
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={items.data?.data} rowKey={(i) => i.id} isLoading={items.isLoading} error={items.error} onRetry={() => items.refetch()} emptyTitle={history ? 'No prices ever set' : 'No current prices'} emptyHint="A product without a row here falls back to the next matching list, then to its default price." rowClassName={(i) => (isClosed(i) ? 'opacity-60' : '')} />
        <Pagination page={items.data} onPage={setPage} />
      </div>
    </div>
  )
}

function PriceListHeaderForm({ list, onDone, onCancel }: { list: PriceList; onDone: () => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ name: list.name, priority: String(list.priority), effective_to: list.effective_to ? list.effective_to.slice(0, 10) : '', is_active: list.is_active })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPatch<PriceList>(`/api/price-lists/${list.id}`, { name: form.name, priority: Number(form.priority || 0), effective_to: form.effective_to || null, is_active: form.is_active }),
    onSuccess: (l) => {
      toast.success(`${l.code} updated`)
      queryClient.invalidateQueries({ queryKey: ['price-lists'] })
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  return (
    <div className="ui-card p-4 space-y-3">
      <div className="grid grid-cols-3 gap-3">
        <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
        <Field label="Priority" hint="Higher wins when several lists match." error={err?.errors.priority?.[0]}><Input inputMode="numeric" className="tabular" value={form.priority} onChange={(e) => set({ priority: e.target.value.replace(/\D/g, '') })} /></Field>
        <Field label="Effective to" hint="Blank keeps the list open-ended." error={err?.errors.effective_to?.[0]}><Input type="date" value={form.effective_to} onChange={(e) => set({ effective_to: e.target.value })} /></Field>
      </div>
      <label className="flex items-center gap-2 text-[12px]"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> Active</label>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button size="sm" onClick={onCancel}>Cancel</Button>
        <Button size="sm" variant="primary" disabled={!form.name || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save'}</Button>
      </div>
    </div>
  )
}

/** A new effective-dated row; the server closes the row it supersedes the day before (Part 6.2). */
function PriceItemForm({ list, onDone, onCancel }: { list: PriceList; onDone: () => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [product, setProduct] = useState<Product | null>(null)
  const [form, setForm] = useState({ uom_id: '', factor_type: 'FIXED' as PriceFactorType, unit_price: '', factor_pct: '', effective_from: '', effective_to: '' })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const detail = useProduct(product?.id)
  const uoms = detail.data?.uoms ?? product?.uoms ?? []
  const isFixed = form.factor_type === 'FIXED'

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<PriceListItem>(`/api/price-lists/${list.id}/items`, {
        product_id: product?.id,
        uom_id: form.uom_id || null,
        factor_type: form.factor_type,
        ...(isFixed ? { unit_price: form.unit_price } : { factor_value: String(Number(form.factor_pct) / 100) }),
        effective_from: form.effective_from || null,
        effective_to: form.effective_to || null,
      }),
    onSuccess: (row) => {
      toast.success(`Price set for ${row.product?.name ?? 'product'}`)
      queryClient.invalidateQueries({ queryKey: ['price-lists', list.id] })
      queryClient.invalidateQueries({ queryKey: ['products'] })
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const valueOk = isFixed ? form.unit_price.trim() !== '' && !Number.isNaN(Number(form.unit_price)) : form.factor_pct.trim() !== '' && !Number.isNaN(Number(form.factor_pct))

  return (
    <div className="space-y-3">
      <Field label="Product" required error={err?.errors.product_id?.[0]}>
        {product ? (
          <div className="ui-input flex items-center gap-2"><span className="flex-1 truncate">{product.name} <span className="text-[var(--text-muted)] tabular">{product.code}</span></span><button type="button" onClick={() => { setProduct(null); set({ uom_id: '' }) }} className="text-[var(--text-muted)]">×</button></div>
        ) : (
          <ProductSearch onSelect={(p) => { setProduct(p); set({ uom_id: '' }) }} placeholder="Search product…" />
        )}
      </Field>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <Field label="Unit" hint="Blank = base unit." error={err?.errors.uom_id?.[0]}>
          <Select value={form.uom_id} disabled={!product} onChange={(e) => set({ uom_id: e.target.value })}>
            <option value="">Base unit</option>
            {uoms.map((u) => (<option key={u.id} value={u.uom_id}>{u.uom?.code ?? u.uom_id.slice(0, 8)}{u.is_base ? ' (base)' : ` × ${u.factor_to_base}`}</option>))}
          </Select>
        </Field>
        <Field label="Basis" required>
          <Select value={form.factor_type} onChange={(e) => set({ factor_type: e.target.value as PriceFactorType })}>
            {PRICE_FACTOR_TYPES.map((t) => (<option key={t} value={t}>{FACTOR_LABEL[t]}</option>))}
          </Select>
        </Field>
        {isFixed ? (
          <Field label={`Unit price (${list.currency})`} required error={err?.errors.unit_price?.[0]}><Input inputMode="decimal" className="tabular" value={form.unit_price} onChange={(e) => set({ unit_price: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
        ) : (
          <Field label="Factor (%)" required hint="35 = 35% markup / margin / of list." error={err?.errors.factor_value?.[0]}><Input inputMode="decimal" className="tabular" value={form.factor_pct} onChange={(e) => set({ factor_pct: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
        )}
        <Field label="Effective from" hint="Blank = today." error={err?.errors.effective_from?.[0]}><Input type="date" value={form.effective_from} onChange={(e) => set({ effective_from: e.target.value })} /></Field>
        <Field label="Effective to" error={err?.errors.effective_to?.[0]}><Input type="date" value={form.effective_to} onChange={(e) => set({ effective_to: e.target.value })} /></Field>
      </div>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button size="sm" onClick={onCancel}>Cancel</Button>
        <Button size="sm" variant="primary" disabled={!product || !valueOk || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Add price'}</Button>
      </div>
    </div>
  )
}

// ---- Create forms ----------------------------------------------------------

function TierForm({ lists, onDone, onCancel }: { lists: PriceList[]; onDone: () => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ code: '', name: '', default_price_list_id: '', default_discount_pct: '', max_discount_pct: '', credit_terms_days: '' })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<CustomerTier>('/api/customer-tiers', {
        code: form.code,
        name: form.name,
        default_price_list_id: form.default_price_list_id || null,
        default_discount_pct: form.default_discount_pct || null,
        max_discount_pct: form.max_discount_pct || null,
        credit_terms_days: form.credit_terms_days === '' ? null : Number(form.credit_terms_days),
      }),
    onSuccess: (t) => {
      toast.success(`Tier ${t.code} created`)
      queryClient.invalidateQueries({ queryKey: ['customer-tiers'] })
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  return (
    <div className="space-y-4">
      <FormSection
        title="Pricing Tier Configuration"
        description="Define customer tier identification, default pricing schedule, and credit terms"
        icon={Tag}
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} onChange={(e) => set({ code: e.target.value.toUpperCase() })} /></Field>
          <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
          <Field label="Default price list" className="col-span-1 sm:col-span-2" error={err?.errors.default_price_list_id?.[0]}>
            <Select value={form.default_price_list_id} onChange={(e) => set({ default_price_list_id: e.target.value })}><option value="">None</option>{lists.map((l) => (<option key={l.id} value={l.id}>{l.code} · {l.name}</option>))}</Select>
          </Field>
          <Field label="Default discount (%)" error={err?.errors.default_discount_pct?.[0]}><Input inputMode="decimal" className="tabular" value={form.default_discount_pct} onChange={(e) => set({ default_discount_pct: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
          <Field label="Max discount (%)" error={err?.errors.max_discount_pct?.[0]}><Input inputMode="decimal" className="tabular" value={form.max_discount_pct} onChange={(e) => set({ max_discount_pct: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
          <Field label="Credit terms (days)" className="col-span-1 sm:col-span-2" error={err?.errors.credit_terms_days?.[0]}><Input inputMode="numeric" className="tabular" value={form.credit_terms_days} onChange={(e) => set({ credit_terms_days: e.target.value.replace(/\D/g, '') })} /></Field>
        </div>
      </FormSection>

      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}

      <DrawerFooter
        onCancel={onCancel}
        onSubmit={() => save.mutate()}
        submitLabel="Create tier"
        disabled={!form.code || !form.name || save.isPending}
        isPending={save.isPending}
      />
    </div>
  )
}

function PriceListForm({ tiers, onDone, onCancel }: { tiers: CustomerTier[]; onDone: (l: PriceList) => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()
  const [form, setForm] = useState({ code: '', name: '', sale_mode: '', tier_id: '', branch_id: '', currency: 'KES', prices_include_tax: false, effective_from: '', effective_to: '', priority: '0' })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<PriceList>('/api/price-lists', {
        code: form.code,
        name: form.name,
        sale_mode: form.sale_mode || null,
        tier_id: form.tier_id || null,
        branch_id: form.branch_id || null,
        currency: form.currency,
        prices_include_tax: form.prices_include_tax,
        effective_from: form.effective_from || null,
        effective_to: form.effective_to || null,
        priority: Number(form.priority || 0),
      }),
    onSuccess: (l) => {
      toast.success(`Price list ${l.code} created`)
      queryClient.invalidateQueries({ queryKey: ['price-lists'] })
      onDone(l)
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  return (
    <div className="space-y-4">
      <FormSection
        title="Price List Scope"
        description="Define application scope across commercial channel, tier, and branch"
        icon={Receipt}
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} onChange={(e) => set({ code: e.target.value.toUpperCase() })} /></Field>
          <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
          <Field label="Sale mode" hint="Blank applies to every mode."><Select value={form.sale_mode} onChange={(e) => set({ sale_mode: e.target.value })}><option value="">Any</option>{['RETAIL', 'WHOLESALE', 'DISPENSING'].map((m) => (<option key={m} value={m}>{titleCase(m)}</option>))}</Select></Field>
          <Field label="Tier" hint="Blank applies to every tier."><Select value={form.tier_id} onChange={(e) => set({ tier_id: e.target.value })}><option value="">Any</option>{tiers.map((t) => (<option key={t.id} value={t.id}>{t.code} · {t.name}</option>))}</Select></Field>
          <Field label="Branch" hint="Blank applies to every branch."><Select value={form.branch_id} onChange={(e) => set({ branch_id: e.target.value })}><option value="">All branches</option>{(user?.branches ?? []).map((b) => (<option key={b.id} value={b.id}>{b.code} · {b.name}</option>))}</Select></Field>
          <Field label="Currency"><Input value={form.currency} maxLength={3} onChange={(e) => set({ currency: e.target.value.toUpperCase() })} /></Field>
        </div>
      </FormSection>

      <FormSection
        title="Validity & Priority Rules"
        description="Scheduling parameters and tax calculation method"
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Priority" hint="Higher wins when several lists match." error={err?.errors.priority?.[0]}><Input inputMode="numeric" className="tabular" value={form.priority} onChange={(e) => set({ priority: e.target.value.replace(/\D/g, '') })} /></Field>
          <div className="pt-6">
            <label className="flex items-center gap-2 text-[12px] cursor-pointer"><input type="checkbox" checked={form.prices_include_tax} onChange={(e) => set({ prices_include_tax: e.target.checked })} /> Prices include tax</label>
          </div>
          <Field label="Effective from" hint="Blank = today." error={err?.errors.effective_from?.[0]}><Input type="date" value={form.effective_from} onChange={(e) => set({ effective_from: e.target.value })} /></Field>
          <Field label="Effective to" error={err?.errors.effective_to?.[0]}><Input type="date" value={form.effective_to} onChange={(e) => set({ effective_to: e.target.value })} /></Field>
        </div>
      </FormSection>

      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}

      <DrawerFooter
        onCancel={onCancel}
        onSubmit={() => save.mutate()}
        submitLabel="Create price list"
        disabled={!form.code || !form.name || save.isPending}
        isPending={save.isPending}
      />
    </div>
  )
}
