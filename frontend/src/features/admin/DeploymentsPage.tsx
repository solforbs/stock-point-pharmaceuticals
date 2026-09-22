import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { GitBranch, RefreshCw, Rocket } from 'lucide-react'
import { useState } from 'react'
import { Modal } from '../../components/ui/Modal'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card } from '../../components/ui/primitives'
import { InlineError, NoAccess } from '../../components/ui/States'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'

type DeployRun = {
  run_id: string | null
  status: 'never' | 'running' | 'succeeded' | 'failed' | 'unknown'
  started_at: string | null
  finished_at: string | null
  exit_code: number | null
  log: string
}

type Release = { version: string; released_at: string | null; notes: string }

type DeploymentStatus = {
  enabled: boolean
  version: string | null
  latest_version: string | null
  update_available: boolean
  releases: Release[]
  available: boolean
  root: string
  branch: string
  commit: string | null
  subject: string | null
  committed_at: string | null
  author: string | null
  dirty: boolean
  behind: number
  incoming: { sha: string; subject: string }[]
  fetched_at: string | null
  deploy: DeployRun
}

const runTone = { succeeded: 'green', failed: 'red', running: 'blue', unknown: 'amber', never: 'slate' } as const

/**
 * Part 17 — deployments. Shows what is running, what is waiting on GitHub,
 * and runs the deploy script. The page offers two fixed actions; it never
 * sends a command, so nothing typed here can reach a shell.
 */
