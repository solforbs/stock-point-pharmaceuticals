import { useSyncExternalStore } from 'react'

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
 * Part 17.5 — the persistent online/offline chip. No offline queue is built
 * yet, so the pending count is always 0; the chip is here so the POS shows
 * connectivity honestly and the Sync Centre has somewhere to hang off.
 */
export function SyncStatusChip({ className = '' }: { className?: string }) {
  const online = useOnline()
  const color = online ? 'var(--status-green)' : 'var(--status-red)'
  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border ${className}`}
      style={{ color, borderColor: color, background: `color-mix(in srgb, ${color} 10%, transparent)` }}
      title={online ? 'Connected to the server' : 'No connection — sales cannot post until the server is reachable'}
    >
      <span className="w-1.5 h-1.5 rounded-full" style={{ background: color }} aria-hidden />
      {online ? 'Online · 0 pending' : 'Offline'}
    </span>
  )
}
