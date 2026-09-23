import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { Pill } from 'lucide-react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { Modal } from '../../components/ui/Modal'
import { Page, PageHeader, FilterBar } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { Button, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { InlineError, NoAccess } from '../../components/ui/States'
import { apiGet, apiPost, newIdempotencyKey, withIdempotency } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { usePermissions } from '../../lib/permissions'
import { useStores } from '../../lib/hooks'
import { toast } from '../../lib/toast'
import type { Paginated, Prescription } from '../../lib/types'
import { patientAge, patientName } from '../hospital/api'

/**
 * The pharmacist's queue of prescriptions sent over from the hospital
 * module. Dispensing runs through the existing checkout engine — pricing,
 * FEFO batches, stock deduction, the sale and the receipt are the
 * pharmacy's own machinery.
 */
export default function PrescriptionsPage() {
  const perms = usePermissions()
  const canView = perms.has('prescription.view')

  const [params, setParams] = useSearchParams()
  const selectedId = params.get('prescription')
  const [status, setStatus] = useState('PENDING')
  const [page, setPage] = useState(1)

  const list = useQuery({
    queryKey: ['prescriptions', 'list', status, page],
    queryFn: () => apiGet<Paginated<Prescription>>('/api/prescriptions', { status: status || undefined, page, per_page: 25 }),
    enabled: canView,
    placeholderData: (prev) => prev,
  })

  if (!canView) {
    return (
      <Page>
        <PageHeader parent="Sales" title="Prescriptions" />
        <div className="ui-card"><NoAccess permission="prescription.view" /></div>
      </Page>
    )
  }

  function select(id: string | null) {
    setParams((prev) => {
      const next = new URLSearchParams(prev)
      if (id) next.set('prescription', id)
      else next.delete('prescription')
      return next
    })
  }

  const columns: Column<Prescription>[] = [
    { key: 'rx_no', header: 'Prescription', render: (p) => <span className="font-semibold text-slate-900">{p.rx_no}</span> },
    { key: 'patient', header: 'Patient', render: (p) => (
      <div>
        <div className="font-medium text-slate-900">{patientName(p.patient)}</div>
        <div className="text-xs text-slate-500">{p.patient?.patient_no} · {patientAge(p.patient)}</div>
      </div>
    ) },
    { key: 'prescriber', header: 'Prescribed by', render: (p) => p.prescribed_by?.name ?? '—' },
    { key: 'lines', header: 'Items', align: 'right', render: (p) => String(p.lines_count ?? p.lines?.length ?? 0) },
    { key: 'status', header: 'Status', render: (p) => <StatusBadge status={p.status} /> },
    { key: 'created_at', header: 'Received', render: (p) => <span className="text-slate-500">{formatDateTime(p.created_at)}</span> },
  ]

  return (
    <Page>
      <PageHeader parent="Sales" title="Prescriptions" subtitle="Sent from the hospital module — dispense through the normal checkout" />

      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {['PENDING', 'DISPENSED', 'CANCELLED'].map((s) => <option key={s} value={s}>{titleCase(s)}</option>)}
          </Select>
        </Field>
      </FilterBar>

      <div className="ui-card">
        <DataTable<Prescription>
          columns={columns}
          rows={list.data?.data ?? []}
          rowKey={(p) => p.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={list.refetch}
          onRowClick={(p) => select(p.id)}
          selectedKey={selectedId ?? undefined}
          emptyTitle="No prescriptions in this state"
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <PrescriptionDrawer prescriptionId={selectedId} onClose={() => select(null)} />
    </Page>
  )
}

function PrescriptionDrawer({ prescriptionId, onClose }: { prescriptionId: string | null; onClose: () => void }) {
  const perms = usePermissions()
  const queryClient = useQueryClient()
  const [dispensing, setDispensing] = useState(false)

  const detail = useQuery({
    queryKey: ['prescriptions', 'detail', prescriptionId],
    queryFn: () => apiGet<Prescription>(`/api/prescriptions/${prescriptionId}`),
    enabled: !!prescriptionId,
  })
  const rx = detail.data

  function refresh() {
    queryClient.invalidateQueries({ queryKey: ['prescriptions'] })
  }

  const cancel = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost(`/api/prescriptions/${prescriptionId}/cancel`, {}),
    onSuccess: () => {
      toast.success('Prescription cancelled')
      refresh()
    },
  })

  const stockedLines = (rx?.lines ?? []).filter((l) => l.product_id)

  return (
    <Drawer
      open={!!prescriptionId}
      onClose={onClose}
      title={rx?.rx_no ?? 'Prescription'}
      subtitle={rx ? `${patientName(rx.patient)} · ${rx.patient?.patient_no}` : undefined}
      width={600}
    >
      {rx && (
        <div className="space-y-6">
          <div className="flex items-center gap-2">
            <StatusBadge status={rx.status} />
            <div className="ml-auto flex gap-2">
              {perms.has('prescription.dispense') && rx.status === 'PENDING' && stockedLines.length > 0 && (
                <Button variant="primary" size="sm" onClick={() => setDispensing(true)}>
                  <Pill size={14} className="mr-1.5" /> Dispense
                </Button>
              )}
              {rx.status === 'PENDING' && (
                <Button variant="danger" size="sm" onClick={() => cancel.mutate()} disabled={cancel.isPending}>Cancel</Button>
              )}
            </div>
          </div>
          {cancel.isError && <InlineError error={cancel.error} />}

          <DescriptionList
            items={[
              { label: 'Encounter', value: rx.encounter?.encounter_no },
              { label: 'Prescribed by', value: rx.prescribed_by?.name },
              { label: 'Received', value: formatDateTime(rx.created_at) },
              { label: 'Notes', value: rx.notes },
              ...(rx.sale ? [{ label: 'Sale', value: `${rx.sale.doc_number} · ${rx.sale.grand_total}` }] : []),
              ...(rx.dispensed_by ? [{ label: 'Dispensed by', value: `${rx.dispensed_by.name} · ${rx.dispensed_at ? formatDateTime(rx.dispensed_at) : ''}` }] : []),
            ]}
          />

          <div>
            <div className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-2">Medicines</div>
            <table className="ui-table">
              <thead><tr><th>Medicine</th><th className="text-right">Qty</th><th>Directions</th><th /></tr></thead>
              <tbody>
                {(rx.lines ?? []).map((l) => (
                  <tr key={l.id}>
                    <td className="font-medium">{l.medicine_name}</td>
                    <td className="text-right tabular">{l.quantity}</td>
                    <td className="text-slate-600">{[l.frequency, l.duration, l.instructions].filter(Boolean).join(' · ') || '—'}</td>
                    <td>{!l.product_id && <span className="text-[10px] font-semibold uppercase tracking-wide text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded">not stocked</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            {stockedLines.length === 0 && (
              <div className="text-xs text-slate-500 mt-2">
                Nothing on this prescription is stocked here — the patient fills it at an outside pharmacy.
              </div>
            )}
          </div>
        </div>
      )}

      {rx && (
        <DispenseModal
          rx={rx}
          open={dispensing}
          onClose={() => setDispensing(false)}
          onDone={() => { setDispensing(false); refresh() }}
        />
      )}
    </Drawer>
  )
}

function DispenseModal({ rx, open, onClose, onDone }: { rx: Prescription; open: boolean; onClose: () => void; onDone: () => void }) {
  const stores = useStores()
  const sellable = (stores.data ?? []).filter((s) => s.is_sellable)
  const [storeId, setStoreId] = useState('')
  const [method, setMethod] = useState('CASH')
  const [amount, setAmount] = useState('')
  const idempotencyKey = useMemo(() => newIdempotencyKey(), [open])

  const effectiveStoreId = storeId || (sellable.length === 1 ? sellable[0].id : '')

  const dispense = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<Prescription>(`/api/prescriptions/${rx.id}/dispense`, {
        store_id: effectiveStoreId,
        payments: amount ? [{ method, amount }] : [],
      }, withIdempotency(idempotencyKey)),
    onSuccess: (updated) => {
      toast.success(`Dispensed — sale ${updated.sale?.doc_number ?? 'posted'}`)
      onDone()
    },
  })

  return (
    <Modal open={open} onClose={onClose} title={`Dispense ${rx.rx_no}`}
      footer={
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
          <Button variant="primary" onClick={() => dispense.mutate()} disabled={!effectiveStoreId || dispense.isPending}>
            {dispense.isPending ? 'Dispensing…' : 'Dispense & post sale'}
          </Button>
        </div>
      }
    >
      <div className="space-y-3">
        <div className="text-sm text-slate-600">
          Stock is allocated FEFO, deducted from the store you pick, and a sale posts through the normal checkout.
          The payment must cover the priced total.
        </div>
        <Field label="Store" required>
          <Select value={effectiveStoreId} onChange={(e) => setStoreId(e.target.value)}>
            <option value="">Select store…</option>
            {sellable.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </Select>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Payment method">
            <Select value={method} onChange={(e) => setMethod(e.target.value)}>
              {['CASH', 'MPESA', 'CARD', 'BANK'].map((m) => <option key={m} value={m}>{titleCase(m)}</option>)}
            </Select>
          </Field>
          <Field label="Amount received" hint="The server rejects a short payment with the exact total.">
            <Input type="number" min="0" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} placeholder="0.00" />
          </Field>
        </div>
        {dispense.isError && <InlineError error={dispense.error} />}
      </div>
    </Modal>
  )
}
