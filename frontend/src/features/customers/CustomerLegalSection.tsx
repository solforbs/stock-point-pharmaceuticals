import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, FileCheck2, Paperclip, Scale, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { InlineError } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, FormSection, Input, Select } from '../../components/ui/primitives'
import { api, apiGet, ensureCsrfCookie, getApiError } from '../../lib/api'
import { downloadBlob } from '../../lib/csv'
import { formatDate, titleCase } from '../../lib/format'
import { toast } from '../../lib/toast'
import type { Customer } from '../../lib/types'

export const LEGAL_STATUSES = ['SOLE_PROPRIETOR', 'PARTNERSHIP', 'LIMITED_COMPANY', 'GOVERNMENT', 'NGO_FAITH_BASED', 'OTHER'] as const

/** The documents a customer is usually asked for; the licence register knows a few more. */
export const DOCUMENT_TYPES: { value: string; label: string; expires: boolean }[] = [
  { value: 'CERT_OF_INCORPORATION', label: 'Certificate of Incorporation', expires: false },
  { value: 'PPB_PREMISES', label: "Pharmacy & Poisons Board licence (premises)", expires: true },
  { value: 'PPB_PHARMACIST', label: "PPB licence – superintendent pharmacist", expires: true },
  { value: 'COUNTY_PERMIT', label: 'County permit (single business permit)', expires: true },
  { value: 'KRA_TCC', label: 'KRA tax compliance certificate', expires: true },
  { value: 'OTHER', label: 'Other document', expires: true },
]
const labelOf = (type: string) => DOCUMENT_TYPES.find((t) => t.value === type)?.label ?? titleCase(type)
const expires = (type: string) => DOCUMENT_TYPES.find((t) => t.value === type)?.expires ?? true

export type LegalForm = { legal_status: string; registration_number: string; kra_pin: string; superintendent_name: string; superintendent_reg_no: string }

export const legalFormOf = (c?: Customer | null): LegalForm => ({
  legal_status: c?.legal_status ?? '',
  registration_number: c?.registration_number ?? '',
  kra_pin: c?.kra_pin ?? '',
  superintendent_name: c?.superintendent_name ?? '',
  superintendent_reg_no: c?.superintendent_reg_no ?? '',
})

/** The legal fields as the API takes them (blank means none). */
export const legalPayload = (f: LegalForm) => Object.fromEntries(Object.entries(f).map(([k, v]) => [k, v.trim() === '' ? null : v.trim()]))

/** Legal status, registration and superintendent — on the create and edit forms. */
export function LegalStatusFields<T extends LegalForm>({ form, set, errors }: { form: T; set: (patch: Partial<LegalForm>) => void; errors?: Record<string, string[]> }) {
  return (
    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <Field label="Legal status">
        <Select value={form.legal_status} onChange={(e) => set({ legal_status: e.target.value })}>
          <option value="">Not recorded</option>
          {LEGAL_STATUSES.map((s) => (<option key={s} value={s}>{s === 'NGO_FAITH_BASED' ? 'NGO / faith-based' : titleCase(s)}</option>))}
        </Select>
      </Field>
      <Field label="Registration / incorporation number" error={errors?.registration_number?.[0]}>
        <Input value={form.registration_number} onChange={(e) => set({ registration_number: e.target.value })} placeholder="e.g. PVT-ABC1234" />
      </Field>
      <Field label="KRA PIN" error={errors?.kra_pin?.[0]}>
        <Input value={form.kra_pin} onChange={(e) => set({ kra_pin: e.target.value.toUpperCase() })} placeholder="e.g. P051234567M" />
      </Field>
      <div />
      <Field label="Superintendent pharmacist" error={errors?.superintendent_name?.[0]}>
        <Input value={form.superintendent_name} onChange={(e) => set({ superintendent_name: e.target.value })} placeholder="Full name" />
      </Field>
      <Field label="Superintendent PPB registration no." error={errors?.superintendent_reg_no?.[0]}>
        <Input value={form.superintendent_reg_no} onChange={(e) => set({ superintendent_reg_no: e.target.value })} />
      </Field>
    </div>
  )
}

/** One document as entered, before or after it is saved. */
export type DocumentDraft = { key: string; licence_type: string; licence_number: string; issued_by: string; issue_date: string; expiry_date: string; file: File | null }

