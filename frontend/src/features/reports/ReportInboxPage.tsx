import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { CheckCircle2, Download, Flag } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, NoAccess } from '../../components/ui/States'
import { StatusBadge, type StatusTone } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { ReportReviewStatus, ScheduledReportRun, ScheduledReportRunDetail, ScheduledReportRunPage } from '../../lib/types'
import { downloadFile } from '../quality/files'
import { formatCell, isNumericType } from './reportCells'

const REVIEW_TONE: Record<ReportReviewStatus, StatusTone> = { UNREVIEWED: 'amber', VERIFIED: 'green', FLAGGED: 'red' }
const REVIEW_LABEL: Record<ReportReviewStatus, string> = { UNREVIEWED: 'To check', VERIFIED: 'Verified', FLAGGED: 'Flagged' }

function ReviewBadge({ run }: { run: Pick<ScheduledReportRun, 'status' | 'review_status'> }) {
  if (run.status === 'FAILED') return <StatusBadge status="FAILED" label="Run failed" />
  return <StatusBadge status={run.review_status} tone={REVIEW_TONE[run.review_status]} label={REVIEW_LABEL[run.review_status]} />
}

function period(run: Pick<ScheduledReportRun, 'period_from' | 'period_to'>) {
  return run.period_from === run.period_to ? formatDate(run.period_from) : `${formatDate(run.period_from)} → ${formatDate(run.period_to)}`
}

/** Part 20.3 — every scheduled report that was generated, kept here to open, check and sign off. */
export default function ReportInboxPage() {
  const canReview = usePermission('report.review')
  const canSchedule = usePermission('report.schedule')
  const [reviewStatus, setReviewStatus] = useState<ReportReviewStatus | ''>('UNREVIEWED')
  const [page, setPage] = useState(1)
  const [openId, setOpenId] = useState<string | null>(null)

  const list = useQuery({
    queryKey: ['scheduled-report-runs', reviewStatus, page],
    queryFn: () => apiGet<ScheduledReportRunPage>('/api/scheduled-report-runs', { review_status: reviewStatus || undefined, page }),
    enabled: canReview || canSchedule,
  })

  if (!canReview && !canSchedule) return <NoAccess permission="report.review" />

  const counts = list.data?.counts
  const tabs: { value: ReportReviewStatus | ''; label: string; count?: number }[] = [
    { value: 'UNREVIEWED', label: 'To check', count: counts?.UNREVIEWED },
    { value: 'FLAGGED', label: 'Flagged', count: counts?.FLAGGED },
    { value: 'VERIFIED', label: 'Verified', count: counts?.VERIFIED },
    { value: '', label: 'All' },
  ]

  const columns: Column<ScheduledReportRun>[] = [
    { key: 'report', header: 'Report', render: (r) => <><div className="font-semibold">{r.report_title}</div><div className="text-xs text-slate-500">{period(r)}</div></> },
    { key: 'generated', header: 'Generated', render: (r) => <><div className="tabular">{formatDateTime(r.generated_at)}</div><div className="text-xs text-slate-500">{r.trigger === 'MANUAL' ? `Run now by ${r.requester?.name ?? 'someone'}` : 'On schedule'}</div></> },
    { key: 'rows', header: 'Rows', align: 'right', render: (r) => <span className="tabular">{r.row_count ?? '—'}</span> },
    { key: 'emailed', header: 'Emailed to', render: (r) => (r.emailed_to?.length ? <span title={r.emailed_to.join(', ')}>{r.emailed_to[0]}{r.emailed_to.length > 1 && <span className="text-slate-400 text-xs"> +{r.emailed_to.length - 1}</span>}</span> : <span className="text-slate-400">Not sent</span>) },
    { key: 'review', header: 'Status', render: (r) => <ReviewBadge run={r} /> },
    { key: 'reviewer', header: 'Checked by', render: (r) => (r.reviewer ? <><div>{r.reviewer.name}</div><div className="text-xs text-slate-500 tabular">{formatDateTime(r.reviewed_at)}</div></> : <span className="text-slate-400">—</span>) },
  ]

  return (
    <Page>
      <PageHeader
        parent="Reports"
        title="Report Inbox"
        subtitle="Every scheduled report that was generated. Open one to check it, download the CSV, and mark it verified or flagged."
        actions={canSchedule ? <Link to="/reports/scheduled"><Button>Manage schedules</Button></Link> : undefined}
      />
      <FilterBar>
        <div className="flex flex-wrap gap-1.5" role="tablist">
          {tabs.map((t) => (
            <button
              key={t.value || 'ALL'}
              type="button"
              role="tab"
              aria-selected={reviewStatus === t.value}
              onClick={() => { setReviewStatus(t.value); setPage(1) }}
              className={`inline-flex items-center gap-1.5 rounded-lg border px-3 py-1.5 text-xs font-semibold ${reviewStatus === t.value ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-slate-200 text-slate-700 hover:bg-slate-50'}`}
            >
              {t.label}
              {t.count !== undefined && t.count > 0 && (
                <span className={`rounded-full px-1.5 text-[11px] tabular ${reviewStatus === t.value ? 'bg-white/25' : t.value === 'UNREVIEWED' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600'}`}>{t.count}</span>
              )}
            </button>
          ))}
        </div>
      </FilterBar>
      <div className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data?.data}
          rowKey={(r) => r.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          onRowClick={(r) => setOpenId(r.id)}
          emptyTitle={reviewStatus === 'UNREVIEWED' ? 'Nothing to check' : 'No reports here'}
          emptyHint="Scheduled reports appear here each time they run."
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer open={openId !== null} onClose={() => setOpenId(null)} title="Report" width={960}>
        {openId && <RunDetail key={openId} runId={openId} canReview={canReview} />}
      </Drawer>
    </Page>
  )
}

