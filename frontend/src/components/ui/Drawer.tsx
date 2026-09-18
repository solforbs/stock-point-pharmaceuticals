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
  footer,
}: {
  open: boolean
  onClose: () => void
  title: ReactNode
  subtitle?: ReactNode
  children: ReactNode
  width?: number
  actions?: ReactNode
  footer?: ReactNode
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
    <div className="fixed inset-0 z-50 flex justify-end">
      <div
        className="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity animate-in fade-in duration-200"
        onClick={onClose}
        aria-hidden
      />
      <aside
        role="dialog"
        aria-modal="true"
        className="relative h-full w-full bg-slate-50 border-l border-slate-200 shadow-2xl flex flex-col z-10 animate-in slide-in-from-right duration-250"
        style={{ maxWidth: `min(100vw, ${width}px)` }}
      >
        <header className="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-200/90 bg-white shrink-0">
          <div className="flex-1 min-w-0">
            <h2 className="text-[17px] font-black text-slate-900 tracking-tight truncate">{title}</h2>
            {subtitle && <div className="text-[12.5px] text-slate-500 font-medium mt-0.5">{subtitle}</div>}
          </div>
          <div className="flex items-center gap-2">
            {actions}
            <button
              type="button"
              onClick={onClose}
              aria-label="Close"
              className="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
            >
              <X size={18} />
            </button>
          </div>
        </header>
        <div className="flex-1 overflow-y-auto p-4 sm:p-6">{children}</div>
        {footer && (
          <footer className="shrink-0 px-4 sm:px-6 py-3.5 border-t border-slate-200 bg-white/95 backdrop-blur-xs flex items-center justify-between gap-3">
            {footer}
          </footer>
        )}
      </aside>
    </div>
  )
}
