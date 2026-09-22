import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Building2, CreditCard, Inbox, LogOut, Plus, ShieldCheck, Tags } from 'lucide-react'
import { useState } from 'react'
import { Link, useNavigate, useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, DrawerFooter, Field, Input, PrimaryAction, Select, Textarea } from '../../components/ui/primitives'
import { forgetCachedUser, useCurrentUser } from '../../hooks/useCurrentUser'
import { api, apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { formatMoney } from '../../lib/money'
import { toast } from '../../lib/toast'
import type { InvitationRow, Paginated, Plan, SubscriptionPaymentRow, TenantDetail, TenantRequestRow, TenantRow } from '../../lib/types'
import { ACCESS_LABEL } from '../billing/accessState'

type Tab = 'requests' | 'tenants' | 'plans' | 'payments'

const TABS: { key: Tab; label: string; icon: typeof Inbox }[] = [
  { key: 'requests', label: 'Quote requests', icon: Inbox },
  { key: 'tenants', label: 'Institutions', icon: Building2 },
  { key: 'plans', label: 'Plans', icon: Tags },
  { key: 'payments', label: 'Payments', icon: CreditCard },
]

/**
 * The platform console: quote requests, institutions (trials, suspension,
 * complimentary accounts, activation links), the plans on offer and the
 * payments received. It manages institutions, never their business data.
 */
export default function PlatformPage() {
  const [params, setParams] = useSearchParams()
  const tab = (params.get('tab') as Tab) || 'requests'
  const { data: user } = useCurrentUser()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const logout = useMutation({
    mutationFn: () => api.post('/auth/logout'),
    onSuccess: () => {
      forgetCachedUser()
      queryClient.clear()
      queryClient.setQueryData(['auth', 'user'], null)
      navigate('/login')
    },
  })

  return (
    <div className="min-h-svh bg-[#f8fafc]">
      <header className="h-14 bg-white border-b border-slate-200 flex items-center gap-3 px-4 sm:px-6">
        <div className="w-8 h-8 rounded-lg bg-slate-900 flex items-center justify-center text-white"><ShieldCheck size={16} /></div>
        <div className="leading-tight">
          <div className="font-display font-bold text-slate-900">Platform console</div>
          <div className="text-[11px] text-slate-500">{user?.name}</div>
        </div>
        <div className="ml-auto flex items-center gap-2">
          {user?.organisation && (
            <Link to="/dashboard" className="text-xs font-semibold text-blue-600 hover:underline">Open {user.organisation.name}</Link>
          )}
          <Button size="sm" onClick={() => logout.mutate()}><LogOut size={13} className="mr-1" /> Sign out</Button>
        </div>
      </header>

      <div className="p-4 sm:p-6 max-w-[1500px] mx-auto space-y-4">
        <div className="flex items-center gap-1.5 p-1 bg-slate-100/80 rounded-xl border border-slate-200/60 w-fit flex-wrap">
          {TABS.map(({ key, label, icon: Icon }) => (
            <button
              key={key}
              type="button"
              onClick={() => setParams({ tab: key })}
              className={`h-8 px-4 rounded-lg text-xs font-bold transition-all inline-flex items-center gap-1.5 cursor-pointer ${tab === key ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'}`}
            >
              <Icon size={13} /> {label}
            </button>
          ))}
        </div>
        {tab === 'requests' && <RequestsTab />}
        {tab === 'tenants' && <TenantsTab />}
        {tab === 'plans' && <PlansTab />}
        {tab === 'payments' && <PaymentsTab />}
      </div>
    </div>
  )
}

// ---------------------------------------------------------------- Quote requests

function RequestsTab() {
  const queryClient = useQueryClient()
  const [status, setStatus] = useState('PENDING')
  const [page, setPage] = useState(1)
  const [rejecting, setRejecting] = useState<TenantRequestRow | null>(null)
  const [approving, setApproving] = useState<TenantRequestRow | null>(null)
  const list = useQuery({
    queryKey: ['platform', 'requests', status, page],
    queryFn: () => apiGet<Paginated<TenantRequestRow>>('/api/platform/quote-requests', { status, page }),
    placeholderData: (prev) => prev,
  })
  const refresh = () => queryClient.invalidateQueries({ queryKey: ['platform'] })

  const approve = useMutation({
    mutationFn: (id: string) => apiPost(`/api/platform/quote-requests/${id}/approve`),
    onSuccess: () => {
      toast.success('Approved', 'The registration link has been emailed.')
      setApproving(null)
      refresh()
    },
  })
  const reject = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) => apiPost(`/api/platform/quote-requests/${id}/reject`, { reason }),
    onSuccess: () => {
      toast.success('Rejected', 'The requester has been told why.')
      setRejecting(null)
      refresh()
    },
  })

  const columns: Column<TenantRequestRow>[] = [
    { key: 'institution', header: 'Institution', render: (r) => <div><div className="font-semibold">{r.institution_name}</div><div className="text-[11px] text-slate-500">{r.town ?? ''}</div></div> },
    { key: 'contact', header: 'Contact', render: (r) => <div><div>{r.contact_name}</div><div className="text-[11px] text-slate-500">{r.email}{r.phone ? ` · ${r.phone}` : ''}</div></div> },
    { key: 'size', header: 'Size', render: (r) => [r.branches_count && `${r.branches_count} branch(es)`, r.users_count && `${r.users_count} users`].filter(Boolean).join(' · ') || '—' },
    { key: 'plan', header: 'Plan', render: (r) => r.plan?.name ?? '—' },
    { key: 'message', header: 'Message', render: (r) => <span className="text-xs text-slate-600 line-clamp-2 max-w-xs">{r.message ?? '—'}</span> },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} tone={r.status === 'REGISTERED' ? 'green' : r.status === 'REJECTED' ? 'red' : r.status === 'APPROVED' ? 'blue' : 'amber'} /> },
    { key: 'received', header: 'Received', render: (r) => formatDateTime(r.created_at) },
    {
      key: 'actions', header: '', align: 'right',
      render: (r) => (r.status === 'PENDING' || r.status === 'APPROVED') && (
        <div className="flex gap-1.5 justify-end">
          <Button size="sm" variant="success" onClick={() => setApproving(r)}>{r.status === 'APPROVED' ? 'Resend link' : 'Approve'}</Button>
          {r.status === 'PENDING' && <Button size="sm" variant="danger" onClick={() => setRejecting(r)}>Reject</Button>}
        </div>
      ),
    },
  ]

  return (
    <>
      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {['PENDING', 'APPROVED', 'REGISTERED', 'REJECTED'].map((s) => <option key={s} value={s}>{titleCase(s)}</option>)}
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} emptyTitle="No quote requests" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <ConfirmDialog
        open={!!approving}
        title={approving?.status === 'APPROVED' ? 'Send a new registration link' : 'Approve and email a registration link'}
        message={approving ? `${approving.email} will receive a one-time link to set up ${approving.institution_name} on a 7-day trial.` : undefined}
        confirmLabel="Send link"
        isPending={approve.isPending}
        onCancel={() => setApproving(null)}
        onConfirm={() => approving && approve.mutate(approving.id)}
      />
      <ConfirmDialog
        open={!!rejecting}
        title="Reject request"
        message={rejecting ? `${rejecting.email} will be told the reason.` : undefined}
        confirmLabel="Reject"
        danger
        requireReason="Reason"
        isPending={reject.isPending}
        onCancel={() => setRejecting(null)}
        onConfirm={(reason) => rejecting && reject.mutate({ id: rejecting.id, reason: reason ?? '' })}
      />
    </>
  )
}

