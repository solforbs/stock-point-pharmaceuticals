import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useDebounced } from '../../components/ProductSearch'
import { UserPicker } from '../../components/UserPicker'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime, todayIso, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated } from '../../lib/types'
import { downloadFile, MAX_UPLOAD_BYTES, toFormData } from './files'

type Category = 'SOP' | 'POLICY' | 'FORM' | 'WORK_INSTRUCTION' | 'OTHER'
type DocStatus = 'DRAFT' | 'ACTIVE' | 'RETIRED'

type DocVersion = {
  id: string
  controlled_document_id: string
  version: string
  file_name: string
  change_summary: string | null
  effective_date: string
  created_at: string
  acknowledgements_count?: number
  uploader?: { id: number; name: string } | null
}

type ControlledDoc = {
  id: string
  code: string
  title: string
  category: Category
  status: DocStatus
  review_due_date: string | null
  current_version_id: string | null
  current_version: DocVersion | null
  owner?: { id: number; name: string } | null
  my_acknowledged_at?: string | null
  acknowledgement_count?: number
  versions?: DocVersion[]
}

type AckList = {
  version: DocVersion | null
  acknowledged: { id: string; user_id: number; acknowledged_at: string; user: { id: number; name: string; username: string | null } | null }[]
  pending: { id: number; name: string; username: string | null }[]
}

const CATEGORIES: Category[] = ['SOP', 'POLICY', 'FORM', 'WORK_INSTRUCTION', 'OTHER']
const ACCEPT = '.pdf,.doc,.docx,.jpg,.jpeg,.png'

