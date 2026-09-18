import { X } from 'lucide-react'
import { useEffect, type ReactNode } from 'react'

/**
 * Part 1.2 principle 5 — drawers and detail pages over stacked modals.
 * A 40-line wholesale order lives here, not in a modal.
 */
export function Drawer({
  open,
  onClose,
  title,
  subtitle,
  children,
  width = 560,
  actions,
}: {
  open: boolean
  onClose: () => void
  title: ReactNode
  subtitle?: ReactNode
  children: ReactNode
  width?: number
  actions?: ReactNode
}) {
  useEffect(() => {
    if (!open) return
    function onKey(e: KeyboardEvent) {
      if (e.key === 'Escape') onClose()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open, onClose])

  if (!open) return null

  return (
    <div className="fixed inset-0 z-40 flex justify-end">
      <div className="absolute inset-0 bg-black/30" onClick={onClose} aria-hidden />
      <aside
        role="dialog"
        aria-modal="true"
        className="relative h-full bg-[var(--card)] border-l border-[var(--border)] shadow-2xl flex flex-col max-w-[96vw]"
        style={{ width }}
      >
        <header className="flex items-start gap-3 px-5 py-3.5 border-b border-[var(--border)] shrink-0">
          <div className="flex-1 min-w-0">
            <h2 className="text-[15px] font-bold text-[var(--text)] truncate">{title}</h2>
            {subtitle && <div className="text-[11.5px] text-[var(--text-muted)] mt-0.5">{subtitle}</div>}
          </div>
          {actions}
          <button type="button" onClick={onClose} aria-label="Close" className="p-1 rounded text-[var(--text-muted)] hover:text-[var(--text)]">
            <X size={16} />
          </button>
        </header>
        <div className="flex-1 overflow-y-auto px-5 py-4">{children}</div>
      </aside>
    </div>
  )
}
