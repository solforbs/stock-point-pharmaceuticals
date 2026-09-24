import { Clock, Stethoscope, TestTubes, UserPlus, Users } from 'lucide-react'
import { Link } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { StatCard } from '../../components/ui/StatCard'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { NoAccess } from '../../components/ui/States'
import { formatDateTime, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import type { Encounter } from '../../lib/types'
import { patientAge, patientName, useEncounters } from './api'

/** The hospital module's landing page: today's queue at a glance. */
export default function HospitalDashboardPage() {
  const canView = usePermission('hospital.view')
  const open = useEncounters({ status: 'OPEN', per_page: 100 }, canView)

  if (!canView) {
    return (
      <Page>
        <PageHeader parent="Hospital" title="Dashboard" />
        <div className="ui-card"><NoAccess permission="hospital.view" /></div>
      </Page>
    )
  }

  const rows = open.data?.data ?? []
  const byStatus = (s: Encounter['status']) => rows.filter((e) => e.status === s).length

  const columns: Column<Encounter>[] = [
    { key: 'encounter_no', header: 'Encounter', render: (e) => <span className="font-semibold text-slate-900">{e.encounter_no}</span> },
    { key: 'patient', header: 'Patient', render: (e) => (
      <div>
        <div className="font-medium text-slate-900">{patientName(e.patient)}</div>
        <div className="text-xs text-slate-500">{e.patient?.patient_no} · {patientAge(e.patient)}</div>
      </div>
    ) },
    { key: 'facility', header: 'Facility', render: (e) => e.facility?.name ?? '—' },
    { key: 'department', header: 'Department', render: (e) => e.department?.name ?? '—' },
    { key: 'status', header: 'Status', render: (e) => <StatusBadge status={e.status} /> },
    { key: 'started_at', header: 'Arrived', render: (e) => <span className="text-slate-500">{formatDateTime(e.started_at)}</span> },
  ]

  return (
    <Page>
      <PageHeader
        parent="Hospital"
        title="Dashboard"
        subtitle="Today's queue across your facilities"
        actions={
          <Link to="/hospital/reception" className="inline-flex">
            <span className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 shadow-sm transition-colors">
              <UserPlus size={16} /> Reception
            </span>
          </Link>
        }
      />

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 mb-4">
        <StatCard label="Waiting" value={String(byStatus('WAITING') + byStatus('REGISTERED'))} icon={Clock} />
        <StatCard label="In consultation" value={String(byStatus('IN_CONSULTATION'))} icon={Stethoscope} />
        <StatCard label="Awaiting lab results" value={String(byStatus('AWAITING_RESULTS'))} icon={TestTubes} />
        <StatCard label="Open encounters" value={String(open.data?.total ?? rows.length)} icon={Users} />
      </div>

      <div className="ui-card">
        <DataTable<Encounter>
          columns={columns}
          rows={rows}
          rowKey={(e) => e.id}
          isLoading={open.isLoading}
          error={open.error}
          onRetry={open.refetch}
          emptyTitle={`No open encounters ${titleCase('today').toLowerCase()}`}
        />
      </div>
    </Page>
  )
}
