import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { useNavigate } from 'react-router-dom'
import { api, ensureCsrfCookie, getApiError } from '../lib/api'
import { formatDateTime } from '../lib/format'
import type { CurrentUser } from '../lib/types'

type LoginResult = { mfa_required: true } | { mfa_required?: false; email: string }

/**
 * Part 18.2 — password, then TOTP when the account requires it; lockout is
 * a 423 with the time the lock lifts. The server never says whether the
 * username exists, and neither does this screen.
 */
export default function Login() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [remember, setRemember] = useState(false)
  const [code, setCode] = useState('')
  const [step, setStep] = useState<'credentials' | 'mfa'>('credentials')

  async function finishSignIn() {
    // The login response carries relation objects, not the effective
    // permission list the SPA needs — fetch /api/user before rendering.
    const { data } = await api.get<CurrentUser>('/api/user')
    queryClient.setQueryData(['auth', 'user'], data)
    navigate('/dashboard')
  }

  const login = useMutation({
    meta: { silent: true },
    mutationFn: async () => {
      await ensureCsrfCookie()
      const { data, status } = await api.post<LoginResult>('/auth/login', { email, password, remember })
      return { data, status }
    },
    onSuccess: async ({ data, status }) => {
      if (status === 202 && data.mfa_required) {
        setStep('mfa')
        return
      }
      await finishSignIn()
    },
  })

  const verify = useMutation({
    meta: { silent: true },
    mutationFn: async () => {
      const { data } = await api.post('/auth/mfa/verify', { code })
      return data
    },
    onSuccess: finishSignIn,
  })

  function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (step === 'mfa') verify.mutate()
    else login.mutate()
  }

  const active = step === 'mfa' ? verify : login
  const error = active.isError ? getApiError(active.error) : null
  let errorMessage: string | null = null
  if (error) {
    if (error.code === 'ACCOUNT_LOCKED') {
      const until = error.details.locked_until as string | undefined
      errorMessage = `${error.message}${until ? ` Locked until ${formatDateTime(until)}.` : ''}`
    } else if (error.code === 'VALIDATION') {
      errorMessage = error.errors.email?.[0] ?? error.errors.code?.[0] ?? error.message
    } else {
      errorMessage = error.message
    }
  }

  const inputClass =
    'w-full h-[38px] px-3 rounded-md border border-[var(--border)] bg-[var(--input-bg)] text-[var(--text)] outline-none focus:border-[var(--color-navy)]'

  return (
    <div className="min-h-svh flex items-center justify-center bg-[var(--bg)] px-4">
      <form
        onSubmit={onSubmit}
        className="w-[420px] max-w-full bg-[var(--card)] border border-[var(--border)] rounded-xl p-9 shadow-sm"
      >
        <div className="flex items-center gap-2.5 mb-7">
          <div className="w-9 h-9 rounded-lg bg-[var(--color-navy)]" />
          <div className="text-[17px] font-bold text-[var(--text)]">Stockpoint Pharma ERP</div>
        </div>

        {step === 'credentials' ? (
          <>
            <label className="ui-label">Email</label>
            <input type="email" required autoFocus value={email} onChange={(e) => setEmail(e.target.value)} className={`${inputClass} mb-4`} autoComplete="username" />

            <label className="ui-label">Password</label>
            <input type="password" required value={password} onChange={(e) => setPassword(e.target.value)} className={`${inputClass} mb-3`} autoComplete="current-password" />

            <label className="flex items-center gap-2 text-[12px] text-[var(--text-secondary)] mb-2">
              <input type="checkbox" checked={remember} onChange={(e) => setRemember(e.target.checked)} />
              Keep me signed in on this terminal
            </label>
          </>
        ) : (
          <>
            <p className="text-[12.5px] text-[var(--text-secondary)] mb-4">
              This account requires a second factor. Enter the 6-digit code from your authenticator app.
            </p>
            <label className="ui-label">Authenticator code</label>
            <input
              type="text"
              inputMode="numeric"
              pattern="[0-9]{6}"
              maxLength={6}
              required
              autoFocus
              value={code}
              onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
              className={`${inputClass} mb-3 tabular text-center text-[18px] tracking-[0.4em]`}
              autoComplete="one-time-code"
            />
          </>
        )}

        {errorMessage && (
          <p role="alert" className="text-[12px] text-[var(--color-danger)] mb-3">
            {errorMessage}
          </p>
        )}

        <button
          type="submit"
          disabled={active.isPending}
          className="w-full h-[42px] mt-3 rounded-md bg-[var(--color-navy)] text-white font-bold text-[13px] disabled:opacity-60"
        >
          {active.isPending ? 'Please wait…' : step === 'mfa' ? 'VERIFY CODE' : 'SIGN IN'}
        </button>

        {step === 'mfa' && (
          <button
            type="button"
            onClick={() => {
              setStep('credentials')
              setCode('')
              verify.reset()
            }}
            className="w-full mt-2 text-[11.5px] text-[var(--text-muted)] hover:text-[var(--text)]"
          >
            Back to sign in
          </button>
        )}

        <p className="text-[10px] text-[var(--text-muted)] text-center mt-4">
          One account for every role and branch — no separate retail or wholesale portal.
        </p>
      </form>
    </div>
  )
}