// ---------------------------------------------------------------- Institutions

function TenantsTab() {
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [selected, setSelected] = useState<string | null>(null)
  const list = useQuery({
    queryKey: ['platform', 'tenants', q, page],
    queryFn: () => apiGet<Paginated<TenantRow>>('/api/platform/tenants', { q, page }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<TenantRow>[] = [
    { key: 'name', header: 'Institution', render: (t) => <div><div className="font-semibold">{t.name}</div><div className="text-[11px] text-slate-500">{t.contact_email ?? ''}</div></div> },
    { key: 'state', header: 'Status', render: (t) => <StatusBadge status={t.access_state} tone={ACCESS_LABEL[t.access_state].tone} label={t.is_complimentary && t.access_state === 'ACTIVE' ? 'Complimentary' : ACCESS_LABEL[t.access_state].label} /> },
    { key: 'plan', header: 'Plan', render: (t) => t.plan?.name ?? '—' },
    { key: 'until', header: 'Until', render: (t) => formatDate(t.current_period_end ?? t.trial_ends_at) || '—' },
    { key: 'branches', header: 'Branches', align: 'right', render: (t) => <span className="tabular">{t.branches_count}</span> },
    { key: 'users', header: 'Users', align: 'right', render: (t) => <span className="tabular">{t.users_count}</span> },
    { key: 'created', header: 'Joined', render: (t) => formatDate(t.created_at) },
  ]

  return (
    <>
      <FilterBar>
        <Field label="Search" className="w-72">
          <Input value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} placeholder="Name or email" />
        </Field>
        <PrimaryAction icon={Plus} onClick={() => setCreating(true)} className="ml-auto">New institution</PrimaryAction>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(t) => t.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(t) => setSelected(t.id)} selectedKey={selected} emptyTitle="No institutions" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <NewTenantDrawer open={creating} onClose={() => setCreating(false)} onCreated={(id) => { setCreating(false); setSelected(id) }} />
      <TenantDrawer id={selected} onClose={() => setSelected(null)} />
    </>
  )
}

function NewTenantDrawer({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (id: string) => void }) {
  const queryClient = useQueryClient()
  const empty = { institution_name: '', contact_email: '', contact_phone: '', branch_name: '', county: '', admin_name: '', admin_email: '', complimentary: false }
  const [form, setForm] = useState(empty)
  const set = (patch: Partial<typeof form>) => setForm((f) => ({ ...f, ...patch }))

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<TenantRow>('/api/platform/tenants', {
      ...form,
      contact_phone: form.contact_phone || null,
      branch_name: form.branch_name || null,
      county: form.county || null,
    }),
    onSuccess: (tenant) => {
      toast.success(`${tenant.name} created`, `An activation link has been emailed to ${form.admin_email}.`)
      queryClient.invalidateQueries({ queryKey: ['platform'] })
      setForm(empty)
      onCreated(tenant.id)
    },
  })
  const err = create.isError ? getApiError(create.error) : null
  const valid = form.institution_name.trim() && form.contact_email.includes('@') && form.admin_name.trim() && form.admin_email.includes('@')

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="New institution"
      subtitle="Sets up the institution with its books, roles and first branch, and emails its administrator a link to choose a password."
      width={720}
      footer={<DrawerFooter onCancel={onClose} onSubmit={() => create.mutate()} submitLabel="Create and send link" disabled={!valid || create.isPending} isPending={create.isPending} />}
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Institution name" required className="sm:col-span-2" error={err?.errors.institution_name?.[0]}>
          <Input value={form.institution_name} onChange={(e) => set({ institution_name: e.target.value })} />
        </Field>
        <Field label="Institution email" required error={err?.errors.contact_email?.[0]}>
          <Input type="email" value={form.contact_email} onChange={(e) => set({ contact_email: e.target.value })} />
        </Field>
        <Field label="Phone">
          <Input value={form.contact_phone} onChange={(e) => set({ contact_phone: e.target.value })} />
        </Field>
        <Field label="First branch name">
          <Input value={form.branch_name} onChange={(e) => set({ branch_name: e.target.value })} placeholder="Main branch" />
        </Field>
        <Field label="County">
          <Input value={form.county} onChange={(e) => set({ county: e.target.value })} />
        </Field>
        <Field label="Administrator's name" required error={err?.errors.admin_name?.[0]}>
          <Input value={form.admin_name} onChange={(e) => set({ admin_name: e.target.value })} />
        </Field>
        <Field label="Administrator's email" required hint="They sign in with this; the link is sent here." error={err?.errors.admin_email?.[0]}>
          <Input type="email" value={form.admin_email} onChange={(e) => set({ admin_email: e.target.value })} />
        </Field>
        <label className="flex items-center gap-2 text-sm sm:col-span-2">
          <input type="checkbox" checked={form.complimentary} onChange={(e) => set({ complimentary: e.target.checked })} />
          Complimentary (never billed, no trial)
        </label>
        {err && Object.keys(err.errors).length === 0 && <InlineError error={create.error} className="sm:col-span-2" />}
      </div>
    </Drawer>
  )
}

function TenantDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [suspending, setSuspending] = useState(false)
  const [trialDays, setTrialDays] = useState('7')
  const detail = useQuery({ queryKey: ['platform', 'tenant', id], queryFn: () => apiGet<TenantDetail>(`/api/platform/tenants/${id}`), enabled: !!id })
  const refresh = (message: string) => {
    toast.success(message)
    queryClient.invalidateQueries({ queryKey: ['platform'] })
  }

  const suspend = useMutation({ mutationFn: (reason: string) => apiPost(`/api/platform/tenants/${id}/suspend`, { reason }), onSuccess: () => { setSuspending(false); refresh('Institution suspended') } })
  const reactivate = useMutation({ mutationFn: () => apiPost(`/api/platform/tenants/${id}/reactivate`), onSuccess: () => refresh('Institution reactivated') })
  const extend = useMutation({ mutationFn: () => apiPost(`/api/platform/tenants/${id}/extend-trial`, { days: Number(trialDays) }), onSuccess: () => refresh('Trial extended') })
  const complimentary = useMutation({ mutationFn: (value: boolean) => apiPost(`/api/platform/tenants/${id}/complimentary`, { is_complimentary: value }), onSuccess: () => refresh('Billing updated') })
  const resend = useMutation({ mutationFn: (invitationId: string) => apiPost(`/api/platform/invitations/${invitationId}/resend`), onSuccess: () => refresh('A new link has been emailed') })

  const t = detail.data
  const invitationColumns: Column<InvitationRow>[] = [
    { key: 'purpose', header: 'Link', render: (i) => (i.purpose === 'REGISTER' ? 'Registration' : 'Activation') },
    { key: 'email', header: 'Sent to', render: (i) => i.email },
    { key: 'state', header: 'State', render: (i) => <StatusBadge status={i.state} tone={i.state === 'USED' ? 'green' : i.state === 'SENT' ? 'blue' : 'slate'} /> },
    { key: 'expires', header: 'Expires', render: (i) => formatDate(i.expires_at) },
    { key: 'resend', header: '', align: 'right', render: (i) => i.state !== 'USED' && <Button size="sm" disabled={resend.isPending} onClick={() => resend.mutate(i.id)}>Resend</Button> },
  ]

  return (
    <Drawer open={!!id} onClose={onClose} title={t?.name ?? 'Institution'} subtitle={t?.contact_email ?? undefined} width={900}>
      {detail.isLoading && <LoadingSkeleton />}
      {detail.isError && <InlineError error={detail.error} />}
      {t && (
        <div className="space-y-5">
          <div className="flex items-center gap-2 flex-wrap">
            <StatusBadge status={t.access_state} tone={ACCESS_LABEL[t.access_state].tone} label={ACCESS_LABEL[t.access_state].label} />
            {t.is_complimentary && <StatusBadge status="COMPLIMENTARY" tone="purple" label="Complimentary" />}
          </div>
          <DescriptionList
            items={[
              { label: 'Legal name', value: t.legal_name ?? '—' },
              { label: 'KRA PIN', value: t.kra_pin ?? '—' },
              { label: 'Phone', value: t.contact_phone ?? '—' },
              { label: 'Trial ends', value: formatDate(t.trial_ends_at) || '—' },
              { label: 'Plan', value: t.plan ? `${t.plan.name} until ${formatDate(t.current_period_end)}` : '—' },
              { label: 'Branches / users', value: `${t.branches_count} / ${t.users_count}` },
              { label: 'Suspended', value: t.suspended_at ? `${formatDateTime(t.suspended_at)} — ${t.suspension_reason ?? ''}` : '—' },
              { label: 'Joined', value: formatDate(t.created_at) },
            ]}
          />

          <div className="ui-card p-4 space-y-3">
            <h3 className="text-sm font-semibold text-slate-900">Actions</h3>
            <div className="flex flex-wrap items-end gap-2">
              {t.suspended_at ? (
                <Button variant="success" disabled={reactivate.isPending} onClick={() => reactivate.mutate()}>Reactivate</Button>
              ) : (
                <Button variant="danger" onClick={() => setSuspending(true)}>Suspend</Button>
              )}
              <Button disabled={complimentary.isPending} onClick={() => complimentary.mutate(!t.is_complimentary)}>
                {t.is_complimentary ? 'Start billing' : 'Make complimentary'}
              </Button>
              <div className="flex items-end gap-1.5 ml-auto">
                <Field label="Extend trial (days)" className="w-32">
                  <Input inputMode="numeric" value={trialDays} onChange={(e) => setTrialDays(e.target.value.replace(/\D/g, ''))} />
                </Field>
                <Button disabled={!trialDays || extend.isPending} onClick={() => extend.mutate()}>Extend</Button>
              </div>
            </div>
          </div>

          <ClearTransactionsCard organisationId={t.id} organisationName={t.name} />

          <div className="ui-card">
            <header className="px-5 py-3 border-b border-slate-100"><h3 className="text-sm font-semibold">Administrators</h3></header>
            <table className="ui-table">
              <thead><tr><th>Name</th><th>Email</th><th>Active</th><th>Last sign-in</th></tr></thead>
              <tbody>
                {t.admins.map((a) => (
                  <tr key={a.id}><td>{a.name}</td><td>{a.email}</td><td>{a.is_active ? 'Yes' : 'No'}</td><td>{formatDateTime(a.last_login_at) || 'Never'}</td></tr>
                ))}
              </tbody>
            </table>
          </div>

          <div className="ui-card">
            <header className="px-5 py-3 border-b border-slate-100"><h3 className="text-sm font-semibold">Links sent</h3></header>
            <DataTable columns={invitationColumns} rows={t.invitations} rowKey={(i) => i.id} emptyTitle="No links sent" />
          </div>

          <div className="ui-card">
            <header className="px-5 py-3 border-b border-slate-100"><h3 className="text-sm font-semibold">Payments</h3></header>
            <DataTable columns={paymentColumns(false)} rows={t.payments} rowKey={(p) => p.id} emptyTitle="No payments" />
          </div>
        </div>
      )}
      <ConfirmDialog open={suspending} title="Suspend institution" message="Its users are stopped at once, except to see why." confirmLabel="Suspend" danger requireReason="Reason" isPending={suspend.isPending} onCancel={() => setSuspending(false)} onConfirm={(reason) => suspend.mutate(reason ?? '')} />
    </Drawer>
  )
}

