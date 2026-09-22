import { useQuery } from '@tanstack/react-query'
import { RefreshCw } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useOnline } from '../../components/SyncStatusChip'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { ErrorState, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { usePendingCount } from '../../lib/offline/outbox'
import { usePermissions } from '../../lib/permissions'
import { DeviceOutboxCard, ServerOfflineSalesCard } from './OfflineSalesPanels'

type TerminalRow = { terminal_id: string | null; sales: number; voided: number; last_posted_at: string | null }
type SyncStatus = {
  server_time: string
  session: { user_id: number; name: string; username: string | null; branch: { id: string; code: string; name: string } | null; ip: string | null; user_agent: string | null; auth: 'session' | 'token' }
  offline_queue: { available: boolean; conflicts: number; posted_24h: number; price_variances_24h: number; note: string }
  replays: { recorded: boolean; note: string }
  activity_24h: { since: string; sales: number; quotations: number; sales_orders: number; payments: number; terminals: TerminalRow[] }
  etims: { pending: number; submitted: number; failed: number; not_configured: number; enabled: boolean; driver: string }
}
type Ping = { ok: boolean; status: number; latency_ms: number; checked_at: string }

/**
 * Times a round trip to Laravel's /up health route (no auth, no database).
 * Under the Vite dev server /up is not proxied and returns the SPA shell,
 * so the proxied /sanctum/csrf-cookie is timed instead when that happens.
 */
async function pingServer(): Promise<Ping> {
  const started = performance.now()
  const done = (ok: boolean, status: number): Ping => ({ ok, status, latency_ms: Math.round(performance.now() - started), checked_at: new Date().toISOString() })
  try {
    const up = await fetch('/up', { cache: 'no-store', credentials: 'same-origin' })
    const body = up.ok ? await up.text() : ''
    if (!up.ok || body.includes('Application up')) return done(up.ok, up.status)
    const fallback = await fetch('/sanctum/csrf-cookie', { cache: 'no-store', credentials: 'same-origin' })
    return done(fallback.ok, fallback.status)
  } catch {
    return done(false, 0)
  }
}

/**
 * Part 17.5 — the Sync Centre: whether this device can reach the server and
 * how quickly, what is still waiting on this device after an outage, the
 * offline sales that need a supervisor, what each till posted in the last
 * 24 hours, and the eTIMS queue.
 */
export default function SyncCentrePage() {
  const permissions = usePermissions()
  const canView = permissions.has('admin.settings') || permissions.has('sale.view')
  const online = useOnline()
  const pending = usePendingCount()
  const ping = useQuery({ queryKey: ['sync', 'ping'], queryFn: pingServer, refetchInterval: 15_000, enabled: canView })
  const status = useQuery({ queryKey: ['sync', 'status'], queryFn: () => apiGet<SyncStatus>('/api/admin/sync-status'), refetchInterval: 30_000, enabled: canView })

  if (!canView) return <Page><PageHeader parent="Admin" title="Sync Centre" /><div className="ui-card"><NoAccess permission="sale.view" /></div></Page>

  const p = ping.data
  const s = status.data
  const reach: { label: string; tone: 'green' | 'amber' | 'red'; hint: string } = !online
    ? { label: 'Offline', tone: 'red', hint: 'This browser reports no network. Sales cannot post until the connection returns.' }
    : !p ? { label: 'Checking', tone: 'amber', hint: 'Contacting the server…' }
      : !p.ok ? { label: 'Unreachable', tone: 'red', hint: p.status ? `The server answered HTTP ${p.status}.` : 'The network is up but the server did not answer.' }
        : p.latency_ms > 1500 ? { label: 'Slow', tone: 'amber', hint: `Round trip ${p.latency_ms} ms; checkout will feel slow.` }
          : { label: 'Reachable', tone: 'green', hint: `Round trip ${p.latency_ms} ms.` }

  const columns: Column<TerminalRow>[] = [
    { key: 'terminal', header: 'Terminal', render: (t) => <span className="font-semibold tabular">{t.terminal_id ?? 'Not identified'}</span>, sortValue: (t) => t.terminal_id ?? '' },
    { key: 'sales', header: 'Sales', align: 'right', render: (t) => <span className="tabular">{t.sales}</span>, sortValue: (t) => t.sales },
    { key: 'voided', header: 'Voided', align: 'right', render: (t) => <span className="tabular">{t.voided}</span>, sortValue: (t) => t.voided },
    { key: 'last', header: 'Last posted', render: (t) => <span className="tabular">{formatDateTime(t.last_posted_at)}</span>, sortValue: (t) => t.last_posted_at ?? '' },
  ]

  return (
    <Page>
      <PageHeader
        parent="Admin"
        title="Sync Centre"
        subtitle="Connectivity between this device and the server, and what the tills have posted."
        actions={<Button onClick={() => { void ping.refetch(); void status.refetch() }} disabled={ping.isFetching || status.isFetching}><RefreshCw size={13} className={ping.isFetching || status.isFetching ? 'animate-spin' : ''} /> Check now</Button>}
      />

      <div className="space-y-4">
        <div id="tour-sync-connectivity" className="grid grid-cols-1 md:grid-cols-3 gap-3">
          <section className="ui-card p-4 border-l-4" style={{ borderLeftColor: `var(--status-${online ? 'green' : 'red'})` }}>
            <div className="flex items-center justify-between"><h2 className="text-sm font-semibold text-slate-900">This device</h2><StatusBadge status={online ? 'ONLINE' : 'OFFLINE'} tone={online ? 'green' : 'red'} label={online ? 'Online' : 'Offline'} /></div>
            <p className="text-xs text-slate-500 mt-1">{online ? 'The browser has a network connection.' : 'The browser has no network connection.'}</p>
          </section>
          <section className="ui-card p-4 border-l-4" style={{ borderLeftColor: `var(--status-${reach.tone})` }}>
            <div className="flex items-center justify-between"><h2 className="text-sm font-semibold text-slate-900">Server</h2><StatusBadge status={reach.label} tone={reach.tone} label={reach.label} /></div>
            <p className="text-xs text-slate-500 mt-1">{reach.hint}</p>
            {p && <div className="text-xs text-slate-400 font-mono mt-1">Checked {formatDateTime(p.checked_at)} · every 15 seconds</div>}
          </section>
          <section className="ui-card p-4 border-l-4" style={{ borderLeftColor: `var(--status-${s?.offline_queue.conflicts ? 'red' : pending > 0 ? 'amber' : 'green'})` }}>
            <div className="flex items-center justify-between">
              <h2 className="text-[13px] font-bold">Offline queue</h2>
              <StatusBadge status="QUEUE" tone={pending > 0 ? 'amber' : 'slate'} label={`${pending} on this device`} />
            </div>
            {s && (
              <p className="text-[11.5px] text-[var(--text-secondary)] mt-1">
                {s.offline_queue.conflicts} {s.offline_queue.conflicts === 1 ? 'conflict needs' : 'conflicts need'} a supervisor · {s.offline_queue.posted_24h} synced in 24h
                {s.offline_queue.price_variances_24h > 0 && ` (${s.offline_queue.price_variances_24h} at a different price)`}. {s.offline_queue.note}
              </p>
            )}
          </section>
        </div>

        <DeviceOutboxCard />
        <ServerOfflineSalesCard />

        {status.isLoading && <div className="ui-card"><LoadingSkeleton rows={6} /></div>}
        {status.isError && <div className="ui-card"><ErrorState error={status.error} onRetry={() => status.refetch()} /></div>}

        {s && (
          <>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <Card title="Your session">
                <div className="p-4">
                  <DescriptionList items={[
                    { label: 'Signed in as', value: `${s.session.name}${s.session.username ? ` (${s.session.username})` : ''}` },
                    { label: 'Branch', value: s.session.branch ? `${s.session.branch.code} · ${s.session.branch.name}` : '—' },
                    { label: 'Authentication', value: s.session.auth === 'token' ? 'API token' : 'Browser session cookie' },
                    { label: 'IP address', value: <span className="tabular">{s.session.ip ?? '—'}</span> },
                    { label: 'Browser', value: <span className="text-xs text-slate-500 break-all">{s.session.user_agent ?? '—'}</span> },
                    { label: 'Server time', value: formatDateTime(s.server_time) },
                  ]} />
                </div>
              </Card>
              <div id="tour-sync-etims">
                <Card title="eTIMS transmission queue" actions={<Link to="/finance/tax-centre" className="text-xs text-blue-600 hover:underline">Tax Centre</Link>}>
                  <div className="p-4 space-y-3">
                    <div className="grid grid-cols-4 gap-2 text-center">
                      {([['Pending', s.etims.pending, 'amber'], ['Submitted', s.etims.submitted, 'green'], ['Failed', s.etims.failed, 'red'], ['Not configured', s.etims.not_configured, 'slate']] as const).map(([label, n, tone]) => (
                        <div key={label} className="rounded-lg border border-slate-200 p-2.5">
                          <div className="text-lg font-extrabold tabular font-mono" style={{ color: n > 0 ? `var(--status-${tone})` : undefined }}>{n}</div>
                          <div className="text-xs text-slate-500 mt-0.5">{label}</div>
                        </div>
                      ))}
                    </div>
                    <div className="text-xs text-slate-600">
                      {s.etims.enabled ? `Transmission is on (driver: ${s.etims.driver}). Documents are sent in the background and never block a sale.` : 'eTIMS transmission is switched off (ETIMS_ENABLED=false); documents are tracked but not sent.'}
                    </div>
                  </div>
                </Card>
              </div>
            </div>

            <div id="tour-sync-terminals">
              <Card title="Posted in the last 24 hours">
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 p-4 border-b border-slate-200">
                  {([['Sales', s.activity_24h.sales], ['Quotations', s.activity_24h.quotations], ['Sales orders', s.activity_24h.sales_orders], ['Customer payments', s.activity_24h.payments]] as const).map(([label, n]) => (
                    <div key={label}><div className="text-xs text-slate-500">{label}</div><div className="text-lg font-extrabold tabular font-mono">{n}</div></div>
                  ))}
                </div>
                <DataTable columns={columns} rows={s.activity_24h.terminals} rowKey={(t) => t.terminal_id ?? '—'} emptyTitle="No sales in the last 24 hours" emptyHint="Each till appears here once it posts a sale." />
                <div className="px-4 py-2 border-t border-slate-200 text-xs text-slate-500">
                  Branch {s.session.branch?.code ?? ''}, since {formatDateTime(s.activity_24h.since)}. {s.replays.note}
                </div>
              </Card>
            </div>
          </>
        )}
      </div>
    </Page>
  )
}
