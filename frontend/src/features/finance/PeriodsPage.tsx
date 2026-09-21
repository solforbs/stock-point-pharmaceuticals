import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { ConfirmDialog } from '../../components/ui/Modal'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { FinancialPeriod } from '../../lib/types'

export default function PeriodsPage() {
  const queryClient = useQueryClient()
  const canClose = usePermission('period.close')
  const [closing, setClosing] = useState<FinancialPeriod | null>(null)
  const periods = useQuery({ queryKey: ['finance', 'periods'], queryFn: () => apiGet<FinancialPeriod[]>('/api/finance/periods') })

  const close = useMutation({
    meta: { silent: true },
    mutationFn: (id: string) => apiPost<FinancialPeriod>(`/api/finance/periods/${id}/close`),
    onSuccess: (p) => {
      toast.success(`Period ${p.fiscal_year}/${p.period_no} closed`)
      setClosing(null)
      queryClient.invalidateQueries({ queryKey: ['finance', 'periods'] })
    },
  })
  const closeError = close.isError ? getApiError(close.error) : null

  const columns: Column<FinancialPeriod>[] = [
    { key: 'period', header: 'Period', render: (p) => <span className="font-semibold tabular">{p.fiscal_year} / {String(p.period_no).padStart(2, '0')}</span> },
    { key: 'range', header: 'Dates', render: (p) => <span className="tabular">{formatDate(p.start_date)} – {formatDate(p.end_date)}</span> },
    { key: 'status', header: 'Status', render: (p) => <StatusBadge status={p.status} /> },
    { key: 'closed', header: 'Closed', render: (p) => formatDateTime(p.closed_at) },
    { key: 'act', header: '', align: 'right', render: (p) => (p.status === 'OPEN' && canClose ? <Button size="sm" variant="danger" onClick={() => setClosing(p)}>Close period</Button> : null) },
  ]

  return (
    <Page>
      <PageHeader parent="Finance" title="Periods" subtitle="A period closes only when every journal balances and the stock ledger reconciles (Part 12.4)." />
      {closeError && (
        <div className="mb-3">
          <InlineError error={close.error} />
          {closeError.code === 'CLOSE_CHECKLIST_FAILED' && (
            <div className="text-xs text-slate-500 mt-1 tabular font-mono">
              Unbalanced journals: {String(closeError.details.unbalanced_journals ?? 0)} · stock balance drift rows: {String(closeError.details.stock_balance_drift_rows ?? 0)}. Run the period close checklist report for detail.
            </div>
          )}
        </div>
      )}
      <div id="tour-periods-table" className="ui-card">
        <DataTable columns={columns} rows={periods.data} rowKey={(p) => p.id} isLoading={periods.isLoading} error={periods.error} onRetry={() => periods.refetch()} emptyTitle="No financial periods" />
      </div>
      <ConfirmDialog
        open={closing !== null}
        title={closing ? `Close period ${closing.fiscal_year}/${closing.period_no}?` : ''}
        message="Closing runs the checklist: every journal in the period must balance and the ledger must reconcile. Nothing can post into a closed period."
        confirmLabel="Close period"
        danger
        isPending={close.isPending}
        onCancel={() => setClosing(null)}
        onConfirm={() => closing && close.mutate(closing.id)}
      />
    </Page>
  )
}
