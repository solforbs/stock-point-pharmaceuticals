import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { RefreshCw } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { ConfirmDialog } from '../../components/ui/Modal'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { ErrorState, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { StatusBadge, type StatusTone } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatBytes } from '../../lib/bytes'
import { formatDateTime } from '../../lib/format'
import { formatKes } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'

type Light = 'green' | 'amber' | 'red'
type Check = { status: Light; message: string } & Record<string, unknown>

type FailedJob = { id: number; uuid: string | null; connection: string; queue: string; job: string | null; exception: string; failed_at: string }
type GlRow = { organisation: string; ledger_value: string; gl_value: string | null; matches: boolean }

type Health = {
  generated_at: string
  app: { version: string; environment: string; php_version: string; laravel_version: string; timezone: string; server_time: string }
  checks: {
    database: Check & { latency_ms: number | null; size_mb: number | null }
    queue: Check & { connection: string; pending: number; failed: number; oldest_pending_minutes: number | null }
    scheduler: Check & { last_run_at: string | null; minutes_ago: number | null }
    reconciliation: Check & { drift_rows: number; orphan_ledger_rows: number; negative_balances: number; gl_mismatches: number; gl: GlRow[] }
    storage: Check & { free_bytes: number | null; total_bytes: number | null; free_pct: number | null }
    backup: Check & { last: { name: string; size: number; created_at: string } | null; hours_ago: number | null }
    financial_period: Check & { period: { fiscal_year: number; period_no: number; start_date: string; end_date: string } | null }
  }
  failed_jobs: FailedJob[]
}

const LIGHT_LABEL: Record<Light, string> = { green: 'Healthy', amber: 'Attention', red: 'Action needed' }
const LIGHT_TONE: Record<Light, StatusTone> = { green: 'green', amber: 'amber', red: 'red' }

