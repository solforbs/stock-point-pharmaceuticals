import type { ReactNode } from 'react'

export function PageHeader({ title, parent, subtitle, actions }: { title: ReactNode; parent?: ReactNode; subtitle?: ReactNode; actions?: ReactNode }) {
  return (
    <div className="flex items-end justify-between gap-4 mb-4">
      <div className="min-w-0">
        {parent && <div className="text-[11px] text-[var(--text-muted)] mb-0.5">{parent}</div>}
        <h1 className="text-[19px] font-extrabold text-[var(--text)] leading-tight">{title}</h1>
        {subtitle && <div className="text-[11.5px] text-[var(--text-muted)] mt-1">{subtitle}</div>}
      </div>
      {actions && <div className="flex items-center gap-2 shrink-0">{actions}</div>}
    </div>
  )
}

export function Page({ children, className = '' }: { children: ReactNode; className?: string }) {
  return <div className={`p-6 max-w-[1500px] ${className}`}>{children}</div>
}

export function FilterBar({ children }: { children: ReactNode }) {
  return <div className="flex flex-wrap items-end gap-3 mb-3">{children}</div>
}
