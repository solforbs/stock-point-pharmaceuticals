import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDate, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, Supplier } from '../../lib/types'
import { ExpiryBadge } from '../inventory/StockOnHandPage'

const STATUSES = ['ACTIVE', 'SUSPENDED', 'BLACKLISTED'] as const

export default function SuppliersPage() {
  const [params, setParams] = useSearchParams()
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [editing, setEditing] = useState(false)
  const dq = useDebounced(q, 250)
  const selectedId = params.get('supplier')
  const canManage = usePermission('supplier.manage')

  const list = useQuery({
    queryKey: ['suppliers', 'list', dq, page],
    queryFn: () => apiGet<Paginated<Supplier>>('/api/suppliers', { q: dq, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })
  const selected = list.data?.data.find((s) => s.id === selectedId) ?? null

  const columns: Column<Supplier>[] = [
    { key: 'code', header: 'Code', render: (s) => <span className="font-semibold tabular">{s.code}</span>, sortValue: (s) => s.code },
    { key: 'name', header: 'Name', render: (s) => s.name, sortValue: (s) => s.name },
    { key: 'contact', header: 'Contact', render: (s) => <>{s.contact_name ?? '—'}{s.phone && <div className="text-[10.5px] text-[var(--text-muted)]">{s.phone}</div>}</> },
    { key: 'licence', header: 'Licence expiry', render: (s) => <ExpiryBadge date={s.licence_expiry} />, sortValue: (s) => s.licence_expiry ?? '' },
    { key: 'terms', header: 'Terms', align: 'right', render: (s) => <span className="tabular">{s.payment_terms_days ?? 0} d</span> },
    { key: 'status', header: 'Status', render: (s) => <StatusBadge status={s.is_active ? s.status : 'INACTIVE'} /> },
    { key: 'payable', header: 'Payable', align: 'right', render: (s) => <MoneyCell value={s.payable_balance} />, sortValue: (s) => Number(s.payable_balance ?? 0) },
  ]

  const close = () => {
    setEditing(false)
    setParams({})
  }

  return (
    <Page>
      <PageHeader
        parent="Buy"
        title="Suppliers"
        subtitle="A purchase order can only go to an active, licensed supplier (Part 9.6)."
        actions={canManage ? <Button variant="primary" onClick={() => setCreating(true)}>New supplier</Button> : null}
      />
      <FilterBar>
        <Field label="Search" className="w-72"><Input placeholder="Supplier name" value={q} onChange={(e) => setQ(e.target.value)} /></Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(s) => s.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(s) => { setEditing(false); setParams({ supplier: s.id }) }} selectedKey={selectedId} emptyTitle="No suppliers" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <Drawer open={!!selected} onClose={close} title={selected?.name ?? ''} subtitle={selected?.code} width={editing ? 720 : undefined}>
        {selected && editing && <SupplierForm supplier={selected} onDone={() => setEditing(false)} onCancel={() => setEditing(false)} />}
        {selected && !editing && (
          <div className="space-y-4">
            <div className="flex items-center justify-between gap-2">
              <div className="flex items-center gap-2"><StatusBadge status={selected.is_active ? selected.status : 'INACTIVE'} /><span className="tabular text-[12.5px]">Payable <MoneyCell value={selected.payable_balance} symbol className="font-bold" /></span></div>
              {canManage && <Button onClick={() => setEditing(true)}>Edit</Button>}
            </div>
            <DescriptionList
              items={[
                { label: 'Contact', value: selected.contact_name ?? '—' },
                { label: 'Phone', value: selected.phone ?? '—' },
                { label: 'Email', value: selected.email ?? '—' },
                { label: 'Licence', value: `${selected.licence_number ?? '—'} · expires ${formatDate(selected.licence_expiry)}` },
                { label: 'Payment terms', value: `${selected.payment_terms_days ?? 0} days` },
                { label: 'Lead time', value: `${selected.lead_time_days ?? 0} days` },
              ]}
            />
            <div className="flex gap-3 text-[12px]">
              <Link to={`/buy/purchase-orders?supplier=${selected.id}`} className="text-[var(--color-navy)] underline">Raise purchase order</Link>
              <Link to={`/finance/payables?supplier=${selected.id}`} className="text-[var(--color-navy)] underline">Record payment</Link>
            </div>
          </div>
        )}
      </Drawer>
      <Drawer open={creating} onClose={() => setCreating(false)} title="New supplier" width={720}>
        {creating && <SupplierForm onDone={(s) => { setCreating(false); setParams({ supplier: s.id }) }} onCancel={() => setCreating(false)} />}
      </Drawer>
    </Page>
  )
}

type SupplierForm = {
  code: string; name: string; contact_name: string; phone: string; email: string; address: string
  licence_number: string; licence_expiry: string; payment_terms_days: string; lead_time_days: string
  currency: string; bank_name: string; bank_account: string; status: string; is_active: boolean
}

/** One form for both create (no `supplier`) and edit; the code is fixed once issued. */
function SupplierForm({ supplier, onDone, onCancel }: { supplier?: Supplier; onDone: (s: Supplier) => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<SupplierForm>({
    code: supplier?.code ?? '',
    name: supplier?.name ?? '',
    contact_name: supplier?.contact_name ?? '',
    phone: supplier?.phone ?? '',
    email: supplier?.email ?? '',
    address: '',
    licence_number: supplier?.licence_number ?? '',
    licence_expiry: supplier?.licence_expiry ? supplier.licence_expiry.slice(0, 10) : '',
    payment_terms_days: String(supplier?.payment_terms_days ?? 30),
    lead_time_days: String(supplier?.lead_time_days ?? 14),
    currency: 'KES',
    bank_name: '',
    bank_account: '',
    status: supplier?.status ?? 'ACTIVE',
    is_active: supplier?.is_active ?? true,
  })
  const set = (patch: Partial<SupplierForm>) => setForm({ ...form, ...patch })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = {
        name: form.name,
        contact_name: form.contact_name || null,
        phone: form.phone || null,
        email: form.email || null,
        licence_number: form.licence_number || null,
        licence_expiry: form.licence_expiry || null,
        payment_terms_days: Number(form.payment_terms_days || 0),
        lead_time_days: Number(form.lead_time_days || 0),
        status: form.status,
        is_active: form.is_active,
        // Bank details are write-only: the API never echoes them back, so
        // only send a value the user actually typed (Part 19.2).
        ...(form.bank_name ? { bank_name: form.bank_name } : {}),
        ...(form.bank_account ? { bank_account: form.bank_account } : {}),
        ...(form.address ? { address: form.address } : {}),
      }
      return supplier
        ? apiPatch<Supplier>(`/api/suppliers/${supplier.id}`, body)
        : apiPost<Supplier>('/api/suppliers', { ...body, code: form.code, currency: form.currency })
    },
    onSuccess: (s) => {
      toast.success(supplier ? `${s.code} updated` : `Supplier ${s.code} created`)
      queryClient.invalidateQueries({ queryKey: ['suppliers'] })
      onDone(s)
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} disabled={!!supplier} onChange={(e) => set({ code: e.target.value.toUpperCase() })} /></Field>
        <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
        <Field label="Contact person"><Input value={form.contact_name} onChange={(e) => set({ contact_name: e.target.value })} /></Field>
        <Field label="Phone"><Input value={form.phone} onChange={(e) => set({ phone: e.target.value })} /></Field>
        <Field label="Email" error={err?.errors.email?.[0]}><Input type="email" value={form.email} onChange={(e) => set({ email: e.target.value })} /></Field>
        <Field label="Address"><Input value={form.address} onChange={(e) => set({ address: e.target.value })} /></Field>
        <Field label="PPB licence number"><Input value={form.licence_number} onChange={(e) => set({ licence_number: e.target.value })} /></Field>
        <Field label="Licence expiry" hint="Purchase orders are refused once this passes." error={err?.errors.licence_expiry?.[0]}><Input type="date" value={form.licence_expiry} onChange={(e) => set({ licence_expiry: e.target.value })} /></Field>
        <Field label="Payment terms (days)"><Input inputMode="numeric" className="tabular" value={form.payment_terms_days} onChange={(e) => set({ payment_terms_days: e.target.value.replace(/\D/g, '') })} /></Field>
        <Field label="Lead time (days)" hint="Feeds the reorder advisor's required-by date."><Input inputMode="numeric" className="tabular" value={form.lead_time_days} onChange={(e) => set({ lead_time_days: e.target.value.replace(/\D/g, '') })} /></Field>
        <Field label="Status"><Select value={form.status} onChange={(e) => set({ status: e.target.value })}>{STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}</Select></Field>
        {!supplier && <Field label="Currency"><Input value={form.currency} maxLength={3} onChange={(e) => set({ currency: e.target.value.toUpperCase() })} /></Field>}
        <Field label="Bank name" hint="Stored encrypted; never shown again."><Input value={form.bank_name} onChange={(e) => set({ bank_name: e.target.value })} placeholder={supplier ? 'Leave blank to keep' : ''} /></Field>
        <Field label="Bank account"><Input value={form.bank_account} onChange={(e) => set({ bank_account: e.target.value })} placeholder={supplier ? 'Leave blank to keep' : ''} /></Field>
        {supplier && (
          <label className="flex items-center gap-2 text-[12px] pt-4"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> Active</label>
        )}
      </div>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={!form.code || !form.name || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : supplier ? 'Save changes' : 'Create supplier'}</Button>
      </div>
    </div>
  )
}
