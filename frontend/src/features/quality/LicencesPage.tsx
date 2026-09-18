import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, NoAccess } from '../../components/ui/States'
import { StatusBadge, type StatusTone } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import { downloadFile, MAX_UPLOAD_BYTES, toFormData } from './files'

type HolderType = 'ORGANISATION' | 'BRANCH' | 'EMPLOYEE' | 'SUPPLIER'
type LicenceStatus = 'VALID' | 'EXPIRING' | 'EXPIRED'

type Licence = {
  id: string
  holder_type: HolderType
  holder_id: string | null
  holder_name: string | null
  licence_type: string
  licence_number: string | null
  issued_by: string | null
  issue_date: string | null
  expiry_date: string | null
  notes: string | null
  is_active: boolean
  status: LicenceStatus
  days_to_expiry: number | null
  has_document: boolean
  document_name?: string | null
  source: 'licence' | 'supplier'
  read_only: boolean
  updated_at?: string
}

type LicenceList = { data: Licence[]; summary: { valid: number; expiring: number; expired: number } }

const HOLDER_TYPES: HolderType[] = ['ORGANISATION', 'BRANCH', 'EMPLOYEE', 'SUPPLIER']
const LICENCE_TYPES: { value: string; label: string }[] = [
  { value: 'PPB_PREMISES', label: 'PPB premises licence' },
  { value: 'PPB_PHARMACIST', label: 'PPB pharmacist practising licence' },
  { value: 'PPB_PHARMTECH', label: 'PPB pharmaceutical technologist licence' },
  { value: 'BUSINESS_PERMIT', label: 'County single business permit' },
  { value: 'FIRE', label: 'Fire safety certificate' },
  { value: 'PUBLIC_HEALTH', label: 'Public health certificate' },
  { value: 'KRA_TCC', label: 'KRA tax compliance certificate' },
  { value: 'NHIF_SHIF', label: 'SHIF / NHIF accreditation' },
  { value: 'OTHER', label: 'Other' },
]
const typeLabel = (v: string) => LICENCE_TYPES.find((t) => t.value === v)?.label ?? titleCase(v)
const STATUS_TONE: Record<LicenceStatus, StatusTone> = { VALID: 'green', EXPIRING: 'amber', EXPIRED: 'red' }

/** Licence expiry against the register's 60-day warning window (not the stock expiry tiers). */
function LicenceExpiry({ licence }: { licence: Licence }) {
  const days = licence.days_to_expiry
  const label = licence.status === 'EXPIRED' ? 'Expired' : licence.status === 'EXPIRING' ? `${days} d left` : 'Valid'
  return (
    <span className="inline-flex items-center gap-1.5 tabular whitespace-nowrap">
      {formatDate(licence.expiry_date)}
      <StatusBadge status={licence.status} tone={STATUS_TONE[licence.status]} label={label} />
    </span>
  )
}

