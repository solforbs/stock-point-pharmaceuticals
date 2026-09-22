import { useSyncExternalStore } from 'react'
import { useIsOffline } from '../lib/offline/connectivity'
import { usePendingCount } from '../lib/offline/outbox'

function subscribe(callback: () => void) {
  window.addEventListener('online', callback)
  window.addEventListener('offline', callback)
  return () => {
    window.removeEventListener('online', callback)
    window.removeEventListener('offline', callback)
  }
}

export function useOnline(): boolean {
  return useSyncExternalStore(subscribe, () => navigator.onLine, () => true)
}

/**
 * Part 16.7 — whether the server is answering, and how many sales this
 * device still holds. "Synchronized" only when both are true.
 */
export function SyncStatusChip({
  className = '',
  invert = false,
}: {
  className?: string
  invert?: boolean
}) {
  const offline = useIsOffline()
  const pending = usePendingCount()

  const label = offline
    ? pending > 0 ? `Offline · ${pending} queued` : 'Offline'
    : pending > 0 ? `Online · ${pending} to sync` : 'Online · Synchronized'
  const title = offline
    ? 'The server is not answering. The POS sells walk-in retail from its offline price list and queues the sales on this device.'
    : pending > 0 ? `${pending} offline ${pending === 1 ? 'sale is' : 'sales are'} still on this device, waiting to be sent.` : 'Connected to the server; nothing is waiting on this device.'

  if (invert) {
    return (
      <span
        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-white/20 text-white border border-white/30 backdrop-blur-xs ${className}`}
        title={title}
      >
        <span
          className={`w-2 h-2 rounded-full ${offline ? 'bg-rose-400' : pending > 0 ? 'bg-amber-300' : 'bg-emerald-300 animate-pulse'}`}
          aria-hidden
        />
        {label}
      </span>
    )
  }

  const color = offline ? 'var(--status-red)' : pending > 0 ? 'var(--status-amber)' : 'var(--status-green)'
  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border ${className}`}
      style={{ color, borderColor: color, background: `color-mix(in srgb, ${color} 10%, transparent)` }}
      title={title}
    >
      <span className="w-1.5 h-1.5 rounded-full" style={{ background: color }} aria-hidden />
      {label}
    </span>
  )
}
