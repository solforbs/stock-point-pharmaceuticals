import { useMutation, useQueryClient } from '@tanstack/react-query'
import { KeyRound, Mail } from 'lucide-react'
import { useState } from 'react'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError } from '../../components/ui/States'
import { Button, Field, FormSection, Input } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiPatch, getApiError } from '../../lib/api'
import { toast } from '../../lib/toast'

/**
 * The signed-in person's own login: the email they sign in with and their
 * password. Both ask for the current password first.
 */
export default function MyAccountPage() {
  const { data: user } = useCurrentUser()
  return (
    <Page>
      <PageHeader title="My account" subtitle={user ? `${user.name} · signs in as ${user.email}` : undefined} />
      <div className="max-w-2xl space-y-4">
        <EmailForm currentEmail={user?.email ?? ''} />
        <PasswordForm />
      </div>
    </Page>
  )
}

function EmailForm({ currentEmail }: { currentEmail: string }) {
  const queryClient = useQueryClient()
  const [email, setEmail] = useState('')
  const [currentPassword, setCurrentPassword] = useState('')
  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPatch<{ email: string }>('/api/account/email', { email: email.trim(), current_password: currentPassword }),
    onSuccess: (r) => {
      toast.success('Login email changed', `Sign in with ${r.email} from now on.`)
      setEmail('')
      setCurrentPassword('')
      queryClient.invalidateQueries({ queryKey: ['auth', 'user'] })
    },
  })
  const err = save.isError ? getApiError(save.error) : null

  return (
    <FormSection title="Login email" description={`You sign in with ${currentEmail || 'your email'}.`} icon={Mail}>
      <div className="space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="New email" required error={err?.errors.email?.[0]}>
            <Input type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} />
          </Field>
          <Field label="Current password" required error={err?.errors.current_password?.[0]}>
            <Input type="password" autoComplete="current-password" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} />
          </Field>
        </div>
        {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
        <div className="flex justify-end">
          <Button variant="primary" disabled={!email.trim() || !currentPassword || email.trim().toLowerCase() === currentEmail.toLowerCase() || save.isPending} onClick={() => save.mutate()}>
            {save.isPending ? 'Saving…' : 'Change email'}
          </Button>
        </div>
      </div>
    </FormSection>
  )
}

function PasswordForm() {
  const [currentPassword, setCurrentPassword] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')
  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPatch('/api/account/password', { current_password: currentPassword, password, password_confirmation: confirmation }),
    onSuccess: () => {
      toast.success('Password changed', 'Use the new password next time you sign in.')
      setCurrentPassword('')
      setPassword('')
      setConfirmation('')
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const tooShort = password !== '' && password.length < 12
  const mismatch = confirmation !== '' && confirmation !== password

  return (
    <FormSection title="Password" description="At least 12 characters. A short phrase is easier to remember and harder to guess." icon={KeyRound}>
      <div className="space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <Field label="Current password" required error={err?.errors.current_password?.[0]}>
            <Input type="password" autoComplete="current-password" value={currentPassword} onChange={(e) => setCurrentPassword(e.target.value)} />
          </Field>
          <Field label="New password" required error={tooShort ? 'Use at least 12 characters.' : err?.errors.password?.[0]}>
            <Input type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} />
          </Field>
          <Field label="Repeat new password" required error={mismatch ? 'The two new passwords do not match.' : undefined}>
            <Input type="password" autoComplete="new-password" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} />
          </Field>
        </div>
        {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
        <div className="flex justify-end">
          <Button variant="primary" disabled={!currentPassword || password.length < 12 || password !== confirmation || save.isPending} onClick={() => save.mutate()}>
            {save.isPending ? 'Saving…' : 'Change password'}
          </Button>
        </div>
      </div>
    </FormSection>
  )
}
