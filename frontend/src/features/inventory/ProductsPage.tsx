import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { useProduct, useProductCategories, useProductStock, useTaxCodes, useUoms } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, Product } from '../../lib/types'
import { StockStatesTable } from './StockOnHandPage'

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

  const list = useQuery({
    queryKey: ['products', 'list', dq, dbarcode, page],
    queryFn: () => apiGet<Paginated<Product>>('/api/products', { q: dq, barcode: dbarcode, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<Product>[] = [
    { key: 'code', header: 'Code', render: (p) => <span className="font-semibold tabular">{p.code}</span>, sortValue: (p) => p.code },
    { key: 'name', header: 'Name', render: (p) => <>{p.name}{p.strength && <b className="ml-1">{p.strength}</b>}</>, sortValue: (p) => p.name },
    { key: 'generic', header: 'Generic', render: (p) => p.generic_name ?? '—', sortValue: (p) => p.generic_name ?? '' },
    { key: 'base', header: 'Base UOM', render: (p) => p.base_uom?.code ?? '—' },
    { key: 'uoms', header: 'Sales UOMs', render: (p) => (p.uoms ?? []).filter((u) => u.is_sales).map((u) => u.uom?.code).join(', ') },
    { key: 'price', header: 'Default price', align: 'right', render: (p) => <MoneyCell value={p.default_price} />, sortValue: (p) => Number(p.default_price ?? 0) },
    { key: 'active', header: 'Status', render: (p) => <StatusBadge status={p.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
  ]

  return (
    <Page>
      <PageHeader
        parent="Inventory"
        title="Products"
        actions={canCreate ? <Button variant="primary" onClick={() => setCreating(true)}>New product</Button> : null}
      />
      <FilterBar>
        <Field label="Search" className="w-72">
          <Input placeholder="Name, code, SKU or generic" value={q} onChange={(e) => setQ(e.target.value)} />
        </Field>
        <Field label="Barcode (exact)" className="w-52">
          <Input placeholder="Scan…" value={barcode} onChange={(e) => setBarcode(e.target.value)} />
        </Field>
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

type EditForm = { name: string; generic_name: string; strength: string; category_id: string; tax_code_id: string; base_uom_id: string; default_price: string; reorder_point: string; safety_stock: string; lead_time_days: string; pack_integrity: boolean; is_active: boolean }

function ProductEditForm({ product, onDone }: { product: Product; onDone: () => void }) {
  const queryClient = useQueryClient()
  const uoms = useUoms()
  const categories = useProductCategories()
  const taxCodes = useTaxCodes()
  const [form, setForm] = useState<EditForm>({
    name: product.name,
    generic_name: product.generic_name ?? '',
    strength: product.strength ?? '',
    category_id: product.category?.id ?? '',
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
      <div className="grid grid-cols-2 gap-3">
        <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
        <Field label="Generic name"><Input value={form.generic_name} onChange={(e) => set({ generic_name: e.target.value })} /></Field>
        <Field label="Strength"><Input value={form.strength} onChange={(e) => set({ strength: e.target.value })} /></Field>
        <Field label="Category"><Select value={form.category_id} onChange={(e) => set({ category_id: e.target.value })}><option value="">None</option>{(categories.data ?? []).map((c) => (<option key={c.id} value={c.id}>{c.code} · {c.name}</option>))}</Select></Field>
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
  const [editing, setEditing] = useState(false)
  const p = product.data
  return (
    <Drawer open={!!id} onClose={() => { setEditing(false); onClose() }} title={p ? `${p.name}${p.strength ? ` ${p.strength}` : ''}` : 'Product'} subtitle={p?.code} width={820}
      actions={p && canEdit && !editing ? <Button size="sm" onClick={() => setEditing(true)}>Edit</Button> : null}
    >
      {product.isLoading && <LoadingSkeleton />}
      {product.isError && <InlineError error={product.error} />}
      {p && editing && <ProductEditForm product={p} onDone={() => setEditing(false)} />}
      {p && !editing && (
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

function ProductCreateDrawer({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (p: Product) => void }) {
  const queryClient = useQueryClient()
  const uoms = useUoms()
  const categories = useProductCategories()
  const taxCodes = useTaxCodes()
  const [form, setForm] = useState({ code: '', name: '', generic_name: '', strength: '', sku: '', gtin: '', category_id: '', tax_code_id: '', base_uom_id: '', default_price: '', reorder_point: '0', safety_stock: '0', lead_time_days: '0', is_discrete: true, pack_integrity: false, requires_batch: true })
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

  return (
    <Drawer open={open} onClose={onClose} title="New product" width={760}>
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} onChange={(e) => set({ code: e.target.value })} /></Field>
          <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
          <Field label="Generic name"><Input value={form.generic_name} onChange={(e) => set({ generic_name: e.target.value })} /></Field>
          <Field label="Strength"><Input value={form.strength} onChange={(e) => set({ strength: e.target.value })} placeholder="500mg" /></Field>
          <Field label="SKU" error={err?.errors.sku?.[0]}><Input value={form.sku} onChange={(e) => set({ sku: e.target.value })} /></Field>
          <Field label="GTIN"><Input value={form.gtin} onChange={(e) => set({ gtin: e.target.value })} /></Field>
          <Field label="Category"><Select value={form.category_id} onChange={(e) => set({ category_id: e.target.value })}><option value="">None</option>{(categories.data ?? []).map((c) => (<option key={c.id} value={c.id}>{c.code} · {c.name}</option>))}</Select></Field>
          <Field label="Tax code" hint="VAT treatment used by every quote (Part 13); none means 0% until set."><Select value={form.tax_code_id} onChange={(e) => set({ tax_code_id: e.target.value })}><option value="">None (untaxed)</option>{(taxCodes.data ?? []).map((t) => (<option key={t.id} value={t.id}>{t.code} · {t.name}{t.rate_pct != null ? ` · ${Number(t.rate_pct)}%` : ''}</option>))}</Select></Field>
          <Field label="Base UOM" required hint="Immutable once stock moves (Part 5.3)." error={err?.errors.base_uom_id?.[0]}>
            <Select value={form.base_uom_id} onChange={(e) => set({ base_uom_id: e.target.value })}>
              <option value="">Choose…</option>
              {(uoms.data ?? []).map((u) => (
                <option key={u.id} value={u.id}>{u.code} · {u.name}</option>
              ))}
            </Select>
          </Field>
          <Field label="Default price (per base unit)"><Input inputMode="decimal" className="tabular" value={form.default_price} onChange={(e) => set({ default_price: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
          <Field label="Reorder point"><Input inputMode="decimal" className="tabular" value={form.reorder_point} onChange={(e) => set({ reorder_point: e.target.value })} /></Field>
          <Field label="Safety stock"><Input inputMode="decimal" className="tabular" value={form.safety_stock} onChange={(e) => set({ safety_stock: e.target.value })} /></Field>
          <Field label="Lead time (days)"><Input inputMode="numeric" className="tabular" value={form.lead_time_days} onChange={(e) => set({ lead_time_days: e.target.value.replace(/\D/g, '') })} /></Field>
          <div className="flex flex-col gap-1.5 text-[12px] pt-4">
            <label className="flex items-center gap-2"><input type="checkbox" checked={form.is_discrete} onChange={(e) => set({ is_discrete: e.target.checked })} /> Discrete (whole units only)</label>
            <label className="flex items-center gap-2"><input type="checkbox" checked={form.pack_integrity} onChange={(e) => set({ pack_integrity: e.target.checked })} /> Pack integrity (never split a sealed pack)</label>
            <label className="flex items-center gap-2"><input type="checkbox" checked={form.requires_batch} onChange={(e) => set({ requires_batch: e.target.checked })} /> Batch-tracked</label>
          </div>
        </div>

        <Field label="Additional units of measure" hint="The base UOM row is created automatically with factor 1.">
          <div className="space-y-2">
            {rows.map((r, i) => (
              <div key={i} className="grid grid-cols-[1fr_90px_auto_auto_auto_1fr_auto] gap-2 items-center text-[11.5px]">
                <Select value={r.uom_id} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, uom_id: e.target.value } : x)))}>
                  <option value="">UOM…</option>
                  {(uoms.data ?? []).map((u) => (
                    <option key={u.id} value={u.id}>{u.code}</option>
                  ))}
                </Select>
                <Input inputMode="numeric" className="tabular" placeholder="×base" value={r.factor_to_base} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, factor_to_base: e.target.value.replace(/\D/g, '') } : x)))} />
                <label className="flex items-center gap-1"><input type="checkbox" checked={r.is_sales} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, is_sales: e.target.checked } : x)))} />Sales</label>
                <label className="flex items-center gap-1"><input type="checkbox" checked={r.is_purchase} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, is_purchase: e.target.checked } : x)))} />Purchase</label>
                <label className="flex items-center gap-1"><input type="checkbox" checked={r.is_default_sales} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, is_default_sales: e.target.checked } : { ...x, is_default_sales: false })))} />Default</label>
                <Input placeholder="Barcode" value={r.barcode} onChange={(e) => setRows(rows.map((x, j) => (j === i ? { ...x, barcode: e.target.value } : x)))} />
                <Button size="sm" variant="ghost" onClick={() => setRows(rows.filter((_, j) => j !== i))} aria-label="Remove"><Trash2 size={13} /></Button>
              </div>
            ))}
            <Button size="sm" onClick={() => setRows([...rows, { uom_id: '', factor_to_base: '', is_purchase: true, is_sales: true, is_default_sales: rows.length === 0, barcode: '' }])}>
              <Plus size={12} /> Add UOM
            </Button>
          </div>
        </Field>

        {err && !Object.keys(err.errors).length && <InlineError error={create.error} />}
        <div className="flex justify-end gap-2">
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="primary" disabled={!form.code || !form.name || !form.base_uom_id || create.isPending || rows.some((r) => !r.uom_id || Number(r.factor_to_base) < 2)} onClick={() => create.mutate()}>
            {create.isPending ? 'Saving…' : 'Create product'}
          </Button>
        </div>
      </div>
    </Drawer>
  )
}

export function ProductQtyCell({ value, unit }: { value: string; unit?: string }) {
  return <QtyCell value={value} unit={unit} />
}
