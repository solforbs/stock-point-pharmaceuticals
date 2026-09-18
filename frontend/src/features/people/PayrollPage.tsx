import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Printer } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { Modal } from '../../components/ui/Modal'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { formatMoney, formatPct } from '../../lib/money'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, PayrollBand, PayrollRun, PayrollRunLine, Payslip } from '../../lib/types'

const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
type Inputs = Record<string, { allowances: string; overtime: string; pension_contribution: string; other_deductions: string }>

/** Part 21.16 — a run stamps the bands it used, needs a second person to approve, posts the journal, then pays out of the bank. */
export default function PayrollPage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const [page, setPage] = useState(1)
  const now = new Date()
  const [year, setYear] = useState(String(now.getFullYear()))
  const [month, setMonth] = useState(String(now.getMonth() + 1))
  const [bandsOpen, setBandsOpen] = useState(false)
  const selectedId = params.get('run')

  const runs = useQuery({ queryKey: ['payroll', 'runs', page], queryFn: () => apiGet<Paginated<PayrollRun>>('/api/payroll/runs', { page, per_page: 24 }), placeholderData: (prev) => prev })
  const bands = useQuery({ queryKey: ['payroll', 'bands'], queryFn: () => apiGet<{ as_of: string; data: PayrollBand[] }>('/api/payroll/bands'), enabled: bandsOpen })

  const open = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<PayrollRun>('/api/payroll/runs', { period_year: Number(year), period_month: Number(month) }),
    onSuccess: (r) => {
      toast.success(`Payroll run ${r.doc_number} opened`)
      queryClient.invalidateQueries({ queryKey: ['payroll', 'runs'] })
      setParams({ run: r.id })
    },
  })

  const columns: Column<PayrollRun>[] = [
    { key: 'doc', header: 'Run', render: (r) => <span className="font-semibold tabular">{r.doc_number}</span> },
    { key: 'period', header: 'Period', render: (r) => <span className="tabular">{MONTHS[r.period_month - 1]} {r.period_year}</span>, sortValue: (r) => r.period_year * 100 + r.period_month },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} tone={r.status === 'PAID' ? 'green' : r.status === 'POSTED' ? 'blue' : r.status === 'APPROVED' ? 'teal' : r.status === 'COMPUTED' ? 'amber' : 'slate'} /> },
    { key: 'lines', header: 'Employees', align: 'right', render: (r) => <span className="tabular">{r.lines_count ?? '—'}</span> },
    { key: 'gross', header: 'Gross', align: 'right', render: (r) => <MoneyCell value={r.total_gross} /> },
    { key: 'paye', header: 'PAYE', align: 'right', render: (r) => <MoneyCell value={r.total_paye} /> },
    { key: 'net', header: 'Net pay', align: 'right', render: (r) => <MoneyCell value={r.total_net} className="font-bold" /> },
    { key: 'paid', header: 'Paid', render: (r) => formatDateTime(r.paid_at) },
  ]

  return (
    <Page>
      <PageHeader parent="People" title="Payroll" subtitle="Open a monthly run, compute with the month's variable inputs, approve (a different person), post the journal, pay from the bank." actions={<Button size="sm" onClick={() => setBandsOpen(true)}>Statutory bands in force</Button>} />
      {perms.has('payroll.process') && (
        <FilterBar>
          <Field label="Year"><Input inputMode="numeric" className="tabular w-24" value={year} onChange={(e) => setYear(e.target.value.replace(/\D/g, ''))} /></Field>
          <Field label="Month"><Select value={month} onChange={(e) => setMonth(e.target.value)}>{MONTHS.map((m, i) => (<option key={m} value={i + 1}>{m}</option>))}</Select></Field>
          <Button variant="primary" disabled={open.isPending} onClick={() => open.mutate()}>{open.isPending ? 'Opening…' : 'Open payroll run'}</Button>
          {open.isError && <InlineError error={open.error} />}
        </FilterBar>
      )}
      <div className="ui-card">
        <DataTable columns={columns} rows={runs.data?.data} rowKey={(r) => r.id} isLoading={runs.isLoading} error={runs.error} onRetry={() => runs.refetch()} onRowClick={(r) => setParams({ run: r.id })} selectedKey={selectedId} emptyTitle="No payroll runs" />
        <Pagination page={runs.data} onPage={setPage} />
      </div>
      <RunDrawer id={selectedId} onClose={() => setParams({})} />
      <Modal open={bandsOpen} onClose={() => setBandsOpen(false)} title={`Statutory bands in force${bands.data ? ` as of ${formatDate(bands.data.as_of)}` : ''}`} width={720}>
        {bands.isLoading && <LoadingSkeleton />}
        {bands.data && (
          <table className="ui-table">
            <thead><tr><th>Band</th><th className="text-right">#</th><th className="text-right">Lower</th><th className="text-right">Upper</th><th className="text-right">Rate</th><th className="text-right">Fixed</th><th>Effective</th><th>Source</th></tr></thead>
            <tbody>
              {bands.data.data.map((b) => (
                <tr key={b.id}><td className="font-semibold">{b.band_type}</td><td className="text-right tabular">{b.sequence}</td><td className="text-right"><MoneyCell value={b.lower} /></td><td className="text-right">{b.upper ? <MoneyCell value={b.upper} /> : '∞'}</td><td className="text-right tabular">{b.rate_pct ? formatPct(b.rate_pct) : '—'}</td><td className="text-right"><MoneyCell value={b.fixed_amount} /></td><td className="tabular text-[11px]">{formatDate(b.effective_from)} → {b.effective_to ? formatDate(b.effective_to) : 'open'}</td><td className="text-[11px]">{b.source ?? '—'}</td></tr>
              ))}
            </tbody>
          </table>
        )}
      </Modal>
    </Page>
  )
}

function RunDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const [inputs, setInputs] = useState<Inputs>({})
  const [payRef, setPayRef] = useState('')
  const [payslipEmployee, setPayslipEmployee] = useState<string | null>(null)
  const run = useQuery({ queryKey: ['payroll', 'runs', id], queryFn: () => apiGet<PayrollRun>(`/api/payroll/runs/${id}`), enabled: !!id })

  function refresh(r: PayrollRun, msg: string) {
    toast.success(`${r.doc_number} ${msg}`)
    queryClient.setQueryData(['payroll', 'runs', id], (prev: PayrollRun | undefined) => ({ ...(prev ?? r), ...r }))
    queryClient.invalidateQueries({ queryKey: ['payroll', 'runs'] })
    queryClient.invalidateQueries({ queryKey: ['finance'] })
  }
  const compute = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<PayrollRun>(`/api/payroll/runs/${id}/compute`, { inputs: Object.entries(inputs).map(([employee_id, v]) => ({ employee_id, allowances: v.allowances || null, overtime: v.overtime || null, pension_contribution: v.pension_contribution || null, other_deductions: v.other_deductions || null })) }),
    onSuccess: (r) => { refresh(r, 'computed'); queryClient.invalidateQueries({ queryKey: ['payroll', 'runs', id] }) },
  })
  const approve = useMutation({ meta: { silent: true }, mutationFn: () => apiPost<PayrollRun>(`/api/payroll/runs/${id}/approve`), onSuccess: (r) => refresh(r, 'approved') })
  const post = useMutation({ meta: { silent: true }, mutationFn: () => apiPost<PayrollRun>(`/api/payroll/runs/${id}/post`), onSuccess: (r) => refresh(r, 'posted to the ledger') })
  const pay = useMutation({ meta: { silent: true }, mutationFn: () => apiPost<PayrollRun>(`/api/payroll/runs/${id}/pay`, { reference: payRef || null }), onSuccess: (r) => refresh(r, 'paid') })

  const r = run.data
  const editable = r && (r.status === 'DRAFT' || r.status === 'COMPUTED') && perms.has('payroll.process')
  const anyError = compute.error ?? approve.error ?? post.error ?? pay.error

  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={r ? `${r.doc_number} · ${MONTHS[r.period_month - 1]} ${r.period_year}` : 'Payroll run'}
      subtitle={r?.bands_as_of ? `Bands as of ${formatDate(r.bands_as_of)}` : undefined}
      width={980}
      actions={
        r ? (
          <div className="flex gap-2">
            {editable && <Button size="sm" variant="primary" disabled={compute.isPending} onClick={() => compute.mutate()}>{compute.isPending ? 'Computing…' : r.status === 'DRAFT' ? 'Compute' : 'Recompute'}</Button>}
            {r.status === 'COMPUTED' && perms.has('payroll.process') && <Button size="sm" variant="success" disabled={approve.isPending} onClick={() => approve.mutate()}>Approve</Button>}
            {r.status === 'APPROVED' && perms.has('journal.post') && <Button size="sm" variant="primary" disabled={post.isPending} onClick={() => post.mutate()}>Post journal</Button>}
          </div>
        ) : null
      }
    >
      {run.isLoading && <LoadingSkeleton />}
      {run.isError && <InlineError error={run.error} />}
      {anyError && <InlineError error={anyError} className="mb-3" />}
      {r && (
        <div className="space-y-4">
          <div className="flex items-center gap-2"><StatusBadge status={r.status} /><span className="text-[11.5px] text-[var(--text-muted)]">{perms.has('payroll.approve.own') ? 'You may approve runs you prepared yourself; that is recorded in the audit log.' : 'Separation of duties: the person who prepared the run cannot approve it.'}</span></div>
          <div className="grid grid-cols-3 md:grid-cols-6 gap-2 text-center">
            {[['Gross', r.total_gross], ['PAYE', r.total_paye], ['NSSF (ee)', r.total_nssf_employee], ['SHIF', r.total_shif], ['Housing levy (ee)', r.total_housing_levy_employee], ['Net pay', r.total_net]].map(([label, value]) => (
              <div key={label} className="ui-card p-2"><div className="ui-label !mb-0">{label}</div><div className="text-[14px] font-extrabold tabular">{formatMoney(value)}</div></div>
            ))}
          </div>
          {r.status === 'POSTED' && perms.has('payment.record') && (
            <Card title="Pay net salaries from the bank">
              <div className="p-4 flex items-end gap-3">
                <Field label="Payment reference" className="flex-1"><Input value={payRef} onChange={(e) => setPayRef(e.target.value)} placeholder="KCB-BULK-0917" /></Field>
                <Button variant="success" disabled={pay.isPending} onClick={() => pay.mutate()}>{pay.isPending ? 'Paying…' : `Pay ${formatMoney(r.total_net)}`}</Button>
              </div>
            </Card>
          )}
          <table className="ui-table">
            <thead><tr><th>Employee</th><th className="text-right">Basic</th><th className="text-right">Allowances</th><th className="text-right">Overtime</th><th className="text-right">Pension</th><th className="text-right">Other ded.</th><th className="text-right">Gross</th><th className="text-right">PAYE</th><th className="text-right">NSSF</th><th className="text-right">SHIF</th><th className="text-right">Housing</th><th className="text-right">Net</th><th /></tr></thead>
            <tbody>
              {(r.lines ?? []).map((l) => {
                const inp = inputs[l.employee_id] ?? { allowances: String(Number(l.allowances) || ''), overtime: String(Number(l.overtime) || ''), pension_contribution: String(Number(l.pension_contribution) || ''), other_deductions: String(Number(l.other_deductions) || '') }
                const cell = (key: keyof typeof inp, value: string) => editable ? <input value={inp[key]} onChange={(e) => setInputs({ ...inputs, [l.employee_id]: { ...inp, [key]: e.target.value.replace(/[^\d.]/g, '') } })} className="ui-input h-7 w-20 tabular text-right" /> : <MoneyCell value={value} />
                return (
                  <tr key={l.id}>
                    <td><div className="font-semibold">{l.employee?.name ?? l.employee_id.slice(0, 8)}</div><div className="text-[10.5px] text-[var(--text-muted)]">{l.employee?.employee_no}{l.employee?.job_title ? ` · ${l.employee.job_title}` : ''}</div></td>
                    <td className="text-right"><MoneyCell value={l.basic} /></td>
                    <td className="text-right">{cell('allowances', l.allowances)}</td>
                    <td className="text-right">{cell('overtime', l.overtime)}</td>
                    <td className="text-right">{cell('pension_contribution', l.pension_contribution)}</td>
                    <td className="text-right">{cell('other_deductions', l.other_deductions)}</td>
                    <td className="text-right"><MoneyCell value={l.gross} /></td>
                    <td className="text-right"><MoneyCell value={l.paye} /></td>
                    <td className="text-right"><MoneyCell value={l.nssf_employee} /></td>
                    <td className="text-right"><MoneyCell value={l.shif} /></td>
                    <td className="text-right"><MoneyCell value={l.housing_levy_employee} /></td>
                    <td className="text-right"><MoneyCell value={l.net} className="font-bold" /></td>
                    <td><Button size="sm" variant="ghost" onClick={() => setPayslipEmployee(l.employee_id)} aria-label="Payslip"><Printer size={13} /></Button></td>
                  </tr>
                )
              })}
              {(r.lines ?? []).length === 0 && <tr><td colSpan={13} className="text-center text-[var(--text-muted)] py-4">Compute the run to list active employees with their statutory deductions.</td></tr>}
            </tbody>
          </table>
          {editable && <p className="text-[11px] text-[var(--text-muted)]">Enter this month's variable inputs, then Compute. Statutory deductions come from the bands stamped on the run.</p>}
          <DescriptionList items={[{ label: 'Computed', value: formatDateTime(r.computed_at) }, { label: 'Approved', value: formatDateTime(r.approved_at) }, { label: 'Posted', value: `${formatDateTime(r.posted_at)}${r.journal_id ? ` · journal ${r.journal_id.slice(0, 8)}` : ''}` }, { label: 'Paid', value: `${formatDateTime(r.paid_at)}${r.payment_journal_id ? ` · journal ${r.payment_journal_id.slice(0, 8)}` : ''}` }]} />
        </div>
      )}
      <PayslipModal runId={id} employeeId={payslipEmployee} onClose={() => setPayslipEmployee(null)} />
    </Drawer>
  )
}

