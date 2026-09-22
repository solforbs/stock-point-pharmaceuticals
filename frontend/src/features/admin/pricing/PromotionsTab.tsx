import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { CustomerPicker, useCustomer } from '../../../components/CustomerPicker'
import { useDebounced } from '../../../components/ProductSearch'
import { DataTable, type Column } from '../../../components/ui/DataTable'
import { Drawer } from '../../../components/ui/Drawer'
import { FilterBar } from '../../../components/ui/PageHeader'
import { Pagination } from '../../../components/ui/Pagination'
import { InlineError } from '../../../components/ui/States'
import { StatusBadge } from '../../../components/ui/StatusBadge'
import { Button, Field, Input, Select } from '../../../components/ui/primitives'
import { useCurrentUser } from '../../../hooks/useCurrentUser'
import { apiGet, apiPatch, apiPost, getApiError } from '../../../lib/api'
import { addDaysIso, formatDate, todayIso } from '../../../lib/format'
import { useSuppliers } from '../../../lib/hooks'
import { toast, toastApiError } from '../../../lib/toast'
import type { Customer, Paginated } from '../../../lib/types'
import { ProductField, UomSelect } from './fields'
import { PROMO_LABEL, dateOnly, decimalInput, defaultUomId, type Promotion, type PromotionType } from './shared'

function promotionState(p: Promotion): { status: string; label: string } {
  const today = todayIso()
  if (!p.is_active) return { status: 'INACTIVE', label: 'Inactive' }
  if (dateOnly(p.effective_to) < today) return { status: 'EXPIRED', label: 'Ended' }
  if (dateOnly(p.effective_from) > today) return { status: 'PENDING', label: 'Scheduled' }
  return { status: 'ACTIVE', label: 'Running' }
}

/** Part 6.3 — promotions: rank 2 of the price hierarchy, and buy-X-get-Y bonus schemes. */
export default function PromotionsTab({ canManage }: { canManage: boolean }) {
  const queryClient = useQueryClient()
  const [filters, setFilters] = useState({ q: '', status: '', promo_type: '' })
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState<string | 'new' | null>(null)
  const dq = useDebounced(filters.q, 250)

  const list = useQuery({
    queryKey: ['pricing-rules', 'promotions', { ...filters, q: dq }, page],
    queryFn: () => apiGet<Paginated<Promotion>>('/api/pricing-rules/promotions', { ...filters, q: dq, page }),
    placeholderData: (prev) => prev,
  })
  const toggle = useMutation({
    mutationFn: (p: Promotion) => apiPost<Promotion>(`/api/pricing-rules/promotions/${p.id}/${p.is_active ? 'deactivate' : 'activate'}`),
    onSuccess: (p) => {
      toast.success(`${p.code} ${p.is_active ? 'activated' : 'deactivated'}`)
      queryClient.invalidateQueries({ queryKey: ['pricing-rules', 'promotions'] })
    },
    onError: (e) => toastApiError(e),
  })

  const columns: Column<Promotion>[] = [
    { key: 'code', header: 'Code', render: (p) => <span className="font-semibold tabular">{p.code}</span>, sortValue: (p) => p.code },
    { key: 'name', header: 'Name', render: (p) => p.name, sortValue: (p) => p.name },
    { key: 'type', header: 'Type', render: (p) => PROMO_LABEL[p.promo_type] },
    { key: 'dates', header: 'Runs', render: (p) => <span className="tabular">{formatDate(p.effective_from)} – {formatDate(p.effective_to)}</span>, sortValue: (p) => p.effective_from },
    { key: 'lines', header: 'Lines', align: 'right', render: (p) => <span className="tabular">{p.lines_count ?? 0}</span> },
    { key: 'funded', header: 'Funded by', render: (p) => (p.funded_by === 'SUPPLIER' ? p.supplier?.name ?? 'Supplier' : 'Us') },
    { key: 'state', header: 'Status', render: (p) => { const s = promotionState(p); return <StatusBadge status={s.status} label={s.label} /> } },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (p) =>
        canManage ? (
          <div onClick={(e) => e.stopPropagation()}>
            <Button size="sm" variant="ghost" disabled={toggle.isPending} onClick={() => toggle.mutate(p)}>{p.is_active ? 'Deactivate' : 'Activate'}</Button>
          </div>
        ) : null,
    },
  ]

  return (
    <>
      <FilterBar>
        <Field label="Search" className="w-60"><Input placeholder="Code or name" value={filters.q} onChange={(e) => { setFilters({ ...filters, q: e.target.value }); setPage(1) }} /></Field>
        <Field label="Status">
          <Select value={filters.status} onChange={(e) => { setFilters({ ...filters, status: e.target.value }); setPage(1) }}>
            <option value="">All</option>
            <option value="CURRENT">Running now</option>
            <option value="SCHEDULED">Scheduled</option>
            <option value="EXPIRED">Ended</option>
            <option value="INACTIVE">Inactive</option>
          </Select>
        </Field>
        <Field label="Type">
          <Select value={filters.promo_type} onChange={(e) => { setFilters({ ...filters, promo_type: e.target.value }); setPage(1) }}>
            <option value="">All types</option>
            {(Object.keys(PROMO_LABEL) as PromotionType[]).map((t) => <option key={t} value={t}>{PROMO_LABEL[t]}</option>)}
          </Select>
        </Field>
        {canManage && <div className="ml-auto"><Button variant="primary" onClick={() => setOpen('new')}><Plus size={13} /> New promotion</Button></div>}
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(p) => p.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(p) => setOpen(p.id)} emptyTitle="No promotions" emptyHint="A promotion beats the list price when it is cheaper for the customer." />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <Drawer open={open !== null} onClose={() => setOpen(null)} title={open === 'new' ? 'New promotion' : 'Promotion'} width={820}>
        {open !== null && <PromotionEditor key={open} id={open === 'new' ? null : open} canManage={canManage} onDone={() => { setOpen(null); queryClient.invalidateQueries({ queryKey: ['pricing-rules', 'promotions'] }) }} />}
      </Drawer>
    </>
  )
}

