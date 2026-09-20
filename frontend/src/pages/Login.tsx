import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Eye, EyeOff, ArrowLeft, ShieldCheck, Cpu } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { api, ensureCsrfCookie, getApiError } from '../lib/api'
import { formatDateTime } from '../lib/format'
import type { CurrentUser } from '../lib/types'

type LoginResult = { mfa_required: true } | { mfa_required?: false; email: string }

/**
 * Enterprise Pharmacy ERP Login Page
 * - Responsive 2-panel split layout on desktop
 * - Integrated mobile hero banner with digital pharmacy branding
 * - Multi-Factor Authentication (TOTP) step
 * - Sanctum CSRF and session authentication
 */
export default function Login() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [showPassword, setShowPassword] = useState(false)
  const [remember, setRemember] = useState(false)
  const [code, setCode] = useState('')
  const [step, setStep] = useState<'credentials' | 'mfa'>('credentials')

  async function finishSignIn() {
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

  return (
    <div className="min-h-screen w-full bg-slate-900 flex flex-col md:flex-row">
      {/* Mobile Top Hero Banner (Visible only on mobile screens < 768px) */}
      <div className="md:hidden relative h-48 w-full overflow-hidden shrink-0">
        <img
          src="/assets/login-hero.jpg"
          alt="Automated Pharmaceutical Logistics"
          className="w-full h-full object-cover"
        />
        <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent flex flex-col justify-end p-5 text-white">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center shadow-md">
              <ShieldCheck className="w-5 h-5 text-white" />
            </div>
            <div>
              <div className="text-base font-bold tracking-tight leading-tight">Stockpoint Pharma ERP</div>
              <div className="text-[10px] text-blue-200 tracking-wider uppercase font-semibold flex items-center gap-1">
                <Cpu className="w-3 h-3" /> Digital Pharmacy Automation
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Left Panel - Desktop Hero Section (Visible >= 768px) */}
      <div className="hidden md:flex flex-1 relative overflow-hidden bg-slate-950">
        {/* Back Button */}
        <div className="absolute top-6 left-6 z-20">
          <button
            type="button"
            onClick={() => navigate('/')}
            className="w-10 h-10 bg-black/40 backdrop-blur-md rounded-full flex items-center justify-center hover:bg-black/60 transition-all cursor-pointer border border-white/10"
            aria-label="Back to home"
          >
            <ArrowLeft className="w-5 h-5 text-white" />
          </button>
        </div>

        {/* Ambient Brand Overlay */}
        <div className="absolute inset-0 z-10 bg-gradient-to-t from-slate-950 via-slate-950/40 to-transparent flex flex-col justify-end p-12 text-white">
          <div className="flex items-center gap-3 mb-4">
            <div className="w-11 h-11 rounded-xl bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-500/30">
              <ShieldCheck className="w-6 h-6 text-white" />
            </div>
            <div>
              <div className="text-2xl font-bold tracking-tight">Stockpoint Pharma ERP</div>
              <div className="text-xs text-blue-300 uppercase tracking-widest font-semibold flex items-center gap-1.5 mt-0.5">
                <Cpu className="w-3.5 h-3.5 text-blue-400" /> Digital Pharmacy & Supply Chain Automation
              </div>
            </div>
          </div>
          <p className="text-sm text-slate-300 max-w-lg leading-relaxed">
            Automated multi-branch inventory tracking, high-speed wholesale POS, intelligent procurement, and compliance-grade lot traceability.
          </p>
        </div>

        {/* High-Resolution Pharmaceutical Automation Hero Image */}
        <div className="absolute inset-0">
          <img
            src="/assets/login-hero.jpg"
            alt="Pharmaceutical Logistics & Digital Automation"
            className="w-full h-full object-cover"
          />
        </div>
      </div>

      {/* Right Panel - Form Section */}
      <div className="flex-1 flex items-center justify-center bg-white px-6 py-10 md:py-12 overflow-y-auto">
        <div className="w-full max-w-md">
          {/* Form Header */}
          <div className="mb-8">
            <h1 className="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">
              {step === 'credentials' ? 'Welcome Back' : 'Two-Factor Authentication'}
            </h1>
            <p className="text-gray-600 text-sm">
              {step === 'credentials'
                ? 'Sign in to access your pharmacy operations portal'
                : 'Enter the 6-digit code from your authenticator app to verify'}
            </p>
          </div>

          {/* Form */}
          <form onSubmit={onSubmit} className="space-y-5">
            {step === 'credentials' ? (
              <>
                {/* Email Address */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Email Address
                  </label>
                  <input
                    type="email"
                    required
                    autoFocus
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    placeholder="name@pharmapoint.com"
                    className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm"
                    autoComplete="username"
                  />
                </div>

                {/* Password with Eye Toggle */}
                <div>
                  <label className="block text-sm font-medium text-gray-700 mb-2">
                    Password
                  </label>
                  <div className="relative">
                    <input
                      type={showPassword ? 'text' : 'password'}
                      required
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      placeholder="••••••••••••"
                      className="w-full px-4 py-3 pr-12 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-sm"
                      autoComplete="current-password"
                    />
                    <button
                      type="button"
                      onClick={() => setShowPassword(!showPassword)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 p-1.5 text-gray-400 hover:text-gray-600 rounded-full cursor-pointer hover:bg-gray-100 transition-colors"
                      tabIndex={-1}
                      aria-label={showPassword ? 'Hide password' : 'Show password'}
                    >
                      {showPassword ? (
                        <EyeOff className="w-5 h-5" />
                      ) : (
                        <Eye className="w-5 h-5" />
                      )}
                    </button>
                  </div>
                </div>

                {/* Remember Terminal + Security Indicator */}
                <div className="flex items-center justify-between text-xs sm:text-sm">
                  <label className="flex items-center space-x-2 text-gray-600 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={remember}
                      onChange={(e) => setRemember(e.target.checked)}
                      className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500 cursor-pointer"
                    />
                    <span>Remember terminal</span>
                  </label>
                  <span className="text-xs text-slate-400 font-medium">
                    Sanctum Secured
                  </span>
                </div>
              </>
            ) : (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Authenticator Code
                </label>
                <input
                  type="text"
                  inputMode="numeric"
                  pattern="[0-9]{6}"
                  maxLength={6}
                  required
                  autoFocus
                  value={code}
                  onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                  placeholder="000000"
                  className="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none transition-all text-center text-xl tracking-[0.3em] font-mono"
                  autoComplete="one-time-code"
                />
              </div>
            )}

            {/* Error Message */}
            {errorMessage && (
              <div role="alert" className="p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium">
                {errorMessage}
              </div>
            )}

            {/* Submit Button */}
            <button
              type="submit"
              disabled={active.isPending}
              className="w-full bg-slate-900 text-white py-3.5 px-4 rounded-xl font-medium hover:bg-slate-800 active:scale-[0.99] transition-all disabled:opacity-60 cursor-pointer shadow-sm text-sm"
            >
              {active.isPending
                ? 'Authenticating…'
                : step === 'mfa'
                ? 'Verify Code'
                : 'Sign In'}
            </button>

            {step === 'mfa' && (
              <button
                type="button"
                onClick={() => {
                  setStep('credentials')
                  setCode('')
                  verify.reset()
                }}
                className="w-full text-center text-xs text-gray-500 hover:text-gray-800 transition-colors cursor-pointer py-1"
              >
                ← Back to credentials
              </button>
            )}

            {/* Enterprise Security Footer */}
            <div className="pt-4 border-t border-gray-100 text-center">
              <p className="text-xs text-gray-400">
                Authorized Pharmacy Personnel Only • Audit Logging Active
              </p>
            </div>
          </form>
        </div>
      </div>
    </div>
  )
}
