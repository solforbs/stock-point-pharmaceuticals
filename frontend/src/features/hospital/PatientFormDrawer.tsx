import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { UserPlus } from 'lucide-react'
import { Drawer } from '../../components/ui/Drawer'
import { DrawerFooter, Field, FormSection, Input, Select, Textarea } from '../../components/ui/primitives'
import { InlineError } from '../../components/ui/States'
import { apiPatch, apiPost } from '../../lib/api'
import { toast } from '../../lib/toast'
import type { Patient } from '../../lib/types'

type Form = {
  first_name: string
  last_name: string
  sex: string
  date_of_birth: string
  phone: string
  email: string
  national_id: string
  address: string
  next_of_kin_name: string
  next_of_kin_phone: string
  notes: string
}

const EMPTY: Form = {
  first_name: '', last_name: '', sex: '', date_of_birth: '', phone: '', email: '',
  national_id: '', address: '', next_of_kin_name: '', next_of_kin_phone: '', notes: '',
}

/**
 * Register a new patient, or edit an existing record. One person, one
 * record — the drawer is reception's "not found, register them" step.
 */
export function PatientFormDrawer({
  open,
  onClose,
  patient,
  onSaved,
}: {
  open: boolean
  onClose: () => void
  patient?: Patient | null
  onSaved?: (patient: Patient) => void
}) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<Form>(EMPTY)

  useEffect(() => {
    if (!open) return
    setForm(patient ? {
      first_name: patient.first_name ?? '', last_name: patient.last_name ?? '', sex: patient.sex ?? '',
      date_of_birth: patient.date_of_birth?.slice(0, 10) ?? '', phone: patient.phone ?? '', email: patient.email ?? '',
      national_id: patient.national_id ?? '', address: patient.address ?? '',
      next_of_kin_name: patient.next_of_kin_name ?? '', next_of_kin_phone: patient.next_of_kin_phone ?? '',
      notes: patient.notes ?? '',
    } : EMPTY)
  }, [open, patient])

  const save = useMutation({
    meta: { silent: true },
    mutationFn: async () => {
      const payload = Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))
      return patient
        ? apiPatch<Patient>(`/api/hospital/patients/${patient.id}`, payload)
        : apiPost<Patient>('/api/hospital/patients', payload)
    },
    onSuccess: (saved) => {
      toast.success(patient ? 'Patient updated' : `Registered ${saved.patient_no}`)
      queryClient.invalidateQueries({ queryKey: ['hospital', 'patients'] })
      onSaved?.(saved)
      onClose()
    },
  })

  function set<K extends keyof Form>(key: K, value: string) {
    setForm((f) => ({ ...f, [key]: value }))
  }

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={patient ? `Edit ${patient.patient_no}` : 'Register patient'}
      subtitle={patient ? undefined : 'One person, one record — repeat visits become encounters'}
      footer={
        <DrawerFooter
          onCancel={onClose}
          onSubmit={() => save.mutate()}
          submitLabel={patient ? 'Save changes' : 'Register patient'}
          isPending={save.isPending}
          disabled={!form.first_name.trim() || !form.last_name.trim()}
        />
      }
    >
      <div className="space-y-5">
        <FormSection title="Identity" icon={UserPlus}>
          <div className="grid grid-cols-2 gap-3">
            <Field label="First name" required>
              <Input value={form.first_name} onChange={(e) => set('first_name', e.target.value)} autoFocus />
            </Field>
            <Field label="Last name" required>
              <Input value={form.last_name} onChange={(e) => set('last_name', e.target.value)} />
            </Field>
            <Field label="Sex">
              <Select value={form.sex} onChange={(e) => set('sex', e.target.value)}>
                <option value="">—</option>
                <option value="MALE">Male</option>
                <option value="FEMALE">Female</option>
                <option value="OTHER">Other</option>
              </Select>
            </Field>
            <Field label="Date of birth">
              <Input type="date" value={form.date_of_birth} onChange={(e) => set('date_of_birth', e.target.value)} />
            </Field>
            <Field label="National ID">
              <Input value={form.national_id} onChange={(e) => set('national_id', e.target.value)} />
            </Field>
            <Field label="Phone">
              <Input value={form.phone} onChange={(e) => set('phone', e.target.value)} placeholder="07…" />
            </Field>
          </div>
        </FormSection>

        <FormSection title="Contact & next of kin">
          <div className="grid grid-cols-2 gap-3">
            <Field label="Email" className="col-span-2">
              <Input type="email" value={form.email} onChange={(e) => set('email', e.target.value)} />
            </Field>
            <Field label="Address" className="col-span-2">
              <Input value={form.address} onChange={(e) => set('address', e.target.value)} />
            </Field>
            <Field label="Next of kin">
              <Input value={form.next_of_kin_name} onChange={(e) => set('next_of_kin_name', e.target.value)} />
            </Field>
            <Field label="Next of kin phone">
              <Input value={form.next_of_kin_phone} onChange={(e) => set('next_of_kin_phone', e.target.value)} />
            </Field>
            <Field label="Notes" className="col-span-2">
              <Textarea rows={2} value={form.notes} onChange={(e) => set('notes', e.target.value)} />
            </Field>
          </div>
        </FormSection>

        {save.isError && <InlineError error={save.error} />}
      </div>
    </Drawer>
  )
}
