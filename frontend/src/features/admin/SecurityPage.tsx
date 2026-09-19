import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { api, getApiError } from '../../lib/api'
import { toast } from '../../lib/toast'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { Button, Card, Field, Input } from '../../components/ui/primitives'
import { StatusBadge } from '../../components/ui/StatusBadge'

type Setup = { secret: string; otpauth_url: string }

/**
 * Part 18.2 — TOTP second factor. Setup issues a secret; MFA is only
 * enforced once the first code from the authenticator verifies.
 */
export default function SecurityPage() {
  const { data: user } = useCurrentUser()
  const queryClient = useQueryClient()
  const [setup, setSetup] = useState<Setup | null>(null)
  const [code, setCode] = useState('')

  const start = useMutation({
    mutationFn: async () => (await api.post<Setup>('/auth/mfa/setup')).data,
    onSuccess: (data) => {
      setSetup(data)
      setCode('')
    },
  })

  const verify = useMutation({
    meta: { silent: true },
    mutationFn: async () => (await api.post('/auth/mfa/verify', { code })).data,
    onSuccess: () => {
      toast.success('Two-factor authentication enabled', 'Every sign-in now asks for an authenticator code.')
      setSetup(null)
      setCode('')
      queryClient.invalidateQueries({ queryKey: ['auth', 'user'] })
    },
  })

  const verifyError = verify.isError ? getApiError(verify.error) : null

  return (
    <Page>
      <PageHeader parent="Admin" title="Security" subtitle="Your sign-in protections for this account." />

      <div className="grid gap-4 md:grid-cols-2 max-w-4xl">
        <Card title="Two-factor authentication (TOTP)">
          <div className="p-4 space-y-3">
            <div className="flex items-center gap-2">
              <span className="text-xs text-slate-500">Status</span>
              <StatusBadge status={user?.mfa_required ? 'ACTIVE' : 'DISABLED'} label={user?.mfa_required ? 'Enabled' : 'Not enabled'} />
            </div>
            <p className="text-sm text-slate-600">
              Roles with finance, admin, void or adjustment-approval permissions must use an authenticator app. Setting up a new
              authenticator replaces the previous one.
            </p>
            <Button variant="primary" onClick={() => start.mutate()} disabled={start.isPending}>
              {start.isPending ? 'Generating…' : user?.mfa_required ? 'Re-enrol a new authenticator' : 'Set up authenticator'}
            </Button>
          </div>
        </Card>

        {setup && (
          <Card title="Finish enrolment">
            <div className="p-4 space-y-3">
              <p className="text-sm text-slate-600">
                Add this account in Google Authenticator, Microsoft Authenticator or any TOTP app, then enter the first code it shows.
              </p>
              <Field label="Secret (manual entry)">
                <code className="block ui-input !h-auto py-2 tabular tracking-wider break-all select-all">{setup.secret}</code>
              </Field>
              <Field label="otpauth URL" hint="Open this on the device with your authenticator app, or paste it into an app that accepts setup links.">
                <a href={setup.otpauth_url} className="block ui-input !h-auto py-2 text-xs break-all text-blue-600 underline">
                  {setup.otpauth_url}
                </a>
              </Field>
              <Field label="First code from the app" error={verifyError?.errors.code?.[0] ?? verifyError?.message ?? null}>
                <Input
                  inputMode="numeric"
                  maxLength={6}
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                  className="tabular text-center text-xl tracking-[0.4em] font-mono"
                  autoFocus
                />
              </Field>
              <Button variant="success" onClick={() => verify.mutate()} disabled={code.length !== 6 || verify.isPending}>
                {verify.isPending ? 'Verifying…' : 'Verify and enable'}
              </Button>
            </div>
          </Card>
        )}
      </div>
    </Page>
  )
}
