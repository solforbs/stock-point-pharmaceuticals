import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Box, ChevronRight, FlaskConical, PackageCheck, Plus, Settings2, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DeleteRecordButton } from '../../components/DeleteRecordButton'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, Field, Input, PrimaryAction, Select } from '../../components/ui/primitives'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { useDosageForms, useProduct, useProductCategories, useProductStock, useStorageConditions, useTaxCodes, useUoms } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, Product } from '../../lib/types'
import { ExpiryBadge, StockStatesTable } from './StockOnHandPage'
import { StockInForm } from './StockInForm'
import { ProductCatalogueActions } from './ProductCatalogueTools'

export default function ProductsPage() {
  const [params, setParams] = useSearchParams()
  const canCreate = usePermission('product.create')
  const [q, setQ] = useState('')
  const [barcode, setBarcode] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const dq = useDebounced(q, 250)
  const dbarcode = useDebounced(barcode, 250)
  const selectedId = params.get('product')
  const canSeeStock = usePermission('stock.view')
  const [stockFilter, setStockFilter] = useState('')

  const list = useQuery({
    queryKey: ['products', 'list', dq, dbarcode, stockFilter, page],
    queryFn: () => apiGet<Paginated<Product>>('/api/products', { q: dq, barcode: dbarcode, stock: stockFilter, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<Product>[] = [
    { key: 'code', header: 'Code', render: (p) => <span className="font-semibold tabular">{p.code}</span>, sortValue: (p) => p.code },
    { key: 'name', header: 'Name', render: (p) => <>{p.name}{p.strength && <b className="ml-1">{p.strength}</b>}</>, sortValue: (p) => p.name },
    { key: 'generic', header: 'Generic', render: (p) => p.generic_name ?? '—', sortValue: (p) => p.generic_name ?? '' },
    { key: 'base', header: 'Base UOM', render: (p) => p.base_uom?.code ?? '—' },
    { key: 'uoms', header: 'Sales UOMs', render: (p) => (p.uoms ?? []).filter((u) => u.is_sales).map((u) => u.uom?.code).join(', ') },
    { key: 'price', header: 'Default price', align: 'right', render: (p) => <MoneyCell value={p.default_price} />, sortValue: (p) => Number(p.default_price ?? 0) },
    ...(canSeeStock
      ? ([
          { key: 'on_hand', header: 'On hand', align: 'right', render: (p) => <QtyCell value={p.stock?.on_hand ?? '0'} />, sortValue: (p) => Number(p.stock?.on_hand ?? 0) },
          {
            key: 'free',
            header: 'Free to sell',
            align: 'right',
            render: (p) => {
              const free = Number(p.stock?.free_to_sell ?? 0)
              const low = Number(p.reorder_point) > 0 && free < Number(p.reorder_point)
              return <span className={low ? 'text-[var(--status-red)] font-semibold' : ''} title={low ? `Below reorder point ${p.reorder_point}` : undefined}><QtyCell value={p.stock?.free_to_sell ?? '0'} /></span>
            },
            sortValue: (p) => Number(p.stock?.free_to_sell ?? 0),
          },
          { key: 'expiry', header: 'Nearest expiry', render: (p) => (p.stock?.nearest_expiry ? <ExpiryBadge date={p.stock.nearest_expiry} /> : '—'), sortValue: (p) => p.stock?.nearest_expiry ?? '9999' },
        ] as Column<Product>[])
      : []),
    { key: 'active', header: 'Status', render: (p) => <StatusBadge status={p.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
    {
      key: 'delete',
      header: '',
      align: 'right',
      render: (p) => (
        <DeleteRecordButton type="products" id={p.id} label={`${p.code} · ${p.name}`} permission="product.edit" invalidateKeys={[['products']]} />
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        parent="Inventory & Formulations"
        title="Medication & Product Catalogue"
        subtitle="Manage active stock-keeping units, pharmaceutical strengths, packaging factor UOMs, and reorder levels"
        actions={<><ProductCatalogueActions />{canCreate ? <PrimaryAction icon={Plus} onClick={() => setCreating(true)}>New product</PrimaryAction> : null}</>}
      />
      <FilterBar>
        <Field label="Search" className="w-72">
          <Input placeholder="Name, code, SKU or generic" value={q} onChange={(e) => setQ(e.target.value)} />
        </Field>
        <Field label="Barcode (exact)" className="w-52">
          <Input placeholder="Scan…" value={barcode} onChange={(e) => setBarcode(e.target.value)} />
        </Field>
        {canSeeStock && (
          <Field label="Stock" className="w-48">
            <Select value={stockFilter} onChange={(e) => { setStockFilter(e.target.value); setPage(1) }}>
              <option value="">All products</option>
              <option value="in_stock">In stock</option>
              <option value="out_of_stock">Out of stock</option>
              <option value="below_reorder">Below reorder point</option>
            </Select>
          </Field>
        )}
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(p) => p.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(p) => setParams({ product: p.id })} selectedKey={selectedId} emptyTitle="No products match" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <ProductDrawer id={selectedId} onClose={() => setParams({})} />
      <ProductCreateDrawer open={creating} onClose={() => setCreating(false)} onCreated={(p) => setParams({ product: p.id })} />
    </Page>
  )
}

type EditForm = { name: string; generic_name: string; strength: string; category_id: string; dosage_form_id: string; storage_condition_id: string; tax_code_id: string; base_uom_id: string; default_price: string; reorder_point: string; safety_stock: string; lead_time_days: string; pack_integrity: boolean; is_active: boolean }

function ProductEditForm({ product, onDone }: { product: Product; onDone: () => void }) {
  const queryClient = useQueryClient()
  const uoms = useUoms()
  const categories = useProductCategories()
  const dosageForms = useDosageForms()
  const storageConditions = useStorageConditions()
  const taxCodes = useTaxCodes()
  const [form, setForm] = useState<EditForm>({
    name: product.name,
    generic_name: product.generic_name ?? '',
    strength: product.strength ?? '',
    category_id: product.category?.id ?? '',
    dosage_form_id: product.dosage_form?.id ?? '',
    storage_condition_id: product.storage_condition?.id ?? '',
    tax_code_id: product.tax_code?.id ?? '',
    base_uom_id: product.base_uom_id,
    default_price: product.default_price ? String(Number(product.default_price)) : '',
    reorder_point: String(Number(product.reorder_point)),
    safety_stock: String(Number(product.safety_stock)),
    lead_time_days: String(product.lead_time_days),
    pack_integrity: product.pack_integrity,
    is_active: product.is_active,
  })
  const set = (patch: Partial<EditForm>) => setForm({ ...form, ...patch })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPatch<Product>(`/api/products/${product.id}`, {
        name: form.name,
        generic_name: form.generic_name || null,
        strength: form.strength || null,
        category_id: form.category_id || null,
        dosage_form_id: form.dosage_form_id || null,
        storage_condition_id: form.storage_condition_id || null,
        tax_code_id: form.tax_code_id || null,
        ...(form.base_uom_id !== product.base_uom_id ? { base_uom_id: form.base_uom_id } : {}),
        default_price: form.default_price || null,
        reorder_point: form.reorder_point || '0',
        safety_stock: form.safety_stock || '0',
        lead_time_days: Number(form.lead_time_days || 0),
        pack_integrity: form.pack_integrity,
        is_active: form.is_active,
      }),
    onSuccess: (updated) => {
      toast.success(`${updated.code} updated`)
      queryClient.invalidateQueries({ queryKey: ['products'] })
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
        <Field label="Generic name"><Input value={form.generic_name} onChange={(e) => set({ generic_name: e.target.value })} /></Field>
        <Field label="Strength"><Input value={form.strength} onChange={(e) => set({ strength: e.target.value })} /></Field>
        <Field label="Category"><Select value={form.category_id} onChange={(e) => set({ category_id: e.target.value })}><option value="">None</option>{(categories.data ?? []).map((c) => (<option key={c.id} value={c.id}>{c.code} · {c.name}</option>))}</Select></Field>
        <Field label="Dosage form"><Select value={form.dosage_form_id} onChange={(e) => set({ dosage_form_id: e.target.value })}><option value="">None</option>{(dosageForms.data ?? []).map((d) => (<option key={d.id} value={d.id}>{d.code} · {d.name}</option>))}</Select></Field>
        <Field label="Storage condition" hint="What cold-chain monitoring holds this product to (Part 8.5)."><Select value={form.storage_condition_id} onChange={(e) => set({ storage_condition_id: e.target.value })}><option value="">None</option>{(storageConditions.data ?? []).map((c) => (<option key={c.id} value={c.id}>{c.name}</option>))}</Select></Field>
        <Field label="Tax code" hint="VAT treatment used by every quote (Part 13); none means 0% until set."><Select value={form.tax_code_id} onChange={(e) => set({ tax_code_id: e.target.value })}><option value="">None (untaxed)</option>{(taxCodes.data ?? []).map((t) => (<option key={t.id} value={t.id}>{t.code} · {t.name}{t.rate_pct != null ? ` · ${Number(t.rate_pct)}%` : ''}</option>))}</Select></Field>
        <Field label="Base UOM" hint="Locked once stock has moved (BASE_UOM_LOCKED)." error={err?.code === 'BASE_UOM_LOCKED' ? err.message : err?.errors.base_uom_id?.[0]}>
          <Select value={form.base_uom_id} onChange={(e) => set({ base_uom_id: e.target.value })}>{(uoms.data ?? []).map((u) => (<option key={u.id} value={u.id}>{u.code} · {u.name}</option>))}</Select>
        </Field>
        <Field label="Default price (per base unit)" hint="Price-list rows have no edit endpoint yet."><Input inputMode="decimal" className="tabular" value={form.default_price} onChange={(e) => set({ default_price: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
        <Field label="Reorder point"><Input inputMode="decimal" className="tabular" value={form.reorder_point} onChange={(e) => set({ reorder_point: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
        <Field label="Safety stock"><Input inputMode="decimal" className="tabular" value={form.safety_stock} onChange={(e) => set({ safety_stock: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
        <Field label="Lead time (days)"><Input inputMode="numeric" className="tabular" value={form.lead_time_days} onChange={(e) => set({ lead_time_days: e.target.value.replace(/\D/g, '') })} /></Field>
        <div className="flex flex-col gap-1.5 text-[12px] pt-4">
          <label className="flex items-center gap-2"><input type="checkbox" checked={form.pack_integrity} onChange={(e) => set({ pack_integrity: e.target.checked })} /> Pack integrity (never split a sealed pack)</label>
          <label className="flex items-center gap-2"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> Active</label>
        </div>
      </div>
      {err && err.code !== 'BASE_UOM_LOCKED' && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onDone}>Cancel</Button>
        <Button variant="primary" disabled={!form.name || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save changes'}</Button>
      </div>
    </div>
  )
}

function ProductDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const product = useProduct(id)
  const stock = useProductStock(id)
  const showCost = usePermission('product.cost.view')
  const canEdit = usePermission('product.edit')
  const canReceive = usePermission('grn.create')
  const [editing, setEditing] = useState(false)
  const [stockingIn, setStockingIn] = useState(false)
  const p = product.data
  return (
    <Drawer open={!!id} onClose={() => { setEditing(false); setStockingIn(false); onClose() }} title={p ? `${p.name}${p.strength ? ` ${p.strength}` : ''}` : 'Product'} subtitle={p?.code} width={820}
      actions={
        p && !editing && !stockingIn ? (
          <div className="flex gap-2">
            {canReceive && p.is_active && <Button size="sm" variant="primary" onClick={() => setStockingIn(true)}>Stock in</Button>}
            {canEdit && <Button size="sm" onClick={() => setEditing(true)}>Edit</Button>}
          </div>
        ) : null
      }
    >
      {product.isLoading && <LoadingSkeleton />}
      {product.isError && <InlineError error={product.error} />}
      {p && editing && <ProductEditForm product={p} onDone={() => setEditing(false)} />}
      {p && stockingIn && <StockInForm product={p} onDone={() => setStockingIn(false)} onCancel={() => setStockingIn(false)} />}
      {p && !editing && !stockingIn && (
        <div className="space-y-5">
          <DescriptionList
            items={[
              { label: 'Generic', value: p.generic_name ?? '—' },
              { label: 'SKU / GTIN', value: `${p.sku ?? '—'} / ${p.gtin ?? '—'}` },
              { label: 'Category', value: p.category?.name ?? '—' },
              { label: 'Manufacturer', value: p.manufacturer?.name ?? '—' },
              { label: 'Dosage form', value: p.dosage_form?.name ?? '—' },
              { label: 'Tax code', value: p.tax_code?.name ?? p.tax_code?.code ?? '—' },
              { label: 'Flags', value: [p.is_discrete && 'discrete', p.pack_integrity && 'pack integrity', p.requires_batch && 'batch-tracked', !p.is_active && 'inactive'].filter(Boolean).join(' · ') || '—' },
              { label: 'Reorder / safety', value: `${p.reorder_point} / ${p.safety_stock} · lead ${p.lead_time_days} days` },
              { label: 'Default price', value: <MoneyCell value={p.default_price} symbol /> },
            ]}
          />
          <Card title="Units of measure">
            <table className="ui-table">
              <thead>
                <tr>
                  <th>UOM</th>
                  <th className="text-right">Factor to base</th>
                  <th>Sales</th>
                  <th>Purchase</th>
                  <th>Default sales</th>
                  <th>Barcode</th>
                </tr>
              </thead>
              <tbody>
                {(p.uoms ?? []).map((u) => (
                  <tr key={u.id}>
                    <td className="font-semibold">{u.uom?.code} {u.is_base && <span className="text-[10px] text-[var(--text-muted)]">base</span>}</td>
                    <td className="text-right tabular">{u.factor_to_base}</td>
                    <td>{u.is_sales ? 'Yes' : '—'}</td>
                    <td>{u.is_purchase ? 'Yes' : '—'}</td>
                    <td>{u.is_default_sales ? 'Yes' : '—'}</td>
                    <td className="tabular">{u.barcode ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
          <Card title="Prices">
            {(p.prices ?? []).length === 0 ? (
              <div className="p-4 text-[12px] text-[var(--text-muted)]">No price-list rows; the default price applies.</div>
            ) : (
              <table className="ui-table">
                <thead>
                  <tr>
                    <th>Price list</th>
                    <th>UOM</th>
                    <th className="text-right">Unit price</th>
                    <th>Type</th>
                    <th>Effective</th>
                  </tr>
                </thead>
                <tbody>
                  {(p.prices ?? []).map((pr) => (
                    <tr key={pr.id}>
                      <td>{pr.price_list?.name ?? pr.price_list_id.slice(0, 8)} {pr.price_list?.sale_mode && <StatusBadge status={pr.price_list.sale_mode} />}</td>
                      <td>{(p.uoms ?? []).find((u) => u.uom_id === pr.uom_id)?.uom?.code ?? '—'}</td>
                      <td className="text-right"><MoneyCell value={pr.unit_price} /></td>
                      <td>{pr.factor_type}</td>
                      <td>{formatDate(pr.effective_from)} → {pr.effective_to ? formatDate(pr.effective_to) : 'open'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Card>
          <Card title="Stock by store">
            {stock.isLoading ? <LoadingSkeleton rows={3} /> : stock.data ? <StockStatesTable rows={stock.data.stores} showCost={showCost} showProduct={false} /> : null}
          </Card>
        </div>
      )}
    </Drawer>
  )
}

type UomRow = { uom_id: string; factor_to_base: string; is_purchase: boolean; is_sales: boolean; is_default_sales: boolean; barcode: string }

/** Accessible pill toggle-switch — replaces raw <input type="checkbox"> */
function ToggleSwitch({
  checked,
  onChange,
  label,
  hint,
  accentColor = 'bg-blue-600',
}: {
  checked: boolean
  onChange: (v: boolean) => void
  label: string
  hint?: string
  accentColor?: string
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => onChange(!checked)}
      className="w-full flex items-center justify-between gap-3 p-3 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 transition-colors cursor-pointer text-left group"
    >
      <div className="flex-1 min-w-0">
        <div className="text-[13px] font-semibold text-slate-800">{label}</div>
        {hint && <div className="text-[11px] text-slate-500 font-medium mt-0.5">{hint}</div>}
      </div>
      <div
        className={`relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 ${
          checked ? accentColor : 'bg-slate-200'
        }`}
      >
        <span
          className={`pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow-sm transform transition-transform duration-200 ${
            checked ? 'translate-x-4' : 'translate-x-0'
          }`}
        />
      </div>
    </button>
  )
}

/** Section card wrapper for the create form */
function FormCard({ icon, title, children }: { icon: React.ReactNode; title: string; children: React.ReactNode }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-xs">
      <div className="flex items-center gap-2.5 px-4 py-3 border-b border-slate-100 bg-slate-50/60">
        <div className="p-1.5 rounded-lg bg-blue-50 text-blue-600">{icon}</div>
        <span className="text-[13px] font-bold text-slate-800 tracking-tight">{title}</span>
      </div>
      <div className="p-4">{children}</div>
    </div>
  )
}

function ProductCreateDrawer({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (p: Product) => void }) {
  const queryClient = useQueryClient()
  const uoms = useUoms()
  const categories = useProductCategories()
  const dosageForms = useDosageForms()
  const storageConditions = useStorageConditions()
  const taxCodes = useTaxCodes()
  const [form, setForm] = useState({ code: '', name: '', generic_name: '', strength: '', sku: '', gtin: '', category_id: '', dosage_form_id: '', storage_condition_id: '', tax_code_id: '', base_uom_id: '', default_price: '', reorder_point: '0', safety_stock: '0', lead_time_days: '0', is_discrete: true, pack_integrity: false, requires_batch: true })
  const [rows, setRows] = useState<UomRow[]>([])

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<Product>('/api/products', {
        ...form,
        generic_name: form.generic_name || null,
        strength: form.strength || null,
        sku: form.sku || null,
        gtin: form.gtin || null,
        category_id: form.category_id || null,
        dosage_form_id: form.dosage_form_id || null,
        storage_condition_id: form.storage_condition_id || null,
        tax_code_id: form.tax_code_id || null,
        default_price: form.default_price || null,
        lead_time_days: Number(form.lead_time_days || 0),
        uoms: rows.map((r) => ({ uom_id: r.uom_id, factor_to_base: Number(r.factor_to_base), is_purchase: r.is_purchase, is_sales: r.is_sales, is_default_sales: r.is_default_sales, barcode: r.barcode || null })),
      }),
    onSuccess: (p) => {
      toast.success(`Product ${p.code} created`)
      queryClient.invalidateQueries({ queryKey: ['products'] })
      onClose()
      onCreated(p)
    },
  })
  const err = create.isError ? getApiError(create.error) : null
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const canSubmit = !!form.code && !!form.name && !!form.base_uom_id && !create.isPending && !rows.some((r) => !r.uom_id || Number(r.factor_to_base) < 2)

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="New Product"
      subtitle="Register a new medicine or supply item in the catalogue"
      width={820}
      footer={
        <div className="flex items-center justify-between gap-3 w-full">
          <div className="flex-1">
            {err && !Object.keys(err.errors).length && <InlineError error={create.error} />}
          </div>
          <Button onClick={onClose}>Cancel</Button>
          <Button
            variant="primary"
            disabled={!canSubmit}
            onClick={() => create.mutate()}
          >
            {create.isPending ? 'Saving…' : 'Create Product →'}
          </Button>
        </div>
      }
    >
      <div className="space-y-4 pb-2">

        {/* ── Card 1: Medication Identity ───────────────────────── */}
        <FormCard icon={<FlaskConical size={14} />} title="Medication Identity">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Code" required error={err?.errors.code?.[0]}>
              <Input value={form.code} onChange={(e) => set({ code: e.target.value })} placeholder="e.g. AMX500" />
            </Field>
            <Field label="Brand / Trade Name" required error={err?.errors.name?.[0]}>
              <Input value={form.name} onChange={(e) => set({ name: e.target.value })} placeholder="e.g. Amoxil" />
            </Field>
            <Field label="Generic (INN) Name">
              <Input value={form.generic_name} onChange={(e) => set({ generic_name: e.target.value })} placeholder="e.g. Amoxicillin" />
            </Field>
            <Field label="Strength / Concentration">
              <Input value={form.strength} onChange={(e) => set({ strength: e.target.value })} placeholder="e.g. 500mg, 5mg/mL" />
            </Field>
            <Field label="Default Price (per base unit)">
              <div className="relative">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-[12px] font-bold text-slate-400 pointer-events-none">KES</span>
                <Input
                  inputMode="decimal"
                  className="tabular pl-11"
                  value={form.default_price}
                  onChange={(e) => set({ default_price: e.target.value.replace(/[^\d.]/g, '') })}
                  placeholder="0.00"
                />
              </div>
            </Field>
          </div>
        </FormCard>

        {/* ── Card 2: Classification & Regulatory ──────────────── */}
        <FormCard icon={<ChevronRight size={14} />} title="Classification & Regulatory">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Category">
              <Select value={form.category_id} onChange={(e) => set({ category_id: e.target.value })}>
                <option value="">None</option>
                {(categories.data ?? []).map((c) => (
                  <option key={c.id} value={c.id}>{c.code} · {c.name}</option>
                ))}
              </Select>
            </Field>
            <Field label="Dosage form">
              <Select value={form.dosage_form_id} onChange={(e) => set({ dosage_form_id: e.target.value })}>
                <option value="">None</option>
                {(dosageForms.data ?? []).map((d) => (
                  <option key={d.id} value={d.id}>{d.code} · {d.name}</option>
                ))}
              </Select>
            </Field>
            <Field label="Storage condition" hint="What cold-chain monitoring holds this product to.">
              <Select value={form.storage_condition_id} onChange={(e) => set({ storage_condition_id: e.target.value })}>
                <option value="">None</option>
                {(storageConditions.data ?? []).map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </Select>
            </Field>
            <Field label="Tax Code" hint="VAT treatment; 'None' means 0% (untaxed).">
              <Select value={form.tax_code_id} onChange={(e) => set({ tax_code_id: e.target.value })}>
                <option value="">None (untaxed)</option>
                {(taxCodes.data ?? []).map((t) => (
                  <option key={t.id} value={t.id}>
                    {t.code} · {t.name}{t.rate_pct != null ? ` · ${Number(t.rate_pct)}%` : ''}
                  </option>
                ))}
              </Select>
            </Field>
            <Field label="SKU" error={err?.errors.sku?.[0]}>
              <Input value={form.sku} onChange={(e) => set({ sku: e.target.value })} placeholder="Internal stock-keeping unit" />
            </Field>
            <Field label="GTIN / Barcode">
              <Input value={form.gtin} onChange={(e) => set({ gtin: e.target.value })} placeholder="Global Trade Item Number" />
            </Field>
          </div>
        </FormCard>

        {/* ── Card 3: Inventory Controls ───────────────────────── */}
        <FormCard icon={<Settings2 size={14} />} title="Inventory Controls">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <Field label="Base UOM" required hint="Immutable once stock moves." error={err?.errors.base_uom_id?.[0]}>
              <Select value={form.base_uom_id} onChange={(e) => set({ base_uom_id: e.target.value })}>
                <option value="">Choose base unit…</option>
                {(uoms.data ?? []).map((u) => (
                  <option key={u.id} value={u.id}>{u.code} · {u.name}</option>
                ))}
              </Select>
            </Field>
            <Field label="Reorder Point">
              <Input inputMode="decimal" className="tabular" value={form.reorder_point} onChange={(e) => set({ reorder_point: e.target.value })} />
            </Field>
            <Field label="Safety Stock">
              <Input inputMode="decimal" className="tabular" value={form.safety_stock} onChange={(e) => set({ safety_stock: e.target.value })} />
            </Field>
            <Field label="Lead Time (days)">
              <Input inputMode="numeric" className="tabular" value={form.lead_time_days} onChange={(e) => set({ lead_time_days: e.target.value.replace(/\D/g, '') })} />
            </Field>
          </div>

          {/* Toggle switches replacing native checkboxes */}
          <div className="text-[11px] font-extrabold uppercase tracking-widest text-slate-400 mb-2">Product Flags</div>
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <ToggleSwitch
              checked={form.is_discrete}
              onChange={(v) => set({ is_discrete: v })}
              label="Discrete"
              hint="Whole units only"
              accentColor="bg-blue-600"
            />
            <ToggleSwitch
              checked={form.pack_integrity}
              onChange={(v) => set({ pack_integrity: v })}
              label="Pack Integrity"
              hint="Never split a sealed pack"
              accentColor="bg-amber-500"
            />
            <ToggleSwitch
              checked={form.requires_batch}
              onChange={(v) => set({ requires_batch: v })}
              label="Batch-Tracked"
              hint="Requires batch & expiry"
              accentColor="bg-emerald-600"
            />
          </div>
        </FormCard>

        {/* ── Card 4: Additional UOMs ──────────────────────────── */}
        <FormCard icon={<Box size={14} />} title="Additional Units of Measure">
          <p className="text-[12px] text-slate-500 font-medium mb-3">
            The base UOM row is created automatically with factor 1. Add extra units (e.g. strips, boxes).
          </p>

          {rows.length === 0 ? (
            <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-5 text-center mb-3">
              <PackageCheck size={22} className="text-slate-300 mx-auto mb-1.5" />
              <p className="text-[12.5px] text-slate-500 font-medium">No additional UOMs added yet.</p>
              <p className="text-[11px] text-slate-400 mt-0.5">Click below to add a strip, box, or carton.</p>
            </div>
          ) : (
            <div className="space-y-2 mb-3">
              {/* Column headers */}
              <div className="grid grid-cols-[1fr_100px_1fr_auto] gap-2 px-1">
                <span className="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400">Unit</span>
                <span className="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400">× Base</span>
                <span className="text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400">Barcode</span>
                <span className="w-7" />
              </div>

              {rows.map((r, i) => (
                <div key={i} className="rounded-xl border border-slate-200 bg-slate-50/60 p-3 space-y-2">
                  <div className="grid grid-cols-[1fr_100px_1fr_auto] gap-2 items-center">
                    <Select
                      value={r.uom_id}
                      onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, uom_id: e.target.value } : x)))}
                    >
                      <option value="">Choose UOM…</option>
                      {(uoms.data ?? []).map((u) => (
                        <option key={u.id} value={u.id}>{u.code} · {u.name}</option>
                      ))}
                    </Select>
                    <Input
                      inputMode="numeric"
                      className="tabular"
                      placeholder="e.g. 10"
                      value={r.factor_to_base}
                      onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, factor_to_base: e.target.value.replace(/\D/g, '') } : x)))}
                    />
                    <Input
                      placeholder="Barcode (optional)"
                      value={r.barcode}
                      onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, barcode: e.target.value } : x)))}
                    />
                    <Button
                      size="sm"
                      variant="ghost"
                      onClick={() => setRows(rows.filter((_, j) => j !== i))}
                      aria-label="Remove UOM row"
                    >
                      <Trash2 size={13} />
                    </Button>
                  </div>

                  {/* Pill toggles for Sales / Purchase / Default */}
                  <div className="flex items-center gap-2 flex-wrap">
                    {(
                      [
                        { key: 'is_sales', label: 'Sales' },
                        { key: 'is_purchase', label: 'Purchase' },
                        { key: 'is_default_sales', label: 'Default Sales' },
                      ] as { key: keyof UomRow; label: string }[]
                    ).map(({ key, label }) => (
                      <button
                        key={key}
                        type="button"
                        onClick={() => {
                          if (key === 'is_default_sales') {
                            setRows(rows.map((x, j) => ({ ...x, is_default_sales: j === i })))
                          } else {
                            setRows(rows.map((x, j) => (j === i ? { ...x, [key]: !x[key] } : x)))
                          }
                        }}
                        className={`px-2.5 py-1 rounded-full text-[11px] font-bold border transition-colors cursor-pointer ${
                          r[key]
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white text-slate-500 border-slate-200 hover:border-blue-400 hover:text-blue-600'
                        }`}
                      >
                        {label}
                      </button>
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}

          <Button
            size="sm"
            onClick={() => setRows([...rows, { uom_id: '', factor_to_base: '', is_purchase: true, is_sales: true, is_default_sales: rows.length === 0, barcode: '' }])}
          >
            <Plus size={13} /> Add UOM Row
          </Button>
        </FormCard>

      </div>
    </Drawer>
  )
}

export function ProductQtyCell({ value, unit }: { value: string; unit?: string }) {
  return <QtyCell value={value} unit={unit} />
}