/** V6 Part 16.3 — every licence and certificate the business depends on, with expiry warnings at 60 days. */
export default function LicencesPage() {
  const canView = usePermission('licence.view')
  const canManage = usePermission('licence.manage')
  const [params, setParams] = useSearchParams()
  const selectedId = params.get('licence')
  const [holderType, setHolderType] = useState('')
  const [status, setStatus] = useState('')
  const [q, setQ] = useState('')
  const [archived, setArchived] = useState(false)
  const [creating, setCreating] = useState(false)
  const dq = useDebounced(q, 250)

  const list = useQuery({
    queryKey: ['licences', holderType, status, dq, archived],
    queryFn: () => apiGet<LicenceList>('/api/licences', { holder_type: holderType, status, q: dq, include_archived: archived ? 1 : undefined }),
    enabled: canView || canManage,
    placeholderData: (prev) => prev,
  })
  const selected = list.data?.data.find((l) => l.id === selectedId) ?? null

  if (!canView && !canManage) {
    return (
      <Page>
        <PageHeader parent="Quality & Compliance" title="Licences & Certificates" />
        <div className="ui-card"><NoAccess permission="licence.view" /></div>
      </Page>
    )
  }

  const columns: Column<Licence>[] = [
    { key: 'type', header: 'Licence', render: (l) => <><div className="font-semibold">{typeLabel(l.licence_type)}</div><div className="text-[10.5px] text-[var(--text-muted)] tabular">{l.licence_number ?? 'No number recorded'}</div></>, sortValue: (l) => l.licence_type },
    { key: 'holder', header: 'Holder', render: (l) => <>{l.holder_name ?? '—'}<div className="text-[10.5px] text-[var(--text-muted)]">{titleCase(l.holder_type)}{l.source === 'supplier' ? ' · from supplier master' : ''}</div></>, sortValue: (l) => l.holder_name ?? '' },
    { key: 'issuer', header: 'Issued by', render: (l) => l.issued_by ?? '—' },
    { key: 'expiry', header: 'Expiry', render: (l) => <LicenceExpiry licence={l} />, sortValue: (l) => l.expiry_date ?? '' },
    { key: 'doc', header: 'Document', render: (l) => (l.has_document ? <button type="button" className="text-[var(--color-navy)] underline text-[12px]" onClick={(e) => { e.stopPropagation(); void downloadFile(`/api/licences/${l.id}/document`, `${l.licence_number ?? 'licence'}.pdf`) }}>Download</button> : <span className="text-[var(--text-muted)]">—</span>) },
    { key: 'state', header: '', render: (l) => (!l.is_active ? <StatusBadge status="ARCHIVED" /> : l.read_only ? <StatusBadge status="INFO" tone="slate" label="Read only" /> : null) },
  ]

  const summary = list.data?.summary

  return (
    <Page>
      <PageHeader
        parent="Quality & Compliance"
        title="Licences & Certificates"
        subtitle="Premises, practising, permit and tax certificates in one register. Supplier licences come from the supplier master; a PO to a supplier with an expired licence is refused."
        actions={canManage ? <Button variant="primary" onClick={() => setCreating(true)}>Add licence</Button> : null}
      />
      <div className="grid grid-cols-3 gap-3 mb-4 max-w-2xl">
        {([['EXPIRED', 'Expired', summary?.expired], ['EXPIRING', 'Expiring ≤ 60 days', summary?.expiring], ['VALID', 'Valid', summary?.valid]] as const).map(([key, label, count]) => (
          <button key={key} type="button" onClick={() => setStatus(status === key ? '' : key)} className={`ui-card p-3 text-left ${status === key ? 'ring-2 ring-[var(--color-navy)]' : ''}`}>
            <div className="text-[11px] text-[var(--text-muted)]">{label}</div>
            <div className="text-[22px] font-extrabold tabular" style={{ color: `var(--status-${STATUS_TONE[key]})` }}>{count ?? '…'}</div>
          </button>
        ))}
      </div>
      <FilterBar>
        <Field label="Search" className="w-64"><Input placeholder="Number, holder or issuer" value={q} onChange={(e) => setQ(e.target.value)} /></Field>
        <Field label="Holder" className="w-44">
          <Select value={holderType} onChange={(e) => setHolderType(e.target.value)}>
            <option value="">All holders</option>
            {HOLDER_TYPES.map((h) => (<option key={h} value={h}>{titleCase(h)}</option>))}
          </Select>
        </Field>
        <Field label="Status" className="w-40">
          <Select value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All</option>
            <option value="EXPIRED">Expired</option>
            <option value="EXPIRING">Expiring</option>
            <option value="VALID">Valid</option>
          </Select>
        </Field>
        <label className="flex items-center gap-2 text-[12px] pb-2"><input type="checkbox" checked={archived} onChange={(e) => setArchived(e.target.checked)} /> Include archived</label>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(l) => l.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(l) => setParams({ licence: l.id })} selectedKey={selectedId} emptyTitle="No licences recorded" emptyHint={canManage ? 'Add the premises licence, practising licences and permits so expiry is never a surprise.' : undefined} />
      </div>
      <LicenceDrawer licence={selected} onClose={() => setParams({})} />
      <Drawer open={creating} onClose={() => setCreating(false)} title="Add licence or certificate" width={640}>
        {creating && <LicenceForm onDone={(l) => { setCreating(false); setParams({ licence: l.id }) }} onCancel={() => setCreating(false)} />}
      </Drawer>
    </Page>
  )
}

