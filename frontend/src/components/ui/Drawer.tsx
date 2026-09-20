import { AnimatePresence, motion } from 'framer-motion'
import { X } from 'lucide-react'
import { useEffect, type ReactNode } from 'react'

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

  return (
    <AnimatePresence>
      {open && (
        <div className="fixed inset-0 z-50 flex justify-end overflow-hidden">
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.2 }}
            className="fixed inset-0 bg-slate-900/50 backdrop-blur-xs"
            onClick={onClose}
            aria-hidden
          />

          {/* Slide-over Aside Panel */}
          <motion.aside
            role="dialog"
            aria-modal="true"
            initial={{ x: '100%' }}
            animate={{ x: 0 }}
            exit={{ x: '100%' }}
            transition={{ type: 'spring', damping: 30, stiffness: 300 }}
            className="relative h-full w-full bg-[#f8fafc] border-l border-slate-200/90 shadow-2xl flex flex-col z-10 overflow-hidden"
            style={{ maxWidth: `min(100vw, ${width}px)` }}
          >
            {/* Header */}
            <header className="flex items-center justify-between gap-3 px-5 sm:px-6 py-4 border-b border-slate-200/90 bg-white/95 backdrop-blur-md shrink-0 shadow-2xs z-10">
              <div className="flex-1 min-w-0">
                <h2 className="text-lg font-black text-slate-900 tracking-tight truncate leading-tight">
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
                  aria-label="Close drawer"
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
              <footer className="shrink-0 px-5 sm:px-6 py-3.5 border-t border-slate-200/90 bg-white/95 backdrop-blur-md flex items-center justify-between gap-3 shadow-2xs z-10">
                {footer}
              </footer>
            )}
          </motion.aside>
        </div>
      )}
    </AnimatePresence>
  )
}
