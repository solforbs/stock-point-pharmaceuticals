import { useMutation } from '@tanstack/react-query'
import { AlertTriangle, CheckCircle2 } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { Button, Field, Input } from '../../components/ui/primitives'
import { InlineError } from '../../components/ui/States'
import { api, ensureCsrfCookie, getApiError } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import type { OpenedInvitation } from '../../lib/types'
import { PublicShell } from './PublicShell'

/**
 * The page behind a one-time emailed link: /register sets up a new
 * institution, /activate lets a platform-created institution's admin choose
 * a password. Opening the link spends it, so the token is used exactly once
 * (guarded against React's double effect) and then dropped from the URL.
 */
export default function InvitationPage() {
  const [params, setParams] = useSearchParams()
  const opened = useRef(false)
  const [arrivedWithToken] = useState(() => !!params.get('token'))
  const [invitation, setInvitation] = useState<OpenedInvitation | null>(null)

  const open = useMutation({
    meta: { silent: true },
    mutationFn: async (token: string) => {
      await ensureCsrfCookie()
      const { data } = await api.post<OpenedInvitation>('/api/public/invitations/open', { token })
      return data
    },
    onSuccess: (data) => setInvitation(data),
  })

  useEffect(() => {
    const token = params.get('token')
    if (!token || opened.current) return
    opened.current = true
    open.mutate(token)
    setParams({}, { replace: true })
  }, [params, setParams, open])

  if (!invitation) {
    if (open.isError || !arrivedWithToken) {
      const message = open.isError ? getApiError(open.error).message : 'This page needs the link from your email.'
      return (
        <PublicShell title="This link cannot be used">
          <div className="flex flex-col items-center text-center gap-3 py-2">
            <AlertTriangle className="w-10 h-10 text-amber-500" />
            <p className="text-sm text-slate-600">{message}</p>
            <p className="text-xs text-slate-500">Each link works once. Ask the platform team to send a new one.</p>
          </div>
        </PublicShell>
      )
    }
    return <PublicShell title="Opening your link…">{null}</PublicShell>
  }

  return invitation.purpose === 'REGISTER' ? <RegisterForm invitation={invitation} /> : <ActivateForm invitation={invitation} />
}

function Expiry({ at }: { at: string }) {
  return <p className="text-xs text-slate-500 mb-4">This form stays open until {formatDateTime(at)}. The emailed link has now been used.</p>
}

