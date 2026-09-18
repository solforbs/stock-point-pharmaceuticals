import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { DataTable, type Column } from '../../../components/ui/DataTable'
import { Drawer } from '../../../components/ui/Drawer'
import { ConfirmDialog } from '../../../components/ui/Modal'
import { MoneyCell } from '../../../components/ui/MoneyCell'
import { FilterBar } from '../../../components/ui/PageHeader'
import { EmptyState, InlineError } from '../../../components/ui/States'
import { StatusBadge } from '../../../components/ui/StatusBadge'
import { Button, Field, Input, Select } from '../../../components/ui/primitives'
import { api, apiGet, apiPatch, apiPost, getApiError } from '../../../lib/api'
import { usePriceLists } from '../../../lib/hooks'
import { formatQty } from '../../../lib/money'
import { toast, toastApiError } from '../../../lib/toast'
import type { Paginated, PriceListItem } from '../../../lib/types'
import { ProductField } from './fields'
import { decimalInput, type PriceBreak } from './shared'

/**
 * Part 4.4 — quantity breaks on a price-list row. STEP prices the whole
 * quantity at the tier it lands in; MARGINAL prices band by band. The
 * quantity is in the row's own unit.
 */
export default function PriceBreaksTab({ canManage }: { canManage: boolean }) {
  const queryClient = useQueryClient()
  const lists = usePriceLists()
  const [listId, setListId] = useState('')
  const [productId, setProductId] = useState('')
  const [editing, setEditing] = useState<PriceBreak | 'new' | null>(null)
  const [deleting, setDeleting] = useState<PriceBreak | null>(null)
  const activeList = listId || lists.data?.[0]?.id || ''

  const breaks = useQuery({
    queryKey: ['pricing-rules', 'price-breaks', activeList, productId],
    queryFn: () => apiGet<{ data: PriceBreak[] }>('/api/pricing-rules/price-breaks', { price_list_id: activeList, product_id: productId }),
    enabled: !!activeList,
  })
  const remove = useMutation({
    mutationFn: (b: PriceBreak) => api.delete(`/api/pricing-rules/price-breaks/${b.id}`),
    onSuccess: () => {
      toast.success('Break removed')
      setDeleting(null)
      queryClient.invalidateQueries({ queryKey: ['pricing-rules', 'price-breaks'] })
    },
    onError: (e) => toastApiError(e),
  })

  const columns: Column<PriceBreak>[] = [
    { key: 'product', header: 'Product', render: (b) => <>{b.product_price?.product?.name ?? '—'}<div className="text-[10.5px] text-[var(--text-muted)]">{b.product_price?.product?.code}</div></>, sortValue: (b) => b.product_price?.product?.name ?? '' },
    { key: 'uom', header: 'Unit', render: (b) => b.product_price?.uom?.code ?? '—' },
    { key: 'list', header: 'List price', align: 'right', render: (b) => (b.product_price?.factor_type === 'FIXED' ? <MoneyCell value={b.product_price.unit_price} /> : <span className="text-[11px] text-[var(--text-muted)]">{b.product_price?.factor_type}</span>) },
    { key: 'range', header: 'Quantity', render: (b) => <span className="tabular">{formatQty(b.min_qty)} – {b.max_qty ? formatQty(b.max_qty) : 'and above'}</span>, sortValue: (b) => Number(b.min_qty) },
    { key: 'price', header: 'Unit price', align: 'right', render: (b) => <MoneyCell value={b.unit_price} className="font-semibold" /> },
    { key: 'type', header: 'Type', render: (b) => <StatusBadge status={b.break_type} tone={b.break_type === 'STEP' ? 'blue' : 'purple'} /> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (b) => (canManage ? <div onClick={(e) => e.stopPropagation()}><Button size="sm" variant="ghost" onClick={() => setDeleting(b)}>Remove</Button></div> : null),
    },
  ]

  return (
    <>
      <FilterBar>
        <Field label="Price list" className="w-64">
          <Select value={activeList} onChange={(e) => setListId(e.target.value)} disabled={lists.isLoading}>
            {!lists.data?.length && <option value="">{lists.isLoading ? 'Loading…' : 'No price lists'}</option>}
            {(lists.data ?? []).map((l) => <option key={l.id} value={l.id}>{l.code} · {l.name}{l.is_active ? '' : ' (inactive)'}</option>)}
          </Select>
        </Field>
        <Field label="Product" className="w-72"><ProductField productId={productId} onChange={(p) => setProductId(p?.id ?? '')} /></Field>
        {canManage && activeList && <div className="ml-auto"><Button variant="primary" onClick={() => setEditing('new')}><Plus size={13} /> New break</Button></div>}
      </FilterBar>
      {lists.isError && <InlineError error={lists.error} className="mb-3" />}
      <div className="ui-card">
        {!activeList && !lists.isLoading ? (
          <EmptyState title="No price lists" hint="Create a price list under Customers → Tiers & Price Lists first; breaks attach to its rows." />
        ) : (
          <DataTable
            columns={columns}
            rows={breaks.data?.data}
            rowKey={(b) => b.id}
            isLoading={breaks.isLoading}
            error={breaks.error}
            onRetry={() => breaks.refetch()}
            onRowClick={canManage ? (b) => setEditing(b) : undefined}
            emptyTitle="No quantity breaks"
            emptyHint="Without a break, every quantity sells at the row's list price."
          />
        )}
      </div>
      <Drawer open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'New quantity break' : 'Edit quantity break'} width={520}>
        {editing !== null && (
          <BreakForm
            key={editing === 'new' ? 'new' : editing.id}
            listId={activeList}
            initialProductId={productId}
            existing={editing === 'new' ? null : editing}
            onDone={() => { setEditing(null); queryClient.invalidateQueries({ queryKey: ['pricing-rules', 'price-breaks'] }) }}
          />
        )}
      </Drawer>
      <ConfirmDialog
        open={!!deleting}
        title="Remove this break?"
        message={deleting ? `${deleting.product_price?.product?.name ?? ''} from ${formatQty(deleting.min_qty)} will sell at the list price again.` : undefined}
        confirmLabel="Remove"
        danger
        isPending={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting)}
        onCancel={() => setDeleting(null)}
      />
    </>
  )
}

