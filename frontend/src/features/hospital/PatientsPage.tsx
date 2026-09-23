import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Pencil, Search, UserPlus } from 'lucide-react'
import { Link, useSearchParams } from 'react-router-dom'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { Page, PageHeader, FilterBar } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { Button, DescriptionList, Field, Input, PrimaryAction } from '../../components/ui/primitives'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { NoAccess } from '../../components/ui/States'
import { apiGet } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import type { Paginated, Patient } from '../../lib/types'
import { patientAge, patientName } from './api'
import { PatientFormDrawer } from './PatientFormDrawer'

/** The central patient registry: one lifetime record per person. */
export default function PatientsPage() {
  const canView = usePermission('hospital.patient.view')
  const canManage = usePermission('hospital.patient.manage')

  const [params, setParams] = useSearchParams()
  const selectedId = params.get('patient')
  const [query, setQuery] = useState('')
  const debounced = useDebounced(query)
  const [page, setPage] = useState(1)
  const [registering, setRegistering] = useState(false)
  const [editing, setEditing] = useState(false)

  const list = useQuery({
    queryKey: ['hospital', 'patients', 'list', debounced, page],
    queryFn: () => apiGet<Paginated<Patient>>('/api/hospital/patients', { q: debounced || undefined, page, per_page: 25 }),
    enabled: canView,
    placeholderData: (prev) => prev,
  })

  const detail = useQuery({
    queryKey: ['hospital', 'patient', selectedId],
    queryFn: () => apiGet<Patient>(`/api/hospital/patients/${selectedId}`),
    enabled: canView && !!selectedId,
  })

  if (!canView) {
    return (
      <Page>
        <PageHeader parent="Hospital" title="Patients" />
        <div className="ui-card"><NoAccess permission="hospital.patient.view" /></div>
      </Page>
    )
  }

  function select(id: string | null) {
    setParams((prev) => {
      const next = new URLSearchParams(prev)
      if (id) next.set('patient', id)
      else next.delete('patient')
      return next
    })
  }

  const columns: Column<Patient>[] = [
    { key: 'patient_no', header: 'Number', render: (p) => <span className="font-semibold text-slate-900">{p.patient_no}</span> },
    { key: 'name', header: 'Name', render: (p) => patientName(p), sortValue: (p) => `${p.last_name} ${p.first_name}` },
    { key: 'sex', header: 'Sex', render: (p) => (p.sex ? p.sex.charAt(0) + p.sex.slice(1).toLowerCase() : '—') },
    { key: 'age', header: 'Age', render: (p) => patientAge(p) },
    { key: 'phone', header: 'Phone', render: (p) => p.phone ?? '—' },
    { key: 'national_id', header: 'National ID', render: (p) => p.national_id ?? '—' },
  ]

  const selected = detail.data

  return (
    <Page>
      <PageHeader
        parent="Hospital"
        title="Patients"
        subtitle="One person, one record — visits attach as encounters"
        actions={canManage ? <PrimaryAction icon={UserPlus} onClick={() => setRegistering(true)}>Register patient</PrimaryAction> : null}
      />

      <FilterBar>
        <Field label="Search">
          <div className="relative">
            <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <Input className="pl-8 w-72" placeholder="Name, number, phone, ID…" value={query} onChange={(e) => { setQuery(e.target.value); setPage(1) }} />
          </div>
        </Field>
      </FilterBar>

      <div className="ui-card">
        <DataTable<Patient>
          columns={columns}
          rows={list.data?.data ?? []}
          rowKey={(p) => p.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={list.refetch}
          onRowClick={(p) => select(p.id)}
          selectedKey={selectedId ?? undefined}
          emptyTitle="No patients registered yet"
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer
        open={!!selectedId}
        onClose={() => select(null)}
        title={selected ? patientName(selected) : 'Patient'}
        subtitle={selected?.patient_no}
        width={560}
        footer={
          canManage && selected ? (
            <div className="flex justify-end">
              <Button variant="secondary" onClick={() => setEditing(true)}>
                <Pencil size={14} className="mr-1.5" /> Edit record
              </Button>
            </div>
          ) : undefined
        }
      >
        {selected && (
          <div className="space-y-6">
            <DescriptionList
              items={[
                { label: 'Sex', value: selected.sex ? selected.sex.toLowerCase() : '—' },
                { label: 'Date of birth', value: selected.date_of_birth ? `${formatDate(selected.date_of_birth)} (${patientAge(selected)})` : '—' },
                { label: 'Phone', value: selected.phone },
                { label: 'Email', value: selected.email },
                { label: 'National ID', value: selected.national_id },
                { label: 'Address', value: selected.address },
                { label: 'Next of kin', value: selected.next_of_kin_name ? `${selected.next_of_kin_name} (${selected.next_of_kin_phone ?? 'no phone'})` : '—' },
                { label: 'Notes', value: selected.notes },
              ]}
            />

            <div>
              <div className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Visit history</div>
              {(selected.encounters ?? []).length === 0 ? (
                <div className="text-sm text-slate-500">No encounters yet.</div>
              ) : (
                <table className="ui-table">
                  <thead>
                    <tr><th>Encounter</th><th>Facility</th><th>Status</th><th>Date</th></tr>
                  </thead>
                  <tbody>
                    {(selected.encounters ?? []).map((e) => (
                      <tr key={e.id}>
                        <td>
                          <Link className="text-blue-700 font-medium hover:underline" to={`/hospital/encounters?encounter=${e.id}`}>
                            {e.encounter_no}
                          </Link>
                        </td>
                        <td>{e.facility?.name ?? '—'}</td>
                        <td><StatusBadge status={e.status} /></td>
                        <td className="text-slate-500">{formatDateTime(e.started_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </div>
        )}
      </Drawer>

      <PatientFormDrawer open={registering} onClose={() => setRegistering(false)} />
      <PatientFormDrawer open={editing} onClose={() => setEditing(false)} patient={selected ?? null} />
    </Page>
  )
}
