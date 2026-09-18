import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { EmptyState, InlineError, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime, todayIso } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast, toastApiError } from '../../lib/toast'
import type { Paginated } from '../../lib/types'

type LeaveType = { id: string; code: string; name: string; days_per_year: string; is_paid: boolean; requires_document: boolean; carry_forward_max: string; is_active: boolean }
type LeaveEmployee = { id: string; employee_no: string; name: string; job_title: string | null; department: string | null; user_id: number | null; date_joined: string | null; is_active: boolean }
type LeaveStatus = 'DRAFT' | 'PENDING' | 'APPROVED' | 'REJECTED' | 'CANCELLED'
type LeaveRequest = {
  id: string
  doc_number: string
  employee_id: string
  leave_type_id: string
  start_date: string
  end_date: string
  days: string
  reason: string | null
  status: LeaveStatus
  approved_at: string | null
  rejection_reason: string | null
  created_at: string
  employee?: { id: string; employee_no: string; name: string; department: string | null; user_id: number | null }
  leave_type?: { id: string; code: string; name: string; is_paid: boolean }
  approver?: { id: number; name: string } | null
  creator?: { id: number; name: string } | null
}
type Balance = { year: number; employee_id: string; leave_type_id: string; code: string; name: string; is_paid: boolean; entitlement: string; taken: string; pending: string; balance: string }
type BalancesResponse = { year: number; types: LeaveType[]; data: { employee: { id: string; employee_no: string; name: string; department: string | null }; balances: Balance[] }[] }

const STATUSES: LeaveStatus[] = ['PENDING', 'APPROVED', 'REJECTED', 'CANCELLED']
const days = (v: string | number | null | undefined) => {
  const n = Number(v ?? 0)
  return Number.isInteger(n) ? String(n) : n.toFixed(1)
}

/** Working days (Mon–Fri) in a date range, the same rule the server applies. */
function workingDays(start: string, end: string): number {
  if (!start || !end || end < start) return 0
  let count = 0
  const d = new Date(`${start}T00:00:00`)
  const last = new Date(`${end}T00:00:00`)
  while (d <= last && count < 1000) {
    const wd = d.getDay()
    if (wd !== 0 && wd !== 6) count++
    d.setDate(d.getDate() + 1)
  }
  return count
}

function useLeaveTypes() {
  return useQuery({ queryKey: ['leave', 'types'], queryFn: () => apiGet<{ data: LeaveType[] }>('/api/leave/types'), staleTime: 5 * 60_000 })
}

function useLeaveEmployees() {
  return useQuery({ queryKey: ['leave', 'employees'], queryFn: () => apiGet<{ data: LeaveEmployee[] }>('/api/leave/employees'), staleTime: 60_000 })
}

/** Part 21.16 — leave requests, approvals and balances. */
export default function LeavePage() {
  const canRequest = usePermission('leave.request')
  const canApprove = usePermission('leave.approve')
  const [tab, setTab] = useState<'requests' | 'balances'>('requests')

  if (!canRequest && !canApprove) return <NoAccess permission="leave.request" />

  return (
    <Page>
      <PageHeader
        parent="People"
        title="Leave"
        subtitle="Days are counted Monday to Friday. Only approved leave uses the balance, and nobody approves their own request."
      />
      <div className="flex gap-1 mb-3 border-b border-[var(--border)]">
        {(['requests', 'balances'] as const).map((t) => (
          <button key={t} type="button" onClick={() => setTab(t)} className={`px-3 py-2 text-[12.5px] font-semibold border-b-2 -mb-px ${tab === t ? 'border-[var(--color-navy)] text-[var(--text)]' : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text)]'}`}>
            {t === 'requests' ? 'Requests' : 'Balances'}
          </button>
        ))}
      </div>
      {tab === 'requests' ? <RequestsTab canApprove={canApprove} /> : <BalancesTab />}
    </Page>
  )
}

