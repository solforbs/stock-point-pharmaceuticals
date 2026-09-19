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
    <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 pb-1">
      <div className="min-w-0">
        {parent && (
          <div className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-slate-100 border border-slate-200/80 text-slate-600 font-medium text-xs mb-2">
            <span className="w-1.5 h-1.5 rounded-full bg-slate-400 inline-block" />
            {parent}
          </div>
        )}
        <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight leading-tight">
          {title}
        </h1>
        {subtitle && (
          <p className="text-sm text-slate-500 mt-1 font-normal max-w-3xl leading-relaxed">
            {subtitle}
          </p>
        )}
      </div>
      {actions && <div className="flex items-center gap-2.5 shrink-0 flex-wrap">{actions}</div>}
    </div>
  )
}

export function Page({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`p-4 sm:p-6 md:p-8 max-w-[1600px] mx-auto space-y-5 ${className}`}>{children}</div>
}

export function FilterBar({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={`flex flex-wrap items-end gap-3 p-3.5 sm:p-4 rounded-xl bg-white border border-slate-200 shadow-xs mb-4 ${className}`}
    >
      {children}
    </div>
  )
}
