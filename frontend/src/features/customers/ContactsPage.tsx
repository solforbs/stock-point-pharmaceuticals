import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CustomerPicker } from '../../components/CustomerPicker'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime, titleCase, todayIso } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Customer, Paginated } from '../../lib/types'

type CustomerRef = { id: string; code: string; name: string }

type CustomerContactRow = {
  id: string
  customer_id: string
  name: string
  role: string | null
  email: string | null
  phone: string | null
  is_primary: boolean
  customer?: CustomerRef | null
}

const CHANNELS = ['CALL', 'VISIT', 'EMAIL', 'SMS', 'WHATSAPP', 'OTHER'] as const
type Channel = (typeof CHANNELS)[number]

type CustomerInteractionRow = {
  id: string
  customer_id: string
  contact_id: string | null
  channel: Channel
  summary: string
  follow_up_date: string | null
  follow_up_done: boolean
  occurred_at: string
  customer?: CustomerRef | null
  contact?: { id: string; name: string; role: string | null; phone: string | null } | null
  user?: { id: number; name: string } | null
}

/** Customer contacts and the communication log with follow-ups. */
export default function ContactsPage() {
  const canView = usePermission('sale.view')
  const [params, setParams] = useSearchParams()
  const [customer, setCustomer] = useState<Customer | null>(null)
  const tab = params.get('tab') === 'interactions' ? 'interactions' : 'contacts'

  if (!canView) return <NoAccess permission="sale.view" />

  return (
    <Page>
      <PageHeader parent="Customers" title="Contacts" subtitle="The people you deal with at each customer, and a log of every call, visit and message with follow-ups that stay due until done." />
      <div className="flex gap-1 mb-3">
        {(['contacts', 'interactions'] as const).map((t) => (
          <button key={t} type="button" onClick={() => setParams(t === 'contacts' ? {} : { tab: t })} className={`h-8 px-3 rounded-md text-[12px] font-semibold ${tab === t ? 'bg-[var(--color-navy)] text-white' : 'bg-[var(--surface-2)] text-[var(--text-secondary)]'}`}>
            {t === 'contacts' ? 'Contacts' : 'Interactions'}
          </button>
        ))}
      </div>
      <FilterBar>
        <Field label="Customer" className="w-96"><CustomerPicker value={customer} onChange={setCustomer} placeholder="All customers — search to filter…" /></Field>
      </FilterBar>
      {tab === 'contacts' ? <ContactsTab customer={customer} /> : <InteractionsTab customer={customer} />}
    </Page>
  )
}