function RequestsTab({ canApprove }: { canApprove: boolean }) {
  const queryClient = useQueryClient()
  const { data: me } = useCurrentUser()
  const types = useLeaveTypes()
  const employees = useLeaveEmployees()
  const [filters, setFilters] = useState({ status: canApprove ? 'PENDING' : '', employee_id: '', leave_type_id: '', year: String(new Date().getFullYear()) })
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [selected, setSelected] = useState<LeaveRequest | null>(null)
  const [action, setAction] = useState<{ kind: 'reject' | 'cancel'; request: LeaveRequest } | null>(null)

  const list = useQuery({
    queryKey: ['leave', 'requests', filters, page],
    queryFn: () => apiGet<Paginated<LeaveRequest>>('/api/leave/requests', { ...filters, page, per_page: 25 }),
    placeholderData: (prev) => prev,
  })
  const refresh = () => queryClient.invalidateQueries({ queryKey: ['leave'] })

  const transition = useMutation({
    mutationFn: ({ kind, request, reason }: { kind: 'approve' | 'reject' | 'cancel'; request: LeaveRequest; reason?: string }) =>
      apiPost<LeaveRequest>(`/api/leave/requests/${request.id}/${kind}`, reason !== undefined ? { reason } : undefined),
    onSuccess: (r, v) => {
      toast.success(`${r.doc_number} ${v.kind === 'approve' ? 'approved' : v.kind === 'reject' ? 'rejected' : 'cancelled'}`)
      setAction(null)
      setSelected((cur) => (cur && cur.id === r.id ? r : cur))
      refresh()
    },
    onError: (e) => toastApiError(e),
  })

  const isOwnRequest = (r: LeaveRequest) => (me?.id != null && (r.employee?.user_id === me.id || r.creator?.id === me.id))
  const canCancel = (r: LeaveRequest) => ['PENDING', 'APPROVED', 'DRAFT'].includes(r.status) && (canApprove || isOwnRequest(r))

  const columns: Column<LeaveRequest>[] = [
    { key: 'doc', header: 'Request', render: (r) => <span className="font-semibold tabular">{r.doc_number}</span>, sortValue: (r) => r.doc_number },
    { key: 'employee', header: 'Employee', render: (r) => <>{r.employee?.name ?? '—'}<div className="text-[10.5px] text-[var(--text-muted)]">{r.employee?.employee_no}{r.employee?.department ? ` · ${r.employee.department}` : ''}</div></>, sortValue: (r) => r.employee?.name ?? '' },
    { key: 'type', header: 'Type', render: (r) => <>{r.leave_type?.name ?? '—'}{r.leave_type && !r.leave_type.is_paid && <span className="ml-1 text-[10.5px] text-[var(--text-muted)]">(unpaid)</span>}</> },
    { key: 'dates', header: 'Dates', render: (r) => <span className="tabular">{formatDate(r.start_date)} – {formatDate(r.end_date)}</span>, sortValue: (r) => r.start_date },
    { key: 'days', header: 'Days', align: 'right', render: (r) => <span className="tabular font-semibold">{days(r.days)}</span>, sortValue: (r) => Number(r.days) },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (r) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          {canApprove && r.status === 'PENDING' && (
            <>
              <Button size="sm" variant="success" disabled={transition.isPending} onClick={() => transition.mutate({ kind: 'approve', request: r })}>Approve</Button>
              <Button size="sm" disabled={transition.isPending} onClick={() => setAction({ kind: 'reject', request: r })}>Reject</Button>
            </>
          )}
          {canCancel(r) && <Button size="sm" variant="ghost" disabled={transition.isPending} onClick={() => setAction({ kind: 'cancel', request: r })}>Cancel</Button>}
        </div>
      ),
    },
  ]

  const year = new Date().getFullYear()

  return (
    <>
      <FilterBar>
        <Field label="Status">
          <Select value={filters.status} onChange={(e) => { setFilters({ ...filters, status: e.target.value }); setPage(1) }}>
            <option value="">All</option>
            {STATUSES.map((s) => <option key={s} value={s}>{s.charAt(0) + s.slice(1).toLowerCase()}</option>)}
          </Select>
        </Field>
        {(employees.data?.data.length ?? 0) > 1 && (
          <Field label="Employee" className="w-60">
            <Select value={filters.employee_id} onChange={(e) => { setFilters({ ...filters, employee_id: e.target.value }); setPage(1) }}>
              <option value="">Everyone</option>
              {(employees.data?.data ?? []).map((emp) => <option key={emp.id} value={emp.id}>{emp.employee_no} · {emp.name}</option>)}
            </Select>
          </Field>
        )}
        <Field label="Type">
          <Select value={filters.leave_type_id} onChange={(e) => { setFilters({ ...filters, leave_type_id: e.target.value }); setPage(1) }}>
            <option value="">All types</option>
            {(types.data?.data ?? []).map((t) => <option key={t.id} value={t.id}>{t.name}</option>)}
          </Select>
        </Field>
        <Field label="Year">
          <Select value={filters.year} onChange={(e) => { setFilters({ ...filters, year: e.target.value }); setPage(1) }}>
            <option value="">Any</option>
            {[year + 1, year, year - 1, year - 2].map((y) => <option key={y} value={y}>{y}</option>)}
          </Select>
        </Field>
        <div className="ml-auto">
          <Button variant="primary" onClick={() => setCreating(true)} disabled={!employees.data?.data.length} title={employees.data && !employees.data.data.length ? 'No employee record is linked to your user' : undefined}>
            <Plus size={13} /> New request
          </Button>
        </div>
      </FilterBar>
      {employees.data && !employees.data.data.length && (
        <div className="mb-3 text-[11.5px] text-[var(--text-muted)]">Your user is not linked to an employee record, so you cannot request leave yet. Ask payroll to link it.</div>
      )}
      <div className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data?.data}
          rowKey={(r) => r.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          onRowClick={setSelected}
          selectedKey={selected?.id ?? null}
          emptyTitle="No leave requests"
          emptyHint={filters.status ? 'Nothing matches these filters.' : 'Requests appear here once raised.'}
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer open={!!selected} onClose={() => setSelected(null)} title={selected?.doc_number ?? ''} subtitle={selected?.employee?.name}>
        {selected && (
          <div className="space-y-4">
            <div className="flex items-center gap-2"><StatusBadge status={selected.status} /><span className="text-[12px] text-[var(--text-secondary)]">{days(selected.days)} working day(s)</span></div>
            <DescriptionList
              items={[
                { label: 'Employee', value: `${selected.employee?.employee_no ?? ''} · ${selected.employee?.name ?? ''}` },
                { label: 'Leave type', value: `${selected.leave_type?.name ?? '—'}${selected.leave_type?.is_paid === false ? ' (unpaid)' : ''}` },
                { label: 'Dates', value: `${formatDate(selected.start_date)} – ${formatDate(selected.end_date)}` },
                { label: 'Reason', value: selected.reason || '—' },
                { label: 'Requested by', value: `${selected.creator?.name ?? '—'} · ${formatDateTime(selected.created_at)}` },
                ...(selected.approver ? [{ label: selected.status === 'REJECTED' ? 'Rejected by' : 'Approved by', value: `${selected.approver.name} · ${formatDateTime(selected.approved_at)}` }] : []),
                ...(selected.rejection_reason ? [{ label: 'Rejection reason', value: selected.rejection_reason }] : []),
              ]}
            />
            <div className="flex gap-2">
              {canApprove && selected.status === 'PENDING' && (
                <>
                  <Button variant="success" disabled={transition.isPending} onClick={() => transition.mutate({ kind: 'approve', request: selected })}>Approve</Button>
                  <Button disabled={transition.isPending} onClick={() => setAction({ kind: 'reject', request: selected })}>Reject</Button>
                </>
              )}
              {canCancel(selected) && <Button variant="ghost" onClick={() => setAction({ kind: 'cancel', request: selected })}>Cancel request</Button>}
            </div>
          </div>
        )}
      </Drawer>

      <Drawer open={creating} onClose={() => setCreating(false)} title="New leave request" width={520}>
        {creating && <RequestForm employees={employees.data?.data ?? []} types={(types.data?.data ?? []).filter((t) => t.is_active)} onDone={() => { setCreating(false); refresh() }} />}
      </Drawer>

      <ConfirmDialog
        open={!!action}
        title={action?.kind === 'reject' ? `Reject ${action.request.doc_number}?` : `Cancel ${action?.request.doc_number ?? ''}?`}
        message={action?.kind === 'cancel' && action.request.status === 'APPROVED' ? 'The days go back to the employee’s balance.' : undefined}
        requireReason={action?.kind === 'reject' ? 'Reason for rejection' : undefined}
        confirmLabel={action?.kind === 'reject' ? 'Reject' : 'Cancel request'}
        danger
        isPending={transition.isPending}
        onConfirm={(reason) => action && transition.mutate({ kind: action.kind, request: action.request, reason: reason || undefined })}
        onCancel={() => setAction(null)}
      />
    </>
  )
}

