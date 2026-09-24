import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Beaker, CheckCircle2, Droplets, PlayCircle } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog, Modal } from '../../components/ui/Modal'
import { Page, PageHeader, FilterBar } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { Button, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { InlineError, NoAccess } from '../../components/ui/States'
import { apiGet, apiPost } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { LabOrder, Paginated } from '../../lib/types'
import { patientAge, patientName } from '../hospital/api'

const STATUSES = ['PENDING', 'ACCEPTED', 'SAMPLE_COLLECTED', 'PROCESSING', 'COMPLETED', 'CANCELLED']

/**
 * The laboratory bench: the order queue and the guarded workflow —
 * accept → collect sample → process → enter results. Each step needs its
 * own permission and records who did it, when.
 */
export default function LabOrdersPage() {
  const perms = usePermissions()
  const canView = perms.has('laboratory.view')

  const [params, setParams] = useSearchParams()
  const selectedId = params.get('order')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)

  const list = useQuery({
    queryKey: ['laboratory', 'orders', status, page],
    queryFn: () => apiGet<Paginated<LabOrder>>('/api/laboratory/orders', { status: status || undefined, page, per_page: 25 }),
    enabled: canView,
    placeholderData: (prev) => prev,
  })

  if (!canView) {
    return (
      <Page>
        <PageHeader parent="Laboratory" title="Lab Orders" />
        <div className="ui-card"><NoAccess permission="laboratory.view" /></div>
      </Page>
    )
  }

  function select(id: string | null) {
    setParams((prev) => {
      const next = new URLSearchParams(prev)
      if (id) next.set('order', id)
      else next.delete('order')
      return next
    })
  }

  const columns: Column<LabOrder>[] = [
    { key: 'order_no', header: 'Order', render: (o) => <span className="font-semibold text-slate-900">{o.order_no}</span> },
    { key: 'patient', header: 'Patient', render: (o) => (
      <div>
        <div className="font-medium text-slate-900">{patientName(o.patient)}</div>
        <div className="text-xs text-slate-500">{o.patient?.patient_no} · {patientAge(o.patient)}</div>
      </div>
    ) },
    { key: 'facility', header: 'Laboratory', render: (o) => o.facility?.name ?? '—' },
    { key: 'tests', header: 'Tests', align: 'right', render: (o) => String(o.tests_count ?? o.tests?.length ?? 0) },
    { key: 'ordered_by', header: 'Requested by', render: (o) => o.ordered_by?.name ?? '—' },
    { key: 'status', header: 'Status', render: (o) => <StatusBadge status={o.status} /> },
    { key: 'created_at', header: 'Received', render: (o) => <span className="text-slate-500">{formatDateTime(o.created_at)}</span> },
  ]

  return (
    <Page>
      <PageHeader parent="Laboratory" title="Lab Orders" subtitle="The bench queue — accept, collect, process, result" />

      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {STATUSES.map((s) => <option key={s} value={s}>{titleCase(s)}</option>)}
          </Select>
        </Field>
      </FilterBar>

      <div className="ui-card">
        <DataTable<LabOrder>
          columns={columns}
          rows={list.data?.data ?? []}
          rowKey={(o) => o.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={list.refetch}
          onRowClick={(o) => select(o.id)}
          selectedKey={selectedId ?? undefined}
          emptyTitle="No lab orders in this state"
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <LabOrderDrawer orderId={selectedId} onClose={() => select(null)} />
    </Page>
  )
}

function LabOrderDrawer({ orderId, onClose }: { orderId: string | null; onClose: () => void }) {
  const perms = usePermissions()
  const queryClient = useQueryClient()
  const [collecting, setCollecting] = useState(false)
  const [resulting, setResulting] = useState(false)
  const [cancelling, setCancelling] = useState(false)

  const detail = useQuery({
    queryKey: ['laboratory', 'order', orderId],
    queryFn: () => apiGet<LabOrder>(`/api/laboratory/orders/${orderId}`),
    enabled: !!orderId,
  })
  const order = detail.data

  function refresh() {
    queryClient.invalidateQueries({ queryKey: ['laboratory'] })
  }

  const action = useMutation({
    meta: { silent: true },
    mutationFn: ({ path, body }: { path: string; body?: Record<string, unknown> }) =>
      apiPost(`/api/laboratory/orders/${orderId}/${path}`, body ?? {}),
    onSuccess: () => refresh(),
  })

  return (
    <Drawer
      open={!!orderId}
      onClose={onClose}
      title={order?.order_no ?? 'Lab order'}
      subtitle={order ? `${patientName(order.patient)} · ${order.patient?.patient_no} · ${patientAge(order.patient)}` : undefined}
      width={620}
    >
      {order && (
        <div className="space-y-6">
          <div className="flex flex-wrap items-center gap-2">
            <StatusBadge status={order.status} />
            <div className="ml-auto flex flex-wrap gap-2">
              {perms.has('laboratory.order.accept') && order.status === 'PENDING' && (
                <Button variant="primary" size="sm" onClick={() => action.mutate({ path: 'accept' })} disabled={action.isPending}>
                  <CheckCircle2 size={14} className="mr-1.5" /> Accept
                </Button>
              )}
              {perms.has('laboratory.sample.collect') && order.status === 'ACCEPTED' && (
                <Button variant="primary" size="sm" onClick={() => setCollecting(true)} disabled={action.isPending}>
                  <Droplets size={14} className="mr-1.5" /> Collect sample
                </Button>
              )}
              {perms.has('laboratory.result.enter') && order.status === 'SAMPLE_COLLECTED' && (
                <Button variant="primary" size="sm" onClick={() => action.mutate({ path: 'start-processing' })} disabled={action.isPending}>
                  <PlayCircle size={14} className="mr-1.5" /> Start processing
                </Button>
              )}
              {perms.has('laboratory.result.enter') && order.status === 'PROCESSING' && (
                <Button variant="primary" size="sm" onClick={() => setResulting(true)} disabled={action.isPending}>
                  <Beaker size={14} className="mr-1.5" /> Enter results
                </Button>
              )}
              {perms.has('laboratory.order.cancel') && !['COMPLETED', 'CANCELLED'].includes(order.status) && (
                <Button variant="danger" size="sm" onClick={() => setCancelling(true)} disabled={action.isPending}>Cancel</Button>
              )}
            </div>
          </div>
          {action.isError && <InlineError error={action.error} />}

          <DescriptionList
            items={[
              { label: 'Encounter', value: order.encounter?.encounter_no },
              { label: 'Laboratory', value: order.facility?.name },
              { label: 'Requested by', value: order.ordered_by?.name },
              { label: 'Clinical notes', value: order.clinical_notes },
              { label: 'Accepted', value: order.accepted_at ? formatDateTime(order.accepted_at) : '—' },
              { label: 'Completed', value: order.completed_at ? formatDateTime(order.completed_at) : '—' },
              ...(order.cancel_reason ? [{ label: 'Cancelled', value: order.cancel_reason }] : []),
            ]}
          />

          <div>
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Tests</div>
            <table className="ui-table">
              <thead><tr><th>Test</th><th>Result</th><th>Normal range</th><th>Entered by</th></tr></thead>
              <tbody>
                {(order.tests ?? []).map((t) => (
                  <tr key={t.id}>
                    <td className="font-medium">{t.test_name}</td>
                    <td className={t.is_abnormal ? 'text-rose-700 font-semibold' : ''}>
                      {t.result_value ?? <span className="text-slate-400">pending</span>}{t.result_value && t.unit ? ` ${t.unit}` : ''}
                      {t.result_notes && <div className="text-xs text-slate-500 font-normal">{t.result_notes}</div>}
                    </td>
                    <td className="text-slate-500">{t.normal_range ?? '—'}</td>
                    <td className="text-slate-500">{t.result_entered_by?.name ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {(order.samples ?? []).length > 0 && (
            <div>
              <div className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Samples</div>
              <table className="ui-table">
                <thead><tr><th>Sample</th><th>Type</th><th>Collected by</th><th>At</th></tr></thead>
                <tbody>
                  {(order.samples ?? []).map((s) => (
                    <tr key={s.id}>
                      <td className="font-medium">{s.sample_no}</td>
                      <td>{s.sample_type}</td>
                      <td>{s.collected_by?.name ?? '—'}</td>
                      <td className="text-slate-500">{formatDateTime(s.collected_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {order && (
        <CollectSampleModal
          order={order}
          open={collecting}
          onClose={() => setCollecting(false)}
          onSaved={() => { setCollecting(false); refresh() }}
        />
      )}
      {order && (
        <ResultsModal
          order={order}
          open={resulting}
          onClose={() => setResulting(false)}
          onSaved={() => { setResulting(false); refresh() }}
        />
      )}

      <ConfirmDialog
        open={cancelling}
        title="Cancel this lab order?"
        message="Its pending charges on the visit are waived."
        confirmLabel="Cancel order"
        danger
        requireReason="Reason for cancelling"
        isPending={action.isPending}
        onCancel={() => setCancelling(false)}
        onConfirm={(reason) => action.mutate({ path: 'cancel', body: { reason } }, { onSuccess: () => setCancelling(false) })}
      />
    </Drawer>
  )
}

function CollectSampleModal({ order, open, onClose, onSaved }: { order: LabOrder; open: boolean; onClose: () => void; onSaved: () => void }) {
  const defaultType = order.tests?.find((t) => t)?.test_name ? '' : ''
  const [sampleType, setSampleType] = useState(defaultType || 'Blood')
  const [notes, setNotes] = useState('')

  const collect = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost(`/api/laboratory/orders/${order.id}/collect-sample`, { sample_type: sampleType, condition_notes: notes || null }),
    onSuccess: () => {
      toast.success('Sample collected')
      onSaved()
    },
  })

  return (
    <Modal open={open} onClose={onClose} title="Collect sample"
      footer={
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
          <Button variant="primary" onClick={() => collect.mutate()} disabled={!sampleType.trim() || collect.isPending}>
            {collect.isPending ? 'Saving…' : 'Record collection'}
          </Button>
        </div>
      }
    >
      <div className="space-y-3">
        <Field label="Sample type" required>
          <Input value={sampleType} onChange={(e) => setSampleType(e.target.value)} placeholder="Blood, Urine, Swab…" />
        </Field>
        <Field label="Condition notes">
          <Textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} />
        </Field>
        {collect.isError && <InlineError error={collect.error} />}
      </div>
    </Modal>
  )
}

function ResultsModal({ order, open, onClose, onSaved }: { order: LabOrder; open: boolean; onClose: () => void; onSaved: () => void }) {
  const pending = (order.tests ?? []).filter((t) => t.result_value === null)
  const [values, setValues] = useState<Record<string, { result_value: string; result_notes: string; is_abnormal: boolean }>>({})

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost(`/api/laboratory/orders/${order.id}/results`, {
        results: Object.entries(values)
          .filter(([, v]) => v.result_value.trim() !== '')
          .map(([id, v]) => ({ lab_order_test_id: id, result_value: v.result_value, result_notes: v.result_notes || null, is_abnormal: v.is_abnormal })),
      }),
    onSuccess: (updated) => {
      const done = (updated as LabOrder).status === 'COMPLETED'
      toast.success(done ? 'All results in — order completed' : 'Results saved')
      onSaved()
    },
  })

  const anyValue = Object.values(values).some((v) => v.result_value.trim() !== '')

  return (
    <Modal open={open} onClose={onClose} title="Enter results" width={560}
      footer={
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
          <Button variant="primary" onClick={() => save.mutate()} disabled={!anyValue || save.isPending}>
            {save.isPending ? 'Saving…' : 'Save results'}
          </Button>
        </div>
      }
    >
      <div className="space-y-4">
        {pending.map((t) => {
          const v = values[t.id] ?? { result_value: '', result_notes: '', is_abnormal: false }
          const set = (patch: Partial<typeof v>) => setValues((prev) => ({ ...prev, [t.id]: { ...v, ...patch } }))
          return (
            <div key={t.id} className="border border-slate-200 rounded-xl p-3.5">
              <div className="text-sm font-semibold text-slate-900">{t.test_name}</div>
              <div className="text-xs text-slate-500 mb-2">Normal: {t.normal_range ?? '—'}{t.unit ? ` (${t.unit})` : ''}</div>
              <div className="grid grid-cols-[1fr_auto] gap-2 items-end">
                <Field label="Result" required>
                  <Input value={v.result_value} onChange={(e) => set({ result_value: e.target.value })} placeholder={t.unit ? `Value in ${t.unit}` : 'Positive / value…'} />
                </Field>
                <label className="flex items-center gap-2 text-sm text-slate-700 pb-2 cursor-pointer">
                  <input type="checkbox" className="w-4 h-4 rounded border-slate-300 text-rose-600" checked={v.is_abnormal} onChange={(e) => set({ is_abnormal: e.target.checked })} />
                  Abnormal
                </label>
              </div>
              <Field label="Notes" className="mt-2">
                <Input value={v.result_notes} onChange={(e) => set({ result_notes: e.target.value })} />
              </Field>
            </div>
          )
        })}
        {pending.length === 0 && <div className="text-sm text-slate-500">Every test already has a result.</div>}
        {save.isError && <InlineError error={save.error} />}
      </div>
    </Modal>
  )
}
