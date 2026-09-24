import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Activity, ClipboardList, FileText, FlaskConical, Pill, Plus, Send, Stethoscope, XCircle } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import { ProductSearch } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog, Modal } from '../../components/ui/Modal'
import { Page, PageHeader, FilterBar } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { Button, Field, FormSection, Input, Select, Textarea } from '../../components/ui/primitives'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { InlineError, NoAccess } from '../../components/ui/States'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { usePatientFlowRealtime } from '../../hooks/usePatientFlowRealtime'
import { apiPost } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { formatMoney } from '../../lib/money'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Consultation, Encounter, PrescriptionEstimate, Product } from '../../lib/types'
import { patientAge, patientName, useEncounter, useEncounters, useFacilities, useOrderableLabTests } from './api'

const STATUSES = ['OPEN', 'REGISTERED', 'WAITING', 'IN_CONSULTATION', 'AWAITING_RESULTS', 'COMPLETED', 'CANCELLED']

/**
 * The clinician's workspace: the queue, and inside one encounter everything
 * the visit produced — consultation, diagnoses, lab orders, prescriptions,
 * referrals and charges. The workflow stays flexible: nothing forces a lab
 * step or a prescription.
 */
export default function EncountersPage() {
  usePatientFlowRealtime()
  const perms = usePermissions()
  const canSee = perms.has('hospital.encounter.manage') || perms.has('hospital.consultation.manage') || perms.has('hospital.patient.view')

  const [params, setParams] = useSearchParams()
  const selectedId = params.get('encounter')
  const [status, setStatus] = useState('OPEN')
  const [page, setPage] = useState(1)

  const list = useEncounters({ status: status || undefined, page, per_page: 25 }, canSee)

  if (!canSee) {
    return (
      <Page>
        <PageHeader parent="Hospital" title="Encounters" />
        <div className="ui-card"><NoAccess permission="hospital.encounter.manage" /></div>
      </Page>
    )
  }

  function select(id: string | null) {
    setParams((prev) => {
      const next = new URLSearchParams(prev)
      if (id) next.set('encounter', id)
      else next.delete('encounter')
      return next
    })
  }

  const columns: Column<Encounter>[] = [
    { key: 'encounter_no', header: 'Encounter', render: (e) => <span className="font-semibold text-slate-900">{e.encounter_no}</span> },
    { key: 'patient', header: 'Patient', render: (e) => (
      <div>
        <div className="font-medium text-slate-900">{patientName(e.patient)}</div>
        <div className="text-xs text-slate-500">{e.patient?.patient_no} · {patientAge(e.patient)}</div>
      </div>
    ) },
    { key: 'facility', header: 'Facility', render: (e) => e.facility?.name ?? '—' },
    { key: 'clinician', header: 'Clinician', render: (e) => e.attending_clinician?.name ?? '—' },
    { key: 'status', header: 'Status', render: (e) => <StatusBadge status={e.status} /> },
    { key: 'started_at', header: 'Arrived', render: (e) => <span className="text-slate-500">{formatDateTime(e.started_at)}</span> },
  ]

  return (
    <Page>
      <PageHeader parent="Hospital" title="Encounters & Queue" subtitle="Every visit, from arrival to completion" />

      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {STATUSES.map((s) => <option key={s} value={s}>{titleCase(s)}</option>)}
          </Select>
        </Field>
      </FilterBar>

      <div className="ui-card">
        <DataTable<Encounter>
          columns={columns}
          rows={list.data?.data ?? []}
          rowKey={(e) => e.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={list.refetch}
          onRowClick={(e) => select(e.id)}
          selectedKey={selectedId ?? undefined}
          emptyTitle="No encounters match this filter"
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <EncounterDrawer encounterId={selectedId} onClose={() => select(null)} />
    </Page>
  )
}

function EncounterDrawer({ encounterId, onClose }: { encounterId: string | null; onClose: () => void }) {
  const perms = usePermissions()
  const queryClient = useQueryClient()
  const { data: encounter, error, refetch } = useEncounter(encounterId)
  const [cancelling, setCancelling] = useState(false)
  const [orderingLab, setOrderingLab] = useState(false)
  const [prescribing, setPrescribing] = useState(false)

  function refresh() {
    queryClient.invalidateQueries({ queryKey: ['hospital', 'encounter', encounterId] })
    queryClient.invalidateQueries({ queryKey: ['hospital', 'encounters'] })
  }

  const action = useMutation({
    meta: { silent: true },
    mutationFn: ({ path, body }: { path: string; body?: Record<string, unknown> }) =>
      apiPost(`/api/hospital/encounters/${encounterId}/${path}`, body ?? {}),
    onSuccess: () => refresh(),
    onError: () => refetch(),
  })

  const open = !!encounter && ['REGISTERED', 'WAITING', 'IN_CONSULTATION', 'AWAITING_RESULTS'].includes(encounter.status)
  const canConsult = perms.has('hospital.consultation.manage')
  const canManage = perms.has('hospital.encounter.manage')

  return (
    <Drawer
      open={!!encounterId}
      onClose={onClose}
      title={encounter?.encounter_no ?? 'Encounter'}
      subtitle={encounter ? `${patientName(encounter.patient)} · ${encounter.patient?.patient_no} · ${patientAge(encounter.patient)}` : undefined}
      width={720}
    >
      {error ? <InlineError error={error} /> : null}
      {encounter && (
        <div className="space-y-6">
          <div className="flex flex-wrap items-center gap-2">
            <StatusBadge status={encounter.status} />
            <span className="text-xs text-slate-500">
              {encounter.facility?.name}{encounter.department ? ` · ${encounter.department.name}` : ''} · {formatDateTime(encounter.started_at)}
            </span>
            <div className="ml-auto flex flex-wrap gap-2">
              {canConsult && ['REGISTERED', 'WAITING', 'AWAITING_RESULTS'].includes(encounter.status) && (
                <Button variant="primary" size="sm" onClick={() => action.mutate({ path: 'start-consultation' })} disabled={action.isPending}>
                  <Stethoscope size={14} className="mr-1.5" /> Start consultation
                </Button>
              )}
              {canConsult && open && (
                <Button variant="success" size="sm" onClick={() => action.mutate({ path: 'complete' })} disabled={action.isPending}>
                  Complete encounter
                </Button>
              )}
              {canManage && open && (
                <Button variant="danger" size="sm" onClick={() => setCancelling(true)} disabled={action.isPending}>
                  <XCircle size={14} className="mr-1.5" /> Cancel
                </Button>
              )}
            </div>
          </div>
          {action.isError && <InlineError error={action.error} />}
          {encounter.presenting_notes && (
            <div className="text-sm text-slate-600 bg-slate-50 border border-slate-100 rounded-xl px-3.5 py-2.5">
              <span className="font-semibold text-slate-700">Reception note:</span> {encounter.presenting_notes}
            </div>
          )}

          {canConsult && <ConsultationSection encounter={encounter} onSaved={refresh} />}

          <DiagnosesSection encounter={encounter} onSaved={refresh} />

          {/* Lab work */}
          <section>
            <SectionHead icon={<FlaskConical size={15} />} title="Laboratory">
              {perms.has('laboratory.order.create') && open && (
                <Button variant="secondary" size="xs" onClick={() => setOrderingLab(true)}>
                  <Plus size={13} className="mr-1" /> Order tests
                </Button>
              )}
            </SectionHead>
            {(encounter.lab_orders ?? []).length === 0 ? (
              <div className="text-sm text-slate-500">No lab work on this visit.</div>
            ) : (
              <div className="space-y-3">
                {(encounter.lab_orders ?? []).map((order) => (
                  <div key={order.id} className="border border-slate-200 rounded-xl p-3.5">
                    <div className="flex items-center gap-2 text-sm">
                      <span className="font-semibold text-slate-900">{order.order_no}</span>
                      <StatusBadge status={order.status} />
                      <span className="text-xs text-slate-500 ml-auto">{order.ordered_by?.name}</span>
                    </div>
                    <table className="ui-table mt-2">
                      <thead><tr><th>Test</th><th>Result</th><th>Normal range</th></tr></thead>
                      <tbody>
                        {(order.tests ?? []).map((t) => (
                          <tr key={t.id}>
                            <td>{t.test_name}</td>
                            <td className={t.is_abnormal ? 'text-rose-700 font-semibold' : 'font-medium'}>
                              {t.result_value ?? <span className="text-slate-400 font-normal">pending</span>}
                              {t.result_value && t.unit ? ` ${t.unit}` : ''}
                            </td>
                            <td className="text-slate-500">{t.normal_range ?? '—'}</td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                ))}
              </div>
            )}
          </section>

          {/* Prescriptions */}
          <section>
            <SectionHead icon={<Pill size={15} />} title="Prescriptions">
              {perms.has('hospital.prescription.create') && open && (
                <Button variant="secondary" size="xs" onClick={() => setPrescribing(true)}>
                  <Plus size={13} className="mr-1" /> Prescribe
                </Button>
              )}
            </SectionHead>
            {(encounter.prescriptions ?? []).length === 0 ? (
              <div className="text-sm text-slate-500">Nothing prescribed on this visit.</div>
            ) : (
              <div className="space-y-2">
                {(encounter.prescriptions ?? []).map((rx) => (
                  <div key={rx.id} className="border border-slate-200 rounded-xl p-3.5 text-sm">
                    <div className="flex items-center gap-2">
                      <span className="font-semibold text-slate-900">{rx.rx_no}</span>
                      <StatusBadge status={rx.status} />
                    </div>
                    <ul className="mt-1.5 text-slate-600 space-y-0.5">
                      {(rx.lines ?? []).map((l) => (
                        <li key={l.id}>
                          {l.medicine_name} — {l.quantity} {l.frequency ? `· ${l.frequency}` : ''} {l.duration ? `· ${l.duration}` : ''}
                        </li>
                      ))}
                    </ul>
                  </div>
                ))}
              </div>
            )}
          </section>

          <ReferralsSection encounter={encounter} onSaved={refresh} />

          <ChargesSection encounter={encounter} onSaved={refresh} />
        </div>
      )}

      <ConfirmDialog
        open={cancelling}
        title="Cancel this encounter?"
        message="The visit ends without completion. This cannot be undone."
        confirmLabel="Cancel encounter"
        danger
        requireReason="Reason for cancelling"
        isPending={action.isPending}
        onCancel={() => setCancelling(false)}
        onConfirm={(reason) => action.mutate({ path: 'cancel', body: { reason } }, { onSuccess: () => setCancelling(false) })}
      />

      {encounter && <LabOrderModal encounter={encounter} open={orderingLab} onClose={() => setOrderingLab(false)} onSaved={refresh} />}
      {encounter && <PrescribeModal encounter={encounter} open={prescribing} onClose={() => setPrescribing(false)} onSaved={refresh} />}
    </Drawer>
  )
}

function SectionHead({ icon, title, children }: { icon: React.ReactNode; title: string; children?: React.ReactNode }) {
  return (
    <div className="flex items-center gap-2 mb-2">
      <span className="text-slate-400">{icon}</span>
      <span className="text-xs font-semibold uppercase tracking-wider text-slate-400">{title}</span>
      <div className="ml-auto">{children}</div>
    </div>
  )
}

const VITALS: { key: string; label: string; placeholder: string }[] = [
  { key: 'temperature', label: 'Temp °C', placeholder: '36.8' },
  { key: 'bp', label: 'BP', placeholder: '120/80' },
  { key: 'pulse', label: 'Pulse', placeholder: '72' },
  { key: 'resp_rate', label: 'Resp', placeholder: '16' },
  { key: 'spo2', label: 'SpO₂ %', placeholder: '98' },
  { key: 'weight', label: 'Weight kg', placeholder: '64' },
]

function ConsultationSection({ encounter, onSaved }: { encounter: Encounter; onSaved: () => void }) {
  const existing: Consultation | undefined = (encounter.consultations ?? [])[0]
  const [form, setForm] = useState<Record<string, string>>({})
  const [vitals, setVitals] = useState<Record<string, string>>({})

  useEffect(() => {
    setForm({
      chief_complaint: existing?.chief_complaint ?? '',
      history: existing?.history ?? '',
      symptoms: existing?.symptoms ?? '',
      examination: existing?.examination ?? '',
      clinical_notes: existing?.clinical_notes ?? '',
      treatment_plan: existing?.treatment_plan ?? '',
      follow_up: existing?.follow_up ?? '',
      follow_up_date: existing?.follow_up_date?.slice(0, 10) ?? '',
    })
    setVitals((existing?.vital_signs as Record<string, string>) ?? {})
  }, [existing?.id])

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost(`/api/hospital/encounters/${encounter.id}/consultation`, {
        id: existing?.id,
        ...Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v])),
        vital_signs: Object.keys(vitals).length ? vitals : null,
      }),
    onSuccess: () => {
      toast.success('Consultation saved')
      onSaved()
    },
  })

  const fields: { key: string; label: string; rows?: number }[] = [
    { key: 'chief_complaint', label: 'Chief complaint' },
    { key: 'history', label: 'History of presenting illness' },
    { key: 'symptoms', label: 'Symptoms' },
    { key: 'examination', label: 'Physical examination' },
    { key: 'clinical_notes', label: 'Clinical notes' },
    { key: 'treatment_plan', label: 'Treatment plan' },
    { key: 'follow_up', label: 'Follow-up instructions' },
  ]

  return (
    <FormSection title="Consultation" icon={FileText} description={existing ? `Recorded by ${existing.clinician?.name ?? 'clinician'}` : undefined}>
      <div className="space-y-3">
        <div>
          <div className="flex items-center gap-1.5 text-xs font-semibold text-slate-500 mb-1.5"><Activity size={13} /> Vital signs</div>
          <div className="grid grid-cols-3 sm:grid-cols-6 gap-2">
            {VITALS.map((v) => (
              <Field key={v.key} label={v.label}>
                <Input value={vitals[v.key] ?? ''} placeholder={v.placeholder}
                  onChange={(e) => setVitals((prev) => ({ ...prev, [v.key]: e.target.value }))} />
              </Field>
            ))}
          </div>
        </div>
        {fields.map((f) => (
          <Field key={f.key} label={f.label}>
            <Textarea rows={2} value={form[f.key] ?? ''} onChange={(e) => setForm((prev) => ({ ...prev, [f.key]: e.target.value }))} />
          </Field>
        ))}
        <div className="flex items-end justify-between gap-3">
          <Field label="Follow-up date">
            <Input type="date" value={form.follow_up_date ?? ''} onChange={(e) => setForm((prev) => ({ ...prev, follow_up_date: e.target.value }))} />
          </Field>
          <Button variant="primary" size="sm" onClick={() => save.mutate()} disabled={save.isPending}>
            {save.isPending ? 'Saving…' : 'Save consultation'}
          </Button>
        </div>
        {save.isError && <InlineError error={save.error} />}
      </div>
    </FormSection>
  )
}