function RequestForm({ employees, types, onDone }: { employees: LeaveEmployee[]; types: LeaveType[]; onDone: () => void }) {
  const [form, setForm] = useState({ employee_id: employees.length === 1 ? employees[0].id : '', leave_type_id: types.find((t) => t.code === 'ANNUAL')?.id ?? '', start_date: todayIso(), end_date: todayIso(), reason: '' })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const year = form.start_date ? Number(form.start_date.slice(0, 4)) : new Date().getFullYear()
  const balances = useQuery({
    queryKey: ['leave', 'balances', year, form.employee_id],
    queryFn: () => apiGet<BalancesResponse>('/api/leave/balances', { year, employee_id: form.employee_id }),
    enabled: !!form.employee_id,
  })
  const type = types.find((t) => t.id === form.leave_type_id)
  const balance = balances.data?.data[0]?.balances.find((b) => b.leave_type_id === form.leave_type_id)
  const requested = workingDays(form.start_date, form.end_date)
  const short = !!type?.is_paid && !!balance && requested > Number(balance.balance)

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<LeaveRequest>('/api/leave/requests', { ...form, reason: form.reason || null }),
    onSuccess: (r) => {
      toast.success(`${r.doc_number} submitted`, `${days(r.days)} working day(s), awaiting approval.`)
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="space-y-4">
      <Field label="Employee" required error={err?.errors.employee_id?.[0]}>
        <Select value={form.employee_id} onChange={(e) => set({ employee_id: e.target.value })} disabled={employees.length === 1}>
          <option value="">Choose an employee…</option>
          {employees.map((emp) => <option key={emp.id} value={emp.id}>{emp.employee_no} · {emp.name}</option>)}
        </Select>
      </Field>
      <Field label="Leave type" required error={err?.errors.leave_type_id?.[0]} hint={type?.requires_document ? 'Keep the supporting document (e.g. a sick note) ready for the approver.' : undefined}>
        <Select value={form.leave_type_id} onChange={(e) => set({ leave_type_id: e.target.value })}>
          <option value="">Choose…</option>
          {types.map((t) => <option key={t.id} value={t.id}>{t.name}{t.is_paid ? ` · ${days(t.days_per_year)} days/yr` : ' · unpaid'}</option>)}
        </Select>
      </Field>
      <div className="grid grid-cols-2 gap-3">
        <Field label="First day" required error={err?.errors.start_date?.[0]}><Input type="date" value={form.start_date} onChange={(e) => set({ start_date: e.target.value, end_date: form.end_date < e.target.value ? e.target.value : form.end_date })} /></Field>
        <Field label="Last day" required error={err?.errors.end_date?.[0]}><Input type="date" value={form.end_date} min={form.start_date} onChange={(e) => set({ end_date: e.target.value })} /></Field>
      </div>
      <div className="rounded-md border border-[var(--border)] bg-[var(--surface-2)] px-3 py-2 text-[12px] flex flex-wrap gap-x-5 gap-y-1 tabular">
        <span>Working days <b>{requested}</b></span>
        {balance && type?.is_paid && (
          <>
            <span>Entitlement {year} <b>{days(balance.entitlement)}</b></span>
            <span>Taken <b>{days(balance.taken)}</b></span>
            <span>Pending <b>{days(balance.pending)}</b></span>
            <span className={short ? 'text-[var(--status-red)]' : ''}>Available <b>{days(balance.balance)}</b></span>
          </>
        )}
        {type && !type.is_paid && <span className="text-[var(--text-muted)]">Unpaid leave has no balance limit.</span>}
      </div>
      {short && <div className="text-[11.5px] text-[var(--status-red)]">This is more than the remaining balance; the request will be refused.</div>}
      <Field label="Reason"><Textarea rows={3} value={form.reason} onChange={(e) => set({ reason: e.target.value })} /></Field>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onDone}>Cancel</Button>
        <Button variant="primary" disabled={!form.employee_id || !form.leave_type_id || requested === 0 || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Submitting…' : 'Submit request'}</Button>
      </div>
    </div>
  )
}

