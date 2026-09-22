import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ImageUp, Trash2 } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { Button, Card, Field, Input, Textarea } from '../../components/ui/primitives'
import { api, apiDelete, apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { usePermission } from '../../lib/permissions'
import { toast, toastApiError } from '../../lib/toast'
import type { CompanyProfile, CompanyProfileImageKind } from '../../lib/types'

const MAX_IMAGE_BYTES = 2 * 1024 * 1024

const QUERY_KEY = ['admin', 'company-profile'] as const

type Form = {
  tagline: string
  about: string
  mission: string
  vision: string
  core_values: string
  services: string
  website: string
  contact_email: string
  contact_phone: string
  physical_address: string
  postal_address: string
  signatory_name: string
  signatory_title: string
}

const EMPTY: Form = {
  tagline: '', about: '', mission: '', vision: '', core_values: '', services: '', website: '',
  contact_email: '', contact_phone: '', physical_address: '', postal_address: '', signatory_name: '', signatory_title: '',
}

function toForm(p: CompanyProfile): Form {
  return {
    tagline: p.tagline ?? '',
    about: p.about ?? '',
    mission: p.mission ?? '',
    vision: p.vision ?? '',
    core_values: p.core_values.join('\n'),
    services: p.services.join('\n'),
    website: p.website ?? '',
    contact_email: p.contact_email ?? '',
    contact_phone: p.contact_phone ?? '',
    physical_address: p.physical_address ?? '',
    postal_address: p.postal_address ?? '',
    signatory_name: p.signatory_name ?? '',
    signatory_title: p.signatory_title ?? '',
  }
}

/** One entry per line; blank lines are dropped. */
function lines(text: string): string[] {
  return text.split('\n').map((l) => l.trim()).filter(Boolean)
}

/**
 * Client item 14 — the company profile: the letterhead on every PDF, the
 * mission, vision and values, and the logo, stamp and signature printed on
 * issued documents. The company profile PDF prints "To be completed" for
 * anything still empty, so it can be shared while it is being filled in.
 */
export default function CompanyProfilePage() {
  const canManage = usePermission('admin.settings')
  const queryClient = useQueryClient()
  const profile = useQuery({ queryKey: QUERY_KEY, queryFn: () => apiGet<CompanyProfile>('/api/admin/company-profile'), enabled: canManage })
  const [form, setForm] = useState<Form>(EMPTY)

  useEffect(() => {
    if (profile.data) setForm(toForm(profile.data))
  }, [profile.data])

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const blankToNull = (v: string) => (v.trim() === '' ? null : v.trim())
      return apiPatch<CompanyProfile>('/api/admin/company-profile', {
        tagline: blankToNull(form.tagline),
        about: blankToNull(form.about),
        mission: blankToNull(form.mission),
        vision: blankToNull(form.vision),
        core_values: lines(form.core_values),
        services: lines(form.services),
        website: blankToNull(form.website),
        contact_email: blankToNull(form.contact_email),
        contact_phone: blankToNull(form.contact_phone),
        physical_address: blankToNull(form.physical_address),
        postal_address: blankToNull(form.postal_address),
        signatory_name: blankToNull(form.signatory_name),
        signatory_title: blankToNull(form.signatory_title),
      })
    },
    onSuccess: (data) => {
      toast.success('Company profile saved')
      queryClient.setQueryData(QUERY_KEY, data)
    },
  })

  const err = save.isError ? getApiError(save.error) : null
  const fieldError = (key: string) => err?.errors[key]?.[0] ?? err?.errors[`${key}.0`]?.[0]
  const set = (patch: Partial<Form>) => setForm({ ...form, ...patch })

  if (!canManage) {
    return (
      <Page>
        <PageHeader parent="Admin" title="Company Profile" />
        <div className="ui-card"><NoAccess permission="admin.settings" /></div>
      </Page>
    )
  }

  return (
    <Page>
      <PageHeader
        parent="Admin"
        title="Company Profile"
        subtitle="Who you are, as printed on every document. The company profile PDF shows 'To be completed' for anything left empty."
        actions={<PdfDownloadButton size="md" url="/api/admin/company-profile/pdf" filename={`Company-Profile-${profile.data?.name ?? 'company'}`} label="Download company profile" />}
      />

      {profile.isLoading && <div className="ui-card"><LoadingSkeleton /></div>}
      {profile.isError && <InlineError error={profile.error} />}

      {profile.data && (
        <div className="space-y-4">
          <Card title="Logo, stamp and signature" padded>
            <p className="text-xs text-slate-500 mb-4">
              The logo heads every PDF. The stamp and signature are added to invoices, receipts, quotations, purchase orders,
              delivery notes and statements. PNG or JPG, up to 2 MB. A PNG with a clear background prints best.
            </p>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <ImageSlot kind="logo" label="Logo" profile={profile.data} />
              <ImageSlot kind="stamp" label="Official stamp" profile={profile.data} />
              <ImageSlot kind="signature" label="Authorised signature" profile={profile.data} />
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
              <Field label="Signatory name" hint="Printed under the signature." error={fieldError('signatory_name')}>
                <Input value={form.signatory_name} onChange={(e) => set({ signatory_name: e.target.value })} placeholder="e.g. Jane Ekai" />
              </Field>
              <Field label="Signatory title" error={fieldError('signatory_title')}>
                <Input value={form.signatory_title} onChange={(e) => set({ signatory_title: e.target.value })} placeholder="e.g. Managing Director" />
              </Field>
            </div>
          </Card>

          <Card title="Letterhead and contacts" padded>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <Field label="Tagline" hint="A short line under the company name." className="sm:col-span-2" error={fieldError('tagline')}>
                <Input value={form.tagline} maxLength={200} onChange={(e) => set({ tagline: e.target.value })} placeholder="e.g. Quality medicines for Turkana" />
              </Field>
              <Field label="Phone" error={fieldError('contact_phone')}>
                <Input value={form.contact_phone} maxLength={30} onChange={(e) => set({ contact_phone: e.target.value })} />
              </Field>
              <Field label="Email" error={fieldError('contact_email')}>
                <Input type="email" value={form.contact_email} onChange={(e) => set({ contact_email: e.target.value })} />
              </Field>
              <Field label="Website" error={fieldError('website')}>
                <Input value={form.website} onChange={(e) => set({ website: e.target.value })} placeholder="www.example.co.ke" />
              </Field>
              <Field label="Postal address" error={fieldError('postal_address')}>
                <Input value={form.postal_address} onChange={(e) => set({ postal_address: e.target.value })} placeholder="P.O. Box 00-30500 Lodwar" />
              </Field>
              <Field label="Physical address" hint="Used when a branch has no address of its own." className="sm:col-span-2" error={fieldError('physical_address')}>
                <Input value={form.physical_address} onChange={(e) => set({ physical_address: e.target.value })} />
              </Field>
            </div>
          </Card>

          <Card title="About the company" padded>
            <div className="space-y-3">
              <Field label="About us" hint="Who you are, when you started and what you do." error={fieldError('about')}>
                <Textarea rows={5} value={form.about} onChange={(e) => set({ about: e.target.value })} />
              </Field>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Field label="Mission" error={fieldError('mission')}>
                  <Textarea rows={3} value={form.mission} onChange={(e) => set({ mission: e.target.value })} />
                </Field>
                <Field label="Vision" error={fieldError('vision')}>
                  <Textarea rows={3} value={form.vision} onChange={(e) => set({ vision: e.target.value })} />
                </Field>
                <Field label="Core values" hint="One per line." error={fieldError('core_values')}>
                  <Textarea rows={5} value={form.core_values} onChange={(e) => set({ core_values: e.target.value })} placeholder={'Integrity\nPatient safety'} />
                </Field>
                <Field label="Services offered" hint="One per line." error={fieldError('services')}>
                  <Textarea rows={5} value={form.services} onChange={(e) => set({ services: e.target.value })} placeholder={'Wholesale supply\nRetail pharmacy'} />
                </Field>
              </div>
              <p className="text-xs text-slate-500">Licences and branches in the company profile come from Licences &amp; Certificates and Branches &amp; Stores.</p>
            </div>
          </Card>

          {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}

          <div className="flex justify-end">
            <Button variant="primary" disabled={save.isPending} onClick={() => save.mutate()}>
              {save.isPending ? 'Saving…' : 'Save company profile'}
            </Button>
          </div>
        </div>
      )}
    </Page>
  )
}

