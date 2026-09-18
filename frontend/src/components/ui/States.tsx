import { AlertOctagon, Inbox } from 'lucide-react'
import type { ReactNode } from 'react'
import { getApiError } from '../../lib/apiError'
import { Button } from './primitives'

export function EmptyState({ title, hint, action, icon }: { title: ReactNode; hint?: ReactNode; action?: ReactNode; icon?: ReactNode }) {
  return (
    <div className="flex flex-col items-center justify-center text-center py-12 px-6">
      <div className="text-[var(--text-muted)] mb-2">{icon ?? <Inbox size={28} strokeWidth={1.5} />}</div>
      <div className="text-[13px] font-semibold text-[var(--text)]">{title}</div>
      {hint && <div className="text-[11.5px] text-[var(--text-muted)] mt-1 max-w-sm">{hint}</div>}
      {action && <div className="mt-3">{action}</div>}
    </div>
  )
}

export function ErrorState({ error, onRetry, compact }: { error: unknown; onRetry?: () => void; compact?: boolean }) {
  const e = getApiError(error)
  return (
    <div className={`flex flex-col items-center justify-center text-center ${compact ? 'py-4 px-3' : 'py-12 px-6'}`}>
      <AlertOctagon size={compact ? 18 : 28} strokeWidth={1.5} className="text-[var(--status-red)] mb-2" />
      <div className="text-[12.5px] font-semibold text-[var(--text)]">{e.code}</div>
      <div className="text-[11.5px] text-[var(--text-secondary)] mt-1 max-w-md">{e.message}</div>
      {onRetry && (
        <Button size="sm" className="mt-3" onClick={onRetry}>
          Try again
        </Button>
      )}
    </div>
  )
}

export function LoadingSkeleton({ rows = 5, className = '' }: { rows?: number; className?: string }) {
  return (
    <div className={`p-4 space-y-2 ${className}`} aria-busy="true" aria-label="Loading">
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="h-[26px] rounded bg-[var(--surface-3)] animate-pulse" style={{ width: `${100 - (i % 3) * 8}%` }} />
      ))}
    </div>
  )
}

export function InlineError({ error, className = '' }: { error: unknown; className?: string }) {
  const e = getApiError(error)
  return (
    <div className={`text-[11.5px] rounded-md px-3 py-2 border border-[var(--status-red)] bg-[color-mix(in_srgb,var(--status-red)_8%,transparent)] text-[var(--text)] ${className}`}>
      <span className="font-bold mr-1.5">{e.code}</span>
      {e.message}
    </div>
  )
}
