import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Building2, Plus } from 'lucide-react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { Badge } from '../../components/ui/Badge'
import { Button, DrawerFooter, Field, FormSection, Input, PrimaryAction, Select } from '../../components/ui/primitives'
import { InlineError, NoAccess } from '../../components/ui/States'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiPatch, apiPost } from '../../lib/api'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Facility } from '../../lib/types'
import { useFacilities, useHospitalLevels } from './api'

/**
 * Facilities and their service mix. A facility can offer any combination of
 * hospital, laboratory and pharmacy services; its pharmacy points at an
 * existing pharmacy branch. Hospital levels come from the configurable
 * catalogue, never a hard-coded list.
 */
export default function HospitalSetupPage() {
  const canView = usePermission('hospital.view')
  const canManage = usePermission('hospital.manage')
  const facilities = useFacilities(canView)
  const [editing, setEditing] = useState<Facility | null>(null)
  const [creating, setCreating] = useState(false)

  if (!canView) {
    return (
      <Page>
        <PageHeader parent="Hospital" title="Facilities & Setup" />
        <div className="ui-card"><NoAccess permission="hospital.view" /></div>
      </Page>
    )
  }

  const columns: Column<Facility>[] = [
    { key: 'code', header: 'Code', render: (f) => <span className="font-semibold text-slate-900">{f.code}</span> },
    { key: 'name', header: 'Facility', render: (f) => f.name },
    { key: 'level', header: 'Level', render: (f) => f.hospital_level?.name ?? '—' },
    { key: 'services', header: 'Services', render: (f) => (
      <span className="inline-flex gap-1.5">
        {f.offers_hospital && <Badge>Hospital</Badge>}
        {f.offers_laboratory && <Badge>Laboratory</Badge>}
        {f.offers_pharmacy && <Badge>Pharmacy</Badge>}
      </span>
    ) },
    { key: 'pharmacy', header: 'Pharmacy branch', render: (f) => f.pharmacy_branch?.name ?? '—' },
    { key: 'departments', header: 'Departments', render: (f) => String(f.departments?.length ?? 0) },
    { key: 'active', header: 'Status', render: (f) => (f.is_active ? <Badge color="success">Active</Badge> : <Badge color="neutral">Inactive</Badge>) },
  ]

  return (
    <Page>
      <PageHeader
        parent="Hospital"
        title="Facilities & Setup"
        subtitle="A facility offers any mix of hospital, laboratory and pharmacy services"
        actions={canManage ? <PrimaryAction icon={Plus} onClick={() => setCreating(true)}>New facility</PrimaryAction> : null}
      />

      <div className="ui-card">
        <DataTable<Facility>
          columns={columns}
          rows={facilities.data ?? []}
          rowKey={(f) => f.id}
          isLoading={facilities.isLoading}
          error={facilities.error}
          onRetry={facilities.refetch}
          onRowClick={canManage ? (f) => setEditing(f) : undefined}
          emptyTitle="No facilities configured yet"
        />
      </div>

      <FacilityDrawer open={creating || !!editing} facility={editing} onClose={() => { setCreating(false); setEditing(null) }} />
    </Page>
  )
}

type Form = {
  code: string
  name: string
  hospital_level_id: string
  offers_hospital: boolean
  offers_laboratory: boolean
  offers_pharmacy: boolean
  pharmacy_branch_id: string
  phone: string
  email: string
  address: string
  is_active: boolean
}

const EMPTY: Form = {
  code: '', name: '', hospital_level_id: '', offers_hospital: true, offers_laboratory: false,
  offers_pharmacy: false, pharmacy_branch_id: '', phone: '', email: '', address: '', is_active: true,
}

