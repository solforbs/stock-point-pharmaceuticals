import { motion } from 'framer-motion'
import { ArrowLeft, ArrowRight, Building2, Check, HeartPulse, LogOut, ShoppingBag } from 'lucide-react'
import { useState } from 'react'
import { Navigate, useNavigate, useSearchParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { api } from '../../lib/api'
import { forgetCachedUser, useCurrentUser } from '../../hooks/useCurrentUser'
import { MODULES, moduleInfo, postLoginTarget } from '../../lib/modules'
import { sessionModesFor, useSessionMode } from '../../lib/sessionMode'
import type { SaleMode } from '../../lib/types'

const MODE_CARDS: { mode: SaleMode; label: string; description: string; icon: typeof ShoppingBag; highlights: string[] }[] = [
  { mode: 'RETAIL', label: 'Retail', description: 'Counter sales to walk-in customers', icon: ShoppingBag, highlights: ['Retail prices', 'Cash, M-PESA & card', 'Receipts at the counter'] },
  { mode: 'WHOLESALE', label: 'Wholesale', description: 'Trade sales to chemists, clinics, hospitals & institutions', icon: Building2, highlights: ['Trade prices', 'Sales on account', 'Invoices & delivery notes'] },
]

/**
 * The neutral platform-level splash shown after login to anyone with access
 * to two or more modules. Only accessible modules are shown — the list is
 * decided server-side from permissions, never from role names.
 */
export default function ModuleSelectPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()
  const [searchParams] = useSearchParams()
  const sessionMode = useSessionMode((s) => s.mode)
  const chooseSessionMode = useSessionMode((s) => s.choose)
  const [pickingMode, setPickingMode] = useState(searchParams.get('step') === 'mode')

  if (!user) return null
  const modules = user.modules ?? []
  const pharmacyModes = sessionModesFor(user)
  const pharmacyOnly = modules.length === 1 && modules[0] === 'PHARMACY'

  // Nothing to choose: send single-module users straight in, unless their
  // pharmacy sells both retail and wholesale and the session needs a mode.
  if (modules.length === 1 && !(pharmacyOnly && pharmacyModes.length > 1)) return <Navigate to={moduleInfo(modules[0]).home} replace />
  const showModes = pickingMode || pharmacyOnly

  /** The pharmacy opens in the chosen mode, and stays in it for the whole session. */
  function openPharmacy(mode?: SaleMode) {
    const chosen = mode ?? (pharmacyModes.length === 1 ? pharmacyModes[0] : null)
    if (!chosen && pharmacyModes.length > 1) {
      setPickingMode(true)
      return
    }
    if (chosen) chooseSessionMode(chosen)
    navigate(moduleInfo('PHARMACY').home)
  }

  async function signOut() {
    try {
      await api.post('/auth/logout')
    } finally {
      forgetCachedUser()
      queryClient.setQueryData(['auth', 'user'], null)
      navigate('/login')
    }
  }

  const cards = MODULES.filter((m) => modules.includes(m.key))
  const firstName = user.name?.split(' ')[0] ?? user.name

  return (
    <div className="min-h-svh bg-slate-50 flex flex-col">
      <header className="flex items-center justify-between px-4 sm:px-8 py-4">
        <div className="flex items-center gap-2.5">
          <div className="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center text-white shadow-sm">
            <HeartPulse size={18} />
          </div>
          <div className="leading-tight">
            <div className="font-display font-extrabold tracking-tight text-slate-900">Healthcare Platform</div>
            {user.organisation && <div className="text-[11px] font-semibold text-slate-500">{user.organisation.name}</div>}
          </div>
        </div>
        <button
          type="button"
          onClick={signOut}
          className="flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 px-3 py-2 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer"
        >
          <LogOut size={14} /> Sign out
        </button>
      </header>

      <main className="flex-1 flex items-center justify-center px-4 py-8">
        <div className="w-full max-w-4xl">
          <motion.div initial={{ opacity: 0, y: 10 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.35 }} className="text-center mb-8">
            <h1 className="font-display text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">Welcome, {firstName}</h1>
            <p className="text-sm text-slate-500 mt-1.5">
              {showModes ? 'Choose how the pharmacy sells for this session — you can change it at any time' : 'Select a module to continue'}
            </p>
          </motion.div>

          {showModes ? (
            <div className="max-w-2xl mx-auto">
              <div className="grid gap-4 sm:grid-cols-2">
                {MODE_CARDS.filter((c) => pharmacyModes.includes(c.mode)).map((card, i) => {
                  const current = sessionMode === card.mode
                  return (
                    <motion.button
                      key={card.mode}
                      type="button"
                      initial={{ opacity: 0, y: 14 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ duration: 0.3, delay: 0.08 * i }}
                      onClick={() => openPharmacy(card.mode)}
                      className={`group text-left bg-white rounded-2xl border p-6 shadow-xs hover:shadow-lg hover:border-blue-300 hover:-translate-y-0.5 transition-all cursor-pointer ${current ? 'border-blue-400 ring-2 ring-blue-100' : 'border-slate-200'}`}
                    >
                      <div className="flex items-start justify-between">
                        <div className="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                          <card.icon size={22} />
                        </div>
                        {current && (
                          <span className="flex items-center gap-1 text-[11px] font-bold text-blue-700 bg-blue-50 rounded-full px-2 py-0.5">
                            <Check size={12} /> Current
                          </span>
                        )}
                      </div>
                      <div className="mt-4 font-display font-extrabold text-lg tracking-tight text-slate-900">{card.label} mode</div>
                      <div className="text-xs text-slate-500 mt-0.5">{card.description}</div>
                      <ul className="mt-4 space-y-1.5">
                        {card.highlights.map((h) => (
                          <li key={h} className="text-xs text-slate-600 flex items-center gap-2">
                            <span className="w-1 h-1 rounded-full bg-blue-400 shrink-0" />
                            {h}
                          </li>
                        ))}
                      </ul>
                      <div className="mt-5 flex items-center gap-1.5 text-sm font-semibold text-blue-600">
                        Open pharmacy in {card.label.toLowerCase()} <ArrowRight size={15} className="group-hover:translate-x-0.5 transition-transform" />
                      </div>
                    </motion.button>
                  )
                })}
              </div>
              {!pharmacyOnly && (
                <button
                  type="button"
                  onClick={() => setPickingMode(false)}
                  className="mt-6 mx-auto flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-800 px-3 py-2 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer"
                >
                  <ArrowLeft size={14} /> Back to modules
                </button>
              )}
            </div>
          ) : cards.length === 0 ? (
            <div className="ui-card max-w-md mx-auto p-8 text-center">
              <div className="font-semibold text-slate-900">No modules available</div>
              <p className="text-sm text-slate-500 mt-1">
                Your account has no module access yet. Ask your administrator to assign you a role.
              </p>
            </div>
          ) : (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 max-w-4xl mx-auto">
              {cards.map((mod, i) => (
                <motion.button
                  key={mod.key}
                  type="button"
                  initial={{ opacity: 0, y: 14 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.3, delay: 0.08 * i }}
                  onClick={() => (mod.key === 'PHARMACY' ? openPharmacy() : navigate(mod.home))}
                  className="group text-left bg-white rounded-2xl border border-slate-200 p-6 shadow-xs hover:shadow-lg hover:border-blue-300 hover:-translate-y-0.5 transition-all cursor-pointer"
                >
                  <div className="w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition-colors">
                    <mod.icon size={22} />
                  </div>
                  <div className="mt-4 font-display font-extrabold text-lg tracking-tight text-slate-900">{mod.label}</div>
                  <div className="text-xs text-slate-500 mt-0.5">{mod.description}</div>
                  <ul className="mt-4 space-y-1.5">
                    {mod.highlights.map((h) => (
                      <li key={h} className="text-xs text-slate-600 flex items-center gap-2">
                        <span className="w-1 h-1 rounded-full bg-blue-400 shrink-0" />
                        {h}
                      </li>
                    ))}
                  </ul>
                  <div className="mt-5 flex items-center gap-1.5 text-sm font-semibold text-blue-600">
                    Open <ArrowRight size={15} className="group-hover:translate-x-0.5 transition-transform" />
                  </div>
                </motion.button>
              ))}
            </div>
          )}
        </div>
      </main>

      <footer className="text-center pb-6 text-xs text-slate-400">
        Stockpoint by <span className="font-semibold text-slate-500">Solforbs</span> — one platform for hospital, laboratory and pharmacy
      </footer>
    </div>
  )
}

/** Shown to an authenticated account that has access to no module at all. */
export function NoAccessPage() {
  const { data: user } = useCurrentUser()
  // If access appears (an admin fixed the roles and the user query refreshed), leave.
  if (user && (user.modules ?? []).length > 0) return <Navigate to={postLoginTarget(user)} replace />

  return (
    <div className="min-h-svh bg-slate-50 flex items-center justify-center px-4">
      <div className="ui-card max-w-md w-full p-8 text-center">
        <div className="font-display font-extrabold text-lg text-slate-900">Access denied</div>
        <p className="text-sm text-slate-500 mt-1.5">
          Your account is active but has no module access. Ask your administrator to assign you a role with hospital,
          laboratory or pharmacy permissions.
        </p>
      </div>
    </div>
  )
}
