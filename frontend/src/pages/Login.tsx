import { useMutation, useQueryClient } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useEffect, useState, type FormEvent } from 'react'
import { Eye, EyeOff, ArrowLeft, ShieldCheck, Cpu } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { api, apiGet, ensureCsrfCookie, getApiError } from '../lib/api'
import { formatDateTime, todayIso } from '../lib/format'
import type { CurrentUser, DashboardSummary, Paginated, Sale } from '../lib/types'

type LoginResult = { mfa_required: true } | { mfa_required?: false; email: string }

/**
 * Enterprise Pharmacy ERP Login Page — Stockpoint Solforbs
 * - Pristine light-mode aesthetic matching the application UI
 * - Unobstructed 50/50 split layout on desktop with automated pharma hero image
 * - Smooth micro-animations on text and form elements
 * - Clean, cohesive mobile layout with zero awkward dead space
 * - Multi-Factor Authentication (TOTP) step
 * - Sanctum CSRF and session authentication
 * - Background Dashboard chunk & query preloading for instant landing
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

  // Preload Dashboard bundle while user is on the login screen
  useEffect(() => {
    const timer = setTimeout(() => {
      import('./Dashboard')
    }, 150)
    return () => clearTimeout(timer)
  }, [])

  async function finishSignIn() {
    const today = todayIso()

    // Prefetch Dashboard queries in parallel while retrieving user permissions
    queryClient.prefetchQuery({
      queryKey: ['dashboard', 'summary'],
      queryFn: () => apiGet<DashboardSummary>('/api/dashboard/summary'),
      staleTime: 30_000,
    })
    queryClient.prefetchQuery({
      queryKey: ['sales', 'today', today],
      queryFn: () => apiGet<Paginated<Sale>>('/api/sales', { from: today, to: today, status: 'POSTED', per_page: 50 }),
      staleTime: 30_000,
    })

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
    <div className="min-h-screen w-full bg-[#f8fafc] flex flex-col md:flex-row">
      {/* Left Panel - Desktop Hero Section (Visible only >= 768px) */}
      <div className="hidden md:flex flex-1 relative overflow-hidden bg-slate-100 border-r border-slate-200">
        {/* Top Floating Controls — Back button & Brand pill with zero image collision */}
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4 }}
          className="absolute top-6 left-6 z-20 flex items-center gap-3"
        >
          <button
            type="button"
            onClick={() => navigate('/')}
            className="w-10 h-10 bg-white/90 backdrop-blur-md rounded-full flex items-center justify-center hover:bg-white transition-all cursor-pointer shadow-sm border border-slate-200/80 text-slate-700"
            aria-label="Back to home"
          >
            <ArrowLeft className="w-5 h-5" />
          </button>
          <div className="flex items-center gap-2 bg-white/90 backdrop-blur-md py-1.5 px-3.5 rounded-full border border-slate-200/80 shadow-sm">
            <div className="w-6 h-6 rounded-lg bg-blue-600 flex items-center justify-center shrink-0">
              <ShieldCheck className="w-3.5 h-3.5 text-white" />
            </div>
            <span className="text-xs font-bold text-slate-900 tracking-tight">Stockpoint Solforbs</span>
            <span className="text-[10px] text-blue-600 font-semibold uppercase tracking-wider bg-blue-50 px-2 py-0.5 rounded-full">
              Pharma ERP
            </span>
          </div>
        </motion.div>

        {/* Minimal Bottom Info Bar (Non-obstructive) */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ duration: 0.6, delay: 0.2 }}
          className="absolute bottom-0 inset-x-0 z-10 bg-gradient-to-t from-slate-950/70 via-slate-950/20 to-transparent p-6 text-white"
        >
          <div className="flex items-center justify-between text-xs text-white/90 font-medium">
            <span className="flex items-center gap-1.5">
              <Cpu className="w-3.5 h-3.5 text-blue-400" /> Automated Pharmaceutical Logistics & Multi-Branch POS
            </span>
            <span className="text-white/60 text-[11px]">Powered by Solforbs</span>
          </div>
        </motion.div>

        {/* Unpeopled Light-Mode Pharmaceutical Robotics & Dashboard Workstation Image */}
        <div className="absolute inset-0">
          <img
            src="/assets/login-hero.jpg"
            alt="Stockpoint Solforbs Pharmaceutical Automation"
            className="w-full h-full object-cover object-center"
          />
        </div>
      </div>

      {/* Right Panel - Form Section (Full width on mobile, 50% on desktop) */}
      <div className="flex-1 flex flex-col justify-center items-center px-4 sm:px-8 py-8 sm:py-12 overflow-y-auto">
        <div className="w-full max-w-md">
          {/* Mobile Branding Header */}
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            transition={{ duration: 0.4 }}
            className="md:hidden flex flex-col items-center text-center mb-7"
          >
            <div className="w-12 h-12 rounded-xl bg-blue-600 flex items-center justify-center shadow-md shadow-blue-500/30 mb-3">
              <ShieldCheck className="w-7 h-7 text-white" />
            </div>
            <h2 className="text-xl font-bold text-slate-900 tracking-tight">Stockpoint Solforbs</h2>
            <p className="text-xs text-slate-500 font-medium mt-0.5 flex items-center justify-center gap-1">
              <Cpu className="w-3.5 h-3.5 text-blue-600" /> Pharmaceutical ERP & Supply Chain
            </p>
          </motion.div>

          {/* Form Card with Micro-Animations */}
          <motion.div
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.45, ease: 'easeOut' }}
            className="bg-white rounded-2xl border border-slate-200/90 p-6 sm:p-8 shadow-xs"
          >
            <div className="mb-6">
              <motion.h1
                initial={{ opacity: 0, x: -8 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.35, delay: 0.1 }}
                className="text-2xl font-bold text-slate-900 tracking-tight mb-1"
              >
                {step === 'credentials' ? 'Welcome Back' : 'Two-Factor Authentication'}
              </motion.h1>
              <motion.p
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ duration: 0.35, delay: 0.15 }}
                className="text-slate-500 text-xs sm:text-sm"
              >
                {step === 'credentials'
                  ? 'Sign in to access your pharmacy operations portal'
                  : 'Enter the 6-digit code from your authenticator app'}
              </motion.p>
            </div>

            {/* Form */}
            <form onSubmit={onSubmit} className="space-y-4 sm:space-y-5">
              {step === 'credentials' ? (
                <>
                  {/* Email Address */}
                  <div>
                    <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                      Email Address
                    </label>
                    <input
                      type="email"
                      required
                      autoFocus
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      placeholder="name@pharmapoint.com"
                      className="w-full h-11 px-3.5 border border-slate-200 rounded-xl bg-slate-50/50 text-slate-900 text-sm outline-none focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition-all"
                      autoComplete="username"
                    />
                  </div>

                  {/* Password with Eye Toggle */}
                  <div>
                    <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
                      Password
                    </label>
                    <div className="relative">
                      <input
                        type={showPassword ? 'text' : 'password'}
                        required
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        placeholder="••••••••••••"
                        className="w-full h-11 px-3.5 pr-11 border border-slate-200 rounded-xl bg-slate-50/50 text-slate-900 text-sm outline-none focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition-all"
                        autoComplete="current-password"
                      />
                      <button
                        type="button"
                        onClick={() => setShowPassword(!showPassword)}
                        className="absolute right-2.5 top-1/2 -translate-y-1/2 p-1.5 text-slate-400 hover:text-slate-600 rounded-lg cursor-pointer hover:bg-slate-100 transition-colors"
                        tabIndex={-1}
                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                      >
                        {showPassword ? (
                          <EyeOff className="w-4 h-4" />
                        ) : (
                          <Eye className="w-4 h-4" />
                        )}
                      </button>
                    </div>
                  </div>

                  {/* Remember Terminal + Security Indicator */}
                  <div className="flex items-center justify-between text-xs pt-1">
                    <label className="flex items-center space-x-2 text-slate-600 cursor-pointer select-none">
                      <input
                        type="checkbox"
                        checked={remember}
                        onChange={(e) => setRemember(e.target.checked)}
                        className="w-4 h-4 text-blue-600 border-slate-300 rounded focus:ring-blue-500 cursor-pointer"
                      />
                      <span>Remember terminal</span>
                    </label>
                    <span className="text-[11px] text-slate-400 font-medium">
                      Sanctum Secured
                    </span>
                  </div>
                </>
              ) : (
                <div>
                  <label className="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1.5">
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
                    className="w-full h-12 px-4 border border-slate-200 rounded-xl bg-slate-50/50 text-center text-xl tracking-[0.3em] font-mono text-slate-900 outline-none focus:bg-white focus:border-blue-600 focus:ring-2 focus:ring-blue-100 transition-all"
                    autoComplete="one-time-code"
                  />
                </div>
              )}

              {/* Error Message */}
              {errorMessage && (
                <div role="alert" className="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium">
                  {errorMessage}
                </div>
              )}

              {/* Submit Button with Framer Motion Tap Animation */}
              <motion.button
                whileHover={{ scale: 1.008 }}
                whileTap={{ scale: 0.988 }}
                type="submit"
                disabled={active.isPending}
                className="w-full h-11 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white rounded-xl font-semibold text-sm transition-all disabled:opacity-60 cursor-pointer shadow-sm shadow-blue-500/25 mt-2"
              >
                {active.isPending
                  ? 'Authenticating…'
                  : step === 'mfa'
                  ? 'Verify Code'
                  : 'Sign In'}
              </motion.button>

              {step === 'mfa' && (
                <button
                  type="button"
                  onClick={() => {
                    setStep('credentials')
                    setCode('')
                    verify.reset()
                  }}
                  className="w-full text-center text-xs text-slate-500 hover:text-slate-800 transition-colors cursor-pointer py-1"
                >
                  ← Back to credentials
                </button>
              )}
            </form>
          </motion.div>

          {/* Footer Branding */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ duration: 0.5, delay: 0.3 }}
            className="mt-6 text-center"
          >
            <p className="text-xs text-slate-400">
              Stockpoint by <span className="font-semibold text-slate-600">Solforbs</span> • Authorized Pharmacy Personnel Only
            </p>
          </motion.div>
        </div>
      </div>
    </div>
  )
}