/** Part 17 — one screen that says whether the system is healthy, and what to do when it is not. */
export default function SystemHealthPage() {
  const canView = usePermission('admin.settings')
  const queryClient = useQueryClient()
  const [forgetting, setForgetting] = useState<FailedJob | null>(null)
  const health = useQuery({
    queryKey: ['admin', 'system-health'],
    queryFn: () => apiGet<Health>('/api/admin/system-health'),
    enabled: canView,
    refetchInterval: 60_000,
  })

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['admin', 'system-health'] })
  const retry = useMutation({
    mutationFn: (ids: string[]) => apiPost<{ retried: number; remaining_failed: number }>('/api/admin/system-health/retry-failed-jobs', ids.length ? { ids } : {}),
    onSuccess: (r) => { toast.success(`${r.retried} job(s) pushed back onto the queue`); refresh() },
  })
  const forget = useMutation({
    mutationFn: (uuid: string) => apiPost(`/api/admin/system-health/forget-failed-job/${uuid}`),
    onSuccess: () => { toast.success('Failed job discarded'); setForgetting(null); refresh() },
  })

  if (!canView) return <Page><PageHeader parent="Admin" title="System Health" /><div className="ui-card"><NoAccess permission="admin.settings" /></div></Page>

  const h = health.data
  const c = h?.checks

  const jobColumns: Column<FailedJob>[] = [
    { key: 'failed', header: 'Failed', render: (j) => <span className="tabular whitespace-nowrap">{formatDateTime(j.failed_at)}</span>, sortValue: (j) => j.failed_at },
    { key: 'job', header: 'Job', render: (j) => <span className="font-semibold">{j.job ?? '—'}</span>, sortValue: (j) => j.job ?? '' },
    { key: 'queue', header: 'Queue', render: (j) => <span className="tabular">{j.connection}/{j.queue}</span> },
    { key: 'exception', header: 'Error', render: (j) => <span className="text-xs text-rose-600 break-all font-mono">{j.exception}</span> },
    {
      key: 'actions', header: '', align: 'right', render: (j) => j.uuid ? (
        <div className="flex justify-end gap-1.5">
          <Button size="sm" disabled={retry.isPending} onClick={() => retry.mutate([j.uuid as string])}>Retry</Button>
          <Button size="sm" variant="ghost" onClick={() => setForgetting(j)}>Forget</Button>
        </div>
      ) : null,
    },
  ]

  return (
    <Page>
      <PageHeader
        parent="Admin"
        title="System Health"
        subtitle={h ? `Checked ${formatDateTime(h.generated_at)} · refreshes every minute` : 'Database, queue, scheduler, reconciliation, disk, backups and the financial period.'}
        actions={<Button onClick={refresh} disabled={health.isFetching}><RefreshCw size={13} className={health.isFetching ? 'animate-spin' : ''} /> Refresh</Button>}
      />

      {health.isLoading && <div className="ui-card"><LoadingSkeleton rows={8} /></div>}
      {health.isError && <div className="ui-card"><ErrorState error={health.error} onRetry={() => health.refetch()} /></div>}

      {h && c && (
        <div className="space-y-4">
          <div id="tour-health-kpis" className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            <Tile title="Database" check={c.database} metric={c.database.latency_ms != null ? `${c.database.latency_ms} ms · ${c.database.size_mb} MB` : null} />
            <Tile title="Queue" check={c.queue} metric={`${c.queue.pending} waiting · ${c.queue.failed} failed · ${c.queue.connection}`} />
            <Tile title="Scheduler" check={c.scheduler} metric={c.scheduler.last_run_at ? `Last run ${formatDateTime(c.scheduler.last_run_at)}` : 'No heartbeat recorded'} />
            <Tile title="Ledger reconciliation" check={c.reconciliation} metric={`${c.reconciliation.drift_rows} drift · ${c.reconciliation.orphan_ledger_rows} orphan · ${c.reconciliation.negative_balances} negative`} />
            <Tile title="Disk space" check={c.storage} metric={c.storage.free_bytes != null && c.storage.total_bytes != null ? `${formatBytes(c.storage.free_bytes)} free of ${formatBytes(c.storage.total_bytes)}` : null}>
              {c.storage.free_pct != null && (
                <div className="mt-2 h-1.5 rounded-full bg-[var(--surface-3)] overflow-hidden" aria-label={`${c.storage.free_pct}% free`}>
                  <div className="h-full" style={{ width: `${100 - c.storage.free_pct}%`, background: `var(--status-${c.storage.status})` }} />
                </div>
              )}
            </Tile>
            <Tile title="Backups" check={c.backup} metric={c.backup.last ? `${c.backup.last.name} · ${formatBytes(c.backup.last.size)}` : null}>
              <Link to="/admin/backup" className="text-xs text-blue-600 hover:underline mt-1 inline-block">Open Backup</Link>
            </Tile>
            <Tile title="Financial period" check={c.financial_period} metric={c.financial_period.period ? `FY${c.financial_period.period.fiscal_year} P${c.financial_period.period.period_no}` : null}>
              <Link to="/finance/periods" className="text-xs text-blue-600 hover:underline mt-1 inline-block">Open Periods</Link>
            </Tile>
          </div>

          <div id="tour-health-reconcile" className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Card title="Application">
              <div className="p-4">
                <DescriptionList items={[
                  { label: 'Version', value: <span className="tabular">{h.app.version}</span> },
                  { label: 'Environment', value: <StatusBadge status={h.app.environment} tone={h.app.environment === 'production' ? 'green' : 'amber'} /> },
                  { label: 'PHP', value: <span className="tabular">{h.app.php_version}</span> },
                  { label: 'Laravel', value: <span className="tabular">{h.app.laravel_version}</span> },
                  { label: 'Timezone', value: h.app.timezone },
                  { label: 'Server time', value: formatDateTime(h.app.server_time) },
                ]} />
              </div>
            </Card>
            <Card title="Inventory value: ledger against GL">
              {c.reconciliation.gl.length === 0 ? (
                <div className="p-4 text-xs text-slate-500">No organisations to compare.</div>
              ) : (
                <table className="ui-table">
                  <thead><tr><th>Organisation</th><th className="text-right">Stock ledger</th><th className="text-right">GL inventory</th><th>Result</th></tr></thead>
                  <tbody>
                    {c.reconciliation.gl.map((g) => (
                      <tr key={g.organisation}>
                        <td>{g.organisation}</td>
                        <td className="text-right tabular">{formatKes(g.ledger_value)}</td>
                        <td className="text-right tabular">{g.gl_value == null ? '—' : formatKes(g.gl_value)}</td>
                        <td><StatusBadge status={g.matches ? 'MATCHED' : 'UNMATCHED'} tone={g.matches ? 'green' : 'red'} /></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
              <div className="px-4 py-2 border-t border-slate-200 text-xs text-slate-500">
                The same checks as the nightly <code>inventory:reconcile-ledger</code> command. Any drift is a P1 incident: stop adjustments and investigate the stock ledger.
              </div>
            </Card>
          </div>

          <div id="tour-health-jobs">
            <Card
              title={`Failed jobs (${c.queue.failed})`}
              actions={h.failed_jobs.length > 0 ? <Button size="sm" disabled={retry.isPending} onClick={() => retry.mutate([])}>{retry.isPending ? 'Retrying…' : 'Retry all'}</Button> : null}
            >
              <DataTable columns={jobColumns} rows={h.failed_jobs} rowKey={(j) => String(j.id)} emptyTitle="No failed jobs" emptyHint="Background work (eTIMS transmission, scheduled reports, mail) is completing normally." />
              {c.queue.failed > h.failed_jobs.length && (
                <div className="px-4 py-2 border-t border-slate-200 text-xs text-slate-500">Showing the {h.failed_jobs.length} most recent of {c.queue.failed}. Retry all covers every one.</div>
              )}
            </Card>
          </div>
        </div>
      )}

      <ConfirmDialog
        open={!!forgetting}
        title="Forget this failed job?"
        message={`${forgetting?.job ?? 'The job'} will be discarded and never retried. Do this only when the work is no longer needed or has been done another way.`}
        confirmLabel="Forget job"
        danger
        isPending={forget.isPending}
        onConfirm={() => forgetting?.uuid && forget.mutate(forgetting.uuid)}
        onCancel={() => setForgetting(null)}
      />
    </Page>
  )
}

function Tile({ title, check, metric, children }: { title: string; check: Check; metric?: string | null; children?: ReactNode }) {
  const color = `var(--status-${check.status})`
  return (
    <section className="ui-card p-4 border-l-4" style={{ borderLeftColor: color }}>
      <div className="flex items-center justify-between gap-2">
        <h2 className="text-sm font-semibold text-slate-900">{title}</h2>
        <StatusBadge status={check.status} tone={LIGHT_TONE[check.status]} label={LIGHT_LABEL[check.status]} />
      </div>
      {metric && <div className="text-xs tabular font-semibold font-mono mt-1.5 text-slate-700">{metric}</div>}
      <p className="text-xs text-slate-500 mt-1">{check.message}</p>
      {children}
    </section>
  )
}