function DiagnosesSection({ encounter, onSaved }: { encounter: Encounter; onSaved: () => void }) {
  const perms = usePermissions()
  const [text, setText] = useState('')
  const [type, setType] = useState('PROVISIONAL')

  const add = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost(`/api/hospital/encounters/${encounter.id}/diagnoses`, { diagnosis: text, diagnosis_type: type }),
    onSuccess: () => {
      setText('')
      onSaved()
    },
  })

  return (
    <section>
      <SectionHead icon={<ClipboardList size={15} />} title="Diagnoses" />
      {(encounter.diagnoses ?? []).length > 0 && (
        <ul className="mb-2 space-y-1">
          {(encounter.diagnoses ?? []).map((d) => (
            <li key={d.id} className="text-sm text-slate-700 flex items-center gap-2">
              <StatusBadge status={d.diagnosis_type} />
              <span className="font-medium">{d.diagnosis}</span>
              {d.icd_code && <span className="text-xs text-slate-400">{d.icd_code}</span>}
              <span className="text-xs text-slate-400 ml-auto">{d.diagnosed_by?.name}</span>
            </li>
          ))}
        </ul>
      )}
      {perms.has('hospital.diagnosis.manage') && (
        <div className="flex gap-2">
          <Input className="flex-1" placeholder="Add a diagnosis…" value={text} onChange={(e) => setText(e.target.value)} />
          <Select className="w-36" value={type} onChange={(e) => setType(e.target.value)}>
            <option value="PROVISIONAL">Provisional</option>
            <option value="FINAL">Final</option>
          </Select>
          <Button variant="secondary" onClick={() => add.mutate()} disabled={!text.trim() || add.isPending}>Add</Button>
        </div>
      )}
      {add.isError && <InlineError error={add.error} className="mt-2" />}
    </section>
  )
}