function BreakForm({ listId, initialProductId, existing, onDone }: { listId: string; initialProductId: string; existing: PriceBreak | null; onDone: () => void }) {
  const [productId, setProductId] = useState(existing?.product_price?.product_id ?? initialProductId)
  const [rowId, setRowId] = useState(existing?.product_price_id ?? '')
  const [form, setForm] = useState({
    min_qty: existing?.min_qty ?? '',
    max_qty: existing?.max_qty ?? '',
    unit_price: existing?.unit_price ?? '',
    break_type: existing?.break_type ?? 'STEP',
  })
  const rows = useQuery({
    queryKey: ['price-lists', listId, 'items', productId],
    queryFn: () => apiGet<Paginated<PriceListItem>>(`/api/price-lists/${listId}/items`, { product_id: productId, per_page: 50 }),
    enabled: !existing && !!productId && !!listId,
  })
  const chosenRow = existing ? existing.product_price_id : rowId || (rows.data?.data.length === 1 ? rows.data.data[0].id : '')

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = { min_qty: form.min_qty, max_qty: form.max_qty || null, unit_price: form.unit_price, break_type: form.break_type }
      return existing ? apiPatch<PriceBreak>(`/api/pricing-rules/price-breaks/${existing.id}`, body) : apiPost<PriceBreak>('/api/pricing-rules/price-breaks', { ...body, product_price_id: chosenRow })
    },
    onSuccess: () => {
      toast.success(existing ? 'Break updated' : 'Break added')
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-4">
      {existing ? (
        <div className="text-[12px]"><b>{existing.product_price?.product?.name}</b> · {existing.product_price?.uom?.code} · {existing.product_price?.price_list?.code}</div>
      ) : (
        <>
          <Field label="Product" required><ProductField productId={productId} onChange={(p) => { setProductId(p?.id ?? ''); setRowId('') }} /></Field>
          {productId && (
            <Field label="Price row" required error={err?.errors.product_price_id?.[0]} hint="Breaks attach to one current row of the list: one product in one unit.">
              <Select value={chosenRow} onChange={(e) => setRowId(e.target.value)} disabled={rows.isLoading}>
                <option value="">{rows.isLoading ? 'Loading…' : rows.data?.data.length ? 'Choose a row…' : 'This product has no current row in the list'}</option>
                {(rows.data?.data ?? []).map((r) => (
                  <option key={r.id} value={r.id}>{r.uom?.code ?? 'Base unit'} · {r.factor_type === 'FIXED' ? `KES ${r.unit_price}` : r.factor_type} · from {r.effective_from?.slice(0, 10)}</option>
                ))}
              </Select>
            </Field>
          )}
        </>
      )}
      <div className="grid grid-cols-2 gap-3">
        <Field label="From quantity" required error={err?.errors.min_qty?.[0]}><Input inputMode="decimal" className="tabular" value={form.min_qty} onChange={(e) => setForm({ ...form, min_qty: decimalInput(e.target.value) })} /></Field>
        <Field label="To quantity" hint="Empty = and above." error={err?.errors.max_qty?.[0]}><Input inputMode="decimal" className="tabular" value={form.max_qty} onChange={(e) => setForm({ ...form, max_qty: decimalInput(e.target.value) })} /></Field>
        <Field label="Unit price" required error={err?.errors.unit_price?.[0]}><Input inputMode="decimal" className="tabular" value={form.unit_price} onChange={(e) => setForm({ ...form, unit_price: decimalInput(e.target.value) })} /></Field>
        <Field label="Type" error={err?.errors.break_type?.[0]} hint={form.break_type === 'STEP' ? 'Whole quantity at this price.' : 'Only the units in this band.'}>
          <Select value={form.break_type} onChange={(e) => setForm({ ...form, break_type: e.target.value as 'STEP' | 'MARGINAL' })}>
            <option value="STEP">Step</option>
            <option value="MARGINAL">Marginal</option>
          </Select>
        </Field>
      </div>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onDone}>Cancel</Button>
        <Button variant="primary" disabled={!chosenRow || !form.min_qty || !form.unit_price || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : existing ? 'Save break' : 'Add break'}</Button>
      </div>
    </div>
  )
}