/** One uploaded image: a preview fetched through the API (the files are private), with replace and remove. */
function ImageSlot({ kind, label, profile }: { kind: CompanyProfileImageKind; label: string; profile: CompanyProfile }) {
  const queryClient = useQueryClient()
  const input = useRef<HTMLInputElement>(null)
  const hasImage = profile.images[kind]
  const [previewUrl, setPreviewUrl] = useState<string | null>(null)

  const preview = useQuery({
    queryKey: [...QUERY_KEY, 'image', kind, profile.images_version],
    queryFn: async () => (await api.get<Blob>(`/api/admin/company-profile/images/${kind}`, { responseType: 'blob' })).data,
    enabled: hasImage,
    staleTime: Infinity,
  })

  useEffect(() => {
    if (!hasImage || !preview.data) {
      setPreviewUrl(null)
      return
    }
    const url = URL.createObjectURL(preview.data)
    setPreviewUrl(url)
    return () => URL.revokeObjectURL(url)
  }, [hasImage, preview.data])

  const upload = useMutation({
    meta: { silent: true },
    mutationFn: (file: File) => {
      const fd = new FormData()
      fd.append('image', file)
      return apiPost<CompanyProfile>(`/api/admin/company-profile/images/${kind}`, fd)
    },
    onSuccess: (data) => {
      toast.success(`${label} uploaded`)
      queryClient.setQueryData(QUERY_KEY, data)
    },
    onError: (e) => toastApiError(e, `Could not upload the ${label.toLowerCase()}`),
  })

  const remove = useMutation({
    meta: { silent: true },
    mutationFn: () => apiDelete<CompanyProfile>(`/api/admin/company-profile/images/${kind}`),
    onSuccess: (data) => {
      toast.success(`${label} removed`)
      queryClient.setQueryData(QUERY_KEY, data)
    },
    onError: (e) => toastApiError(e, `Could not remove the ${label.toLowerCase()}`),
  })

  const choose = (file: File | undefined) => {
    if (!file) return
    if (!['image/png', 'image/jpeg'].includes(file.type)) {
      toast.error('Choose a PNG or JPG image.')
      return
    }
    if (file.size > MAX_IMAGE_BYTES) {
      toast.error('The image must be 2 MB or smaller.')
      return
    }
    upload.mutate(file)
  }

  const busy = upload.isPending || remove.isPending

  return (
    <div className="rounded-xl border border-slate-200 p-3 space-y-2">
      <div className="text-xs font-semibold text-slate-700">{label}</div>
      <div className="h-28 rounded-lg bg-slate-50 border border-dashed border-slate-200 flex items-center justify-center overflow-hidden">
        {previewUrl ? (
          <img src={previewUrl} alt={label} className="max-h-full max-w-full object-contain" />
        ) : (
          <span className="text-xs text-slate-400">{hasImage ? 'Loading…' : 'Not uploaded'}</span>
        )}
      </div>
      <input
        ref={input}
        type="file"
        accept=".png,.jpg,.jpeg,image/png,image/jpeg"
        className="hidden"
        onChange={(e) => {
          choose(e.target.files?.[0])
          e.target.value = ''
        }}
      />
      <div className="flex gap-2">
        <Button size="sm" disabled={busy} onClick={() => input.current?.click()}>
          <ImageUp size={12} /> {upload.isPending ? 'Uploading…' : hasImage ? 'Replace' : 'Upload'}
        </Button>
        {hasImage && (
          <Button size="sm" variant="ghost" disabled={busy} onClick={() => remove.mutate()}>
            <Trash2 size={12} /> Remove
          </Button>
        )}
      </div>
    </div>
  )
}