function RegisterForm({ invitation }: { invitation: OpenedInvitation }) {
  const [form, setForm] = useState({
    institution_name: invitation.institution_name ?? '',
    kra_pin: '',
    contact_phone: invitation.contact_phone ?? '',
    branch_name: invitation.town ?? '',
    county: '',
    admin_name: invitation.contact_name ?? '',
    admin_password: '',
    admin_password_confirmation: '',
  })
  const set = (patch: Partial<typeof form>) => setForm((f) => ({ ...f, ...patch }))

  const register = useMutation({
    meta: { silent: true },
    mutationFn: async () => {
      await ensureCsrfCookie()
      const { data } = await api.post<{ institution: string; email: string; trial_ends_at: string }>('/api/public/register', {
        session_token: invitation.session_token,
        ...form,
        kra_pin: form.kra_pin || null,
        contact_phone: form.contact_phone || null,
        branch_name: form.branch_name || null,
        county: form.county || null,
      })
      return data
    },
  })
  const err = register.isError ? getApiError(register.error) : null

  if (register.isSuccess) {
    return (
      <PublicShell title="Your institution is ready">
        <div className="flex flex-col items-center text-center gap-3 py-2">
          <CheckCircle2 className="w-12 h-12 text-emerald-600" />
          <p className="text-sm text-slate-600">
            <strong>{register.data.institution}</strong> is set up. Your free trial runs until <strong>{formatDateTime(register.data.trial_ends_at)}</strong>.
          </p>
          <Link to="/login" className="text-blue-600 font-semibold hover:underline text-sm">
            Sign in as {register.data.email}
          </Link>
        </div>
      </PublicShell>
    )
  }

  const valid = form.institution_name.trim() && form.admin_name.trim() && form.admin_password.length >= 12 && form.admin_password === form.admin_password_confirmation

  return (
    <PublicShell wide title="Set up your institution" subtitle={<>You will sign in as <strong>{invitation.email}</strong> and own this institution's account.</>}>
      <Expiry at={invitation.session_expires_at} />
      <form
        className="grid grid-cols-1 sm:grid-cols-2 gap-3"
        onSubmit={(e) => {
          e.preventDefault()
          if (valid) register.mutate()
        }}
      >
        <Field label="Institution name" required className="sm:col-span-2" error={err?.errors.institution_name?.[0]}>
          <Input value={form.institution_name} onChange={(e) => set({ institution_name: e.target.value })} />
        </Field>
        <Field label="KRA PIN" hint="Printed on your invoices; you can add it later." error={err?.errors.kra_pin?.[0]}>
          <Input value={form.kra_pin} onChange={(e) => set({ kra_pin: e.target.value.toUpperCase() })} placeholder="P051234567M" />
        </Field>
        <Field label="Phone">
          <Input value={form.contact_phone} onChange={(e) => set({ contact_phone: e.target.value })} />
        </Field>
        <Field label="First branch name">
          <Input value={form.branch_name} onChange={(e) => set({ branch_name: e.target.value })} placeholder="Main branch" />
        </Field>
        <Field label="County">
          <Input value={form.county} onChange={(e) => set({ county: e.target.value })} />
        </Field>
        <Field label="Your name" required className="sm:col-span-2" error={err?.errors.admin_name?.[0]}>
          <Input value={form.admin_name} onChange={(e) => set({ admin_name: e.target.value })} />
        </Field>
        <Field label="Password" required hint="At least 12 characters." error={err?.errors.admin_password?.[0]}>
          <Input type="password" autoComplete="new-password" value={form.admin_password} onChange={(e) => set({ admin_password: e.target.value })} />
        </Field>
        <Field label="Confirm password" required error={form.admin_password_confirmation && form.admin_password !== form.admin_password_confirmation ? 'The passwords do not match.' : undefined}>
          <Input type="password" autoComplete="new-password" value={form.admin_password_confirmation} onChange={(e) => set({ admin_password_confirmation: e.target.value })} />
        </Field>
        {err && Object.keys(err.errors).length === 0 && <InlineError error={register.error} className="sm:col-span-2" />}
        <Button type="submit" variant="primary" size="lg" className="sm:col-span-2" disabled={!valid || register.isPending}>
          {register.isPending ? 'Setting up…' : 'Create my institution'}
        </Button>
      </form>
    </PublicShell>
  )
}

function ActivateForm({ invitation }: { invitation: OpenedInvitation }) {
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')

  const activate = useMutation({
    meta: { silent: true },
    mutationFn: async () => {
      await ensureCsrfCookie()
      await api.post('/api/public/activate', { session_token: invitation.session_token, password, password_confirmation: confirmation })
    },
  })
  const err = activate.isError ? getApiError(activate.error) : null

  if (activate.isSuccess) {
    return (
      <PublicShell title="Your account is ready">
        <div className="flex flex-col items-center text-center gap-3 py-2">
          <CheckCircle2 className="w-12 h-12 text-emerald-600" />
          <Link to="/login" className="text-blue-600 font-semibold hover:underline text-sm">
            Sign in as {invitation.email}
          </Link>
        </div>
      </PublicShell>
    )
  }

  const valid = password.length >= 12 && password === confirmation

  return (
    <PublicShell title="Choose your password" subtitle={<>For <strong>{invitation.email}</strong>{invitation.institution_name ? <> at {invitation.institution_name}</> : null}.</>}>
      <Expiry at={invitation.session_expires_at} />
      <form
        className="space-y-3"
        onSubmit={(e) => {
          e.preventDefault()
          if (valid) activate.mutate()
        }}
      >
        <Field label="Password" required hint="At least 12 characters." error={err?.errors.password?.[0]}>
          <Input type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} />
        </Field>
        <Field label="Confirm password" required error={confirmation && password !== confirmation ? 'The passwords do not match.' : undefined}>
          <Input type="password" autoComplete="new-password" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} />
        </Field>
        {err && Object.keys(err.errors).length === 0 && <InlineError error={activate.error} />}
        <Button type="submit" variant="primary" size="lg" className="w-full" disabled={!valid || activate.isPending}>
          {activate.isPending ? 'Saving…' : 'Save password'}
        </Button>
      </form>
    </PublicShell>
  )
}
