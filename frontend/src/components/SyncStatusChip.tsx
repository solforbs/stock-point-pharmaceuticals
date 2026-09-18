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

export function SyncStatusChip({
  className = '',
  invert = false,
}: {
  className?: string
  invert?: boolean
}) {
  const online = useOnline()

  if (invert) {
    return (
      <span
        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-white/20 text-white border border-white/30 backdrop-blur-xs ${className}`}
        title={online ? 'Connected to local/remote server' : 'Offline'}
      >
        <span
          className={`w-2 h-2 rounded-full ${online ? 'bg-emerald-300 animate-pulse' : 'bg-rose-400'}`}
          aria-hidden
        />
        {online ? 'Online · Synchronized' : 'Offline'}
      </span>
    )
  }

  const color = online ? 'var(--status-green)' : 'var(--status-red)'
  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold border ${className}`}
      style={{ color, borderColor: color, background: `color-mix(in srgb, ${color} 10%, transparent)` }}
      title={online ? 'Connected to the server' : 'No connection — sales cannot post until the server is reachable'}
    >
      <span className="w-1.5 h-1.5 rounded-full" style={{ background: color }} aria-hidden />
      {online ? 'Online · Synchronized' : 'Offline'}
    </span>
  )
}