function RunDetail({ runId, canReview }: { runId: string; canReview: boolean }) {
  const queryClient = useQueryClient()
  const [notes, setNotes] = useState('')
  const detail = useQuery({ queryKey: ['scheduled-report-run', runId], queryFn: () => apiGet<ScheduledReportRunDetail>(`/api/scheduled-report-runs/${runId}`) })

  const review = useMutation({
    meta: { silent: true },
    mutationFn: (status: 'VERIFIED' | 'FLAGGED') => apiPost<ScheduledReportRun>(`/api/scheduled-report-runs/${runId}/review`, { review_status: status, notes: notes.trim() || null }),
    onSuccess: (r) => {
      toast.success(r.review_status === 'VERIFIED' ? 'Marked as verified' : 'Marked as flagged')
      setNotes('')
      queryClient.invalidateQueries({ queryKey: ['scheduled-report-runs'] })
      queryClient.invalidateQueries({ queryKey: ['scheduled-report-run', runId] })
    },
  })
  const err = review.isError ? getApiError(review.error) : null

  if (detail.isLoading) return <div className="text-sm text-slate-500">Loading…</div>
  if (detail.isError || !detail.data) return <InlineError error={detail.error} />
  const run = detail.data
  const preview = run.preview
  const extraTotals = preview ? Object.entries(preview.totals).filter(([k]) => !preview.columns.find((c) => c.key === k)) : []

  const previewColumns: Column<Record<string, unknown>>[] = (preview?.columns ?? []).map((c) => ({
    key: c.key,
    header: c.label,
    align: isNumericType(c.type) ? 'right' : 'left',
    render: (row) => formatCell(row[c.key], c),
  }))

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <div className="text-lg font-bold text-slate-900">{run.report_title}</div>
          <div className="text-sm text-slate-500">{period(run)}</div>
        </div>
        <div className="flex items-center gap-2">
          <ReviewBadge run={run} />
          {run.has_file && <Button onClick={() => void downloadFile(`/api/scheduled-report-runs/${run.id}/download`, run.csv_filename ?? 'report.csv')}><Download size={13} /> Download CSV</Button>}
        </div>
      </div>

      <DescriptionList
        items={[
          { label: 'Generated', value: `${formatDateTime(run.generated_at)} · ${run.trigger === 'MANUAL' ? `run now by ${run.requester?.name ?? 'someone'}` : 'on schedule'}` },
          { label: 'Rows', value: run.row_count ?? '—' },
          { label: 'Emailed to', value: run.emailed_to?.length ? run.emailed_to.join(', ') : 'Not sent' },
          ...(run.error ? [{ label: 'Error', value: <span className="text-rose-600 font-medium">{run.error}</span> }] : []),
          ...(run.reviewer
            ? [
                { label: run.review_status === 'VERIFIED' ? 'Verified by' : 'Flagged by', value: `${run.reviewer.name} · ${formatDateTime(run.reviewed_at)}` },
                ...(run.review_notes ? [{ label: 'Notes', value: <span className="whitespace-pre-wrap">{run.review_notes}</span> }] : []),
              ]
            : []),
        ]}
      />

      {preview && (
        <div className="ui-card overflow-hidden">
          <DataTable
            columns={previewColumns}
            rows={preview.rows}
            rowKey={(row) => String(preview.rows.indexOf(row))}
            emptyTitle="No rows for this period"
            emptyHint="The report ran but found nothing to show."
            maxHeight="50vh"
            footer={
              preview.columns.some((c) => c.key in preview.totals) ? (
                <tr className="font-bold bg-slate-100 text-slate-900 border-t border-slate-300">
                  {preview.columns.map((c, i) => (
                    <td key={c.key} className={isNumericType(c.type) ? 'text-right px-3 py-2.5' : 'px-3 py-2.5'}>
                      {c.key in preview.totals ? formatCell(preview.totals[c.key], c) : i === 0 ? 'Totals' : ''}
                    </td>
                  ))}
                </tr>
              ) : null
            }
          />
          {extraTotals.length > 0 && (
            <div className="px-4 py-2.5 border-t border-slate-200 bg-slate-50 flex flex-wrap gap-x-5 gap-y-1 text-xs tabular">
              {extraTotals.map(([k, v]) => (
                <span key={k}>
                  <span className="text-slate-600 font-medium">{titleCase(k)}: </span>
                  <b className="text-slate-900">{typeof v === 'boolean' ? (v ? 'Yes' : 'No') : typeof v === 'object' && v !== null ? JSON.stringify(v) : String(v)}</b>
                </span>
              ))}
            </div>
          )}
          {preview.truncated && <div className="px-4 py-2 text-xs text-slate-500 border-t border-slate-200">Showing the first {preview.rows.length} of {run.row_count} rows. Download the CSV to see them all.</div>}
        </div>
      )}

      {run.has_file && canReview && (
        <div className="space-y-3 border-t border-slate-200 pt-4">
          <Field label="Notes" hint="Needed when you flag a report: say what looks wrong." error={err?.errors.notes?.[0]}>
            <Textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="e.g. Cash sales for Monday look short." />
          </Field>
          {err && !Object.keys(err.errors).length && <InlineError error={review.error} />}
          <div className="flex justify-end gap-2">
            <Button variant="danger" disabled={review.isPending} onClick={() => review.mutate('FLAGGED')}><Flag size={13} /> Flag</Button>
            <Button variant="success" disabled={review.isPending} onClick={() => review.mutate('VERIFIED')}><CheckCircle2 size={13} /> Mark verified</Button>
          </div>
        </div>
      )}
    </div>
  )
}