export default function DeploymentsPage() {
  const canDeploy = usePermission('deploy.run')
  const queryClient = useQueryClient()
  const [confirming, setConfirming] = useState(false)
  // Which version the confirm dialog is about; null means the branch tip.
  const [target, setTarget] = useState<string | null>(null)

  const status = useQuery({
    queryKey: ['admin', 'deployments'],
    queryFn: () => apiGet<DeploymentStatus>('/api/admin/deployments'),
    enabled: canDeploy,
    // While a deploy runs the log is worth watching; otherwise stay quiet.
    refetchInterval: (query) => (query.state.data?.deploy.status === 'running' ? 3000 : false),
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['admin', 'deployments'] })

  const check = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<DeploymentStatus>('/api/admin/deployments/check'),
    onSuccess: (s) => {
      toast.success(s.behind > 0 ? `${s.behind} commit(s) waiting to deploy` : 'Already running the latest commit')
      invalidate()
    },
    onError: (e) => toast.error(getApiError(e).message),
  })

  const deploy = useMutation({
    meta: { silent: true },
    mutationFn: (version: string | null) => apiPost<{ run_id: string }>('/api/admin/deployments', { version }),
    onSuccess: (r) => {
      toast.success(`Deploy ${r.run_id} started`)
      setConfirming(false)
      setTarget(null)
      invalidate()
    },
    onError: (e) => toast.error(getApiError(e).message),
  })

  if (!canDeploy) {
    return (
      <Page>
        <PageHeader parent="Admin" title="Deployments" />
        <div className="ui-card"><NoAccess permission="deploy.run" /></div>
      </Page>
    )
  }

  const s = status.data
  const run = s?.deploy
  const running = run?.status === 'running'

  return (
    <Page>
      <PageHeader
        parent="Admin"
        title="Deployments"
        subtitle="Pull the latest code from GitHub and run it through the deploy script."
        actions={
          <div id="tour-deploy-actions" className="flex items-center gap-2">
            <Button disabled={check.isPending || running} onClick={() => check.mutate()}>
              <RefreshCw size={13} className={check.isPending ? 'animate-spin' : ''} /> {check.isPending ? 'Checking…' : 'Check for updates'}
            </Button>
            <Button variant="primary" disabled={running || !s?.enabled} onClick={() => { setTarget(null); setConfirming(true) }}>
              <Rocket size={13} /> {running ? 'Deploying…' : 'Pull and deploy'}
            </Button>
          </div>
        }
      />

      {status.error && <InlineError error={status.error} />}

      {s && !s.enabled && (
        <div className="ui-card p-4 border-l-4 border-l-amber-500 bg-amber-50/20 text-xs text-slate-700">
          <span className="font-bold">Deploying from the browser is switched off here.</span> Set <code>DEPLOY_ENABLED=true</code> in the
          server's <code>.env</code>. Checking for updates still works.
        </div>
      )}

      {s?.update_available && (
        <div className="ui-card p-4 border-l-4 border-l-amber-500 bg-amber-50/20 flex items-center justify-between gap-3">
          <div className="text-xs text-slate-700">
            <span className="font-bold">Version {s.latest_version} is available.</span> This server runs {s.version ?? 'untagged code'}.
          </div>
          <Button variant="primary" disabled={running || !s.enabled} onClick={() => { setTarget(s.latest_version); setConfirming(true) }}>
            <Rocket size={13} /> Update to {s.latest_version}
          </Button>
        </div>
      )}

      <div id="tour-deploy-status" className="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card title="Running now">
          <div className="p-4 space-y-2 text-xs">
            {!s?.available && <p className="text-slate-500">This copy is not a git checkout, so there is no version to report.</p>}
            {s?.available && (
              <>
                <div className="flex items-center gap-2">
                  <GitBranch size={14} className="text-blue-600" />
                  <span className="font-bold tabular">{s.commit}</span>
                  <span className="text-slate-500">on {s.branch}</span>
                  {s.dirty && <StatusBadge status="DIRTY" tone="amber" label="Uncommitted changes on the server" />}
                </div>
                <p className="text-slate-700">{s.subject}</p>
                <p className="text-xs text-slate-500">
                  {s.author} · {formatDateTime(s.committed_at)}
                </p>
                <p className="text-xs font-mono text-slate-400 tabular">{s.root}</p>
                <p className="text-xs mt-1">
                  <span className="font-bold text-slate-700">Version:</span>{' '}
                  <span className="tabular font-bold text-blue-700">{s.version ?? 'untagged'}</span>
                  {s.version?.endsWith('+') && <span className="text-slate-500"> (newer than the release)</span>}
                </p>
              </>
            )}
          </div>
        </Card>

        <Card title={s && s.behind > 0 ? `${s.behind} commit(s) waiting` : 'Up to date'}>
          <div className="p-4 space-y-2 text-xs">
            {s?.fetched_at && <p className="text-xs text-slate-500">Last checked {formatDateTime(s.fetched_at)}</p>}
            {s?.behind === 0 && <p className="text-slate-600">The server is running the newest commit on {s.branch}.</p>}
            {(s?.incoming.length ?? 0) > 0 && (
              <ul className="space-y-1">
                {s?.incoming.map((c) => (
                  <li key={c.sha} className="flex gap-2">
                    <span className="font-bold tabular text-blue-700">{c.sha}</span>
                    <span className="text-slate-700">{c.subject}</span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </Card>
      </div>

      <Card title="Released versions">
        <div className="p-4">
          {(s?.releases.length ?? 0) === 0 && (
            <p className="text-xs text-slate-500">
              No versions have been released yet. Tag a commit <span className="tabular font-mono">v1.0.0</span> and push the tag to publish one.
            </p>
          )}
          {(s?.releases.length ?? 0) > 0 && (
            <table className="w-full text-xs">
              <tbody>
                {s?.releases.map((r) => {
                  const isRunning = s.version === r.version
                  return (
                    <tr key={r.version} className="border-b border-slate-50 last:border-0">
                      <td className="py-2 pr-3 w-24">
                        <span className="font-bold tabular text-slate-800">{r.version}</span>
                        {isRunning && <StatusBadge status="RUNNING" tone="green" label="running" />}
                      </td>
                      <td className="py-2 pr-3 text-slate-600">{r.notes}</td>
                      <td className="py-2 pr-3 text-xs text-slate-500 whitespace-nowrap">{formatDateTime(r.released_at)}</td>
                      <td className="py-2 text-right">
                        {!isRunning && (
                          <Button size="sm" disabled={running || !s.enabled} onClick={() => { setTarget(r.version); setConfirming(true) }}>
                            Switch to this
                          </Button>
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          )}
        </div>
      </Card>

      <Card
        title="Last deploy"
        actions={run && run.status !== 'never' ? <StatusBadge status={run.status.toUpperCase()} tone={runTone[run.status]} /> : null}
      >
        <div className="p-4 space-y-2">
          {run?.status === 'never' && <p className="text-xs text-slate-500">Nothing has been deployed from this screen yet.</p>}
          {run && run.status !== 'never' && (
            <p className="text-xs text-slate-500">
              {run.run_id} · started {formatDateTime(run.started_at)}
              {run.finished_at && <> · finished {formatDateTime(run.finished_at)}</>}
              {run.exit_code !== null && run.exit_code !== 0 && <span className="text-rose-600 font-bold"> · exit code {run.exit_code}</span>}
            </p>
          )}
          {!!run?.log && (
            <pre className="bg-slate-900 text-slate-100 text-xs font-mono leading-relaxed rounded-xl p-3 overflow-auto max-h-[420px] whitespace-pre-wrap">
              {run.log}
            </pre>
          )}
        </div>
      </Card>

      <Modal
        open={confirming}
        onClose={() => setConfirming(false)}
        title={target ? `Switch to ${target}?` : 'Pull and deploy?'}
        footer={
          <>
            <Button onClick={() => setConfirming(false)}>Cancel</Button>
            <Button variant="primary" disabled={deploy.isPending} onClick={() => deploy.mutate(target)}>
              {deploy.isPending ? 'Starting…' : target ? `Deploy ${target}` : 'Deploy now'}
            </Button>
          </>
        }
      >
        <div className="space-y-2 text-xs text-slate-700">
          <p>This runs the deploy script on the server, in this order:</p>
          <ol className="list-decimal pl-5 space-y-1 text-xs">
            <li>back up the database</li>
            <li>fetch {target ? `version ${target}` : `${s?.branch} from GitHub`} and install PHP dependencies</li>
            <li>apply pending migrations and reference seeders</li>
            <li>build the SPA, re-cache and restart the workers</li>
          </ol>
          <p className="text-xs text-slate-500">
            The shop goes into maintenance mode for the database and cache steps — seconds, not the whole build. If it fails, the log
            below shows where it stopped.
          </p>
        </div>
      </Modal>
    </Page>
  )
}