function ContactsTab({ customer }: { customer: Customer | null }) {
  const canManage = usePermission('customer.manage')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<CustomerContactRow | null>(null)
  const [creating, setCreating] = useState(false)
  const dq = useDebounced(q, 250)

  const list = useQuery({
    queryKey: ['customer-contacts', customer?.id ?? '', dq, page],
    queryFn: () => apiGet<Paginated<CustomerContactRow>>('/api/customer-contacts', { customer_id: customer?.id, q: dq, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<CustomerContactRow>[] = [
    { key: 'name', header: 'Name', render: (c) => <span className="font-semibold">{c.name}</span>, sortValue: (c) => c.name },
    { key: 'role', header: 'Role', render: (c) => c.role ?? '—', sortValue: (c) => c.role ?? '' },
    { key: 'customer', header: 'Customer', render: (c) => <>{c.customer?.name}<div className="text-[10.5px] text-[var(--text-muted)] tabular">{c.customer?.code}</div></>, sortValue: (c) => c.customer?.name ?? '' },
    { key: 'phone', header: 'Phone', render: (c) => (c.phone ? <a href={`tel:${c.phone}`} className="tabular underline" onClick={(e) => e.stopPropagation()}>{c.phone}</a> : '—') },
    { key: 'email', header: 'Email', render: (c) => (c.email ? <a href={`mailto:${c.email}`} className="underline" onClick={(e) => e.stopPropagation()}>{c.email}</a> : '—') },
    { key: 'primary', header: '', render: (c) => (c.is_primary ? <StatusBadge status="PRIMARY" tone="blue" label="Primary" /> : null) },
  ]

  return (
    <>
      <div className="flex items-end justify-between gap-3 mb-3">
        <Field label="Search" className="w-72"><Input placeholder="Name, role, phone or email" value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} /></Field>
        {canManage && <Button variant="primary" onClick={() => setCreating(true)}>New contact</Button>}
      </div>
      <div className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data?.data}
          rowKey={(c) => c.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          onRowClick={canManage ? (c) => setEditing(c) : undefined}
          selectedKey={editing?.id}
          emptyTitle={customer ? `No contacts at ${customer.name}` : 'No contacts yet'}
          emptyHint={canManage ? 'Add the buyer, the accounts person and whoever signs for deliveries.' : undefined}
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <Drawer open={creating} onClose={() => setCreating(false)} title="New contact">
        {creating && <ContactForm defaultCustomer={customer} onDone={() => setCreating(false)} onCancel={() => setCreating(false)} />}
      </Drawer>
      <Drawer open={!!editing} onClose={() => setEditing(null)} title={editing?.name ?? ''} subtitle={editing?.customer?.name}>
        {editing && <ContactForm contact={editing} defaultCustomer={null} onDone={() => setEditing(null)} onCancel={() => setEditing(null)} />}
      </Drawer>
    </>
  )
}

function ContactForm({ contact, defaultCustomer, onDone, onCancel }: { contact?: CustomerContactRow; defaultCustomer: Customer | null; onDone: () => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [customer, setCustomer] = useState<Customer | null>(defaultCustomer)
  const [form, setForm] = useState({ name: contact?.name ?? '', role: contact?.role ?? '', phone: contact?.phone ?? '', email: contact?.email ?? '', is_primary: contact?.is_primary ?? false })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = { name: form.name, role: form.role || null, phone: form.phone || null, email: form.email || null, is_primary: form.is_primary }
      return contact ? apiPatch<CustomerContactRow>(`/api/customer-contacts/${contact.id}`, body) : apiPost<CustomerContactRow>('/api/customer-contacts', { ...body, customer_id: customer?.id })
    },
    onSuccess: (c) => {
      toast.success(contact ? `${c.name} updated` : `${c.name} added`)
      queryClient.invalidateQueries({ queryKey: ['customer-contacts'] })
      queryClient.invalidateQueries({ queryKey: ['customers'] })
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-4">
      {!contact && <Field label="Customer" required error={err?.errors.customer_id?.[0]}><CustomerPicker value={customer} onChange={setCustomer} /></Field>}
      <div className="grid grid-cols-2 gap-3">
        <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
        <Field label="Role" error={err?.errors.role?.[0]}><Input value={form.role} onChange={(e) => set({ role: e.target.value })} placeholder="Buyer, Accounts, Pharmacist in charge" /></Field>
        <Field label="Phone" error={err?.errors.phone?.[0]}><Input value={form.phone} onChange={(e) => set({ phone: e.target.value })} /></Field>
        <Field label="Email" error={err?.errors.email?.[0]}><Input type="email" value={form.email} onChange={(e) => set({ email: e.target.value })} /></Field>
      </div>
      <label className="flex items-center gap-2 text-[12px]"><input type="checkbox" checked={form.is_primary} onChange={(e) => set({ is_primary: e.target.checked })} /> Primary contact for this customer</label>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={!form.name || (!contact && !customer) || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : contact ? 'Save changes' : 'Add contact'}</Button>
      </div>
    </div>
  )
}

function InteractionsTab({ customer }: { customer: Customer | null }) {
  const queryClient = useQueryClient()
  const canManage = usePermission('customer.manage')
  const [dueOnly, setDueOnly] = useState(false)
  const [channel, setChannel] = useState('')
  const [page, setPage] = useState(1)
  const [logging, setLogging] = useState(false)

  const list = useQuery({
    queryKey: ['customer-interactions', customer?.id ?? '', dueOnly, channel, page],
    queryFn: () => apiGet<Paginated<CustomerInteractionRow>>('/api/customer-interactions', { customer_id: customer?.id, follow_ups_due: dueOnly ? 1 : undefined, channel: channel || undefined, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const markDone = useMutation({
    mutationFn: (row: CustomerInteractionRow) => apiPatch<CustomerInteractionRow>(`/api/customer-interactions/${row.id}`, { follow_up_done: !row.follow_up_done }),
    onSuccess: (r) => {
      toast.success(r.follow_up_done ? 'Follow-up done' : 'Follow-up reopened')
      queryClient.invalidateQueries({ queryKey: ['customer-interactions'] })
    },
  })

  const today = todayIso()
  const columns: Column<CustomerInteractionRow>[] = [
    { key: 'when', header: 'When', render: (r) => <span className="tabular">{formatDateTime(r.occurred_at)}</span>, sortValue: (r) => r.occurred_at },
    { key: 'customer', header: 'Customer', render: (r) => <>{r.customer?.name}{r.contact && <div className="text-[10.5px] text-[var(--text-muted)]">with {r.contact.name}</div>}</>, sortValue: (r) => r.customer?.name ?? '' },
    { key: 'channel', header: 'Channel', render: (r) => <StatusBadge status={r.channel} tone="slate" label={titleCase(r.channel)} /> },
    { key: 'summary', header: 'Summary', render: (r) => <span className="whitespace-pre-line">{r.summary}</span> },
    { key: 'by', header: 'Logged by', render: (r) => r.user?.name ?? '—' },
    {
      key: 'follow',
      header: 'Follow-up',
      render: (r) => {
        if (!r.follow_up_date) return <span className="text-[var(--text-muted)]">—</span>
        if (r.follow_up_done) return <StatusBadge status="DONE" tone="green" label={`Done · ${formatDate(r.follow_up_date)}`} />
        const overdue = r.follow_up_date.slice(0, 10) <= today
        return <StatusBadge status="DUE" tone={overdue ? 'red' : 'amber'} label={`${overdue ? 'Due' : 'Scheduled'} ${formatDate(r.follow_up_date)}`} />
      },
      sortValue: (r) => r.follow_up_date ?? '',
    },
    {
      key: 'action',
      header: '',
      align: 'right',
      render: (r) => (canManage && r.follow_up_date ? (
        <Button size="sm" variant={r.follow_up_done ? 'ghost' : 'success'} disabled={markDone.isPending} onClick={(e) => { e.stopPropagation(); markDone.mutate(r) }}>{r.follow_up_done ? 'Reopen' : 'Mark done'}</Button>
      ) : null),
    },
  ]

  return (
    <>
      <div className="flex items-end justify-between gap-3 mb-3">
        <div className="flex items-end gap-3">
          <Field label="Channel" className="w-40">
            <Select value={channel} onChange={(e) => { setChannel(e.target.value); setPage(1) }}>
              <option value="">All channels</option>
              {CHANNELS.map((c) => (<option key={c} value={c}>{titleCase(c)}</option>))}
            </Select>
          </Field>
          <label className="flex items-center gap-2 text-[12px] pb-2"><input type="checkbox" checked={dueOnly} onChange={(e) => { setDueOnly(e.target.checked); setPage(1) }} /> Follow-ups due (today or overdue)</label>
        </div>
        {canManage && <Button variant="primary" onClick={() => setLogging(true)}>Log interaction</Button>}
      </div>
      <div className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data?.data}
          rowKey={(r) => r.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          emptyTitle={dueOnly ? 'No follow-ups due' : 'No interactions logged'}
          emptyHint={dueOnly ? 'Everything scheduled up to today has been done.' : canManage ? 'Log calls, visits and messages so the next person knows what was promised.' : undefined}
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <Drawer open={logging} onClose={() => setLogging(false)} title="Log interaction">
        {logging && <InteractionForm defaultCustomer={customer} onDone={() => setLogging(false)} onCancel={() => setLogging(false)} />}
      </Drawer>
    </>
  )
}

function InteractionForm({ defaultCustomer, onDone, onCancel }: { defaultCustomer: Customer | null; onDone: () => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [customer, setCustomer] = useState<Customer | null>(defaultCustomer)
  const [contactId, setContactId] = useState('')
  const [channel, setChannel] = useState<Channel>('CALL')
  const [summary, setSummary] = useState('')
  const [followUp, setFollowUp] = useState('')

  const contacts = useQuery({
    queryKey: ['customer-contacts', customer?.id ?? '', '', 1],
    queryFn: () => apiGet<Paginated<CustomerContactRow>>('/api/customer-contacts', { customer_id: customer?.id, page: 1, per_page: 50 }),
    enabled: !!customer,
  })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<CustomerInteractionRow>('/api/customer-interactions', { customer_id: customer?.id, contact_id: contactId || null, channel, summary, follow_up_date: followUp || null }),
    onSuccess: () => {
      toast.success('Interaction logged')
      queryClient.invalidateQueries({ queryKey: ['customer-interactions'] })
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-4">
      <Field label="Customer" required error={err?.errors.customer_id?.[0]}><CustomerPicker value={customer} onChange={(c) => { setCustomer(c); setContactId('') }} /></Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Contact" error={err?.errors.contact_id?.[0]}>
          <Select value={contactId} onChange={(e) => setContactId(e.target.value)} disabled={!customer}>
            <option value="">—</option>
            {(contacts.data?.data ?? []).map((c) => (<option key={c.id} value={c.id}>{c.name}{c.role ? ` · ${c.role}` : ''}</option>))}
          </Select>
        </Field>
        <Field label="Channel" required error={err?.errors.channel?.[0]}>
          <Select value={channel} onChange={(e) => setChannel(e.target.value as Channel)}>
            {CHANNELS.map((c) => (<option key={c} value={c}>{titleCase(c)}</option>))}
          </Select>
        </Field>
      </div>
      <Field label="Summary" required error={err?.errors.summary?.[0]}><Textarea rows={4} value={summary} onChange={(e) => setSummary(e.target.value)} placeholder="What was discussed or promised" /></Field>
      <Field label="Follow up on" hint="Shows under follow-ups due from this date until marked done." error={err?.errors.follow_up_date?.[0]}><Input type="date" value={followUp} onChange={(e) => setFollowUp(e.target.value)} /></Field>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={!customer || summary.trim().length < 3 || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Log interaction'}</Button>
      </div>
    </div>
  )
}
