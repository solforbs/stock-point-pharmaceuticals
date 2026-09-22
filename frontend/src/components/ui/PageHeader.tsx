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
          <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50/80 border border-blue-200/60 text-blue-700 font-semibold text-xs mb-2.5 shadow-2xs">
            <span className="w-1.5 h-1.5 rounded-full bg-blue-600 inline-block" />
            {parent}
          </div>
        )}
        <h1 className="text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight leading-tight">
          {title}
        </h1>
        {subtitle && (
          <p className="text-sm text-slate-500 mt-1.5 font-normal max-w-3xl leading-relaxed">
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
      className={`flex flex-wrap items-end gap-3.5 p-4 sm:p-5 rounded-2xl bg-white border border-slate-200/90 shadow-xs mb-5 ${className}`}
    >
      {children}
    </div>
  )
}
