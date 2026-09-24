import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { ClipboardList, Search, UserPlus } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { useDebounced } from '../../components/ProductSearch'
import { Drawer } from '../../components/ui/Drawer'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { DrawerFooter, Field, FormSection, Input, PrimaryAction, Select, Textarea } from '../../components/ui/primitives'
import { EmptyState, InlineError, NoAccess } from '../../components/ui/States'
import { apiPost } from '../../lib/api'
import { usePatientFlowRealtime } from '../../hooks/usePatientFlowRealtime'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Encounter, Patient } from '../../lib/types'
import { patientAge, patientName, useFacilities, usePatientSearch } from './api'
import { PatientFormDrawer } from './PatientFormDrawer'

/**
 * Reception: find or register the patient, open the encounter, take the
 * consultation fee, and the patient lands in the clinician's queue.
 */
export default function ReceptionPage() {
  usePatientFlowRealtime()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const canManage = usePermission('hospital.encounter.manage')
  const canRegister = usePermission('hospital.patient.manage')

  const [query, setQuery] = useState('')
  const debounced = useDebounced(query)
  const search = usePatientSearch(debounced, canManage)
  const [registering, setRegistering] = useState(false)
  const [visitPatient, setVisitPatient] = useState<Patient | null>(null)

  if (!canManage) {
    return (
      <Page>
        <PageHeader parent="Hospital" title="Reception" />
        <div className="ui-card"><NoAccess permission="hospital.encounter.manage" /></div>
      </Page>
    )
  }

  const results = search.data?.data ?? []

  return (
    <Page>
      <PageHeader
        parent="Hospital"
        title="Reception"
        subtitle="Search for the patient, or register them once — then open the visit"
        actions={canRegister ? <PrimaryAction icon={UserPlus} onClick={() => setRegistering(true)}>Register patient</PrimaryAction> : null}
      />

      <div className="ui-card p-4">
        <div className="relative max-w-xl">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <Input
            className="pl-9"
            placeholder="Search by name, number, phone or national ID…"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            autoFocus
          />
        </div>

        <div className="mt-4">
          {debounced.trim().length < 2 ? (
            <EmptyState
              icon={<ClipboardList size={28} strokeWidth={1.5} />}
              title="Find the patient first"
              hint="Type at least two characters. If they have never visited, register them — once."
            />
          ) : results.length === 0 && !search.isFetching ? (
            <EmptyState
              title="No patient found"
              hint={`Nobody matches "${debounced}".`}
              action={canRegister ? <PrimaryAction icon={UserPlus} size="sm" onClick={() => setRegistering(true)}>Register new patient</PrimaryAction> : undefined}
            />
          ) : (
            <ul className="divide-y divide-slate-100">
              {results.map((p) => (
                <li key={p.id} className="flex items-center justify-between gap-3 py-3">
                  <div className="min-w-0">
                    <div className="font-semibold text-slate-900 truncate">{patientName(p)}</div>
                    <div className="text-xs text-slate-500">
                      {p.patient_no} · {p.sex ? p.sex.toLowerCase() : '—'} · {patientAge(p)} {p.phone ? `· ${p.phone}` : ''}
                    </div>
                  </div>
                  <PrimaryAction icon={ClipboardList} size="sm" onClick={() => setVisitPatient(p)}>
                    Start visit
                  </PrimaryAction>
                </li>
              ))}
            </ul>
          )}
        </div>
      </div>

      <PatientFormDrawer
        open={registering}
        onClose={() => setRegistering(false)}
        onSaved={(p) => setVisitPatient(p)}
      />

      <NewEncounterDrawer
        patient={visitPatient}
        onClose={() => setVisitPatient(null)}
        onCreated={(e) => {
          setVisitPatient(null)
          queryClient.invalidateQueries({ queryKey: ['hospital', 'encounters'] })
          toast.success(`${e.encounter_no} opened — patient is in the queue`)
          navigate(`/hospital/encounters?encounter=${e.id}`)
        }}
      />
    </Page>
  )
}

function NewEncounterDrawer({
  patient,
  onClose,
  onCreated,
}: {
  patient: Patient | null
  onClose: () => void
  onCreated: (encounter: Encounter) => void
}) {
  const facilities = useFacilities(!!patient)
  const hospitalFacilities = (facilities.data ?? []).filter((f) => f.offers_hospital && f.is_active)
  const [facilityId, setFacilityId] = useState('')
  const [departmentId, setDepartmentId] = useState('')
  const [encounterType, setEncounterType] = useState('OUTPATIENT')
  const [fee, setFee] = useState('')
  const [notes, setNotes] = useState('')

  const facility = hospitalFacilities.find((f) => f.id === facilityId) ?? (hospitalFacilities.length === 1 ? hospitalFacilities[0] : undefined)
  const effectiveFacilityId = facility?.id ?? ''

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<Encounter>('/api/hospital/encounters', {
        patient_id: patient!.id,
        facility_id: effectiveFacilityId,
        department_id: departmentId || null,
        encounter_type: encounterType,
        consultation_fee: fee || null,
        presenting_notes: notes || null,
      }),
    onSuccess: onCreated,
  })

  return (
    <Drawer
      open={!!patient}
      onClose={onClose}
      title="Open encounter"
      subtitle={patient ? `${patientName(patient)} · ${patient.patient_no}` : undefined}
      footer={
        <DrawerFooter
          onCancel={onClose}
          onSubmit={() => create.mutate()}
          submitLabel="Open encounter"
          isPending={create.isPending}
          disabled={!effectiveFacilityId}
        />
      }
    >
      <div className="space-y-5">
        <FormSection title="Visit" description="Where the patient is being seen and why they came.">
          <div className="grid grid-cols-2 gap-3">
            <Field label="Facility" required className="col-span-2">
              <Select value={effectiveFacilityId} onChange={(e) => { setFacilityId(e.target.value); setDepartmentId('') }}>
                <option value="">Select facility…</option>
                {hospitalFacilities.map((f) => (
                  <option key={f.id} value={f.id}>{f.name}{f.hospital_level ? ` · ${f.hospital_level.name}` : ''}</option>
                ))}
              </Select>
            </Field>
            <Field label="Department">
              <Select value={departmentId} onChange={(e) => setDepartmentId(e.target.value)} disabled={!facility}>
                <option value="">—</option>
                {(facility?.departments ?? []).map((d) => (
                  <option key={d.id} value={d.id}>{d.name}</option>
                ))}
              </Select>
            </Field>
            <Field label="Type">
              <Select value={encounterType} onChange={(e) => setEncounterType(e.target.value)}>
                <option value="OUTPATIENT">Outpatient</option>
                <option value="INPATIENT">Inpatient</option>
                <option value="EMERGENCY">Emergency</option>
                <option value="FOLLOW_UP">Follow-up</option>
              </Select>
            </Field>
            <Field label="Consultation fee" hint="Leave empty when the visit carries no fee.">
              <Input type="number" min="0" step="0.01" value={fee} onChange={(e) => setFee(e.target.value)} placeholder="0.00" />
            </Field>
          </div>
        </FormSection>
        <Field label="Presenting notes">
          <Textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Why the patient came in…" />
        </Field>
        {create.isError && <InlineError error={create.error} />}
      </div>
    </Drawer>
  )
}