function FacilityDrawer({ open, facility, onClose }: { open: boolean; facility: Facility | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()
  const levels = useHospitalLevels(open)
  const [form, setForm] = useState<Form>(EMPTY)
  const [newDepartment, setNewDepartment] = useState('')

  useEffect(() => {
    if (!open) return
    setForm(facility ? {
      code: facility.code, name: facility.name,
      hospital_level_id: facility.hospital_level_id ?? '',
      offers_hospital: facility.offers_hospital, offers_laboratory: facility.offers_laboratory,
      offers_pharmacy: facility.offers_pharmacy, pharmacy_branch_id: facility.pharmacy_branch_id ?? '',
      phone: facility.phone ?? '', email: facility.email ?? '', address: facility.address ?? '',
      is_active: facility.is_active,
    } : EMPTY)
    setNewDepartment('')
  }, [open, facility])

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const payload = {
        ...form,
        hospital_level_id: form.hospital_level_id || null,
        pharmacy_branch_id: form.offers_pharmacy ? form.pharmacy_branch_id || null : null,
        phone: form.phone || null,
        email: form.email || null,
        address: form.address || null,
      }
      return facility
        ? apiPatch<Facility>(`/api/hospital/facilities/${facility.id}`, payload)
        : apiPost<Facility>('/api/hospital/facilities', payload)
    },
    onSuccess: () => {
      toast.success(facility ? 'Facility updated' : 'Facility created')
      queryClient.invalidateQueries({ queryKey: ['hospital', 'facilities'] })
      onClose()
    },
  })

  const addDepartment = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost(`/api/hospital/facilities/${facility!.id}/departments`, { name: newDepartment.trim() }),
    onSuccess: () => {
      setNewDepartment('')
      queryClient.invalidateQueries({ queryKey: ['hospital', 'facilities'] })
    },
  })

  function set<K extends keyof Form>(key: K, value: Form[K]) {
    setForm((f) => ({ ...f, [key]: value }))
  }

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title={facility ? `Edit ${facility.code}` : 'New facility'}
      width={560}
      footer={
        <DrawerFooter
          onCancel={onClose}
          onSubmit={() => save.mutate()}
          submitLabel={facility ? 'Save changes' : 'Create facility'}
          isPending={save.isPending}
          disabled={!form.code.trim() || !form.name.trim()}
        />
      }
    >
      <div className="space-y-5">
        <FormSection title="Facility" icon={Building2}>
          <div className="grid grid-cols-2 gap-3">
            <Field label="Code" required>
              <Input value={form.code} onChange={(e) => set('code', e.target.value.toUpperCase())} placeholder="NMC" />
            </Field>
            <Field label="Hospital level">
              <Select value={form.hospital_level_id} onChange={(e) => set('hospital_level_id', e.target.value)}>
                <option value="">—</option>
                {(levels.data ?? []).map((l) => <option key={l.id} value={l.id}>{l.name}</option>)}
              </Select>
            </Field>
            <Field label="Name" required className="col-span-2">
              <Input value={form.name} onChange={(e) => set('name', e.target.value)} placeholder="Nairobi Medical Centre" />
            </Field>
            <Field label="Phone"><Input value={form.phone} onChange={(e) => set('phone', e.target.value)} /></Field>
            <Field label="Email"><Input type="email" value={form.email} onChange={(e) => set('email', e.target.value)} /></Field>
            <Field label="Address" className="col-span-2"><Input value={form.address} onChange={(e) => set('address', e.target.value)} /></Field>
          </div>
        </FormSection>

        <FormSection title="Services" description="Nothing is forced: a diagnostic centre is laboratory-only, a chemist pharmacy-only.">
          <div className="space-y-2.5">
            {([
              ['offers_hospital', 'Hospital — patients, reception, clinical services'],
              ['offers_laboratory', 'Laboratory — test orders, samples, results'],
              ['offers_pharmacy', 'Pharmacy — dispensing through an existing pharmacy branch'],
            ] as const).map(([key, label]) => (
              <label key={key} className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" className="w-4 h-4 rounded border-slate-300 text-blue-600" checked={form[key]} onChange={(e) => set(key, e.target.checked)} />
                {label}
              </label>
            ))}
            {form.offers_pharmacy && (
              <Field label="Dispensing pharmacy branch" hint="The existing pharmacy that fills this facility's prescriptions.">
                <Select value={form.pharmacy_branch_id} onChange={(e) => set('pharmacy_branch_id', e.target.value)}>
                  <option value="">—</option>
                  {(user?.branches ?? []).map((b) => <option key={b.id} value={b.id}>{b.name}</option>)}
                </Select>
              </Field>
            )}
            {facility && (
              <label className="flex items-center gap-2.5 text-sm text-slate-700 cursor-pointer pt-1">
                <input type="checkbox" className="w-4 h-4 rounded border-slate-300 text-blue-600" checked={form.is_active} onChange={(e) => set('is_active', e.target.checked)} />
                Facility is active
              </label>
            )}
          </div>
        </FormSection>

        {facility && (
          <FormSection title="Departments" description="Only the departments this facility actually runs.">
            <div className="space-y-2">
              <ul className="flex flex-wrap gap-1.5">
                {(facility.departments ?? []).map((d) => <li key={d.id}><Badge>{d.name}</Badge></li>)}
                {(facility.departments ?? []).length === 0 && <li className="text-sm text-slate-500">None yet.</li>}
              </ul>
              <div className="flex gap-2">
                <Input className="flex-1" placeholder="Add a department (Outpatient, Maternity…)" value={newDepartment} onChange={(e) => setNewDepartment(e.target.value)} />
                <Button variant="secondary" onClick={() => addDepartment.mutate()} disabled={!newDepartment.trim() || addDepartment.isPending}>Add</Button>
              </div>
              {addDepartment.isError && <InlineError error={addDepartment.error} />}
            </div>
          </FormSection>
        )}

        {save.isError && <InlineError error={save.error} />}
      </div>
    </Drawer>
  )
}
