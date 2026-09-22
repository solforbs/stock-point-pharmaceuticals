import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { FileText, ShieldCheck } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { StatusBadge, type StatusTone } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, DrawerFooter, Field, FormSection, Input, Select, Textarea } from '../../components/ui/primitives'
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

export function useLicence(id: string | null | undefined) {
  return useQuery({
    queryKey: ['licences', id],
    queryFn: () => apiGet<Licence>(`/api/licences/${id}`),
    enabled: !!id,
  })
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
  const single = useLicence(selectedId)
  const selected = single.data ?? list.data?.data.find((l) => l.id === selectedId) ?? null

  if (!canView && !canManage) {
    return (
      <Page>
        <PageHeader parent="Quality & Compliance" title="Licences & Certificates" />
        <div className="ui-card"><NoAccess permission="licence.view" /></div>
      </Page>
    )
  }

  const columns: Column<Licence>[] = [
    { key: 'type', header: 'Licence', render: (l) => <><div className="font-semibold text-slate-900">{typeLabel(l.licence_type)}</div><div className="text-xs text-slate-500 font-mono tabular">{l.licence_number ?? 'No number recorded'}</div></>, sortValue: (l) => l.licence_type },
    { key: 'holder', header: 'Holder', render: (l) => <>{l.holder_name ?? '—'}<div className="text-xs text-slate-500">{titleCase(l.holder_type)}{l.source === 'supplier' ? ' · from supplier master' : ''}</div></>, sortValue: (l) => l.holder_name ?? '' },
    { key: 'issuer', header: 'Issued by', render: (l) => l.issued_by ?? '—' },
    { key: 'expiry', header: 'Expiry', render: (l) => <LicenceExpiry licence={l} />, sortValue: (l) => l.expiry_date ?? '' },
    { key: 'doc', header: 'Document', render: (l) => (l.has_document ? <button type="button" className="text-blue-600 font-medium hover:underline text-xs" onClick={(e) => { e.stopPropagation(); void downloadFile(`/api/licences/${l.id}/document`, `${l.licence_number ?? 'licence'}.pdf`) }}>Download</button> : <span className="text-slate-400">—</span>) },
    { key: 'state', header: '', render: (l) => (!l.is_active ? <StatusBadge status="ARCHIVED" /> : l.read_only ? <StatusBadge status="INFO" tone="slate" label="Read only" /> : null) },
  ]

  const summary = list.data?.summary

  return (
    <Page>
      <PageHeader
        parent="Quality & Compliance"
        title="Licences & Certificates"
        subtitle="Premises, practising, permit and tax certificates in one register. Supplier licences come from the supplier master; a PO to a supplier with an expired licence is refused."
        actions={
          canManage ? (
            <div id="tour-licences-new">
              <Button variant="primary" onClick={() => setCreating(true)}>Add licence</Button>
            </div>
          ) : null
        }
      />
      <div id="tour-licences-kpis" className="grid grid-cols-3 gap-3 mb-4 max-w-2xl">
        {([['EXPIRED', 'Expired', summary?.expired], ['EXPIRING', 'Expiring ≤ 60 days', summary?.expiring], ['VALID', 'Valid', summary?.valid]] as const).map(([key, label, count]) => (
          <button key={key} type="button" onClick={() => setStatus(status === key ? '' : key)} className={`ui-card p-3.5 text-left transition-all ${status === key ? 'ring-2 ring-blue-600 border-blue-600' : 'hover:border-slate-300'}`}>
            <div className="text-xs text-slate-500 font-medium">{label}</div>
            <div className="text-2xl font-bold tabular mt-1" style={{ color: `var(--status-${STATUS_TONE[key]})` }}>{count ?? '…'}</div>
          </button>
        ))}
      </div>
      <div id="tour-licences-filters">
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
          <label className="flex items-center gap-2 text-xs text-slate-600 pb-2 cursor-pointer"><input type="checkbox" checked={archived} onChange={(e) => setArchived(e.target.checked)} /> Include archived</label>
        </FilterBar>
      </div>
      <div id="tour-licences-table" className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(l) => l.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(l) => setParams({ licence: l.id })} selectedKey={selectedId} emptyTitle="No licences recorded" emptyHint={canManage ? 'Add the premises licence, practising licences and permits so expiry is never a surprise.' : undefined} />
      </div>
      <LicenceDrawer id={selectedId} licence={selected} onClose={() => setParams({})} />
      <Drawer open={creating} onClose={() => setCreating(false)} title="Add licence or certificate" width={640}>
        {creating && <LicenceForm onDone={(l) => { setCreating(false); setParams({ licence: l.id }) }} onCancel={() => setCreating(false)} />}
      </Drawer>
    </Page>
  )
}