function PayslipModal({ runId, employeeId, onClose }: { runId: string | null; employeeId: string | null; onClose: () => void }) {
  const payslip = useQuery({ queryKey: ['payroll', 'payslip', runId, employeeId], queryFn: () => apiGet<Payslip>(`/api/payroll/runs/${runId}/payslips/${employeeId}`), enabled: !!runId && !!employeeId })
  const p = payslip.data
  const line: PayrollRunLine | undefined = p?.line
  const bd = line?.breakdown_json
  return (
    <Modal open={!!employeeId} onClose={onClose} title="Payslip" width={620} footer={<><Button onClick={onClose}>Close</Button><Button variant="primary" onClick={() => window.print()}><Printer size={13} /> Print</Button></>}>
      {payslip.isLoading && <LoadingSkeleton />}
      {payslip.isError && <InlineError error={payslip.error} />}
      {p && line && (
        <div className="space-y-3 text-[12.5px]" id="payslip">
          <div className="flex justify-between">
            <div><div className="font-black text-[14px]">STOCKPOINT PHARMA</div><div className="text-[var(--text-muted)]">Payslip · {MONTHS[p.run.period_month - 1]} {p.run.period_year} · {p.run.doc_number}</div></div>
            <StatusBadge status={p.run.status} />
          </div>
          <DescriptionList items={[{ label: 'Employee', value: `${p.employee?.employee_no ?? ''} · ${p.employee?.name ?? ''}` }, { label: 'Position', value: `${p.employee?.job_title ?? '—'}${p.employee?.department ? ` · ${p.employee.department}` : ''}` }, { label: 'KRA PIN', value: p.employee?.kra_pin ?? '—' }, { label: 'NSSF / SHIF', value: `${p.employee?.nssf_no ?? '—'} / ${p.employee?.shif_no ?? '—'}` }, { label: 'Bank', value: p.employee?.bank_name ?? '—' }, { label: 'Bands as of', value: formatDate(p.run.bands_as_of) }]} />
          <div className="grid grid-cols-2 gap-4">
            <table className="ui-table"><thead><tr><th colSpan={2}>Earnings</th></tr></thead><tbody>
              <tr><td>Basic</td><td className="text-right"><MoneyCell value={line.basic} /></td></tr>
              <tr><td>Allowances</td><td className="text-right"><MoneyCell value={line.allowances} /></td></tr>
              <tr><td>Overtime</td><td className="text-right"><MoneyCell value={line.overtime} /></td></tr>
              <tr className="font-bold"><td>Gross</td><td className="text-right"><MoneyCell value={line.gross} /></td></tr>
            </tbody></table>
            <table className="ui-table"><thead><tr><th colSpan={2}>Deductions</th></tr></thead><tbody>
              <tr><td>PAYE</td><td className="text-right"><MoneyCell value={line.paye} /></td></tr>
              <tr><td>NSSF</td><td className="text-right"><MoneyCell value={line.nssf_employee} /></td></tr>
              <tr><td>SHIF</td><td className="text-right"><MoneyCell value={line.shif} /></td></tr>
              <tr><td>Housing levy</td><td className="text-right"><MoneyCell value={line.housing_levy_employee} /></td></tr>
              <tr><td>Pension</td><td className="text-right"><MoneyCell value={line.pension_contribution} /></td></tr>
              <tr><td>Other</td><td className="text-right"><MoneyCell value={line.other_deductions} /></td></tr>
              <tr className="font-bold text-[14px]"><td>NET PAY</td><td className="text-right"><MoneyCell value={line.net} /></td></tr>
            </tbody></table>
          </div>
          {bd?.paye_bands && (
            <div>
              <div className="ui-label">PAYE by band (taxable {formatMoney(line.taxable)})</div>
              <table className="ui-table"><thead><tr><th>Band</th><th className="text-right">From</th><th className="text-right">To</th><th className="text-right">Rate</th><th className="text-right">Amount in band</th><th className="text-right">Tax</th></tr></thead><tbody>
                {bd.paye_bands.map((b) => (<tr key={b.band}><td className="tabular">{b.band}</td><td className="text-right"><MoneyCell value={b.lower} /></td><td className="text-right">{b.upper ? <MoneyCell value={b.upper} /> : '∞'}</td><td className="text-right tabular">{formatPct(b.rate_pct)}</td><td className="text-right"><MoneyCell value={b.amount_in_band} /></td><td className="text-right"><MoneyCell value={b.tax} /></td></tr>))}
                <tr><td colSpan={5} className="text-right">Gross tax before relief</td><td className="text-right"><MoneyCell value={bd.gross_tax_before_relief ?? null} /></td></tr>
                <tr><td colSpan={5} className="text-right">Personal relief</td><td className="text-right"><MoneyCell value={bd.personal_relief ? `-${bd.personal_relief}` : null} /></td></tr>
              </tbody></table>
            </div>
          )}
          {bd?.nssf_tiers && (
            <div>
              <div className="ui-label">NSSF tiers</div>
              <table className="ui-table"><thead><tr><th>Tier</th><th className="text-right">Pensionable</th><th className="text-right">Rate</th><th className="text-right">Employee</th></tr></thead><tbody>
                {bd.nssf_tiers.map((t) => (<tr key={t.tier}><td className="tabular">{t.tier}</td><td className="text-right"><MoneyCell value={t.pensionable} /></td><td className="text-right tabular">{formatPct(t.rate_pct)}</td><td className="text-right"><MoneyCell value={t.contribution} /></td></tr>))}
                <tr><td colSpan={3} className="text-right">Employer NSSF (not deducted)</td><td className="text-right"><MoneyCell value={line.nssf_employer} muted /></td></tr>
              </tbody></table>
            </div>
          )}
        </div>
      )}
    </Modal>
  )
}