// ---------------------------------------------------------------- Plans

type PlanForm = { code: string; name: string; description: string; price_monthly: string; price_yearly: string; max_branches: string; max_users: string; features: string; paystack_plan_monthly: string; paystack_plan_yearly: string; is_active: boolean }

function PlansTab() {
  const [editing, setEditing] = useState<Plan | 'new' | null>(null)
  const plans = useQuery({ queryKey: ['platform', 'plans'], queryFn: () => apiGet<Plan[]>('/api/platform/plans') })

  const columns: Column<Plan>[] = [
    { key: 'name', header: 'Plan', render: (p) => <div><div className="font-semibold">{p.name}</div><div className="text-[11px] text-slate-500 font-mono">{p.code}</div></div> },
    { key: 'monthly', header: 'Monthly', align: 'right', render: (p) => <MoneyCell value={p.price_monthly} /> },
    { key: 'yearly', header: 'Yearly', align: 'right', render: (p) => (p.price_yearly ? <MoneyCell value={p.price_yearly} /> : '—') },
    { key: 'limits', header: 'Limits', render: (p) => `${p.max_branches ?? '∞'} branches · ${p.max_users ?? '∞'} users` },
    { key: 'recurring', header: 'Paystack plan', render: (p) => (p.paystack_plan_monthly || p.paystack_plan_yearly ? 'Recurring' : 'One-off payments') },
    { key: 'active', header: 'Status', render: (p) => <StatusBadge status={p.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
  ]

  return (
    <>
      <FilterBar>
        <p className="text-xs text-slate-500 self-center">Prices change future payments only. A Paystack plan code makes that plan renew automatically.</p>
        <PrimaryAction icon={Plus} onClick={() => setEditing('new')} className="ml-auto">New plan</PrimaryAction>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={plans.data} rowKey={(p) => p.id} isLoading={plans.isLoading} error={plans.error} onRetry={() => plans.refetch()} onRowClick={(p) => setEditing(p)} emptyTitle="No plans yet" />
      </div>
      {editing && <PlanDrawer plan={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
    </>
  )
}

function PlanDrawer({ plan, onClose }: { plan: Plan | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<PlanForm>({
    code: plan?.code ?? '',
    name: plan?.name ?? '',
    description: plan?.description ?? '',
    price_monthly: plan ? String(Number(plan.price_monthly)) : '',
    price_yearly: plan?.price_yearly ? String(Number(plan.price_yearly)) : '',
    max_branches: plan?.max_branches ? String(plan.max_branches) : '',
    max_users: plan?.max_users ? String(plan.max_users) : '',
    features: (plan?.features ?? []).join('\n'),
    paystack_plan_monthly: plan?.paystack_plan_monthly ?? '',
    paystack_plan_yearly: plan?.paystack_plan_yearly ?? '',
    is_active: plan?.is_active ?? true,
  })
  const set = (patch: Partial<PlanForm>) => setForm((f) => ({ ...f, ...patch }))

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = {
        ...form,
        description: form.description || null,
        price_yearly: form.price_yearly || null,
        max_branches: form.max_branches ? Number(form.max_branches) : null,
        max_users: form.max_users ? Number(form.max_users) : null,
        features: form.features.split('\n').map((f) => f.trim()).filter(Boolean),
        paystack_plan_monthly: form.paystack_plan_monthly || null,
        paystack_plan_yearly: form.paystack_plan_yearly || null,
      }
      return plan ? apiPatch<Plan>(`/api/platform/plans/${plan.id}`, body) : apiPost<Plan>('/api/platform/plans', body)
    },
    onSuccess: (saved) => {
      toast.success(`${saved.name} saved`)
      queryClient.invalidateQueries({ queryKey: ['platform', 'plans'] })
      onClose()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <Drawer
      open
      onClose={onClose}
      title={plan ? `Edit ${plan.name}` : 'New plan'}
      width={640}
      footer={<DrawerFooter onCancel={onClose} onSubmit={() => save.mutate()} submitLabel="Save plan" disabled={!form.code || !form.name || !form.price_monthly || save.isPending} isPending={save.isPending} />}
    >
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} onChange={(e) => set({ code: e.target.value.toUpperCase() })} /></Field>
        <Field label="Name" required error={err?.errors.name?.[0]}><Input value={form.name} onChange={(e) => set({ name: e.target.value })} /></Field>
        <Field label="Description" className="sm:col-span-2"><Input value={form.description} onChange={(e) => set({ description: e.target.value })} /></Field>
        <Field label="Monthly price (KES)" required error={err?.errors.price_monthly?.[0]}><Input inputMode="decimal" value={form.price_monthly} onChange={(e) => set({ price_monthly: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
        <Field label="Yearly price (KES)" hint="Blank: not offered yearly."><Input inputMode="decimal" value={form.price_yearly} onChange={(e) => set({ price_yearly: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
        <Field label="Branches" hint="Blank: unlimited."><Input inputMode="numeric" value={form.max_branches} onChange={(e) => set({ max_branches: e.target.value.replace(/\D/g, '') })} /></Field>
        <Field label="Users" hint="Blank: unlimited."><Input inputMode="numeric" value={form.max_users} onChange={(e) => set({ max_users: e.target.value.replace(/\D/g, '') })} /></Field>
        <Field label="Features" hint="One per line." className="sm:col-span-2"><Textarea rows={4} value={form.features} onChange={(e) => set({ features: e.target.value })} /></Field>
        <Field label="Paystack plan code (monthly)" hint="PLN_… from your Paystack dashboard."><Input value={form.paystack_plan_monthly} onChange={(e) => set({ paystack_plan_monthly: e.target.value })} /></Field>
        <Field label="Paystack plan code (yearly)"><Input value={form.paystack_plan_yearly} onChange={(e) => set({ paystack_plan_yearly: e.target.value })} /></Field>
        <label className="flex items-center gap-2 text-sm sm:col-span-2">
          <input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> Offered to institutions
        </label>
        {err && Object.keys(err.errors).length === 0 && <InlineError error={save.error} className="sm:col-span-2" />}
      </div>
    </Drawer>
  )
}

// ---------------------------------------------------------------- Payments

function paymentColumns(withInstitution: boolean): Column<SubscriptionPaymentRow>[] {
  return [
    { key: 'date', header: 'Date', render: (p) => formatDateTime(p.paid_at ?? p.created_at) },
    ...(withInstitution ? [{ key: 'institution', header: 'Institution', render: (p: SubscriptionPaymentRow) => p.organisation?.name ?? '—' }] : []),
    { key: 'plan', header: 'Plan', render: (p) => `${p.plan?.name ?? '—'}${p.billing_interval ? ` · ${p.billing_interval === 'YEARLY' ? 'yearly' : 'monthly'}` : ''}` },
    { key: 'reference', header: 'Reference', render: (p) => <span className="tabular text-xs">{p.reference}</span> },
    { key: 'channel', header: 'Channel', render: (p) => p.channel?.replace('_', ' ') ?? '—' },
    { key: 'status', header: 'Status', render: (p) => <StatusBadge status={p.status} tone={p.status === 'SUCCESS' ? 'green' : p.status === 'FAILED' ? 'red' : 'amber'} /> },
    { key: 'amount', header: 'Amount', align: 'right', render: (p) => <span className="tabular">{p.currency} {formatMoney(p.amount)}</span> },
  ]
}

function PaymentsTab() {
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const list = useQuery({
    queryKey: ['platform', 'payments', status, page],
    queryFn: () => apiGet<Paginated<SubscriptionPaymentRow>>('/api/platform/payments', { status, page }),
    placeholderData: (prev) => prev,
  })

  return (
    <>
      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {['SUCCESS', 'PENDING', 'FAILED'].map((s) => <option key={s} value={s}>{titleCase(s)}</option>)}
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={paymentColumns(true)} rows={list.data?.data} rowKey={(p) => p.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} emptyTitle="No payments yet" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
    </>
  )
}

type PurgePreview = { since: string; documents: { table: string; label: string; count: number }[] }

/**
 * Clearing a demonstration or training session: everything recorded since
 * a chosen moment is removed, and stock, balances and numbering go back to
 * where they stood. A backup is taken first, and the name must be typed.
 */
function ClearTransactionsCard({ organisationId, organisationName }: { organisationId: string; organisationName: string }) {
  const queryClient = useQueryClient()
  const [since, setSince] = useState('')
  const [confirmName, setConfirmName] = useState('')
  const [reason, setReason] = useState('Online demo')

  const preview = useQuery({
    queryKey: ['platform', 'purge-preview', organisationId, since],
    queryFn: () => apiGet<PurgePreview>(`/api/platform/tenants/${organisationId}/transactions-since`, { since }),
    enabled: since !== '',
  })
  const purge = useMutation({
    mutationFn: () => apiPost<{ backup: string; deleted: Record<string, number> }>(`/api/platform/tenants/${organisationId}/clear-transactions`, { since, confirm_name: confirmName, reason }),
    onSuccess: (result) => {
      toast.success('Transactions cleared', `Backup ${result.backup} was taken first.`)
      setConfirmName('')
      queryClient.invalidateQueries()
    },
  })

  const found = (preview.data?.documents ?? []).filter((d) => d.count > 0)

  return (
    <div className="ui-card p-4 space-y-3 border-rose-200">
      <div>
        <h3 className="text-sm font-semibold text-slate-900">Clear demo or training transactions</h3>
        <p className="text-xs text-slate-500">
          Removes every document, stock movement, payment and journal recorded since the moment you choose. Products, prices, customers,
          suppliers, users and everything before that moment stay. A database backup is taken first.
        </p>
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        <Field label="Recorded since">
          <Input type="datetime-local" value={since} onChange={(e) => setSince(e.target.value)} />
        </Field>
        <Field label="Reason">
          <Input value={reason} onChange={(e) => setReason(e.target.value)} />
        </Field>
        <Field label={`Type "${organisationName}" to confirm`}>
          <Input value={confirmName} onChange={(e) => setConfirmName(e.target.value)} />
        </Field>
      </div>
      {preview.isError && <InlineError error={preview.error} />}
      {since && preview.data && (
        found.length === 0
          ? <p className="text-sm text-slate-500">Nothing was recorded since then.</p>
          : <ul className="flex flex-wrap gap-1.5 text-xs">{found.map((d) => <li key={d.table} className="rounded-full bg-rose-50 text-rose-800 px-2.5 py-1 font-semibold">{d.count} × {d.label}</li>)}</ul>
      )}
      {purge.isError && <InlineError error={purge.error} />}
      <div className="flex justify-end">
        <Button variant="danger" disabled={!since || found.length === 0 || confirmName !== organisationName || reason.trim().length < 3 || purge.isPending} onClick={() => purge.mutate()}>
          {purge.isPending ? 'Backing up and clearing…' : 'Clear these transactions'}
        </Button>
      </div>
    </div>
  )
}