function LicenceDrawer({ id, licence, onClose }: { id: string | null; licence: Licence | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const canManage = usePermission('licence.manage')
  const [editing, setEditing] = useState(false)
  const [archiving, setArchiving] = useState(false)
  const single = useLicence(id)
  const l = licence ?? single.data

  const archive = useMutation({
    mutationFn: (active: boolean) => apiPatch<Licence>(`/api/licences/${l?.id}`, { is_active: active }),
    onSuccess: (updated) => {
      toast.success(updated.is_active ? 'Licence restored' : 'Licence archived')
      setArchiving(false)
      queryClient.invalidateQueries({ queryKey: ['licences'] })
    },
  })

  return (
    <Drawer open={!!id} onClose={() => { setEditing(false); onClose() }} title={l ? typeLabel(l.licence_type) : 'Licence Details'} subtitle={l?.holder_name ?? undefined} width={editing ? 640 : 560}>
      {single.isLoading && !l && <LoadingSkeleton />}
      {single.isError && !l && <InlineError error={single.error} />}
      {l && editing && <LicenceForm licence={l} onDone={() => setEditing(false)} onCancel={() => setEditing(false)} />}
      {l && !editing && (
        <div className="space-y-4">
          <div className="flex items-center justify-between gap-2">
            <LicenceExpiry licence={l} />
            {canManage && !l.read_only && (
              <div className="flex gap-2">
                <Button onClick={() => setEditing(true)}>Edit / renew</Button>
                {l.is_active ? <Button variant="danger" onClick={() => setArchiving(true)}>Archive</Button> : <Button disabled={archive.isPending} onClick={() => archive.mutate(true)}>Restore</Button>}
              </div>
            )}
          </div>
          {l.read_only && (
            <div className="text-xs text-slate-600 rounded-lg px-3.5 py-2.5 bg-slate-50 border border-slate-200">
              This licence is maintained on the supplier master (<span className="tabular font-semibold font-mono">{l.holder_name}</span>) and reflects regulatory credentials recorded there.
            </div>
          )}
          <DescriptionList
            items={[
              { label: 'Holder', value: `${l.holder_name ?? '—'} (${titleCase(l.holder_type)})` },
              { label: 'Number', value: <span className="tabular font-semibold font-mono">{l.licence_number ?? '—'}</span> },
              { label: 'Issuer', value: l.issued_by ?? '—' },
              { label: 'Issue date', value: formatDate(l.issue_date) },
              { label: 'Expiry date', value: formatDate(l.expiry_date) },
              { label: 'Document', value: l.has_document ? <button type="button" className="text-blue-600 font-medium hover:underline" onClick={() => void downloadFile(`/api/licences/${l.id}/document`, `${l.licence_number ?? 'licence'}.pdf`)}>Download attached PDF</button> : <span className="text-slate-400">None attached</span> },
              { label: 'Notes', value: l.notes ?? '—' },
              { label: 'Last updated', value: formatDateTime(l.updated_at) },
            ]}
          />
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
      <FormSection
        title="Licence Holder & Classification"
        description="Select the legal entity or practitioner holding the regulatory permit"
        icon={ShieldCheck}
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
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
        </div>
      </FormSection>

      <FormSection
        title="Validity & Issuing Authority"
        description="Statutory dates and regulatory board details"
      >
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Issued by" error={err?.errors.issued_by?.[0]} className="col-span-1 sm:col-span-2"><Input value={form.issued_by} onChange={(e) => set({ issued_by: e.target.value })} /></Field>
          <Field label="Issue date" error={err?.errors.issue_date?.[0]}><Input type="date" value={form.issue_date} onChange={(e) => set({ issue_date: e.target.value })} /></Field>
          <Field label="Expiry date" required hint="Shown as expiring 60 days before this date." error={err?.errors.expiry_date?.[0]}><Input type="date" value={form.expiry_date} onChange={(e) => set({ expiry_date: e.target.value })} /></Field>
        </div>
      </FormSection>

      <FormSection
        title="Documentation & Remarks"
        description="Supporting PDF or scanned certificate upload"
        icon={FileText}
      >
        <Field label="Notes"><Textarea rows={2} value={form.notes} onChange={(e) => set({ notes: e.target.value })} /></Field>
        <Field label={licence?.has_document ? 'Replace document' : 'Document'} hint="PDF, JPG or PNG, up to 10 MB." error={fileError ?? err?.errors.document?.[0]}>
          <input
            type="file"
            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
            className="block text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer"
            onChange={(e) => {
              const f = e.target.files?.[0] ?? null
              setFile(f)
              setFileError(f && f.size > MAX_UPLOAD_BYTES ? 'The file is larger than 10 MB.' : null)
            }}
          />
        </Field>
      </FormSection>

      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}

      <DrawerFooter
        onCancel={onCancel}
        onSubmit={() => save.mutate()}
        submitLabel={licence ? 'Save changes' : 'Add licence'}
        disabled={!valid || save.isPending}
        isPending={save.isPending}
      />
    </div>
  )
}
