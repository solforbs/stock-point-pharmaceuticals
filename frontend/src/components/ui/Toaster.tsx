import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-react'
import { useToastStore, type ToastTone } from '../../lib/toast'

const toneBorder: Record<ToastTone, string> = {
  error: 'border-l-rose-500',
  success: 'border-l-emerald-500',
  info: 'border-l-sky-500',
  warning: 'border-l-amber-500',
}

const toneIconColor: Record<ToastTone, string> = {
  error: 'text-rose-500',
  success: 'text-emerald-500',
  info: 'text-sky-500',
  warning: 'text-amber-500',
}

const toneIcon: Record<ToastTone, typeof Info> = { error: XCircle, success: CheckCircle2, info: Info, warning: AlertTriangle }

export function Toaster() {
  const toasts = useToastStore((s) => s.toasts)
  const dismiss = useToastStore((s) => s.dismiss)
  if (toasts.length === 0) return null
  return (
    <div className="fixed bottom-4 right-4 z-[60] flex flex-col gap-2 w-[360px] max-w-[calc(100vw-32px)]">
      {toasts.map((t) => {
        const Icon = toneIcon[t.tone]
        return (
          <div
            key={t.id}
            role="status"
            className={`bg-white rounded-lg border border-slate-200 shadow-lg px-3.5 py-3 flex gap-2.5 items-start border-l-4 ${toneBorder[t.tone]}`}
          >
            <Icon size={16} className={`shrink-0 mt-0.5 ${toneIconColor[t.tone]}`} />
            <div className="flex-1 min-w-0">
              <div className="text-sm font-semibold text-slate-800 break-words">{t.title}</div>
              {t.message && <div className="text-xs text-slate-500 mt-0.5 break-words">{t.message}</div>}
            </div>
            <button type="button" onClick={() => dismiss(t.id)} aria-label="Dismiss" className="text-slate-400 hover:text-slate-600 transition-colors">
              <X size={14} />
            </button>
          </div>
        )
      })}
    </div>
  )
}