function ReferralsSection({ encounter, onSaved }: { encounter: Encounter; onSaved: () => void }) {
  const perms = usePermissions()
  const [to, setTo] = useState('')
  const [reason, setReason] = useState('')

  const add = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost(`/api/hospital/encounters/${encounter.id}/referrals`, { referred_to: to, reason }),
    onSuccess: () => {
      setTo('')
      setReason('')
      toast.success('Referral recorded')
      onSaved()
    },
  })

  return (
    <section>
      <SectionHead icon={<Send size={15} />} title="Referrals" />
      {(encounter.referrals ?? []).map((r) => (
        <div key={r.id} className="text-sm text-slate-700 mb-1.5">
          <span className="font-medium">{r.referred_to}</span> — {r.reason}
        </div>
      ))}
      {perms.has('hospital.referral.manage') && (
        <div className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]">
          <Input placeholder="Referred to (facility / specialist)…" value={to} onChange={(e) => setTo(e.target.value)} />
          <Input placeholder="Reason…" value={reason} onChange={(e) => setReason(e.target.value)} />
          <Button variant="secondary" onClick={() => add.mutate()} disabled={!to.trim() || !reason.trim() || add.isPending}>Refer</Button>
        </div>
      )}
      {add.isError && <InlineError error={add.error} className="mt-2" />}
    </section>
  )
}

