import { AnimatePresence, motion } from 'framer-motion'
import { X } from 'lucide-react'
import { useEffect, type ReactNode } from 'react'

export function Drawer({
  open,
  onClose,
  title,
  subtitle,
  children,
  width = 680,
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

  return (
    <AnimatePresence>
      {open && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-5 md:p-6 overflow-hidden">
          {/* Backdrop with modern blur */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.18 }}
            className="fixed inset-0 bg-slate-950/60 backdrop-blur-sm"
            onClick={onClose}
            aria-hidden
          />

          {/* Modern Floating Centered Dialog Modal */}
          <motion.div
            role="dialog"
            aria-modal="true"
            initial={{ opacity: 0, scale: 0.96, y: 14 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.96, y: 10 }}
            transition={{ type: 'spring', damping: 28, stiffness: 350 }}
            className="relative w-full max-h-[94vh] sm:max-h-[90vh] bg-[#f8fafc] rounded-2xl border border-slate-200 shadow-2xl flex flex-col z-10 overflow-hidden"
            style={{ maxWidth: `min(calc(100vw - 24px), ${width}px)` }}
          >
            {/* Header */}
            <header className="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-200/90 bg-white shrink-0 shadow-2xs z-10">
              <div className="flex-1 min-w-0">
                <h2 className="text-lg font-bold text-slate-900 tracking-tight truncate leading-tight">
                  {title}
                </h2>
                {subtitle && (
                  <div className="text-xs text-slate-500 font-medium mt-0.5 truncate">
                    {subtitle}
                  </div>
                )}
              </div>
              <div className="flex items-center gap-2">
                {actions}
                <button
                  type="button"
                  onClick={onClose}
                  aria-label="Close dialog"
                  className="p-2 rounded-xl text-slate-400 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer shrink-0"
                >
                  <X size={18} />
                </button>
              </div>
            </header>

            {/* Scrollable Content Body */}
            <div className="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 [scrollbar-width:thin]">
              {children}
            </div>

            {/* Optional Footer */}
            {footer && (
              <footer className="shrink-0 px-5 sm:px-6 py-3.5 border-t border-slate-200/90 bg-white flex items-center justify-between gap-3 shadow-2xs z-10">
                {footer}
              </footer>
            )}
          </motion.div>
        </div>
      )}
    </AnimatePresence>
  )
}