function BalancesTab() {
  const [year, setYear] = useState(new Date().getFullYear())
  const [q, setQ] = useState('')
  const balances = useQuery({ queryKey: ['leave', 'balances', year], queryFn: () => apiGet<BalancesResponse>('/api/leave/balances', { year }) })
  const current = new Date().getFullYear()

  const rows = (balances.data?.data ?? []).filter((r) => !q || `${r.employee.employee_no} ${r.employee.name}`.toLowerCase().includes(q.toLowerCase()))
  const paidTypes = (balances.data?.types ?? []).filter((t) => t.is_paid)

  return (
    <>
      <FilterBar>
        <Field label="Year">
          <Select value={year} onChange={(e) => setYear(Number(e.target.value))}>
            {[current + 1, current, current - 1, current - 2].map((y) => <option key={y} value={y}>{y}</option>)}
          </Select>
        </Field>
        <Field label="Search" className="w-64"><Input placeholder="Employee name or number" value={q} onChange={(e) => setQ(e.target.value)} /></Field>
      </FilterBar>
      <div className="ui-card overflow-auto">
        {balances.isLoading ? (
          <LoadingSkeleton />
        ) : balances.isError ? (
          <InlineError error={balances.error} className="m-3" />
        ) : rows.length === 0 ? (
          <EmptyState title="No employees" hint="Active employees and their leave balances appear here." />
        ) : (
          <table className="ui-table">
            <thead>
              <tr>
                <th className="text-left">Employee</th>
                {paidTypes.map((t) => <th key={t.id} className="text-right" title={`${t.name}: available of entitlement`}>{t.name}</th>)}
              </tr>
            </thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.employee.id}>
                  <td>
                    <div className="font-semibold">{r.employee.name}</div>
                    <div className="text-[10.5px] text-[var(--text-muted)]">{r.employee.employee_no}{r.employee.department ? ` · ${r.employee.department}` : ''}</div>
                  </td>
                  {paidTypes.map((t) => {
                    const b = r.balances.find((x) => x.leave_type_id === t.id)
                    if (!b) return <td key={t.id} className="text-right">—</td>
                    const low = Number(b.balance) <= 0
                    return (
                      <td key={t.id} className="text-right tabular" title={`Entitlement ${b.entitlement} · taken ${b.taken} · pending ${b.pending}`}>
                        <span className={`font-semibold ${low ? 'text-[var(--text-muted)]' : ''}`}>{days(b.balance)}</span>
                        <span className="text-[var(--text-muted)]"> / {days(b.entitlement)}</span>
                        {Number(b.pending) > 0 && <div className="text-[10px] text-[#b45309]">{days(b.pending)} pending</div>}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>
      <p className="mt-2 text-[11px] text-[var(--text-muted)]">Available / entitlement in working days. Entitlements are pro-rated for employees who joined or left during the year. Unpaid leave is not shown because it has no balance.</p>
    </>
  )
}
