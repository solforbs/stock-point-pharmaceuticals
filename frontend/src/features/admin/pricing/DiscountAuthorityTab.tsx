import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { DataTable, type Column } from '../../../components/ui/DataTable'
import { Drawer } from '../../../components/ui/Drawer'
import { ConfirmDialog } from '../../../components/ui/Modal'
import { InlineError } from '../../../components/ui/States'
import { StatusBadge } from '../../../components/ui/StatusBadge'
import { Button, Field, Input } from '../../../components/ui/primitives'
import { api, apiGet, apiPut, getApiError } from '../../../lib/api'
import { formatPct } from '../../../lib/money'
import { toast, toastApiError } from '../../../lib/toast'
import { decimalInput, type AuthorityRow } from './shared'

/**
 * Part 4.5 — each role's discount authority. A user's authority is the
 * most generous of the roles they hold in the branch; a role with no row
 * may not discount at all.
 */
export default function DiscountAuthorityTab({ canManage }: { canManage: boolean }) {
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<AuthorityRow | null>(null)
  const [removing, setRemoving] = useState<AuthorityRow | null>(null)
  const rows = useQuery({ queryKey: ['pricing-rules', 'discount-authorities'], queryFn: () => apiGet<{ data: AuthorityRow[] }>('/api/pricing-rules/discount-authorities') })
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['pricing-rules', 'discount-authorities'] })

  const remove = useMutation({
    mutationFn: (r: AuthorityRow) => api.delete(`/api/pricing-rules/discount-authorities/${r.role_id}`),
    onSuccess: () => {
      toast.success('Authority removed', 'That role can no longer discount.')
      setRemoving(null)
      invalidate()
    },
    onError: (e) => toastApiError(e),
  })

  const columns: Column<AuthorityRow>[] = [
    { key: 'role', header: 'Role', render: (r) => <span className="font-semibold">{r.role}</span>, sortValue: (r) => r.role },
    { key: 'line', header: 'Line discount', align: 'right', render: (r) => (r.authority ? <span className="tabular">{formatPct(r.authority.max_line_discount_pct)}</span> : <span className="text-[var(--text-muted)]">None</span>), sortValue: (r) => Number(r.authority?.max_line_discount_pct ?? -1) },
    { key: 'header', header: 'Whole-sale discount', align: 'right', render: (r) => (r.authority ? <span className="tabular">{formatPct(r.authority.max_header_discount_pct)}</span> : <span className="text-[var(--text-muted)]">None</span>) },
    { key: 'floor', header: 'May go below margin floor', render: (r) => (r.authority ? <StatusBadge status={r.authority.may_override_floor ? 'OK' : 'INACTIVE'} label={r.authority.may_override_floor ? 'Yes, with reason' : 'No'} /> : '—') },
    { key: 'actions', header: '', align: 'right', render: (r) => (canManage && r.authority ? <div onClick={(e) => e.stopPropagation()}><Button size="sm" variant="ghost" onClick={() => setRemoving(r)}>Remove</Button></div> : null) },
  ]

  return (
    <>
      <p className="text-[11.5px] text-[var(--text-muted)] mb-3">The product policy and the customer tier can each lower these limits; the most restrictive wins at the till.</p>
      <div className="ui-card">
        <DataTable columns={columns} rows={rows.data?.data} rowKey={(r) => String(r.role_id)} isLoading={rows.isLoading} error={rows.error} onRetry={() => rows.refetch()} onRowClick={canManage ? setEditing : undefined} emptyTitle="No roles" />
      </div>
      <Drawer open={!!editing} onClose={() => setEditing(null)} title={`Discount authority · ${editing?.role ?? ''}`} width={460}>
        {editing && <AuthorityForm key={editing.role_id} row={editing} onDone={() => { setEditing(null); invalidate() }} />}
      </Drawer>
      <ConfirmDialog
        open={!!removing}
        title={`Remove ${removing?.role ?? ''}'s discount authority?`}
        message="Users whose only roles have no authority cannot give any manual discount."
        confirmLabel="Remove"
        danger
        isPending={remove.isPending}
        onConfirm={() => removing && remove.mutate(removing)}
        onCancel={() => setRemoving(null)}
      />
    </>
  )
}

function AuthorityForm({ row, onDone }: { row: AuthorityRow; onDone: () => void }) {
  const [form, setForm] = useState({
    max_line_discount_pct: row.authority ? String(Number(row.authority.max_line_discount_pct)) : '',
    max_header_discount_pct: row.authority ? String(Number(row.authority.max_header_discount_pct)) : '',
    may_override_floor: row.authority?.may_override_floor ?? false,
  })
  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPut<AuthorityRow>(`/api/pricing-rules/discount-authorities/${row.role_id}`, { ...form, max_line_discount_pct: form.max_line_discount_pct || '0', max_header_discount_pct: form.max_header_discount_pct || '0' }),
    onSuccess: () => {
      toast.success(`${row.role} authority saved`)
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <Field label="Max line discount %" required error={err?.errors.max_line_discount_pct?.[0]}><Input inputMode="decimal" className="tabular" value={form.max_line_discount_pct} onChange={(e) => setForm({ ...form, max_line_discount_pct: decimalInput(e.target.value) })} /></Field>
        <Field label="Max whole-sale discount %" required error={err?.errors.max_header_discount_pct?.[0]}><Input inputMode="decimal" className="tabular" value={form.max_header_discount_pct} onChange={(e) => setForm({ ...form, max_header_discount_pct: decimalInput(e.target.value) })} /></Field>
      </div>
      <label className="flex items-start gap-2 text-[12px]">
        <input type="checkbox" className="mt-0.5" checked={form.may_override_floor} onChange={(e) => setForm({ ...form, may_override_floor: e.target.checked })} />
        <span>May sell below the margin floor, with a recorded reason. Reserve this for managers.</span>
      </label>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onDone}>Cancel</Button>
        <Button variant="primary" disabled={save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save authority'}</Button>
      </div>
    </div>
  )
}