function LicenceDrawer({ licence, onClose }: { licence: Licence | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const canManage = usePermission('licence.manage')
  const [editing, setEditing] = useState(false)
  const [archiving, setArchiving] = useState(false)
  const archive = useMutation({
    mutationFn: (active: boolean) => apiPatch<Licence>(`/api/licences/${licence?.id}`, { is_active: active }),
    onSuccess: (l) => {
      toast.success(l.is_active ? 'Licence restored' : 'Licence archived')
      setArchiving(false)
      queryClient.invalidateQueries({ queryKey: ['licences'] })
    },
  })

  return (
    <Drawer open={!!licence} onClose={() => { setEditing(false); onClose() }} title={licence ? typeLabel(licence.licence_type) : ''} subtitle={licence?.holder_name ?? undefined} width={editing ? 640 : 560}>
      {licence && editing && <LicenceForm licence={licence} onDone={() => setEditing(false)} onCancel={() => setEditing(false)} />}
      {licence && !editing && (
        <div className="space-y-4">
          <div className="flex items-center justify-between gap-2">
            <LicenceExpiry licence={licence} />
            {canManage && !licence.read_only && (
              <div className="flex gap-2">
                <Button onClick={() => setEditing(true)}>Edit / renew</Button>
                {licence.is_active ? <Button variant="danger" onClick={() => setArchiving(true)}>Archive</Button> : <Button disabled={archive.isPending} onClick={() => archive.mutate(true)}>Restore</Button>}
              </div>
            )}
          </div>
          {licence.read_only && (
            <div className="text-[11.5px] text-[var(--text-secondary)] rounded-md px-3 py-2 bg-[var(--surface-2)]">
              This row comes from the supplier master. Change the licence number or expiry on the supplier record (Buy → Suppliers).
            </div>
          )}
          <DescriptionList
            items={[
              { label: 'Licence number', value: licence.licence_number ?? '—' },
              { label: 'Holder', value: `${licence.holder_name ?? '—'} (${titleCase(licence.holder_type)})` },
              { label: 'Issued by', value: licence.issued_by ?? '—' },
              { label: 'Issue date', value: formatDate(licence.issue_date) },
              { label: 'Expiry date', value: formatDate(licence.expiry_date) },
              { label: 'Notes', value: licence.notes ? <span className="whitespace-pre-wrap">{licence.notes}</span> : '—' },
              { label: 'Status', value: licence.is_active ? 'Active' : 'Archived' },
              ...(licence.updated_at ? [{ label: 'Last updated', value: formatDateTime(licence.updated_at) }] : []),
            ]}
          />
          {licence.has_document && (
            <Button onClick={() => void downloadFile(`/api/licences/${licence.id}/document`, licence.document_name ?? 'licence.pdf')}>Download {licence.document_name ?? 'document'}</Button>
          )}
        </div>
      )}
      <ConfirmDialog
        open={archiving}
        title="Archive this licence"
        message="Archived licences leave the register but are never deleted; tick 'Include archived' to see them again."
        confirmLabel="Archive"
        danger
        isPending={archive.isPending}
        onCancel={() => setArchiving(false)}
        onConfirm={() => archive.mutate(false)}
      />
    </Drawer>
  )
}

type LicenceFormState = {
  holder_type: HolderType
  holder_id: string
  licence_type: string
  licence_number: string
  issued_by: string
  issue_date: string
  expiry_date: string
  notes: string
}

function LicenceForm({ licence, onDone, onCancel }: { licence?: Licence; onDone: (l: Licence) => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<LicenceFormState>({
    holder_type: licence?.holder_type ?? 'BRANCH',
    holder_id: licence?.holder_type === 'ORGANISATION' ? '' : (licence?.holder_id ?? ''),
    licence_type: licence?.licence_type ?? 'PPB_PREMISES',
    licence_number: licence?.licence_number ?? '',
    issued_by: licence?.issued_by ?? 'Pharmacy and Poisons Board',
    issue_date: licence?.issue_date?.slice(0, 10) ?? '',
    expiry_date: licence?.expiry_date?.slice(0, 10) ?? '',
    notes: licence?.notes ?? '',
  })
  const [file, setFile] = useState<File | null>(null)
  const [fileError, setFileError] = useState<string | null>(null)
  const set = (patch: Partial<LicenceFormState>) => setForm({ ...form, ...patch })

  const holders = useQuery({
    queryKey: ['licences', 'holders', form.holder_type],
    queryFn: () => apiGet<{ id: string; name: string }[]>('/api/licences/holders', { holder_type: form.holder_type }),
    enabled: form.holder_type !== 'ORGANISATION',
    staleTime: 60_000,
  })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const fd = toFormData({
        holder_type: form.holder_type,
        holder_id: form.holder_type === 'ORGANISATION' ? null : form.holder_id,
        licence_type: form.licence_type,
        licence_number: form.licence_number,
        issued_by: form.issued_by,
        issue_date: form.issue_date,
        expiry_date: form.expiry_date,
        notes: form.notes,
        document: file,
      })
      // POST carries multipart (PHP does not parse multipart PATCH bodies).
      return apiPost<Licence>(licence ? `/api/licences/${licence.id}` : '/api/licences', fd)
    },
    onSuccess: (l) => {
      toast.success(licence ? 'Licence updated' : 'Licence added')
      queryClient.invalidateQueries({ queryKey: ['licences'] })
      onDone(l)
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const valid = form.licence_number.trim() && form.expiry_date && (form.holder_type === 'ORGANISATION' || form.holder_id) && !fileError

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-2 gap-3">
        <Field label="Holder type" required>
          <Select value={form.holder_type} onChange={(e) => set({ holder_type: e.target.value as HolderType, holder_id: '' })}>
            {HOLDER_TYPES.map((h) => (<option key={h} value={h}>{titleCase(h)}</option>))}
          </Select>
        </Field>
        <Field label="Holder" required={form.holder_type !== 'ORGANISATION'} error={err?.errors.holder_id?.[0]}>
          {form.holder_type === 'ORGANISATION' ? (
            <Input disabled value="The organisation itself" />
          ) : (
            <Select value={form.holder_id} onChange={(e) => set({ holder_id: e.target.value })}>
              <option value="">{holders.isLoading ? 'Loading…' : holders.data?.length === 0 ? `No ${titleCase(form.holder_type).toLowerCase()}s found` : 'Choose…'}</option>
              {holders.data?.map((h) => (<option key={h.id} value={h.id}>{h.name}</option>))}
            </Select>
          )}
        </Field>
        <Field label="Licence type" required error={err?.errors.licence_type?.[0]}>
          <Select value={form.licence_type} onChange={(e) => set({ licence_type: e.target.value })}>
            {LICENCE_TYPES.map((t) => (<option key={t.value} value={t.value}>{t.label}</option>))}
          </Select>
        </Field>
        <Field label="Licence number" required error={err?.errors.licence_number?.[0]}><Input value={form.licence_number} onChange={(e) => set({ licence_number: e.target.value })} /></Field>
        <Field label="Issued by" error={err?.errors.issued_by?.[0]}><Input value={form.issued_by} onChange={(e) => set({ issued_by: e.target.value })} /></Field>
        <div />
        <Field label="Issue date" error={err?.errors.issue_date?.[0]}><Input type="date" value={form.issue_date} onChange={(e) => set({ issue_date: e.target.value })} /></Field>
        <Field label="Expiry date" required hint="Shown as expiring 60 days before this date." error={err?.errors.expiry_date?.[0]}><Input type="date" value={form.expiry_date} onChange={(e) => set({ expiry_date: e.target.value })} /></Field>
      </div>
      <Field label="Notes"><Textarea rows={2} value={form.notes} onChange={(e) => set({ notes: e.target.value })} /></Field>
      <Field label={licence?.has_document ? 'Replace document' : 'Document'} hint="PDF, JPG or PNG, up to 10 MB." error={fileError ?? err?.errors.document?.[0]}>
        <input
          type="file"
          accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
          className="block text-[12px]"
          onChange={(e) => {
            const f = e.target.files?.[0] ?? null
            setFile(f)
            setFileError(f && f.size > MAX_UPLOAD_BYTES ? 'The file is larger than 10 MB.' : null)
          }}
        />
      </Field>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={!valid || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : licence ? 'Save changes' : 'Add licence'}</Button>
      </div>
    </div>
  )
}
