import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { useDebounced } from '../../../components/ProductSearch'
import { DataTable, type Column } from '../../../components/ui/DataTable'
import { Drawer } from '../../../components/ui/Drawer'
import { ConfirmDialog } from '../../../components/ui/Modal'
import { MoneyCell } from '../../../components/ui/MoneyCell'
import { FilterBar } from '../../../components/ui/PageHeader'
import { Pagination } from '../../../components/ui/Pagination'
import { InlineError } from '../../../components/ui/States'
import { StatusBadge } from '../../../components/ui/StatusBadge'
import { Button, Field, Input, Select } from '../../../components/ui/primitives'
import { api, apiGet, apiPatch, apiPost, getApiError } from '../../../lib/api'
import { formatPct } from '../../../lib/money'
import { toast, toastApiError } from '../../../lib/toast'
import type { Paginated } from '../../../lib/types'
import { ProductField } from './fields'
import { ROUND_LABEL, decimalInput, type DiscountPolicy, type RoundTo } from './shared'

/**
 * Part 4.11 — one discount policy per product: the discount ceiling, the
 * approval threshold, the margin floor and the price rounding. Products
 * without a policy fall back to the organisation's pricing settings.
 */
export default function DiscountPoliciesTab({ canManage }: { canManage: boolean }) {
  const queryClient = useQueryClient()
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<DiscountPolicy | 'new' | null>(null)
  const [deleting, setDeleting] = useState<DiscountPolicy | null>(null)
  const dq = useDebounced(q, 250)

  const list = useQuery({
    queryKey: ['pricing-rules', 'discount-policies', dq, page],
    queryFn: () => apiGet<Paginated<DiscountPolicy>>('/api/pricing-rules/discount-policies', { q: dq, page }),
    placeholderData: (prev) => prev,
  })
  const remove = useMutation({
    mutationFn: (p: DiscountPolicy) => api.delete(`/api/pricing-rules/discount-policies/${p.id}`),
    onSuccess: () => {
      toast.success('Policy removed', 'The product now follows the default pricing settings.')
      setDeleting(null)
      queryClient.invalidateQueries({ queryKey: ['pricing-rules', 'discount-policies'] })
    },
    onError: (e) => toastApiError(e),
  })

  const columns: Column<DiscountPolicy>[] = [
    { key: 'product', header: 'Product', render: (p) => <>{p.product?.name ?? '—'}<div className="text-xs text-slate-500 font-mono">{p.product?.code}</div></>, sortValue: (p) => p.product?.name ?? '' },
    { key: 'allowed', header: 'Discounts', render: (p) => <StatusBadge status={p.discount_allowed ? 'OK' : 'BLOCKED'} label={p.discount_allowed ? 'Allowed' : 'Not allowed'} /> },
    { key: 'max', header: 'Max discount', align: 'right', render: (p) => <span className="tabular">{formatPct(p.max_discount_pct)}{p.max_discount_amount && <div className="text-xs text-slate-500">≤ <MoneyCell value={p.max_discount_amount} />/unit</div>}</span>, sortValue: (p) => Number(p.max_discount_pct) },
    { key: 'approval', header: 'Approval above', align: 'right', render: (p) => <span className="tabular">{p.discount_approval_pct !== null ? formatPct(p.discount_approval_pct) : '—'}</span> },
    { key: 'margin', header: 'Min margin', align: 'right', render: (p) => <span className="tabular">{formatPct(p.min_margin_pct)}</span>, sortValue: (p) => Number(p.min_margin_pct) },
    { key: 'round', header: 'Rounding', render: (p) => ROUND_LABEL[p.round_to] ?? p.round_to },
    { key: 'bonus', header: 'Bonus / stack', render: (p) => <span className="text-xs text-slate-600">{p.bonus_allowed ? 'Bonus ok' : 'No bonus'} · {p.promo_stackable ? 'stacks' : 'no stacking'}</span> },
    { key: 'actions', header: '', align: 'right', render: (p) => (canManage ? <div onClick={(e) => e.stopPropagation()}><Button size="sm" variant="ghost" onClick={() => setDeleting(p)}>Remove</Button></div> : null) },
  ]

  return (
    <>
      <FilterBar>
        <Field label="Search" className="w-64"><Input placeholder="Product name or code" value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} /></Field>
        {canManage && <div className="ml-auto"><Button variant="primary" onClick={() => setEditing('new')}><Plus size={13} /> New policy</Button></div>}
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(p) => p.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={canManage ? (p) => setEditing(p) : undefined} emptyTitle="No product policies" emptyHint="Without a policy a product uses the default discount ceiling and margin floor from pricing settings." />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <Drawer open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'New discount policy' : `Policy · ${editing?.product?.name ?? ''}`} width={560}>
        {editing !== null && <PolicyForm key={editing === 'new' ? 'new' : editing.id} policy={editing === 'new' ? null : editing} onDone={() => { setEditing(null); queryClient.invalidateQueries({ queryKey: ['pricing-rules', 'discount-policies'] }) }} />}
      </Drawer>
      <ConfirmDialog
        open={!!deleting}
        title="Remove this policy?"
        message={deleting ? `${deleting.product?.name ?? 'The product'} will follow the default pricing settings.` : undefined}
        confirmLabel="Remove"
        danger
        isPending={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting)}
        onCancel={() => setDeleting(null)}
      />
    </>
  )
}