type LineDraft = { product_id: string; product_name?: string; uom_id: string; promo_price: string; discount_pct: string; buy_qty: string; free_qty: string; bonus_product_id: string; bonus_name?: string; max_free_per_order: string; repeat: boolean }
const emptyLine = (): LineDraft => ({ product_id: '', uom_id: '', promo_price: '', discount_pct: '', buy_qty: '', free_qty: '', bonus_product_id: '', max_free_per_order: '', repeat: true })

function PromotionEditor({ id, canManage, onDone }: { id: string | null; canManage: boolean; onDone: () => void }) {
  const existing = useQuery({ queryKey: ['pricing-rules', 'promotions', id], queryFn: () => apiGet<Promotion>(`/api/pricing-rules/promotions/${id}`), enabled: !!id })
  if (id && existing.isLoading) return <div className="text-xs text-slate-500">Loading…</div>
  if (id && existing.isError) return <InlineError error={existing.error} />
  return <PromotionForm promotion={existing.data ?? null} canManage={canManage} onDone={onDone} />
}

function PromotionForm({ promotion, canManage, onDone }: { promotion: Promotion | null; canManage: boolean; onDone: () => void }) {
  const { data: me } = useCurrentUser()
  const suppliers = useSuppliers()
  const savedCustomer = useCustomer(promotion?.customer_scope ?? null)
  const [customer, setCustomer] = useState<Customer | null | undefined>(undefined)
  const [form, setForm] = useState({
    code: promotion?.code ?? '',
    name: promotion?.name ?? '',
    promo_type: promotion?.promo_type ?? ('PERCENT_OFF' as PromotionType),
    effective_from: dateOnly(promotion?.effective_from) || todayIso(),
    effective_to: dateOnly(promotion?.effective_to) || addDaysIso(30),
    branch_scope: promotion?.branch_scope ?? '',
    funded_by: promotion?.funded_by ?? 'US',
    supplier_id: promotion?.supplier_id ?? '',
    is_active: promotion?.is_active ?? true,
  })
  const [lines, setLines] = useState<LineDraft[]>(
    promotion?.lines?.length
      ? promotion.lines.map((l) => ({
          product_id: l.product_id, product_name: l.product?.name, uom_id: l.uom_id,
          promo_price: l.promo_price ?? '', discount_pct: l.discount_pct ?? '', buy_qty: l.buy_qty ?? '', free_qty: l.free_qty ?? '',
          bonus_product_id: l.bonus_product_id ?? '', bonus_name: l.bonus_product?.name, max_free_per_order: l.max_free_per_order ?? '', repeat: l.repeat,
        }))
      : [emptyLine()],
  )
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const setLine = (i: number, patch: Partial<LineDraft>) => setLines(lines.map((l, j) => (j === i ? { ...l, ...patch } : l)))
  const readOnly = !canManage
  const chosenCustomer = customer === undefined ? savedCustomer.data ?? null : customer

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = {
        ...form,
        branch_scope: form.branch_scope || null,
        supplier_id: form.funded_by === 'SUPPLIER' ? form.supplier_id || null : null,
        customer_scope: chosenCustomer?.id ?? null,
        lines: lines.filter((l) => l.product_id).map((l) => ({
          product_id: l.product_id,
          uom_id: l.uom_id,
          promo_price: form.promo_type === 'PRICE_OVERRIDE' ? l.promo_price || null : null,
          discount_pct: form.promo_type === 'PERCENT_OFF' ? l.discount_pct || null : null,
          buy_qty: form.promo_type === 'BUY_X_GET_Y' ? l.buy_qty || null : null,
          free_qty: form.promo_type === 'BUY_X_GET_Y' ? l.free_qty || null : null,
          bonus_product_id: form.promo_type === 'BUY_X_GET_Y' ? l.bonus_product_id || null : null,
          max_free_per_order: form.promo_type === 'BUY_X_GET_Y' ? l.max_free_per_order || null : null,
          repeat: l.repeat,
        })),
      }
      return promotion ? apiPatch<Promotion>(`/api/pricing-rules/promotions/${promotion.id}`, body) : apiPost<Promotion>('/api/pricing-rules/promotions', body)
    },
    onSuccess: (p) => {
      toast.success(promotion ? `${p.code} saved` : `Promotion ${p.code} created`)
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const lineErr = (i: number) => err ? Object.entries(err.errors).filter(([k]) => k.startsWith(`lines.${i}.`)).map(([, v]) => v[0]).join(' ') : ''

  return (
    <fieldset disabled={readOnly} className="space-y-4">
      <div className="grid grid-cols-3 gap-3">
        <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} onChange={(e) => set({ code: e.target.value.toUpperCase() })} /></Field>
        <Field label="Name" required error={err?.errors.name?.[0]} className="col-span-2"><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
        <Field label="Type" required>
          <Select value={form.promo_type} onChange={(e) => set({ promo_type: e.target.value as PromotionType })}>
            {(Object.keys(PROMO_LABEL) as PromotionType[]).map((t) => <option key={t} value={t}>{PROMO_LABEL[t]}</option>)}
          </Select>
        </Field>
        <Field label="From" required error={err?.errors.effective_from?.[0]}><Input type="date" value={form.effective_from} onChange={(e) => set({ effective_from: e.target.value })} /></Field>
        <Field label="To" required error={err?.errors.effective_to?.[0]}><Input type="date" value={form.effective_to} min={form.effective_from} onChange={(e) => set({ effective_to: e.target.value })} /></Field>
        <Field label="Customer" className="col-span-2" hint="Leave empty for every customer.">
          <CustomerPicker value={chosenCustomer} onChange={setCustomer} disabled={readOnly} />
        </Field>
        <Field label="Branch" error={err?.errors.branch_scope?.[0]}>
          <Select value={form.branch_scope} onChange={(e) => set({ branch_scope: e.target.value })}>
            <option value="">All branches</option>
            {(me?.branches ?? []).map((b) => <option key={b.id} value={b.id}>{b.code} · {b.name}</option>)}
          </Select>
        </Field>
        <Field label="Funded by" hint="Who bears the cost (Part 0.4).">
          <Select value={form.funded_by} onChange={(e) => set({ funded_by: e.target.value as 'US' | 'SUPPLIER' })}>
            <option value="US">Us</option>
            <option value="SUPPLIER">Supplier</option>
          </Select>
        </Field>
        {form.funded_by === 'SUPPLIER' && (
          <Field label="Supplier" required error={err?.errors.supplier_id?.[0]} className="col-span-2">
            <Select value={form.supplier_id} onChange={(e) => set({ supplier_id: e.target.value })}>
              <option value="">Choose supplier…</option>
              {(suppliers.data?.data ?? []).map((s) => <option key={s.id} value={s.id}>{s.code} · {s.name}</option>)}
            </Select>
          </Field>
        )}
      </div>

      <div>
        <div className="flex items-center justify-between mb-1.5">
          <h3 className="text-xs font-bold text-slate-900">Products</h3>
          {!readOnly && <Button size="sm" onClick={() => setLines([...lines, emptyLine()])}><Plus size={12} /> Add product</Button>}
        </div>
        {err?.errors.lines?.[0] && <p className="text-xs text-rose-600 mb-1 font-medium">{err.errors.lines[0]}</p>}
        <div className="space-y-2">
          {lines.map((l, i) => (
            <div key={i} className="rounded-md border border-slate-200 p-2.5">
              <div className="grid grid-cols-[1fr_120px_auto] gap-2 items-end">
                <Field label="Product">
                  <ProductField productId={l.product_id} fallbackName={l.product_name} disabled={readOnly} onChange={(p) => setLine(i, { product_id: p?.id ?? '', product_name: p?.name, uom_id: defaultUomId(p) })} />
                </Field>
                <Field label="Unit"><UomSelect productId={l.product_id} value={l.uom_id} onChange={(uom) => setLine(i, { uom_id: uom })} disabled={readOnly} /></Field>
                {!readOnly && lines.length > 1 && <Button variant="ghost" aria-label="Remove line" onClick={() => setLines(lines.filter((_, j) => j !== i))}><Trash2 size={13} /></Button>}
              </div>
              <div className="grid grid-cols-4 gap-2 mt-2">
                {form.promo_type === 'PERCENT_OFF' && <Field label="Discount %"><Input inputMode="decimal" className="tabular" value={l.discount_pct} onChange={(e) => setLine(i, { discount_pct: decimalInput(e.target.value) })} /></Field>}
                {form.promo_type === 'PRICE_OVERRIDE' && <Field label="Promo price (per unit)"><Input inputMode="decimal" className="tabular" value={l.promo_price} onChange={(e) => setLine(i, { promo_price: decimalInput(e.target.value) })} /></Field>}
                {form.promo_type === 'BUY_X_GET_Y' && (
                  <>
                    <Field label="Buy qty"><Input inputMode="decimal" className="tabular" value={l.buy_qty} onChange={(e) => setLine(i, { buy_qty: decimalInput(e.target.value) })} /></Field>
                    <Field label="Free qty"><Input inputMode="decimal" className="tabular" value={l.free_qty} onChange={(e) => setLine(i, { free_qty: decimalInput(e.target.value) })} /></Field>
                    <Field label="Max free / order"><Input inputMode="decimal" className="tabular" value={l.max_free_per_order} onChange={(e) => setLine(i, { max_free_per_order: decimalInput(e.target.value) })} /></Field>
                    <label className="flex items-center gap-2 text-xs text-slate-600 pt-5 cursor-pointer"><input type="checkbox" checked={l.repeat} onChange={(e) => setLine(i, { repeat: e.target.checked })} /> Repeats</label>
                    <Field label="Free product (if different)" className="col-span-4">
                      <ProductField productId={l.bonus_product_id} fallbackName={l.bonus_name} disabled={readOnly} onChange={(p) => setLine(i, { bonus_product_id: p?.id ?? '', bonus_name: p?.name })} />
                    </Field>
                  </>
                )}
              </div>
              {lineErr(i) && <p className="text-xs text-rose-600 mt-1 font-medium">{lineErr(i)}</p>}
            </div>
          ))}
        </div>
      </div>

      {!promotion && <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> Active from the start date</label>}
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      {canManage && (
        <div className="flex justify-end gap-2">
          <Button onClick={onDone}>Cancel</Button>
          <Button variant="primary" disabled={!form.code || !form.name || !lines.some((l) => l.product_id && l.uom_id) || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : promotion ? 'Save promotion' : 'Create promotion'}</Button>
        </div>
      )}
    </fieldset>
  )
}
