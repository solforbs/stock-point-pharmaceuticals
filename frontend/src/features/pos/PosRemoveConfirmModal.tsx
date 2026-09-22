import { Trash2, X } from 'lucide-react'
import { useEffect } from 'react'
import type { CartLine } from './cartStore'
import { formatMoney } from '../../lib/money'

export interface PosRemoveConfirmModalProps {
  open: boolean
  line: CartLine | null
  onConfirm: () => void
  onCancel: () => void
}

export function PosRemoveConfirmModal({
  open,
  line,
  onConfirm,
  onCancel,
}: PosRemoveConfirmModalProps) {
  useEffect(() => {
    if (!open) return
    function handleKey(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        e.preventDefault()
        e.stopPropagation()
        onCancel()
      } else if (e.key === 'Enter') {
        e.preventDefault()
        e.stopPropagation()
        onConfirm()
      }
    }
    window.addEventListener('keydown', handleKey)
    return () => window.removeEventListener('keydown', handleKey)
  }, [open, onCancel, onConfirm])

  if (!open || !line) return null

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="remove-item-title"
      className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs animate-in fade-in duration-200"
      onClick={onCancel}
    >
      <div
        onClick={(e) => e.stopPropagation()}
        className="relative bg-white rounded-3xl p-6 shadow-2xl border border-slate-100 max-w-sm w-full mx-4 text-center animate-in zoom-in-95 duration-200"
      >
        {/* Close Button */}
        <button
          type="button"
          onClick={onCancel}
          aria-label="Close dialog"
          className="absolute top-4 right-4 p-1.5 rounded-full text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
        >
          <X size={16} />
        </button>

        {/* Animated Dustbin Visual */}
        <div className="relative w-18 h-18 mx-auto mb-4 rounded-3xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 shadow-inner group">
          {/* Subtle animated pulsing halo */}
          <div className="absolute inset-0 rounded-3xl bg-rose-400/10 animate-ping pointer-events-none" />

          {/* Custom SVG Dustbin with spring-animated open lid */}
          <svg
            viewBox="0 0 48 48"
            className="w-10 h-10 overflow-visible transition-transform duration-200 group-hover:scale-105"
          >
            <style>{`
              @keyframes dustbinLidSpring {
                0% { transform: rotate(0deg) translateY(0); }
                35% { transform: rotate(-28deg) translateY(-5px); }
                65% { transform: rotate(-18deg) translateY(-3px); }
                100% { transform: rotate(-22deg) translateY(-4px); }
              }
              .dustbin-animated-lid {
                transform-origin: 8px 14px;
                animation: dustbinLidSpring 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
              }
              .group:hover .dustbin-animated-lid {
                transform: rotate(-32deg) translateY(-7px) !important;
                transition: transform 0.2s ease;
              }
            `}</style>

            {/* Bin Body */}
            <path
              d="M12 16h24l-2.4 23.2A3 3 0 0 1 30.6 42H17.4a3 3 0 0 1-2.98-2.8L12 16z"
              fill="#e11d48"
              className="drop-shadow-xs"
            />
            {/* Bin Inner Stripes */}
            <line x1="20" y1="21" x2="19.5" y2="36" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" opacity="0.7" />
            <line x1="24" y1="21" x2="24" y2="36" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" opacity="0.7" />
            <line x1="28" y1="21" x2="28.5" y2="36" stroke="#ffffff" strokeWidth="2" strokeLinecap="round" opacity="0.7" />

            {/* Animated Lid (Handle + Plate) */}
            <g className="dustbin-animated-lid">
              {/* Handle */}
              <rect x="20" y="7" width="8" height="3" rx="1.5" fill="#be123c" />
              {/* Plate */}
              <path
                d="M9 11h30a2 2 0 0 1 2 2v2H7v-2a2 2 0 0 1 2-2z"
                fill="#f43f5e"
              />
            </g>
          </svg>
        </div>

        {/* Modal Title */}
        <h3 id="remove-item-title" className="text-base font-extrabold text-slate-900 mb-1">
          Remove item from cart?
        </h3>
        <p className="text-xs text-slate-500 mb-4">
          This medicine will be removed from the current sale.
        </p>

        {/* Drug Item Details Card */}
        <div className="p-3.5 rounded-2xl bg-slate-50 border border-slate-200/70 text-left mb-5 shadow-2xs">
          <div className="flex items-start justify-between gap-2 mb-1.5">
            <h4 className="font-bold text-slate-900 text-xs leading-snug line-clamp-2">
              {line.productName}
            </h4>
            <span className="text-[11px] font-mono text-slate-500 font-semibold shrink-0">
              #{line.productCode}
            </span>
          </div>

          <div className="flex items-center justify-between text-xs pt-1.5 border-t border-slate-200/50">
            <span className="text-slate-600 font-medium">
              Qty: <strong className="text-slate-800 font-bold">{line.qty} {line.uomCode}</strong>
            </span>
            <span className="text-slate-900 font-bold tabular">
              KES {formatMoney(line.localEstimate)}
            </span>
          </div>
        </div>

        {/* Action Buttons */}
        <div className="flex items-center gap-2.5">
          <button
            type="button"
            onClick={onCancel}
            className="flex-1 h-10 px-4 rounded-full bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-700 font-bold text-xs transition-all cursor-pointer flex items-center justify-center gap-1.5 shadow-2xs"
          >
            <span>Cancel</span>
            <kbd className="hidden sm:inline-block px-1.5 py-0.2 rounded bg-white text-[10px] font-mono text-slate-500 border border-slate-200">
              Esc
            </kbd>
          </button>

          <button
            type="button"
            onClick={onConfirm}
            autoFocus
            className="flex-1 h-10 px-4 rounded-full bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 active:scale-[0.98] text-white font-bold text-xs shadow-md shadow-rose-600/25 transition-all cursor-pointer flex items-center justify-center gap-1.5"
          >
            <Trash2 size={13} />
            <span>Remove</span>
            <kbd className="hidden sm:inline-block px-1.5 py-0.2 rounded bg-white/20 text-[10px] font-mono text-white">
              ↵
            </kbd>
          </button>
        </div>
      </div>
    </div>
  )
}
