import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Building2, MapPin, Pencil, UserSquare2, Wallet } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { DeleteRecordButton } from '../../components/DeleteRecordButton'
import { useCustomer } from '../../components/CustomerPicker'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, DrawerFooter, Field, FormSection, Input, PrimaryAction, Select } from '../../components/ui/primitives'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { dIsNeg } from '../../lib/decimal'
import { titleCase } from '../../lib/format'
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
  const [sort, setSort] = useState<{ key: string; dir: 'asc' | 'desc' } | null>(null)
  const [creating, setCreating] = useState(false)
  const dq = useDebounced(q, 250)
  const selectedId = params.get('customer')

  const list = useQuery({
    queryKey: ['customers', 'list', dq, page, sort?.key, sort?.dir],
    queryFn: () => apiGet<Paginated<Customer>>('/api/customers', { q: dq, page, per_page: 50, sort_by: sort?.key, sort_dir: sort?.dir }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<Customer>[] = [
    { key: 'code', header: 'Code', sortKey: 'code', render: (c) => <span className="font-semibold tabular">{c.code}</span> },
    { key: 'name', header: 'Name', sortKey: 'name', render: (c) => <span className="font-bold text-slate-900">{c.name}</span> },
    { key: 'type', header: 'Type', sortKey: 'customer_type', render: (c) => titleCase(c.customer_type) },
    { key: 'tier', header: 'Tier', sortable: false, render: (c) => <span className="font-bold text-blue-600">{c.tier?.code ?? '—'}</span> },
    { key: 'phone', header: 'Phone', sortable: false, render: (c) => c.phone ?? '—' },
    { key: 'limit', header: 'Credit limit', sortable: false, align: 'right', render: (c) => <MoneyCell value={c.credit?.credit_limit ?? '0'} /> },
    { key: 'balance', header: 'Balance', sortable: false, align: 'right', render: (c) => <MoneyCell value={c.credit?.current_balance ?? '0'} /> },
    { key: 'available', header: 'Available', sortable: false, align: 'right', render: (c) => <MoneyCell value={c.available_credit ?? '0'} className={dIsNeg(c.available_credit ?? '0') ? 'font-bold text-rose-600' : ''} /> },
    { key: 'status', header: 'Status', sortable: false, render: (c) => (c.credit?.on_hold ? <StatusBadge status="ON_HOLD" label="Credit hold" /> : <StatusBadge status={c.is_active ? 'ACTIVE' : 'INACTIVE'} />) },
    {
      key: 'delete',
      header: '',
      sortable: false,
      align: 'right',
      render: (c) => (
        <DeleteRecordButton type="customers" id={c.id} label={`${c.code} · ${c.name}`} permission="customer.manage" invalidateKeys={[['customers']]} />
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        parent="Commerce & Stock"
        title="Customers & Accounts"
        subtitle="Manage wholesale hospital clients, retail pharmacies, pricing tiers, and credit facilities"
        actions={
          canManage ? (
            <div id="tour-customers-new">
              <PrimaryAction onClick={() => setCreating(true)}>
                New Customer
              </PrimaryAction>
            </div>
          ) : null
        }
      />
      <FilterBar>
        <div id="tour-customers-search" className="w-full sm:w-80">
          <Field label="Search Customers">
            <Input placeholder="Search by name, account code, or phone…" value={q} onChange={(e) => setQ(e.target.value)} />
          </Field>
        </div>
      </FilterBar>
      <div id="tour-customers-table" className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data?.data}
          rowKey={(c) => c.id}
          sort={sort}
          onSortChange={setSort}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          onRowClick={(c) => setParams({ customer: c.id })}
          selectedKey={selectedId}
          emptyTitle="No customers"
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <CustomerDetailDrawer id={selectedId} onClose={() => setParams({})} />
      <CustomerCreateDrawer open={creating} onClose={() => setCreating(false)} onCreated={(c) => setParams({ customer: c.id })} />
    </Page>
  )
}

function CustomerDetailDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const [editing, setEditing] = useState(false)
  const canManage = usePermission('customer.manage')
  const customer = useCustomer(id)
  const c = customer.data

  const handleClose = () => {
    setEditing(false)
    onClose()
  }

  return (
    <Drawer
      open={!!id}
      onClose={handleClose}
      title={c ? (editing ? `Edit ${c.name}` : c.name) : 'Customer Details'}
      subtitle={c ? `${c.code} · ${titleCase(c.customer_type)} · ${c.tier?.name ?? 'Standard Tier'}` : undefined}
      width={720}
    >
      {customer.isLoading && <LoadingSkeleton />}
      {customer.isError && <InlineError error={customer.error} />}
      {c && !editing && (
        <div className="space-y-4">
          <div className="flex items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <StatusBadge status={c.is_active ? 'ACTIVE' : 'INACTIVE'} />
              {c.credit?.on_hold && <StatusBadge status="ON_HOLD" label="Credit hold (over limit)" />}
            </div>
            {canManage && (
              <Button size="sm" onClick={() => setEditing(true)} className="inline-flex items-center gap-1.5">
                <Pencil className="w-3.5 h-3.5" />
                <span>Edit Customer</span>
              </Button>
            )}
          </div>

          <FormSection title="Account & Credit Terms" icon={Wallet}>
            <DescriptionList
              items={[
                { label: 'Code', value: <span className="tabular font-mono font-bold">{c.code}</span> },
                { label: 'Tier', value: c.tier?.name ? `${c.tier.name} (${c.tier.code})` : '—' },
                { label: 'Tax status', value: titleCase(c.tax_status) },
                { label: 'Payment terms', value: `${c.payment_terms_days ?? 0} days` },
                { label: 'Credit limit', value: <MoneyCell value={c.credit?.credit_limit ?? '0'} /> },
                { label: 'Current balance', value: <MoneyCell value={c.credit?.current_balance ?? '0'} /> },
                { label: 'Available credit', value: <MoneyCell value={c.available_credit ?? '0'} className={dIsNeg(c.available_credit ?? '0') ? 'text-rose-600 font-bold' : ''} /> },
                { label: 'Open order exposure', value: <MoneyCell value={c.open_order_exposure ?? '0'} /> },
              ]}
            />
          </FormSection>

          <FormSection title="Contact & Address" icon={Building2}>
            <DescriptionList
              items={[
                { label: 'Phone', value: c.phone ?? '—' },
                { label: 'Email', value: c.email ? <a href={`mailto:${c.email}`} className="text-blue-600 hover:underline">{c.email}</a> : '—' },
                { label: 'Physical address', value: c.address ?? '—' },
                { label: 'Fulfilment policy', value: titleCase(c.fulfilment_policy) },
              ]}
            />
          </FormSection>

          <div className="flex items-center justify-end pt-2">
            <Link
              to={`/sell/statements?customer=${c.id}`}
              className="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-700 font-bold text-xs transition-colors"
            >
              <span>View Statement of Account →</span>
            </Link>
          </div>
        </div>
      )}
      {c && editing && (
        <CustomerEditForm
          customer={c}
          onDone={() => setEditing(false)}
          onCancel={() => setEditing(false)}
        />
      )}
    </Drawer>
  )
}

function CustomerEditForm({
  customer,
  onDone,
  onCancel,
}: {
  customer: Customer
  onDone: () => void
  onCancel: () => void
}) {
  const queryClient = useQueryClient()
  const tiers = useCustomerTiers()
  const [form, setForm] = useState({
    name: customer.name ?? '',
    customer_type: customer.customer_type ?? 'RETAIL_PHARMACY',
    tier_id: customer.tier_id ?? customer.tier?.id ?? '',
    tax_status: customer.tax_status ?? 'STANDARD',
    payment_terms_days: String(customer.payment_terms_days ?? 0),
    fulfilment_policy: customer.fulfilment_policy ?? 'PARTIAL',
    phone: customer.phone ?? '',
    email: customer.email ?? '',
    address: customer.address ?? '',
    is_active: customer.is_active,
  })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })

  const update = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPatch<Customer>(`/api/customers/${customer.id}`, {
        ...form,
        tier_id: form.tier_id || null,
        phone: form.phone || null,
        email: form.email || null,
        address: form.address || null,
        payment_terms_days: Number(form.payment_terms_days || 0),
      }),
    onSuccess: (updated) => {
      toast.success(`Customer ${updated.code} updated`)
      queryClient.invalidateQueries({ queryKey: ['customers'] })
      onDone()
    },
  })
  const err = update.isError ? getApiError(update.error) : null

  return (
    <div className="space-y-4">
      <FormSection
        title="Account Classification"
        description="Update account legal name, client type, and pricing tier"
        icon={UserSquare2}
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Customer / Entity Name" required error={err?.errors.name?.[0]}>
            <Input value={form.name} onChange={(e) => set({ name: e.target.value })} />
          </Field>
          <Field label="Customer Type" required>
            <Select value={form.customer_type} onChange={(e) => set({ customer_type: e.target.value })}>
              {TYPES.map((t) => (
                <option key={t} value={t}>{titleCase(t)}</option>
              ))}
            </Select>
          </Field>
          <Field label="Pricing Tier">
            <Select value={form.tier_id} onChange={(e) => set({ tier_id: e.target.value })}>
              <option value="">Default Retail Tier</option>
              {(tiers.data ?? []).map((t) => (
                <option key={t.id} value={t.id}>{t.code} · {t.name}</option>
              ))}
            </Select>
          </Field>
          <Field label="Tax Status">
            <Select value={form.tax_status} onChange={(e) => set({ tax_status: e.target.value })}>
              {['STANDARD', 'EXEMPT', 'ZERO_RATED', 'WITHHOLDING_AGENT'].map((t) => (
                <option key={t} value={t}>{titleCase(t)}</option>
              ))}
            </Select>
          </Field>
        </div>
      </FormSection>

      <FormSection
        title="Commercial Terms & Status"
        description="Payment terms, fulfilment rules, and account active state"
        icon={Wallet}
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Payment Terms (Days)" hint="Net days before invoice becomes overdue">
            <Input inputMode="numeric" className="tabular font-bold" value={form.payment_terms_days} onChange={(e) => set({ payment_terms_days: e.target.value.replace(/\D/g, '') })} />
          </Field>
          <Field label="Fulfilment Policy">
            <Select value={form.fulfilment_policy} onChange={(e) => set({ fulfilment_policy: e.target.value })}>
              {['PARTIAL', 'COMPLETE', 'CANCEL_SHORTFALL'].map((t) => (
                <option key={t} value={t}>{titleCase(t)}</option>
              ))}
            </Select>
          </Field>
          <div className="sm:col-span-2 pt-2">
            <label className="flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer">
              <input
                type="checkbox"
                checked={form.is_active}
                onChange={(e) => set({ is_active: e.target.checked })}
                className="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
              />
              <span>Active Customer (Eligible for sales orders and checkout)</span>
            </label>
          </div>
        </div>
      </FormSection>

      <FormSection
        title="Contact & Location"
        description="Direct contact number, invoice email, and billing address"
        icon={MapPin}
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Phone Number">
            <Input placeholder="+254 7..." value={form.phone} onChange={(e) => set({ phone: e.target.value })} />
          </Field>
          <Field label="Email Address" error={err?.errors.email?.[0]}>
            <Input type="email" placeholder="accounts@hospital.co.ke" value={form.email} onChange={(e) => set({ email: e.target.value })} />
          </Field>
          <Field label="Physical / Postal Address" className="col-span-1 sm:col-span-2">
            <Input placeholder="e.g. Along Lodwar-Kitale Rd, Turkana County" value={form.address} onChange={(e) => set({ address: e.target.value })} />
          </Field>
        </div>
      </FormSection>

      {err && !Object.keys(err.errors).length && <InlineError error={update.error} />}

      <DrawerFooter
        onCancel={onCancel}
        onSubmit={() => update.mutate()}
        submitLabel="Save Changes"
        isSubmitting={update.isPending}
        disabled={!form.name || update.isPending}
      />
    </div>
  )
}

