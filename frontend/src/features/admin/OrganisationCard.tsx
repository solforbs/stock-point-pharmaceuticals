import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { InlineError } from '../../components/ui/States'
import { Button, Field, Input } from '../../components/ui/primitives'
import { apiGet, apiPatch, getApiError } from '../../lib/api'
import { toast } from '../../lib/toast'

type Organisation = {
  id: string
  name: string
  legal_name: string | null
  kra_pin: string | null
  vat_number: string | null
  vat_registered: boolean
  kra_pin_is_placeholder: boolean
}

/**
 * Part 13 — the business as it appears on every invoice. The KRA PIN is the
 * company's own for a limited company (it starts with P); a sole proprietor
 * uses their personal PIN (it starts with A).
 */
export function OrganisationCard() {
  const queryClient = useQueryClient()
  const org = useQuery({ queryKey: ['admin', 'organisation'], queryFn: () => apiGet<Organisation>('/api/admin/organisation') })
  const [form, setForm] = useState({ name: '', legal_name: '', kra_pin: '', vat_number: '', vat_registered: false })

  useEffect(() => {
    if (org.data) {
      setForm({
        name: org.data.name,
        legal_name: org.data.legal_name ?? '',
        kra_pin: org.data.kra_pin_is_placeholder ? '' : (org.data.kra_pin ?? ''),
        vat_number: org.data.vat_number ?? '',
        vat_registered: org.data.vat_registered,
      })
    }
  }, [org.data])

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPatch<Organisation>('/api/admin/organisation', {
        name: form.name,
        legal_name: form.legal_name || null,
        kra_pin: form.kra_pin.trim().toUpperCase(),
        vat_number: form.vat_number || null,
        vat_registered: form.vat_registered,
      }),
    onSuccess: () => {
      toast.success('Business details saved')
      queryClient.invalidateQueries({ queryKey: ['admin', 'organisation'] })
    },
  })

  const err = save.isError ? getApiError(save.error) : null
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })

  return (
    <div className="ui-card p-4 space-y-3">
      <div>
        <h2 className="text-[14px] font-bold text-slate-800">Business details</h2>
        <p className="text-[11.5px] text-slate-500">Printed on every invoice and delivery note.</p>
      </div>

      {org.data?.kra_pin_is_placeholder && (
        <div className="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-[12px] text-amber-800">
          <span className="font-bold">No real KRA PIN is set.</span> Invoices currently say "KRA PIN not set". For a limited company
          enter the <span className="font-bold">company's</span> PIN (starts with P); a sole proprietor enters their personal PIN (starts with A).
        </div>
      )}

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Trading name" required error={err?.errors.name?.[0]}>
          <Input value={form.name} onChange={(e) => set({ name: e.target.value })} />
        </Field>
        <Field label="Registered (legal) name" hint="As on the certificate of incorporation." error={err?.errors.legal_name?.[0]}>
          <Input value={form.legal_name} onChange={(e) => set({ legal_name: e.target.value })} />
        </Field>
        <Field label="KRA PIN" required hint="As on the KRA PIN certificate, e.g. P051234567M." error={err?.errors.kra_pin?.[0] ?? (err?.code === 'PLACEHOLDER_PIN' ? err.message : undefined)}>
          <Input value={form.kra_pin} maxLength={11} className="tabular uppercase" onChange={(e) => set({ kra_pin: e.target.value.toUpperCase().replace(/\s/g, '') })} placeholder="P051234567M" />
        </Field>
        <Field label="VAT number" error={err?.errors.vat_number?.[0]}>
          <Input value={form.vat_number} onChange={(e) => set({ vat_number: e.target.value })} />
        </Field>
      </div>
      <label className="flex items-center gap-2 text-[12px]">
        <input type="checkbox" checked={form.vat_registered} onChange={(e) => set({ vat_registered: e.target.checked })} />
        Registered for VAT
      </label>

      {err && !Object.keys(err.errors).length && err.code !== 'PLACEHOLDER_PIN' && <InlineError error={save.error} />}

      <div className="flex justify-end">
        <Button variant="primary" disabled={!form.name.trim() || !form.kra_pin.trim() || save.isPending} onClick={() => save.mutate()}>
          {save.isPending ? 'Saving…' : 'Save business details'}
        </Button>
      </div>
    </div>
  )
}