export const blankDocument = (): DocumentDraft => ({ key: `${Date.now()}-${Math.random()}`, licence_type: 'CERT_OF_INCORPORATION', licence_number: '', issued_by: '', issue_date: '', expiry_date: '', file: null })

export const documentValid = (d: DocumentDraft) => d.licence_number.trim() !== '' && (!expires(d.licence_type) || d.expiry_date !== '') && (!d.issue_date || !d.expiry_date || d.expiry_date >= d.issue_date)

/** Uploads one document for a customer (multipart, with its scan). */
export async function uploadCustomerDocument(customerId: string, d: DocumentDraft): Promise<void> {
  const fd = new FormData()
  fd.append('licence_type', d.licence_type)
  fd.append('licence_number', d.licence_number.trim())
  if (d.issued_by.trim()) fd.append('issued_by', d.issued_by.trim())
  if (d.issue_date) fd.append('issue_date', d.issue_date)
  if (d.expiry_date && expires(d.licence_type)) fd.append('expiry_date', d.expiry_date)
  if (d.file) fd.append('document', d.file)
  await ensureCsrfCookie()
  await api.post(`/api/customers/${customerId}/documents`, fd)
}

/** The form row for one document: type, number, issuer, dates and the scan. */
export function DocumentDraftFields({ draft, onChange, onRemove }: { draft: DocumentDraft; onChange: (d: DocumentDraft) => void; onRemove?: () => void }) {
  const set = (patch: Partial<DocumentDraft>) => onChange({ ...draft, ...patch })
  return (
    <div className="rounded-xl border border-slate-200 p-3 space-y-3 bg-white">
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Document" required>
          <Select value={draft.licence_type} onChange={(e) => set({ licence_type: e.target.value, expiry_date: expires(e.target.value) ? draft.expiry_date : '' })}>
            {DOCUMENT_TYPES.map((t) => (<option key={t.value} value={t.value}>{t.label}</option>))}
          </Select>
        </Field>
        <Field label="Number" required><Input value={draft.licence_number} onChange={(e) => set({ licence_number: e.target.value })} placeholder="Certificate / licence / permit no." /></Field>
        <Field label="Issued by"><Input value={draft.issued_by} onChange={(e) => set({ issued_by: e.target.value })} placeholder="e.g. PPB, Turkana County, Registrar of Companies" /></Field>
        <Field label="Issue date"><Input type="date" value={draft.issue_date} onChange={(e) => set({ issue_date: e.target.value })} /></Field>
        {expires(draft.licence_type)
          ? <Field label="Expiry date" required hint="Annual documents: the date this year's copy runs out"><Input type="date" value={draft.expiry_date} onChange={(e) => set({ expiry_date: e.target.value })} /></Field>
          : <Field label="Expiry date"><div className="text-xs text-slate-500 pt-2">Does not expire</div></Field>}
        <Field label="Scanned copy" hint="PDF, JPG or PNG, up to 10 MB">
          <label className="flex items-center gap-2 text-sm cursor-pointer text-blue-600 hover:text-blue-700 pt-1.5">
            <Paperclip size={14} />
            <span className="truncate">{draft.file ? draft.file.name : 'Attach file…'}</span>
            <input type="file" accept=".pdf,.jpg,.jpeg,.png" className="hidden" onChange={(e) => set({ file: e.target.files?.[0] ?? null })} />
          </label>
        </Field>
      </div>
      {onRemove && <div className="flex justify-end"><Button size="sm" variant="ghost" onClick={onRemove}><Trash2 size={13} /> Remove</Button></div>}
    </div>
  )
}

type CustomerDocument = {
  id: string
  licence_type: string
  licence_number: string
  issued_by: string | null
  issue_date: string | null
  expiry_date: string | null
  status: 'VALID' | 'EXPIRING' | 'EXPIRED'
  days_to_expiry: number | null
  has_document: boolean
  document_name?: string | null
  is_active: boolean
}

/**
 * The customer's legal status and documents on its detail view: read the
 * registration details, see each document's status, open the scan, add the
 * new year's copy, archive an old one.
 */
