import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, DatabaseBackup, Download } from 'lucide-react'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card } from '../../components/ui/primitives'
import { api, apiGet, apiPost } from '../../lib/api'
import { formatBytes } from '../../lib/bytes'
import { downloadBlob } from '../../lib/csv'
import { formatDateTime } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast, toastApiError } from '../../lib/toast'

type BackupFile = { name: string; size: number; created_at: string; kind: 'database' | 'files' | 'full' }
type BackupListing = { data: BackupFile[]; retention_days: number; mysqldump_available: boolean; schedule: string }

/** Part 17 — database backups: what exists, taking one now, and getting a copy off the server. */
export default function BackupPage() {
  const canManage = usePermission('admin.settings')
  const queryClient = useQueryClient()
  const [downloading, setDownloading] = useState<string | null>(null)
  const listing = useQuery({ queryKey: ['admin', 'backups'], queryFn: () => apiGet<BackupListing>('/api/admin/backups'), enabled: canManage })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: (kind: 'database' | 'full' = 'database') => apiPost<BackupFile>('/api/admin/backups', { kind }),
    onSuccess: (f) => {
      toast.success(`Backup ${f.name} written (${formatBytes(f.size)})`)
      queryClient.invalidateQueries({ queryKey: ['admin', 'backups'] })
      queryClient.invalidateQueries({ queryKey: ['admin', 'system-health'] })
    },
  })

  if (!canManage) return <Page><PageHeader parent="Admin" title="Backup" /><div className="ui-card"><NoAccess permission="admin.settings" /></div></Page>

  const download = async (file: BackupFile) => {
    setDownloading(file.name)
    try {
      const { data } = await api.get<Blob>(`/api/admin/backups/${encodeURIComponent(file.name)}/download`, { responseType: 'blob' })
      downloadBlob(data, file.name, file.name.endsWith('.zip') ? 'application/zip' : 'application/gzip')
    } catch (e) {
      toastApiError(e, 'Download failed')
    } finally {
      setDownloading(null)
    }
  }

  const l = listing.data
  const latest = l?.data.find((f) => f.kind === 'database')
  const latestAgeHours = latest ? (listing.dataUpdatedAt - new Date(latest.created_at).getTime()) / 3_600_000 : null

  const columns: Column<BackupFile>[] = [
    { key: 'name', header: 'File', render: (f) => <span className="font-semibold tabular">{f.name}</span>, sortValue: (f) => f.name },
    {
      key: 'kind',
      header: 'Contents',
      render: (f) => (
        <StatusBadge
          status={f.kind}
          tone={f.kind === 'database' ? 'blue' : f.kind === 'full' ? 'green' : 'slate'}
          label={f.kind === 'database' ? 'Database' : f.kind === 'full' ? 'Database + files' : 'Files'}
        />
      ),
    },
    { key: 'created', header: 'Taken', render: (f) => <span className="tabular">{formatDateTime(f.created_at)}</span>, sortValue: (f) => f.created_at },
    { key: 'size', header: 'Size', align: 'right', render: (f) => <span className="tabular">{formatBytes(f.size)}</span>, sortValue: (f) => f.size },
    {
      key: 'download', header: '', align: 'right', render: (f) => (
        <Button size="sm" disabled={downloading === f.name} onClick={() => void download(f)}><Download size={12} /> {downloading === f.name ? 'Downloading…' : 'Download'}</Button>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        parent="Admin"
        title="Backup"
        subtitle={l ? `${l.schedule} Backups older than ${l.retention_days} days are deleted automatically.` : 'Compressed MySQL dumps of the whole database.'}
        actions={
          <div className="flex items-center gap-2">
            <Button disabled={create.isPending || l?.mysqldump_available === false} onClick={() => create.mutate('database')}>
              <DatabaseBackup size={13} /> {create.isPending && create.variables === 'database' ? 'Backing up…' : 'Database only'}
            </Button>
            <Button variant="primary" disabled={create.isPending || l?.mysqldump_available === false} onClick={() => create.mutate('full')}>
              <DatabaseBackup size={13} /> {create.isPending && create.variables === 'full' ? 'Backing up…' : 'Full backup (zip)'}
            </Button>
          </div>
        }
      />

      <div className="space-y-4">
        <div className="ui-card p-4 border-l-4 flex gap-3" style={{ borderLeftColor: 'var(--status-amber)' }}>
          <AlertTriangle size={18} className="shrink-0 text-[var(--status-amber)]" />
          <div className="text-[12px] text-[var(--text-secondary)] space-y-1">
            <div className="font-bold text-[var(--text)]">Off-site copy: backups on this server do not survive losing the server</div>
            <p>
              Every file listed here sits on the same disk as the live database. A failed disk, a lost or hacked VPS, ransomware or an
              accidental deletion takes the backups with it. At least once a week, download the latest database backup and keep it somewhere
              else (an encrypted USB drive in a different building, or cloud storage the server cannot write to). A backup that has never
              been restored is only a hope: test a restore on a spare machine now and then.
            </p>
          </div>
        </div>

        {l?.mysqldump_available === false && (
          <div className="ui-card p-4 text-[12px] border-l-4" style={{ borderLeftColor: 'var(--status-red)' }}>
            <span className="font-bold">mysqldump was not found on this server.</span> Install the MySQL client tools, or set <code>MYSQLDUMP_PATH</code> in
            <code> .env</code> to the mysqldump binary, then reload this page. Existing backups can still be downloaded.
          </div>
        )}
        {create.isError && <InlineError error={create.error} />}

        {l && (
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <Card><div className="p-4"><div className="text-[11px] text-[var(--text-muted)]">Latest database backup</div><div className="text-[14px] font-bold tabular mt-0.5">{latest ? formatDateTime(latest.created_at) : 'None yet'}</div>
              {latestAgeHours != null && <div className={`text-[11px] mt-0.5 ${latestAgeHours > 26 ? 'text-[var(--status-red)]' : 'text-[var(--text-muted)]'}`}>{latestAgeHours < 1 ? 'within the hour' : `${Math.floor(latestAgeHours)} hour(s) ago`}</div>}</div></Card>
            <Card><div className="p-4"><div className="text-[11px] text-[var(--text-muted)]">Backups kept</div><div className="text-[14px] font-bold tabular mt-0.5">{l.data.length}</div><div className="text-[11px] text-[var(--text-muted)] mt-0.5">{formatBytes(l.data.reduce((s, f) => s + f.size, 0))} in total</div></div></Card>
            <Card><div className="p-4"><div className="text-[11px] text-[var(--text-muted)]">Retention</div><div className="text-[14px] font-bold tabular mt-0.5">{l.retention_days} days</div><div className="text-[11px] text-[var(--text-muted)] mt-0.5">Set BACKUP_RETENTION_DAYS in .env to change it.</div></div></Card>
          </div>
        )}

        <div className="ui-card">
          <DataTable
            columns={columns}
            rows={l?.data}
            rowKey={(f) => f.name}
            isLoading={listing.isLoading}
            error={listing.error}
            onRetry={() => listing.refetch()}
            emptyTitle="No backups yet"
            emptyHint="Take the first one with “Back up now”. Once the scheduler cron is installed a backup is also taken every night at 02:00."
          />
        </div>
      </div>
    </Page>
  )
}