function CustomerCreateDrawer({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (c: Customer) => void }) {
  const queryClient = useQueryClient()
  const tiers = useCustomerTiers()
  const [form, setForm] = useState({
    code: '',
    name: '',
    customer_type: 'RETAIL_PHARMACY',
    tier_id: '',
    tax_status: 'STANDARD',
    payment_terms_days: '30',
    fulfilment_policy: 'PARTIAL',
    phone: '',
    email: '',
    address: '',
    credit_limit: '100000',
  })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<Customer>('/api/customers', {
        ...form,
        tier_id: form.tier_id || null,
        phone: form.phone || null,
        email: form.email || null,
        address: form.address || null,
        payment_terms_days: Number(form.payment_terms_days || 0),
      }),
    onSuccess: (c) => {
      toast.success(`Customer ${c.code} created`)
      queryClient.invalidateQueries({ queryKey: ['customers'] })
      onClose()
      onCreated(c)
    },
  })
  const err = create.isError ? getApiError(create.error) : null

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="Create New Customer"
      subtitle="Register an institutional client, chemist, or hospital account"
      width={720}
    >
      <div className="space-y-4">
        {/* Section 1: Account Classification */}
        <FormSection
          title="Account Classification"
          description="Identify account code, legal name, and commercial tier"
          icon={UserSquare2}
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Account Code" required error={err?.errors.code?.[0]} hint="e.g. HOSP-009 or CUST-104">
              <Input placeholder="CUST-001" value={form.code} onChange={(e) => set({ code: e.target.value })} />
            </Field>
            <Field label="Customer / Entity Name" required error={err?.errors.name?.[0]}>
              <Input placeholder="e.g. Lodwar Referral Hospital" value={form.name} onChange={(e) => set({ name: e.target.value })} />
            </Field>
            <Field label="Customer Type" required>
              <Select value={form.customer_type} onChange={(e) => set({ customer_type: e.target.value })}>
                {TYPES.map((t) => (
                  <option key={t} value={t}>{titleCase(t)}</option>
                ))}
              </Select>
            </Field>
            <Field label="Pricing Tier">
              <Select value={form.tier_id} onChange={(e) => set({ tier_id: e.target.value })}>
                <option value="">Default Retail Tier</option>
                {(tiers.data ?? []).map((t) => (
                  <option key={t.id} value={t.id}>{t.code} · {t.name}</option>
                ))}
              </Select>
            </Field>
          </div>
        </FormSection>

        {/* Section 2: Credit Facility & Terms */}
        <FormSection
          title="Credit Facility & Terms"
          description="Configure authorized credit limit and payment terms"
          icon={Wallet}
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Credit Limit (KES)" hint="Maximum allowed unpaid debt">
              <Input inputMode="decimal" className="tabular font-bold" value={form.credit_limit} onChange={(e) => set({ credit_limit: e.target.value.replace(/[^\d.]/g, '') })} />
            </Field>
            <Field label="Payment Terms (Days)" hint="Net days before invoice becomes overdue">
              <Input inputMode="numeric" className="tabular font-bold" value={form.payment_terms_days} onChange={(e) => set({ payment_terms_days: e.target.value.replace(/\D/g, '') })} />
            </Field>
            <Field label="Tax Status">
              <Select value={form.tax_status} onChange={(e) => set({ tax_status: e.target.value })}>
                {['STANDARD', 'EXEMPT', 'ZERO_RATED', 'WITHHOLDING_AGENT'].map((t) => (
                  <option key={t} value={t}>{titleCase(t)}</option>
                ))}
              </Select>
            </Field>
            <Field label="Fulfilment Policy">
              <Select value={form.fulfilment_policy} onChange={(e) => set({ fulfilment_policy: e.target.value })}>
                {['PARTIAL', 'COMPLETE', 'CANCEL_SHORTFALL'].map((t) => (
                  <option key={t} value={t}>{titleCase(t)}</option>
                ))}
              </Select>
            </Field>
          </div>
        </FormSection>

        {/* Section 3: Contact Details */}
        <FormSection
          title="Contact & Location"
          description="Phone, email, and billing address"
          icon={MapPin}
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Phone Number">
              <Input placeholder="+254 7..." value={form.phone} onChange={(e) => set({ phone: e.target.value })} />
            </Field>
            <Field label="Email Address" error={err?.errors.email?.[0]}>
              <Input type="email" placeholder="accounts@hospital.co.ke" value={form.email} onChange={(e) => set({ email: e.target.value })} />
            </Field>
            <Field label="Physical / Postal Address" className="col-span-1 sm:col-span-2">
              <Input placeholder="e.g. Along Lodwar-Kitale Rd, Turkana County" value={form.address} onChange={(e) => set({ address: e.target.value })} />
            </Field>
          </div>
        </FormSection>

        {err && !Object.keys(err.errors).length && <InlineError error={create.error} />}

        <DrawerFooter
          onCancel={onClose}
          onSubmit={() => create.mutate()}
          submitLabel="Create Customer Account"
          isSubmitting={create.isPending}
          disabled={!form.code || !form.name}
        />
      </div>
    </Drawer>
  )
}
