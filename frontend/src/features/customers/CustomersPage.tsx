import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useCustomer } from '../../components/CustomerPicker'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { dIsNeg } from '../../lib/decimal'
import { formatDate, titleCase } from '../../lib/format'
import { useCustomerTiers } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Customer, Paginated } from '../../lib/types'

const TYPES = ['WALK_IN', 'RETAIL_PHARMACY', 'HOSPITAL', 'CLINIC', 'NGO', 'GOVERNMENT', 'TENDER', 'INSTITUTION']

export default function CustomersPage() {
  const [params, setParams] = useSearchParams()
  const canManage = usePermission('customer.manage')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const dq = useDebounced(q, 250)
  const selectedId = params.get('customer')

  const list = useQuery({
    queryKey: ['customers', 'list', dq, page],
    queryFn: () => apiGet<Paginated<Customer>>('/api/customers', { q: dq, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<Customer>[] = [
    { key: 'code', header: 'Code', render: (c) => <span className="font-semibold tabular">{c.code}</span>, sortValue: (c) => c.code },
    { key: 'name', header: 'Name', render: (c) => c.name, sortValue: (c) => c.name },
    { key: 'type', header: 'Type', render: (c) => titleCase(c.customer_type) },
    { key: 'tier', header: 'Tier', render: (c) => c.tier?.code ?? '—' },
    { key: 'phone', header: 'Phone', render: (c) => c.phone ?? '—' },
    { key: 'limit', header: 'Credit limit', align: 'right', render: (c) => <MoneyCell value={c.credit?.credit_limit ?? '0'} />, sortValue: (c) => Number(c.credit?.credit_limit ?? 0) },
    { key: 'balance', header: 'Balance', align: 'right', render: (c) => <MoneyCell value={c.credit?.current_balance ?? '0'} />, sortValue: (c) => Number(c.credit?.current_balance ?? 0) },
    { key: 'available', header: 'Available', align: 'right', render: (c) => <MoneyCell value={c.available_credit ?? '0'} className={dIsNeg(c.available_credit ?? '0') ? 'font-bold' : ''} /> },
    { key: 'status', header: 'Status', render: (c) => (c.credit?.on_hold ? <StatusBadge status="ON_HOLD" label="Credit hold" /> : <StatusBadge status={c.is_active ? 'ACTIVE' : 'INACTIVE'} />) },
  ]

  return (
    <Page>
      <PageHeader parent="Customers" title="Customers" actions={canManage ? <Button variant="primary" onClick={() => setCreating(true)}>New customer</Button> : null} />
      <FilterBar>
        <Field label="Search" className="w-72"><Input placeholder="Name, code or phone" value={q} onChange={(e) => setQ(e.target.value)} /></Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(c) => c.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(c) => setParams({ customer: c.id })} selectedKey={selectedId} emptyTitle="No customers" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <CustomerDrawer id={selectedId} onClose={() => setParams({})} />
      <CustomerCreateDrawer open={creating} onClose={() => setCreating(false)} onCreated={(c) => setParams({ customer: c.id })} />
    </Page>
  )
}

function CustomerDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const customer = useCustomer(id)
  const c = customer.data
  return (
    <Drawer open={!!id} onClose={onClose} title={c?.name ?? 'Customer'} subtitle={c ? `${c.code} · ${titleCase(c.customer_type)}` : undefined} width={640}>
      {customer.isLoading && <LoadingSkeleton />}
      {customer.isError && <InlineError error={customer.error} />}
      {c && (
        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <StatusBadge status={c.is_active ? 'ACTIVE' : 'INACTIVE'} />
            {c.credit?.on_hold && <StatusBadge status="ON_HOLD" label={`Credit hold: ${c.credit.hold_reason ?? ''}`} />}
            <Link to={`/customers/credit-control?customer=${c.id}`} className="ml-auto text-[11.5px] text-[var(--color-navy)] underline">Credit control</Link>
          </div>
          <Card title="Credit">
            <div className="p-4 grid grid-cols-2 gap-x-6 gap-y-1 text-[12.5px] tabular">
              <span className="text-[var(--text-muted)]">Tier</span><span>{c.tier ? `${c.tier.code} · ${c.tier.name}` : '—'}</span>
              <span className="text-[var(--text-muted)]">Credit limit</span><MoneyCell value={c.credit?.credit_limit ?? '0'} symbol />
              <span className="text-[var(--text-muted)]">Outstanding invoices</span><MoneyCell value={c.credit?.current_balance ?? '0'} symbol />
              <span className="text-[var(--text-muted)]">Open order exposure</span><MoneyCell value={c.open_order_exposure ?? '0'} symbol />
              <span className="text-[var(--text-muted)]">Available credit</span><MoneyCell value={c.available_credit ?? '0'} symbol className="font-bold" />
              <span className="text-[var(--text-muted)]">Payment terms</span><span>{c.payment_terms_days ?? 0} days</span>
            </div>
          </Card>
          <DescriptionList items={[{ label: 'Phone', value: c.phone ?? '—' }, { label: 'Email', value: c.email ?? '—' }, { label: 'Address', value: c.address ?? '—' }, { label: 'Tax status', value: c.tax_status ?? '—' }, { label: 'Fulfilment', value: c.fulfilment_policy ?? '—' }, { label: 'Last credit review', value: formatDate(c.credit?.reviewed_at ?? null) }]} />
          {(c.contacts ?? []).length > 0 && (
            <Card title="Contacts">
              <table className="ui-table"><tbody>
                {(c.contacts ?? []).map((ct) => (<tr key={ct.id}><td className="font-semibold">{ct.name}{ct.is_primary && <span className="ml-1 text-[10px] text-[var(--text-muted)]">primary</span>}</td><td>{ct.role ?? '—'}</td><td>{ct.phone ?? '—'}</td><td>{ct.email ?? '—'}</td></tr>))}
              </tbody></table>
            </Card>
          )}
        </div>
      )}
    </Drawer>
  )
}

function CustomerCreateDrawer({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (c: Customer) => void }) {
  const queryClient = useQueryClient()
  const tiers = useCustomerTiers()
  const [form, setForm] = useState({ code: '', name: '', customer_type: 'RETAIL_PHARMACY', tier_id: '', tax_status: 'STANDARD', payment_terms_days: '0', fulfilment_policy: 'PARTIAL', phone: '', email: '', address: '', credit_limit: '0' })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<Customer>('/api/customers', { ...form, tier_id: form.tier_id || null, phone: form.phone || null, email: form.email || null, address: form.address || null, payment_terms_days: Number(form.payment_terms_days || 0) }),
    onSuccess: (c) => {
      toast.success(`Customer ${c.code} created`)
      queryClient.invalidateQueries({ queryKey: ['customers'] })
      onClose()
      onCreated(c)
    },
  })
  const err = create.isError ? getApiError(create.error) : null

  return (
    <Drawer open={open} onClose={onClose} title="New customer" width={640}>
      <div className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} onChange={(e) => set({ code: e.target.value })} /></Field>
          <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
          <Field label="Type" required>
            <Select value={form.customer_type} onChange={(e) => set({ customer_type: e.target.value })}>{TYPES.map((t) => (<option key={t} value={t}>{titleCase(t)}</option>))}</Select>
          </Field>
          <Field label="Tier">
            <Select value={form.tier_id} onChange={(e) => set({ tier_id: e.target.value })}><option value="">None</option>{(tiers.data ?? []).map((t) => (<option key={t.id} value={t.id}>{t.code} · {t.name}</option>))}</Select>
          </Field>
          <Field label="Tax status"><Select value={form.tax_status} onChange={(e) => set({ tax_status: e.target.value })}>{['STANDARD', 'EXEMPT', 'ZERO_RATED', 'WITHHOLDING_AGENT'].map((t) => (<option key={t} value={t}>{titleCase(t)}</option>))}</Select></Field>
          <Field label="Fulfilment policy"><Select value={form.fulfilment_policy} onChange={(e) => set({ fulfilment_policy: e.target.value })}>{['PARTIAL', 'COMPLETE', 'CANCEL_SHORTFALL'].map((t) => (<option key={t} value={t}>{titleCase(t)}</option>))}</Select></Field>
          <Field label="Payment terms (days)"><Input inputMode="numeric" className="tabular" value={form.payment_terms_days} onChange={(e) => set({ payment_terms_days: e.target.value.replace(/\D/g, '') })} /></Field>
          <Field label="Credit limit (KES)"><Input inputMode="decimal" className="tabular" value={form.credit_limit} onChange={(e) => set({ credit_limit: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
          <Field label="Phone"><Input value={form.phone} onChange={(e) => set({ phone: e.target.value })} /></Field>
          <Field label="Email" error={err?.errors.email?.[0]}><Input type="email" value={form.email} onChange={(e) => set({ email: e.target.value })} /></Field>
          <Field label="Address" className="col-span-2"><Input value={form.address} onChange={(e) => set({ address: e.target.value })} /></Field>
        </div>
        {err && !Object.keys(err.errors).length && <InlineError error={create.error} />}
        <div className="flex justify-end gap-2">
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="primary" disabled={!form.code || !form.name || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Saving…' : 'Create customer'}</Button>
        </div>
      </div>
    </Drawer>
  )
}