/** V6 Part 16.4 — controlled documents: versioned, and acknowledged per version by every member of staff. */
export default function SopsPage() {
  const canManage = usePermission('document.manage')
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()
  const selectedId = params.get('document')
  const [status, setStatus] = useState('')
  const [category, setCategory] = useState('')
  const [pendingOnly, setPendingOnly] = useState(false)
  const [q, setQ] = useState('')
  const dq = useDebounced(q, 250)
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [acking, setAcking] = useState<ControlledDoc | null>(null)

  const list = useQuery({
    queryKey: ['documents', 'list', status, category, pendingOnly, dq, page],
    queryFn: () => apiGet<Paginated<ControlledDoc>>('/api/documents', { status, category, q: dq, pending_only: pendingOnly ? 1 : undefined, page }),
    placeholderData: (prev) => prev,
  })

  const acknowledge = useMutation({
    mutationFn: (doc: ControlledDoc) => apiPost(`/api/documents/${doc.id}/acknowledge`),
    onSuccess: (_, doc) => {
      toast.success(`${doc.code} v${doc.current_version?.version ?? ''} acknowledged`)
      setAcking(null)
      queryClient.invalidateQueries({ queryKey: ['documents'] })
    },
  })

  const columns: Column<ControlledDoc>[] = [
    { key: 'code', header: 'Code', render: (d) => <span className="font-semibold font-mono tabular">{d.code}</span>, sortValue: (d) => d.code },
    { key: 'title', header: 'Title', render: (d) => <>{d.title}<div className="text-xs text-slate-500">{titleCase(d.category)}</div></>, sortValue: (d) => d.title },
    { key: 'version', header: 'Current version', render: (d) => (d.current_version ? <span className="tabular font-mono text-xs">v{d.current_version.version} · effective {formatDate(d.current_version.effective_date)}</span> : '—') },
    { key: 'review', header: 'Review due', render: (d) => <span className={`tabular font-mono text-xs ${d.review_due_date && d.review_due_date < todayIso() ? 'text-rose-600 font-semibold' : ''}`}>{formatDate(d.review_due_date)}</span>, sortValue: (d) => d.review_due_date ?? '' },
    ...(canManage
      ? [
          { key: 'status', header: 'Status', render: (d: ControlledDoc) => <StatusBadge status={d.status} /> } satisfies Column<ControlledDoc>,
          { key: 'acks', header: 'Acknowledged', align: 'right' as const, render: (d: ControlledDoc) => <span className="tabular">{d.acknowledgement_count ?? 0}</span>, sortValue: (d: ControlledDoc) => d.acknowledgement_count ?? 0 } satisfies Column<ControlledDoc>,
        ]
      : []),
    {
      key: 'mine', header: 'You', render: (d) =>
        d.status !== 'ACTIVE' ? <span className="text-slate-400">—</span>
          : d.my_acknowledged_at ? <StatusBadge status="COMPLETED" label={`Acknowledged ${formatDate(d.my_acknowledged_at)}`} />
            : <StatusBadge status="PENDING" label="Not yet acknowledged" />,
    },
    {
      key: 'act', header: '', align: 'right', render: (d) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          {d.current_version && <Button size="sm" onClick={() => void downloadFile(`/api/documents/${d.id}/versions/${d.current_version?.id}/download`, d.current_version?.file_name ?? `${d.code}.pdf`)}>Open</Button>}
          {d.status === 'ACTIVE' && !d.my_acknowledged_at && <Button size="sm" variant="primary" onClick={() => setAcking(d)}>Read &amp; acknowledge</Button>}
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        parent="Quality & Compliance"
        title="SOPs & Documents"
        subtitle="Staff acknowledge each version of an SOP; a new version starts acknowledgement again. Every acknowledgement is recorded in the audit log (V6 Part 16.4)."
        actions={canManage ? <Button variant="primary" onClick={() => setCreating(true)}>New document</Button> : null}
      />
      <FilterBar>
        <Field label="Search" className="w-56"><Input placeholder="Code or title" value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} /></Field>
        <Field label="Category" className="w-44">
          <Select value={category} onChange={(e) => { setCategory(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {CATEGORIES.map((c) => (<option key={c} value={c}>{titleCase(c)}</option>))}
          </Select>
        </Field>
        {canManage && (
          <Field label="Status" className="w-36">
            <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
              <option value="">All</option>
              <option value="DRAFT">Draft</option>
              <option value="ACTIVE">Active</option>
              <option value="RETIRED">Retired</option>
            </Select>
          </Field>
        )}
        <label className="flex items-center gap-2 text-xs text-slate-600 pb-2 cursor-pointer"><input type="checkbox" checked={pendingOnly} onChange={(e) => { setPendingOnly(e.target.checked); setPage(1) }} /> Only ones I have not acknowledged</label>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(d) => d.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={canManage ? (d) => setParams({ document: d.id }) : undefined} selectedKey={selectedId} emptyTitle={pendingOnly ? 'Nothing waiting for your acknowledgement' : 'No documents'} emptyHint={canManage ? 'Upload an SOP, then activate it so staff can acknowledge it.' : undefined} />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      {canManage && <ManageDrawer id={selectedId} onClose={() => setParams({})} />}
      <Drawer open={creating} onClose={() => setCreating(false)} title="New controlled document" width={640}>
        {creating && <NewDocumentForm onDone={(d) => { setCreating(false); setParams({ document: d.id }) }} onCancel={() => setCreating(false)} />}
      </Drawer>
      <ConfirmDialog
        open={acking !== null}
        title={`Acknowledge ${acking?.code ?? ''} v${acking?.current_version?.version ?? ''}`}
        message={
          <span className="block space-y-2">
            <span className="block">By confirming, you record that you have read and understood <b>{acking?.title}</b>, version {acking?.current_version?.version}. This is recorded against your name in the audit log.</span>
            {acking?.current_version && (
              <button type="button" className="text-blue-600 font-medium hover:underline" onClick={() => acking.current_version && void downloadFile(`/api/documents/${acking.id}/versions/${acking.current_version.id}/download`, acking.current_version.file_name)}>Open the document first</button>
            )}
            {acknowledge.isError && <InlineError error={acknowledge.error} />}
          </span>
        }
        confirmLabel="I have read and understood it"
        isPending={acknowledge.isPending}
        onCancel={() => setAcking(null)}
        onConfirm={() => acking && acknowledge.mutate(acking)}
      />
    </Page>
  )
}

function FileInput({ onFile, error }: { onFile: (file: File | null, error: string | null) => void; error?: string | null }) {
  return (
    <Field label="File" required hint="PDF, Word or image, up to 10 MB." error={error}>
      <input
        type="file"
        accept={ACCEPT}
        className="block text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
        onChange={(e) => {
          const f = e.target.files?.[0] ?? null
          onFile(f, f && f.size > MAX_UPLOAD_BYTES ? 'The file is larger than 10 MB.' : null)
        }}
      />
    </Field>
  )
}

function NewDocumentForm({ onDone, onCancel }: { onDone: (d: ControlledDoc) => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ code: '', title: '', category: 'SOP' as Category, review_due_date: '', version: '1.0', change_summary: 'First issue', effective_date: todayIso() })
  const [owner, setOwner] = useState<number | null>(null)
  const [file, setFile] = useState<File | null>(null)
  const [fileError, setFileError] = useState<string | null>(null)
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<ControlledDoc>('/api/documents', toFormData({ ...form, owner_user_id: owner, file })),
    onSuccess: (d) => {
      toast.success(`${d.code} created as a draft`)
      queryClient.invalidateQueries({ queryKey: ['documents'] })
      onDone(d)
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const valid = form.code.trim() && form.title.trim() && form.version.trim() && form.effective_date && file && !fileError

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Code" required error={err?.errors.code?.[0]}><Input value={form.code} placeholder="SOP-CC-01" onChange={(e) => set({ code: e.target.value.toUpperCase() })} /></Field>
        <Field label="Category" required>
          <Select value={form.category} onChange={(e) => set({ category: e.target.value as Category })}>
            {CATEGORIES.map((c) => (<option key={c} value={c}>{titleCase(c)}</option>))}
          </Select>
        </Field>
      </div>
      <Field label="Title" required error={err?.errors.title?.[0]}><Input value={form.title} onChange={(e) => set({ title: e.target.value })} /></Field>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Owner" hint="Defaults to you."><UserPicker value={owner} onChange={(id) => setOwner(id)} /></Field>
        <Field label="Review due" error={err?.errors.review_due_date?.[0]}><Input type="date" value={form.review_due_date} onChange={(e) => set({ review_due_date: e.target.value })} /></Field>
        <Field label="Version" required error={err?.errors.version?.[0]}><Input value={form.version} onChange={(e) => set({ version: e.target.value })} /></Field>
        <Field label="Effective date" required error={err?.errors.effective_date?.[0]}><Input type="date" value={form.effective_date} onChange={(e) => set({ effective_date: e.target.value })} /></Field>
      </div>
      <Field label="Change summary"><Textarea rows={2} value={form.change_summary} onChange={(e) => set({ change_summary: e.target.value })} /></Field>
      <FileInput error={fileError ?? err?.errors.file?.[0]} onFile={(f, e) => { setFile(f); setFileError(e) }} />
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={!valid || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Uploading…' : 'Create draft'}</Button>
      </div>
    </div>
  )
}

function ManageDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [uploading, setUploading] = useState(false)
  const [retiring, setRetiring] = useState(false)
  const detail = useQuery({ queryKey: ['documents', 'detail', id], queryFn: () => apiGet<ControlledDoc>(`/api/documents/${id}`), enabled: !!id })
  const acks = useQuery({ queryKey: ['documents', 'acks', id, detail.data?.current_version_id], queryFn: () => apiGet<AckList>(`/api/documents/${id}/acknowledgements`), enabled: !!id && !!detail.data?.current_version_id })
  const d = detail.data

  const transition = useMutation({
    mutationFn: (to: 'activate' | 'retire') => apiPost<ControlledDoc>(`/api/documents/${id}/${to}`),
    onSuccess: (x) => {
      toast.success(`${x.code} is now ${titleCase(x.status)}`)
      setRetiring(false)
      queryClient.invalidateQueries({ queryKey: ['documents'] })
    },
  })

  return (
    <Drawer open={!!id} onClose={() => { setUploading(false); onClose() }} title={d ? `${d.code} — ${d.title}` : 'Document'} subtitle={d ? `${titleCase(d.category)} · owner ${d.owner?.name ?? '—'}` : undefined} width={680}>
      {detail.isLoading && <LoadingSkeleton rows={8} />}
      {detail.error && <InlineError error={detail.error} />}
      {d && (
        <div className="space-y-5">
          <div className="flex items-center justify-between gap-2">
            <StatusBadge status={d.status} />
            <div className="flex gap-2">
              {d.status !== 'RETIRED' && <Button onClick={() => setUploading(!uploading)}>{uploading ? 'Cancel upload' : 'Upload new version'}</Button>}
              {d.status !== 'ACTIVE' && <Button variant="success" disabled={transition.isPending || !d.current_version_id} onClick={() => transition.mutate('activate')}>{d.status === 'RETIRED' ? 'Reactivate' : 'Activate'}</Button>}
              {d.status === 'ACTIVE' && <Button variant="danger" onClick={() => setRetiring(true)}>Retire</Button>}
            </div>
          </div>
          {transition.isError && <InlineError error={transition.error} />}
          {uploading && <NewVersionForm doc={d} onDone={() => setUploading(false)} />}
          <DescriptionList
            items={[
              { label: 'Current version', value: d.current_version ? `v${d.current_version.version} · effective ${formatDate(d.current_version.effective_date)}` : '—' },
              { label: 'Review due', value: formatDate(d.review_due_date) },
            ]}
          />
          <section>
            <div className="text-xs font-semibold text-slate-900 mb-1.5 uppercase tracking-wide">Versions</div>
            <div className="border border-slate-200 rounded-lg divide-y divide-slate-100">
              {(d.versions ?? []).map((v) => (
                <div key={v.id} className="flex items-start justify-between gap-3 px-3.5 py-2.5">
                  <div className="min-w-0">
                    <div className="font-semibold text-slate-900">v{v.version} {v.id === d.current_version_id && <StatusBadge status="ACTIVE" label="Current" className="ml-1" />}</div>
                    <div className="text-xs text-slate-500 font-mono mt-0.5">Effective {formatDate(v.effective_date)} · uploaded {formatDateTime(v.created_at)} by {v.uploader?.name ?? '—'} · {v.acknowledgements_count ?? 0} acknowledged</div>
                    {v.change_summary && <div className="text-xs text-slate-600 mt-0.5">{v.change_summary}</div>}
                  </div>
                  <Button size="sm" onClick={() => void downloadFile(`/api/documents/${d.id}/versions/${v.id}/download`, v.file_name)}>Download</Button>
                </div>
              ))}
            </div>
          </section>
          <section>
            <div className="flex items-center justify-between mb-1.5">
              <div className="text-xs font-semibold text-slate-900 uppercase tracking-wide">Acknowledgements — v{d.current_version?.version ?? '—'}</div>
              {acks.data && <span className="text-xs text-slate-500 tabular font-mono">{acks.data.acknowledged.length} of {acks.data.acknowledged.length + acks.data.pending.length} staff</span>}
            </div>
            {acks.isLoading && <LoadingSkeleton rows={3} />}
            {acks.error && <InlineError error={acks.error} />}
            {acks.data && (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="border border-slate-200 rounded-lg max-h-64 overflow-y-auto">
                  <div className="px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border-b border-slate-200 rounded-t-lg">Acknowledged</div>
                  {acks.data.acknowledged.length === 0 && <div className="px-3 py-2.5 text-xs text-slate-500">No one yet.</div>}
                  {acks.data.acknowledged.map((a) => (
                    <div key={a.id} className="px-3 py-1.5 text-xs flex justify-between gap-2"><span className="truncate text-slate-800">{a.user?.name ?? `User #${a.user_id}`}</span><span className="tabular text-slate-500 font-mono">{formatDateTime(a.acknowledged_at)}</span></div>
                  ))}
                </div>
                <div className="border border-slate-200 rounded-lg max-h-64 overflow-y-auto">
                  <div className="px-3 py-1.5 text-xs font-semibold text-amber-700 bg-amber-50 border-b border-slate-200 rounded-t-lg">Not yet acknowledged</div>
                  {acks.data.pending.length === 0 && <div className="px-3 py-2.5 text-xs text-slate-500">Everyone has acknowledged.</div>}
                  {acks.data.pending.map((u) => (
                    <div key={u.id} className="px-3 py-1.5 text-xs truncate text-slate-700">{u.name}{u.username ? <span className="text-slate-400 font-mono"> ({u.username})</span> : null}</div>
                  ))}
                </div>
              </div>
            )}
          </section>
        </div>
      )}
      <ConfirmDialog
        open={retiring}
        title={`Retire ${d?.code ?? ''}`}
        message="Retired documents disappear from staff lists and can no longer be acknowledged. The versions and acknowledgement history are kept."
        confirmLabel="Retire"
        danger
        isPending={transition.isPending}
        onCancel={() => setRetiring(false)}
        onConfirm={() => transition.mutate('retire')}
      />
    </Drawer>
  )
}

function suggestNextVersion(current: string | undefined): string {
  if (!current) return '1.0'
  const m = /^(\d+)\.(\d+)$/.exec(current)
  return m ? `${m[1]}.${Number(m[2]) + 1}` : ''
}

function NewVersionForm({ doc, onDone }: { doc: ControlledDoc; onDone: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ version: suggestNextVersion(doc.current_version?.version), change_summary: '', effective_date: todayIso() })
  const [file, setFile] = useState<File | null>(null)
  const [fileError, setFileError] = useState<string | null>(null)
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<DocVersion>(`/api/documents/${doc.id}/versions`, toFormData({ ...form, file })),
    onSuccess: (v) => {
      toast.success(`v${v.version} is now current — acknowledgements start again`)
      queryClient.invalidateQueries({ queryKey: ['documents'] })
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <div className="rounded-lg border border-slate-200 p-4 space-y-3 bg-slate-50">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Version" required error={err?.errors.version?.[0]}><Input value={form.version} onChange={(e) => set({ version: e.target.value })} /></Field>
        <Field label="Effective date" required error={err?.errors.effective_date?.[0]}><Input type="date" value={form.effective_date} onChange={(e) => set({ effective_date: e.target.value })} /></Field>
      </div>
      <Field label="What changed"><Textarea rows={2} value={form.change_summary} onChange={(e) => set({ change_summary: e.target.value })} /></Field>
      <FileInput error={fileError ?? err?.errors.file?.[0]} onFile={(f, e) => { setFile(f); setFileError(e) }} />
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end">
        <Button variant="primary" disabled={!form.version.trim() || !file || !!fileError || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Uploading…' : 'Issue version'}</Button>
      </div>
    </div>
  )
}
