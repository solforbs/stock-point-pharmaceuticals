import { AlertTriangle, CheckCircle2, Info, X, XCircle } from 'lucide-react'
import { useToastStore, type ToastTone } from '../../lib/toast'

const toneColor: Record<ToastTone, string> = {
  error: 'var(--status-red)',
  success: 'var(--status-green)',
  info: 'var(--status-blue)',
  warning: 'var(--status-amber)',
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
            className="ui-card shadow-lg px-3.5 py-3 flex gap-2.5 items-start border-l-4"
            style={{ borderLeftColor: toneColor[t.tone] }}
          >
            <Icon size={16} style={{ color: toneColor[t.tone] }} className="shrink-0 mt-0.5" />
            <div className="flex-1 min-w-0">
              <div className="text-[12.5px] font-bold text-[var(--text)] break-words">{t.title}</div>
              {t.message && <div className="text-[11.5px] text-[var(--text-secondary)] mt-0.5 break-words">{t.message}</div>}
            </div>
            <button type="button" onClick={() => dismiss(t.id)} aria-label="Dismiss" className="text-[var(--text-muted)] hover:text-[var(--text)]">
              <X size={14} />
            </button>
          </div>
        )
      })}
    </div>
  )
}
