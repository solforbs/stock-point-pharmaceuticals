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
    <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6 pb-2">
      <div className="min-w-0">
        {parent && (
          <div className="inline-flex items-center gap-1.5 px-3 py-0.5 rounded-full bg-blue-50/80 border border-blue-200/70 text-blue-700 font-extrabold text-[11px] uppercase tracking-wider mb-2.5">
            <span className="w-1.5 h-1.5 rounded-full bg-blue-600 inline-block" />
            {parent}
          </div>
        )}
        <h1 className="text-[24px] sm:text-[28px] font-black text-slate-900 tracking-tight leading-tight">
          {title}
        </h1>
        {subtitle && (
          <p className="text-[13px] sm:text-[13.5px] text-slate-500 mt-1.5 font-medium max-w-3xl leading-relaxed">
            {subtitle}
          </p>
        )}
      </div>
      {actions && <div className="flex items-center gap-2.5 shrink-0 flex-wrap">{actions}</div>}
    </div>
  )
}

export function Page({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`p-6 md:p-8 max-w-[1600px] mx-auto space-y-5 ${className}`}>{children}</div>
}

export function FilterBar({ children, className = '' }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={`flex flex-wrap items-end gap-3 p-4 rounded-2xl bg-white border border-slate-200/80 shadow-2xs mb-4 ${className}`}
    >
      {children}
    </div>
  )
}
