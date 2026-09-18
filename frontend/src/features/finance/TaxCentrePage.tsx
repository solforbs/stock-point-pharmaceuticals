import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { RefreshCw } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { toast } from '../../lib/toast'
import type { EtimsQueue, EtimsQueueRow } from '../../lib/types'

const STATUSES = ['FAILED', 'PENDING', 'SUBMITTED', 'NOT_CONFIGURED'] as const

/** Part 13.6 — the eTIMS queue: what is outstanding with KRA, with manual retry. Fiscalisation never blocks a sale. */
export default function TaxCentrePage() {
  const queryClient = useQueryClient()
  const [status, setStatus] = useState<(typeof STATUSES)[number]>('FAILED')
  const queue = useQuery({ queryKey: ['etims', 'queue', status], queryFn: () => apiGet<EtimsQueue>('/api/etims/queue', { status, per_page: 200 }), placeholderData: (prev) => prev, refetchInterval: 60_000 })

  const retry = useMutation({
    mutationFn: (row: EtimsQueueRow) => apiPost<{ etims_status?: string; doc_number?: string }>(row.type === 'sale' ? `/api/etims/sales/${row.id}/retry` : `/api/etims/credit-notes/${row.id}/retry`),
    onSuccess: (res, row) => {
      toast.success(`${row.doc_number} ${res.etims_status ? titleCase(res.etims_status) : 'submitted'}`)
      queryClient.invalidateQueries({ queryKey: ['etims'] })
    },
  })

  const s = queue.data?.summary
  const columns: Column<EtimsQueueRow>[] = [
    { key: 'type', header: 'Type', render: (r) => <StatusBadge status={r.type === 'sale' ? 'INVOICE' : 'CREDIT_NOTE'} tone={r.type === 'sale' ? 'blue' : 'purple'} label={r.type === 'sale' ? 'Invoice' : 'Credit note'} /> },
    { key: 'doc', header: 'Document', render: (r) => <Link to={r.type === 'sale' ? `/sell/invoices?sale=${r.id}` : `/sell/returns?return=${r.id}`} className="font-semibold tabular hover:underline">{r.doc_number}{r.credit_note_number ? ` · ${r.credit_note_number}` : ''}</Link>, sortValue: (r) => r.doc_number },
    { key: 'customer', header: 'Customer', render: (r) => r.customer?.name ?? 'Walk-in' },
    { key: 'total', header: 'Total', align: 'right', render: (r) => <MoneyCell value={r.grand_total} />, sortValue: (r) => Number(r.grand_total) },
    { key: 'posted', header: 'Posted', render: (r) => formatDateTime(r.posted_at), sortValue: (r) => r.posted_at ?? '' },
    { key: 'status', header: 'eTIMS', render: (r) => <StatusBadge status={r.etims_status} tone={r.etims_status === 'SUBMITTED' ? 'green' : r.etims_status === 'FAILED' ? 'red' : r.etims_status === 'PENDING' ? 'amber' : 'slate'} /> },
    { key: 'control', header: 'Control code / invoice no.', render: (r) => <span className="tabular text-[11.5px]">{r.etims_control_code ?? '—'}{r.etims_invoice_number ? ` · ${r.etims_invoice_number}` : ''}</span> },
    { key: 'submitted', header: 'Submitted', render: (r) => formatDateTime(r.etims_submitted_at) },
    { key: 'error', header: 'Error', render: (r) => <span className="text-[11px] text-[var(--status-red)] break-words">{r.etims_error ?? ''}</span> },
    { key: 'act', header: '', align: 'right', render: (r) => (r.etims_status !== 'SUBMITTED' ? <Button size="sm" disabled={retry.isPending} onClick={() => retry.mutate(r)}><RefreshCw size={12} /> Retry</Button> : null) },
  ]

  return (
    <Page>
      <PageHeader parent="Finance" title="Tax Centre" subtitle={s ? `eTIMS ${s.enabled ? `enabled (${s.driver} driver)` : 'not enabled on this server'}. Sales post whether or not KRA answers; this queue is what is still outstanding.` : 'eTIMS queue'} actions={<Button size="sm" onClick={() => queue.refetch()}><RefreshCw size={12} /> Refresh</Button>} />
      <div className="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        {STATUSES.map((st) => {
          const n = s ? s[st.toLowerCase() as 'failed' | 'pending' | 'submitted' | 'not_configured'] : null
          const color = st === 'FAILED' ? 'var(--status-red)' : st === 'PENDING' ? 'var(--status-amber)' : st === 'SUBMITTED' ? 'var(--status-green)' : 'var(--status-slate)'
          return (
            <button key={st} type="button" onClick={() => setStatus(st)} className={`ui-card p-3 text-left border-l-4 ${status === st ? 'ring-2 ring-[var(--color-navy)]' : ''}`} style={{ borderLeftColor: color }}>
              <div className="ui-label !mb-0.5">{titleCase(st)}</div>
              <div className="text-[22px] font-extrabold tabular">{n ?? '…'}</div>
            </button>
          )
        })}
      </div>
      <div className="ui-card">
        <DataTable columns={columns} rows={queue.data?.data} rowKey={(r) => `${r.type}-${r.id}`} isLoading={queue.isLoading} error={queue.error} onRetry={() => queue.refetch()} emptyTitle={`Nothing ${titleCase(status).toLowerCase()}`} />
      </div>
      <p className="text-[11px] text-[var(--text-muted)] mt-2">Tax codes, effective-dated rates and VAT return preparation live in Reports (VAT return) until their admin endpoints exist.</p>
    </Page>
  )
}