function ChargesSection({ encounter, onSaved }: { encounter: Encounter; onSaved: () => void }) {
  const perms = usePermissions()

  const settle = useMutation({
    meta: { silent: true },
    mutationFn: ({ chargeId, waive }: { chargeId: string; waive?: boolean }) =>
      apiPost(`/api/hospital/encounters/${encounter.id}/charges/${chargeId}/settle`, { waive }),
    onSuccess: () => onSaved(),
    onError: (e) => toast.error('Could not settle the charge', String((e as Error).message ?? '')),
  })

  if ((encounter.charges ?? []).length === 0) return null

  return (
    <section>
      <SectionHead icon={<ClipboardList size={15} />} title="Charges" />
      <table className="ui-table">
        <thead><tr><th>Type</th><th>Description</th><th className="text-right">Amount</th><th>Status</th><th /></tr></thead>
        <tbody>
          {(encounter.charges ?? []).map((c) => (
            <tr key={c.id}>
              <td>{titleCase(c.charge_type)}</td>
              <td>{c.description}</td>
              <td className="text-right tabular font-medium">{c.amount}</td>
              <td><StatusBadge status={c.status} /></td>
              <td className="text-right">
                {perms.has('hospital.encounter.manage') && c.status === 'PENDING' && (
                  <span className="inline-flex gap-1.5">
                    <Button variant="success" size="xs" onClick={() => settle.mutate({ chargeId: c.id })} disabled={settle.isPending}>Mark paid</Button>
                    <Button variant="ghost" size="xs" onClick={() => settle.mutate({ chargeId: c.id, waive: true })} disabled={settle.isPending}>Waive</Button>
                  </span>
                )}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  )
}

function LabOrderModal({ encounter, open, onClose, onSaved }: { encounter: Encounter; open: boolean; onClose: () => void; onSaved: () => void }) {
  const tests = useOrderableLabTests(open)
  const facilities = useFacilities(open)
  const labFacilities = (facilities.data ?? []).filter((f) => f.offers_laboratory && f.is_active)
  const [facilityId, setFacilityId] = useState('')
  const [picked, setPicked] = useState<Set<string>>(new Set())
  const [notes, setNotes] = useState('')

  const effectiveFacilityId = facilityId || (labFacilities.length === 1 ? labFacilities[0].id : (labFacilities.find((f) => f.id === encounter.facility_id)?.id ?? ''))

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost(`/api/hospital/encounters/${encounter.id}/lab-orders`, {
        facility_id: effectiveFacilityId,
        test_ids: [...picked],
        clinical_notes: notes || null,
      }),
    onSuccess: () => {
      toast.success('Lab order sent to the laboratory')
      setPicked(new Set())
      setNotes('')
      onSaved()
      onClose()
    },
  })

  return (
    <Modal open={open} onClose={onClose} title="Order laboratory tests" width={560}
      footer={
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
          <Button variant="primary" onClick={() => create.mutate()} disabled={picked.size === 0 || !effectiveFacilityId || create.isPending}>
            {create.isPending ? 'Sending…' : `Order ${picked.size || ''} test${picked.size === 1 ? '' : 's'}`}
          </Button>
        </div>
      }
    >
      <div className="space-y-4">
        <Field label="Laboratory" required>
          <Select value={effectiveFacilityId} onChange={(e) => setFacilityId(e.target.value)}>
            <option value="">Select laboratory…</option>
            {labFacilities.map((f) => <option key={f.id} value={f.id}>{f.name}</option>)}
          </Select>
        </Field>
        <Field label="Tests" required>
          <div className="max-h-64 overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100">
            {(tests.data ?? []).map((t) => (
              <label key={t.id} className="flex items-center gap-3 px-3.5 py-2.5 cursor-pointer hover:bg-slate-50">
                <input
                  type="checkbox"
                  className="w-4 h-4 rounded border-slate-300 text-blue-600"
                  checked={picked.has(t.id)}
                  onChange={(e) => setPicked((prev) => {
                    const next = new Set(prev)
                    if (e.target.checked) next.add(t.id)
                    else next.delete(t.id)
                    return next
                  })}
                />
                <span className="flex-1 text-sm">
                  <span className="font-medium text-slate-900">{t.name}</span>
                  <span className="text-xs text-slate-500 ml-2">{t.category?.name}{t.sample_type ? ` · ${t.sample_type}` : ''}</span>
                </span>
                <span className="text-sm tabular text-slate-600">{t.price}</span>
              </label>
            ))}
            {(tests.data ?? []).length === 0 && <div className="p-4 text-sm text-slate-500">No tests configured yet.</div>}
          </div>
        </Field>
        <Field label="Clinical notes for the lab">
          <Textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Fever and headache for 3 days…" />
        </Field>
        {create.isError && <InlineError error={create.error} />}
      </div>
    </Modal>
  )
}

type RxLine = { product: Product | null; medicine_name: string; quantity: string; frequency: string; duration: string; instructions: string }

function PrescribeModal({ encounter, open, onClose, onSaved }: { encounter: Encounter; open: boolean; onClose: () => void; onSaved: () => void }) {
  const { data: user } = useCurrentUser()
  const [lines, setLines] = useState<RxLine[]>([])
  const [freeText, setFreeText] = useState('')
  const [branchId, setBranchId] = useState<string>('')
  const [notes, setNotes] = useState('')

  // Default destination: the facility's own pharmacy branch, when it has one.
  const facilityBranch = encounter.facility?.pharmacy_branch_id ?? null
  const effectiveBranch = branchId !== '' ? (branchId === 'NONE' ? null : branchId) : facilityBranch

  // What the pharmacy till will charge for the stocked lines — the same
  // pricing engine dispensing uses, so the patient hears the real number.
  const stockedLines = lines.filter((l) => l.product && Number(l.quantity) > 0)
  const estimate = useQuery({
    queryKey: ['hospital', 'rx-estimate', effectiveBranch, stockedLines.map((l) => `${l.product!.id}:${l.quantity}`).join('|')],
    queryFn: () =>
      apiPost<PrescriptionEstimate | null>('/api/hospital/prescription-estimate', {
        branch_id: effectiveBranch,
        lines: stockedLines.map((l) => ({ product_id: l.product!.id, quantity: l.quantity })),
      }),
    enabled: open && !!effectiveBranch && stockedLines.length > 0,
    staleTime: 30_000,
    placeholderData: (prev) => prev,
  })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost(`/api/hospital/encounters/${encounter.id}/prescriptions`, {
        branch_id: effectiveBranch,
        notes: notes || null,
        lines: lines.map((l) => ({
          product_id: l.product?.id ?? null,
          medicine_name: l.product ? null : l.medicine_name,
          quantity: l.quantity,
          frequency: l.frequency || null,
          duration: l.duration || null,
          instructions: l.instructions || null,
        })),
      }),
    onSuccess: () => {
      toast.success(effectiveBranch ? 'Prescription sent to the pharmacy' : 'Prescription recorded')
      setLines([])
      setNotes('')
      onSaved()
      onClose()
    },
  })

  function addProduct(product: Product) {
    setLines((prev) => [...prev, { product, medicine_name: product.name, quantity: '', frequency: '', duration: '', instructions: '' }])
  }

  function addFreeText() {
    if (!freeText.trim()) return
    setLines((prev) => [...prev, { product: null, medicine_name: freeText.trim(), quantity: '', frequency: '', duration: '', instructions: '' }])
    setFreeText('')
  }

  function setLine(i: number, patch: Partial<RxLine>) {
    setLines((prev) => prev.map((l, idx) => (idx === i ? { ...l, ...patch } : l)))
  }

  const valid = lines.length > 0 && lines.every((l) => l.quantity && Number(l.quantity) > 0)

  return (
    <Modal open={open} onClose={onClose} title="Write prescription" width={640}
      footer={
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
          <Button variant="primary" onClick={() => create.mutate()} disabled={!valid || create.isPending}>
            {create.isPending ? 'Sending…' : 'Create prescription'}
          </Button>
        </div>
      }
    >
      <div className="space-y-4">
        <Field label="Add stocked medicine" hint="Search the pharmacy catalogue. Quantities are in dispensing units (tablets, capsules…).">
          <ProductSearch onSelect={addProduct} endpoint="/api/hospital/medicines" />
        </Field>
        <Field label="Or a medicine the pharmacy does not stock">
          <div className="flex gap-2">
            <Input className="flex-1" value={freeText} onChange={(e) => setFreeText(e.target.value)} placeholder="e.g. Artemether/Lumefantrine 80/480mg" />
            <Button variant="secondary" onClick={addFreeText} disabled={!freeText.trim()}>Add</Button>
          </div>
        </Field>

        {lines.length > 0 && (
          <div className="space-y-2.5">
            {lines.map((l, i) => (
              <div key={i} className="border border-slate-200 rounded-xl p-3">
                <div className="flex items-center gap-2 text-sm">
                  <Pill size={14} className="text-slate-400" />
                  <span className="font-medium text-slate-900 flex-1">{l.medicine_name}</span>
                  {!l.product && <span className="text-[10px] font-semibold uppercase tracking-wide text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">external</span>}
                  <Button variant="ghost" size="xs" onClick={() => setLines((prev) => prev.filter((_, idx) => idx !== i))}>Remove</Button>
                </div>
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 mt-2">
                  <Field label="Quantity" required>
                    <Input type="number" min="0" value={l.quantity} onChange={(e) => setLine(i, { quantity: e.target.value })} />
                  </Field>
                  <Field label="Frequency">
                    <Input value={l.frequency} placeholder="3 times daily" onChange={(e) => setLine(i, { frequency: e.target.value })} />
                  </Field>
                  <Field label="Duration">
                    <Input value={l.duration} placeholder="7 days" onChange={(e) => setLine(i, { duration: e.target.value })} />
                  </Field>
                  <Field label="Instructions">
                    <Input value={l.instructions} placeholder="After meals" onChange={(e) => setLine(i, { instructions: e.target.value })} />
                  </Field>
                </div>
              </div>
            ))}
          </div>
        )}

        {estimate.data && stockedLines.length > 0 && (
          <div className="rounded-xl bg-slate-50 border border-slate-200 px-3.5 py-2.5 text-sm space-y-1">
            {estimate.data.lines.map((l) => (
              <div key={l.product_id} className="flex items-center justify-between text-xs text-slate-600">
                <span className="truncate">{l.product_name} · {Number(l.quantity)} {l.uom_code} × {formatMoney(l.unit_price)}</span>
                <span className="font-medium text-slate-800">{formatMoney(l.line_total)}</span>
              </div>
            ))}
            <div className="flex items-center justify-between border-t border-slate-200 pt-1.5">
              <span className="text-slate-600">Charge at the pharmacy (incl. tax)</span>
              <span className="font-bold text-slate-900">{formatMoney(estimate.data.grand_total)}</span>
            </div>
          </div>
        )}

        <div className="grid grid-cols-2 gap-3">
          <Field label="Send to pharmacy" hint="An external prescription is printed for the patient instead.">
            <Select value={branchId || (facilityBranch ?? 'NONE')} onChange={(e) => setBranchId(e.target.value)}>
              <option value="NONE">No pharmacy — external</option>
              {(user?.branches ?? []).map((b) => (
                <option key={b.id} value={b.id}>{b.name}</option>
              ))}
              {facilityBranch && !(user?.branches ?? []).some((b) => b.id === facilityBranch) && (
                <option value={facilityBranch}>Facility pharmacy</option>
              )}
            </Select>
          </Field>
          <Field label="Notes">
            <Input value={notes} onChange={(e) => setNotes(e.target.value)} />
          </Field>
        </div>
        {create.isError && <InlineError error={create.error} />}
      </div>
    </Modal>
  )
}
