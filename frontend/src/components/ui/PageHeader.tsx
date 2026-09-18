import type { ReactNode } from 'react'

export function PageHeader({
  title,
  parent,
  subtitle,
  actions,
}: {
  title: ReactNode
  parent?: ReactNode
  subtitle?: ReactNode
  actions?: ReactNode
}) {
  return (
    <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-5">
      <div className="min-w-0">
        {parent && (
          <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-blue-50 border border-blue-200/60 text-blue-600 font-bold text-[11px] uppercase tracking-wider mb-2">
            {parent}
          </div>
        )}
        <h1 className="text-[24px] sm:text-[26px] font-black text-slate-900 tracking-tight leading-tight">
          {title}
        </h1>
        {subtitle && (
          <div className="text-[13px] text-slate-500 mt-1.5 font-medium max-w-3xl">
            {subtitle}
          </div>
        )}
      </div>
      {actions && <div className="flex items-center gap-2.5 shrink-0">{actions}</div>}
    </div>
  )
}

export function Page({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`p-6 max-w-[1600px] mx-auto space-y-4 ${className}`}>{children}</div>
}

export function FilterBar({ children }: { children: ReactNode }) {
  return (
    <div className="flex flex-wrap items-end gap-3 p-4 rounded-2xl bg-white border border-slate-200/80 shadow-xs mb-4">
      {children}
    </div>
  )
}
