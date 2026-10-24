import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

/** The frame for pages reached without signing in: quote request, registration, activation. */
export function PublicShell({ title, subtitle, children, wide = false }: { title: string; subtitle?: ReactNode; children: ReactNode; wide?: boolean }) {
  return (
    <div className="min-h-screen w-full bg-[#f8fafc] flex flex-col items-center px-4 py-8 sm:py-12">
      <Link to="/home" className="flex items-center gap-2.5 mb-6 group transition-transform hover:scale-[1.02]">
        <div className="w-11 h-11 rounded-xl bg-white border border-slate-200/90 shadow-sm p-1.5 flex items-center justify-center group-hover:border-blue-400 group-hover:shadow-blue-500/10 transition-all">
          <img src="/assets/solforbs-logo.png" alt="Solforbs" className="w-full h-full object-contain" />
        </div>
        <div className="flex flex-col">
          <span className="font-display text-lg font-bold text-slate-900 tracking-tight leading-none">Stockpoint</span>
          <span className="text-[11px] font-medium text-slate-500 tracking-wide mt-0.5">by Solforbs</span>
        </div>
      </Link>
      <div className={`w-full ${wide ? 'max-w-2xl' : 'max-w-md'} bg-white rounded-2xl border border-slate-200/90 p-6 sm:p-8 shadow-xs`}>
        <h1 className="text-2xl font-bold text-slate-900 tracking-tight mb-1">{title}</h1>
        {subtitle && <p className="text-slate-500 text-sm mb-6">{subtitle}</p>}
        {children}
      </div>
      <p className="text-xs text-slate-400 mt-6">
        Already have an account? <Link to="/login" className="text-blue-600 font-semibold hover:underline">Sign in</Link>
      </p>
    </div>
  )
}
