import { ShieldCheck } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'

/** The frame for pages reached without signing in: quote request, registration, activation. */
export function PublicShell({ title, subtitle, children, wide = false }: { title: string; subtitle?: ReactNode; children: ReactNode; wide?: boolean }) {
  return (
    <div className="min-h-screen w-full bg-[#f8fafc] flex flex-col items-center px-4 py-8 sm:py-12">
      <Link to="/login" className="flex items-center gap-2.5 mb-6">
        <div className="w-10 h-10 rounded-xl bg-blue-600 flex items-center justify-center shadow-md shadow-blue-500/30">
          <ShieldCheck className="w-6 h-6 text-white" />
        </div>
        <span className="font-display text-lg font-bold text-slate-900 tracking-tight">Stockpoint Solforbs</span>
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