export function CustomerLegalPanel({ customer, canManage }: { customer: Customer; canManage: boolean }) {
  const queryClient = useQueryClient()
  const docs = useQuery({ queryKey: ['customers', customer.id, 'documents'], queryFn: () => apiGet<CustomerDocument[]>(`/api/customers/${customer.id}/documents`), enabled: canManage })
  const [adding, setAdding] = useState<DocumentDraft | null>(null)

  const refresh = () => queryClient.invalidateQueries({ queryKey: ['customers', customer.id, 'documents'] })
  const add = useMutation({
    meta: { silent: true },
    mutationFn: (d: DocumentDraft) => uploadCustomerDocument(customer.id, d),
    onSuccess: () => { toast.success('Document added'); setAdding(null); refresh() },
  })
  const archive = useMutation({
    meta: { silent: true },
    mutationFn: async (id: string) => { await ensureCsrfCookie(); await api.post(`/api/customers/${customer.id}/documents/${id}`, { is_active: false }) },
    onSuccess: () => { toast.success('Document archived'); refresh() },
    onError: (e) => toast.error(getApiError(e).message),
  })
  const open = async (d: CustomerDocument) => {
    try {
      const { data } = await api.get<Blob>(`/api/customers/${customer.id}/documents/${d.id}/file`, { responseType: 'blob' })
      downloadBlob(data, d.document_name ?? `${d.licence_type}-${d.licence_number}`, data.type)
    } catch (e) {
      toast.error(getApiError(e).message || 'The file could not be opened')
    }
  }

  return (
    <FormSection title="Legal Status & Documents" description="Registration details and the certified documents on file" icon={Scale}>
      <div className="space-y-3">
        <DescriptionList
          items={[
            { label: 'Legal status', value: customer.legal_status ? (customer.legal_status === 'NGO_FAITH_BASED' ? 'NGO / faith-based' : titleCase(customer.legal_status)) : '—' },
            { label: 'Registration no.', value: customer.registration_number ?? '—' },
            { label: 'KRA PIN', value: customer.kra_pin ?? '—' },
            { label: 'Superintendent pharmacist', value: customer.superintendent_name ? `${customer.superintendent_name}${customer.superintendent_reg_no ? ` (PPB ${customer.superintendent_reg_no})` : ''}` : '—' },
          ]}
        />
        {canManage && (
          <>
            {docs.isError && <InlineError error={docs.error} />}
            <div className="rounded-xl border border-slate-200 overflow-hidden">
              <table className="ui-table">
                <thead><tr><th>Document</th><th>Number</th><th>Expiry</th><th>Status</th><th /></tr></thead>
                <tbody>
                  {(docs.data ?? []).map((d) => (
                    <tr key={d.id}>
                      <td className="font-medium">{labelOf(d.licence_type)}{d.issued_by && <div className="text-xs text-slate-500">{d.issued_by}</div>}</td>
                      <td className="tabular font-mono text-xs">{d.licence_number}</td>
                      <td className="text-xs">{d.expiry_date ? formatDate(d.expiry_date) : 'Does not expire'}</td>
                      <td><StatusBadge status={d.status} tone={d.status === 'VALID' ? 'green' : d.status === 'EXPIRING' ? 'amber' : 'red'} /></td>
                      <td className="text-right whitespace-nowrap">
                        {d.has_document && <Button size="sm" variant="ghost" onClick={() => void open(d)} aria-label="Open scan"><Download size={13} /></Button>}
                        <Button size="sm" variant="ghost" disabled={archive.isPending} onClick={() => archive.mutate(d.id)}>Archive</Button>
                      </td>
                    </tr>
                  ))}
                  {docs.data && docs.data.length === 0 && (
                    <tr><td colSpan={5} className="text-center text-xs text-slate-500 py-4">No documents on file yet.</td></tr>
                  )}
                </tbody>
              </table>
            </div>
            {adding ? (
              <div className="space-y-2">
                <DocumentDraftFields draft={adding} onChange={setAdding} />
                {add.isError && <InlineError error={add.error} />}
                <div className="flex justify-end gap-2">
                  <Button onClick={() => setAdding(null)}>Cancel</Button>
                  <Button variant="primary" disabled={!documentValid(adding) || add.isPending} onClick={() => add.mutate(adding)}>{add.isPending ? 'Saving…' : 'Save document'}</Button>
                </div>
              </div>
            ) : (
              <Button size="sm" onClick={() => { add.reset(); setAdding(blankDocument()) }}><FileCheck2 size={13} /> Add document</Button>
            )}
          </>
        )}
      </div>
    </FormSection>
  )
}
