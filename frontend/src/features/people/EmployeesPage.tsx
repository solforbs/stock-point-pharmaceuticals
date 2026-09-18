import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDate, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Employee, Paginated } from '../../lib/types'

const blank = { employee_no: '', name: '', national_id: '', kra_pin: '', nssf_no: '', shif_no: '', job_title: '', department: '', employment_type: 'PERMANENT', date_joined: '', date_left: '', basic_salary: '', regular_allowances: '', pension_contribution: '', bank_name: '', bank_account: '', is_active: true }
type Form = typeof blank

export default function EmployeesPage() {
  const canProcess = usePermission('payroll.process')
  const [page, setPage] = useState(1)
  const [activeOnly, setActiveOnly] = useState(true)
  const [editing, setEditing] = useState<Employee | 'new' | null>(null)

  const list = useQuery({
    queryKey: ['payroll', 'employees', page, activeOnly],
    queryFn: () => apiGet<Paginated<Employee>>('/api/payroll/employees', { page, per_page: 50, is_active: activeOnly ? 1 : undefined }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<Employee>[] = [
    { key: 'no', header: 'No.', render: (e) => <span className="font-semibold tabular">{e.employee_no}</span>, sortValue: (e) => e.employee_no },
    { key: 'name', header: 'Name', render: (e) => e.name, sortValue: (e) => e.name },
    { key: 'job', header: 'Job title', render: (e) => e.job_title ?? '—' },
    { key: 'dept', header: 'Department', render: (e) => e.department ?? '—' },
    { key: 'type', header: 'Type', render: (e) => titleCase(e.employment_type) || '—' },
    { key: 'joined', header: 'Joined', render: (e) => formatDate(e.date_joined) },
    { key: 'basic', header: 'Basic salary', align: 'right', render: (e) => <MoneyCell value={e.basic_salary} />, sortValue: (e) => Number(e.basic_salary) },
    { key: 'status', header: 'Status', render: (e) => <StatusBadge status={e.is_active ? 'ACTIVE' : 'INACTIVE'} /> },
  ]

  return (
    <Page>
      <PageHeader parent="People" title="Employees" subtitle="Payroll master data. Bank account numbers are write-only through the API." actions={canProcess ? <Button variant="primary" onClick={() => setEditing('new')}>New employee</Button> : null} />
      <FilterBar>
        <label className="flex items-center gap-2 text-[12px]"><input type="checkbox" checked={activeOnly} onChange={(e) => { setActiveOnly(e.target.checked); setPage(1) }} /> Active only</label>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(e) => e.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(e) => (canProcess ? setEditing(e) : undefined)} emptyTitle="No employees" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <EmployeeDrawer target={editing} onClose={() => setEditing(null)} />
    </Page>
  )
}

function EmployeeDrawer({ target, onClose }: { target: Employee | 'new' | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<Form>(blank)
  const existing = target && target !== 'new' ? target : null

  useEffect(() => {
    if (!target) return
    if (target === 'new') setForm(blank)
    else setForm({ ...blank, ...Object.fromEntries(Object.entries(target).filter(([k]) => k in blank).map(([k, v]) => [k, v === null ? '' : typeof v === 'boolean' ? v : String(v)])) as Partial<Form>, bank_account: '' })
  }, [target])

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const payload: Record<string, unknown> = {}
      for (const [k, v] of Object.entries(form)) {
        if (k === 'bank_account' && v === '') continue
        payload[k] = v === '' ? null : v
      }
      payload.is_active = form.is_active
      return existing ? apiPatch<Employee>(`/api/payroll/employees/${existing.id}`, payload) : apiPost<Employee>('/api/payroll/employees', payload)
    },
    onSuccess: (e) => {
      toast.success(`${e.employee_no} ${existing ? 'updated' : 'created'}`, 'Payroll changes are audited.')
      queryClient.invalidateQueries({ queryKey: ['payroll', 'employees'] })
      onClose()
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const set = (patch: Partial<Form>) => setForm({ ...form, ...patch })
  const text = (key: keyof Form, label: string, extra?: Partial<React.ComponentProps<typeof Input>>) => (
    <Field label={label} error={err?.errors[key]?.[0]}><Input value={String(form[key])} onChange={(e) => set({ [key]: e.target.value } as Partial<Form>)} {...extra} /></Field>
  )

  return (
    <Drawer open={!!target} onClose={onClose} title={existing ? `${existing.employee_no} · ${existing.name}` : 'New employee'} width={680}>
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-3">
          {text('employee_no', 'Employee number')}
          {text('name', 'Full name')}
          {text('job_title', 'Job title')}
          {text('department', 'Department')}
          <Field label="Employment type"><Select value={form.employment_type} onChange={(e) => set({ employment_type: e.target.value })}>{['PERMANENT', 'CONTRACT', 'CASUAL'].map((t) => (<option key={t} value={t}>{titleCase(t)}</option>))}</Select></Field>
          <Field label="Active"><label className="flex items-center gap-2 h-8 text-[12.5px]"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> On payroll</label></Field>
          {text('date_joined', 'Date joined', { type: 'date' })}
          {text('date_left', 'Date left', { type: 'date' })}
          {text('basic_salary', 'Basic salary (KES / month)', { inputMode: 'decimal', className: 'tabular' })}
          {text('regular_allowances', 'Regular allowances', { inputMode: 'decimal', className: 'tabular' })}
          {text('pension_contribution', 'Pension contribution', { inputMode: 'decimal', className: 'tabular' })}
          {text('national_id', 'National ID')}
          {text('kra_pin', 'KRA PIN')}
          {text('nssf_no', 'NSSF number')}
          {text('shif_no', 'SHIF number')}
          {text('bank_name', 'Bank')}
          {text('bank_account', existing ? 'Bank account (leave blank to keep)' : 'Bank account', { autoComplete: 'off' })}
        </div>
        {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
        <div className="flex justify-end gap-2">
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="primary" disabled={!form.employee_no || !form.name || !form.basic_salary || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : existing ? 'Save changes' : 'Create employee'}</Button>
        </div>
      </div>
    </Drawer>
  )
}
