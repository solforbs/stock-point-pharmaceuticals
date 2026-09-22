import { AnimatePresence, motion } from 'framer-motion'
import { Command, Keyboard, ShoppingCart, Sparkles, X } from 'lucide-react'
import { useEffect } from 'react'

export interface KeyboardShortcutsModalProps {
  open: boolean
  onClose: () => void
}

interface ShortcutItem {
  keys: string[]
  label: string
  description?: string
}

interface ShortcutSection {
  title: string
  icon: typeof Keyboard
  items: ShortcutItem[]
}

const SHORTCUT_SECTIONS: ShortcutSection[] = [
  {
    title: 'Global Navigation & Tools',
    icon: Command,
    items: [
      { keys: ['Ctrl', 'K'], label: 'Open Command & Medicine Search' },
      { keys: ['?'], label: 'Show this Keyboard Shortcuts Cheat Sheet' },
      { keys: ['F1'], label: 'Open Help & Shortcuts' },
      { keys: ['Esc'], label: 'Close active drawer, modal, or palette' },
    ],
  },
  {
    title: 'POS Live Terminal (Counter Mode)',
    icon: ShoppingCart,
    items: [
      { keys: ['F2'], label: 'Focus Medicine Search Input' },
      { keys: ['F3'], label: 'Edit Line Quantity' },
      { keys: ['F4'], label: 'Toggle Unit of Measure (Pack / Unit / Box)' },
      { keys: ['F5'], label: 'Select / Change Customer Tier' },
      { keys: ['F7'], label: 'Apply Authorized Line Discount' },
      { keys: ['F8'], label: 'Split Payment (Cash / M-Pesa / Card / Credit)' },
      { keys: ['F9'], label: 'Park / Hold Current Basket' },
      { keys: ['F10'], label: 'Retrieve Held / Parked Basket' },
      { keys: ['F12'], label: 'Complete Sale & Post eTIMS Invoice' },
    ],
  },
  {
    title: 'Guided Tour Navigation',
    icon: Sparkles,
    items: [
      { keys: ['→'], label: 'Next Tour Step' },
      { keys: ['←'], label: 'Previous Tour Step' },
      { keys: ['Esc'], label: 'Exit Guided Tour' },
    ],
  },
]

export function KeyboardShortcutsModal({ open, onClose }: KeyboardShortcutsModalProps) {
  useEffect(() => {
    if (!open) return
    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        onClose()
      }
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [open, onClose])

  if (!open) return null

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
        {/* Backdrop */}
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          className="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"
          onClick={onClose}
        />

        {/* Modal Window */}
        <motion.div
          initial={{ opacity: 0, scale: 0.96, y: 8 }}
          animate={{ opacity: 1, scale: 1, y: 0 }}
          exit={{ opacity: 0, scale: 0.96, y: 8 }}
          transition={{ duration: 0.18 }}
          className="relative z-10 w-full max-w-2xl bg-white rounded-3xl shadow-2xl border border-slate-200/90 overflow-hidden flex flex-col max-h-[85vh]"
        >
          {/* Header */}
          <div className="px-6 py-4.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
            <div className="flex items-center gap-2.5">
              <div className="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                <Keyboard size={18} />
              </div>
              <div>
                <h2 className="text-base font-extrabold text-slate-900">
                  Keyboard Shortcuts Cheat Sheet
                </h2>
                <p className="text-xs text-slate-500">
                  Accelerate daily pharmacy operations with instant hotkeys
                </p>
              </div>
            </div>
            <button
              onClick={onClose}
              aria-label="Close shortcuts modal"
              className="p-1.5 rounded-xl text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
            >
              <X size={18} />
            </button>
          </div>

          {/* Body */}
          <div className="p-6 overflow-y-auto space-y-6 [scrollbar-width:none]">
            {SHORTCUT_SECTIONS.map((sec) => {
              const Icon = sec.icon
              return (
                <div key={sec.title} className="space-y-2.5">
                  <div className="flex items-center gap-2 text-xs font-bold text-slate-800 uppercase tracking-wider">
                    <Icon size={14} className="text-blue-600" />
                    <span>{sec.title}</span>
                  </div>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    {sec.items.map((item, idx) => (
                      <div
                        key={idx}
                        className="flex items-center justify-between gap-3 p-2.5 rounded-xl bg-slate-50/80 border border-slate-150 hover:bg-white hover:border-slate-300 hover:shadow-2xs transition-all"
                      >
                        <span className="text-xs font-medium text-slate-700">
                          {item.label}
                        </span>
                        <div className="flex items-center gap-1 shrink-0">
                          {item.keys.map((k, ki) => (
                            <kbd
                              key={ki}
                              className="px-2 py-1 rounded-lg bg-white border border-slate-200 text-slate-800 text-xs font-bold font-mono shadow-2xs"
                            >
                              {k}
                            </kbd>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              )
            })}
          </div>

          {/* Footer */}
          <div className="px-6 py-3.5 border-t border-slate-100 bg-slate-50/60 flex items-center justify-between text-xs text-slate-500">
            <span>Tip: Press <kbd className="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-slate-700 font-mono font-bold">?</kbd> anywhere in PharmaPoint to view this window</span>
            <button
              onClick={onClose}
              className="px-3.5 py-1.5 rounded-xl bg-slate-900 text-white font-bold text-xs hover:bg-slate-800 transition-colors cursor-pointer"
            >
              Got it
            </button>
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  )
}