function PolicyForm({ policy, onDone }: { policy: DiscountPolicy | null; onDone: () => void }) {
  const [form, setForm] = useState({
    product_id: policy?.product_id ?? '',
    discount_allowed: policy?.discount_allowed ?? true,
    max_discount_pct: policy?.max_discount_pct ? String(Number(policy.max_discount_pct)) : '',
    max_discount_amount: policy?.max_discount_amount ? String(Number(policy.max_discount_amount)) : '',
    discount_approval_pct: policy?.discount_approval_pct ? String(Number(policy.discount_approval_pct)) : '',
    min_margin_pct: policy?.min_margin_pct ? String(Number(policy.min_margin_pct)) : '',
    round_to: policy?.round_to ?? ('NONE' as RoundTo),
    bonus_allowed: policy?.bonus_allowed ?? true,
    promo_stackable: policy?.promo_stackable ?? false,
  })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = {
        discount_allowed: form.discount_allowed,
        max_discount_pct: form.max_discount_pct || '0',
        max_discount_amount: form.max_discount_amount || null,
        discount_approval_pct: form.discount_approval_pct || null,
        min_margin_pct: form.min_margin_pct || '0',
        round_to: form.round_to,
        bonus_allowed: form.bonus_allowed,
        promo_stackable: form.promo_stackable,
      }
      return policy ? apiPatch<DiscountPolicy>(`/api/pricing-rules/discount-policies/${policy.id}`, body) : apiPost<DiscountPolicy>('/api/pricing-rules/discount-policies', { ...body, product_id: form.product_id })
    },
    onSuccess: (p) => {
      toast.success(policy ? 'Policy saved' : 'Policy created', p.product?.name)
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-4">
      <Field label="Product" required error={err?.errors.product_id?.[0]}>
        <ProductField productId={form.product_id} fallbackName={policy?.product?.name} disabled={!!policy} onChange={(p) => set({ product_id: p?.id ?? '' })} />
      </Field>
      <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer"><input type="checkbox" checked={form.discount_allowed} onChange={(e) => set({ discount_allowed: e.target.checked })} /> Manual discounts allowed on this product</label>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Max discount %" required error={err?.errors.max_discount_pct?.[0]} hint="Ceiling for any cashier or rep."><Input inputMode="decimal" className="tabular" value={form.max_discount_pct} onChange={(e) => set({ max_discount_pct: decimalInput(e.target.value) })} disabled={!form.discount_allowed} /></Field>
        <Field label="Max discount (KES / unit)" error={err?.errors.max_discount_amount?.[0]} hint="Optional absolute cap."><Input inputMode="decimal" className="tabular" value={form.max_discount_amount} onChange={(e) => set({ max_discount_amount: decimalInput(e.target.value) })} disabled={!form.discount_allowed} /></Field>
        <Field label="Needs approval above %" error={err?.errors.discount_approval_pct?.[0]} hint="Empty = the default setting."><Input inputMode="decimal" className="tabular" value={form.discount_approval_pct} onChange={(e) => set({ discount_approval_pct: decimalInput(e.target.value) })} /></Field>
        <Field label="Minimum margin %" required error={err?.errors.min_margin_pct?.[0]} hint="Hard floor: price never goes below cost ÷ (1 − margin)."><Input inputMode="decimal" className="tabular" value={form.min_margin_pct} onChange={(e) => set({ min_margin_pct: decimalInput(e.target.value) })} /></Field>
        <Field label="Round final price to" className="col-span-2">
          <Select value={form.round_to} onChange={(e) => set({ round_to: e.target.value as RoundTo })}>
            {(Object.keys(ROUND_LABEL) as RoundTo[]).map((r) => <option key={r} value={r}>{ROUND_LABEL[r]}</option>)}
          </Select>
        </Field>
      </div>
      <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer"><input type="checkbox" checked={form.bonus_allowed} onChange={(e) => set({ bonus_allowed: e.target.checked })} /> Bonus (free goods) schemes may apply</label>
      <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer"><input type="checkbox" checked={form.promo_stackable} onChange={(e) => set({ promo_stackable: e.target.checked })} /> A manual discount may stack on a promotion</label>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onDone}>Cancel</Button>
        <Button variant="primary" disabled={!form.product_id || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : policy ? 'Save policy' : 'Create policy'}</Button>
      </div>
    </div>
  )
}
