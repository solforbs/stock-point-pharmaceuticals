import axios from 'axios'
import { useSyncExternalStore } from 'react'
import { create } from 'zustand'

/**
 * Whether this browser can reach the server right now. The browser's own
 * online flag only knows about the local network; a shop can have Wi-Fi and
 * no internet, so a request that gets no answer marks the server
 * unreachable, and a probe of /up marks it reachable again.
 */
type ConnectivityState = {
  serverReachable: boolean
  markReachable: () => void
  markUnreachable: () => void
}

const PROBE_MS = 10_000

let probeTimer: number | null = null

export const useConnectivityStore = create<ConnectivityState>((set, get) => ({
  serverReachable: true,
  markReachable: () => {
    if (!get().serverReachable) set({ serverReachable: true })
    stopProbe()
  },
  markUnreachable: () => {
    if (get().serverReachable) set({ serverReachable: false })
    startProbe()
  },
}))

function startProbe() {
  if (probeTimer !== null) return
  probeTimer = window.setInterval(() => void probe(), PROBE_MS)
}

function stopProbe() {
  if (probeTimer === null) return
  window.clearInterval(probeTimer)
  probeTimer = null
}

/** Laravel's health route: cheap, unauthenticated, and only answers if PHP does. */
export async function probe(): Promise<boolean> {
  try {
    const res = await fetch('/up', { cache: 'no-store', credentials: 'same-origin' })
    if (res.ok) {
      useConnectivityStore.getState().markReachable()
      return true
    }
  } catch {
    // still unreachable
  }
  return false
}

/**
 * No response at all (dropped connection, DNS, timeout), or a gateway saying
 * the application behind it is down. A 4xx/5xx from Laravel itself is an
 * answer, not an outage.
 */
export function isUnreachableError(error: unknown): boolean {
  if (!axios.isAxiosError(error)) return false
  if (error.code === 'ERR_CANCELED') return false
  if (!error.response) return true
  return [502, 503, 504].includes(error.response.status)
}

function subscribe(callback: () => void) {
  window.addEventListener('online', callback)
  window.addEventListener('offline', callback)
  return () => {
    window.removeEventListener('online', callback)
    window.removeEventListener('offline', callback)
  }
}

/** True when sales must go to the outbox instead of the server. */
export function useIsOffline(): boolean {
  const browserOnline = useSyncExternalStore(subscribe, () => navigator.onLine, () => true)
  const serverReachable = useConnectivityStore((s) => s.serverReachable)
  return !browserOnline || !serverReachable
}

export function isOfflineNow(): boolean {
  return !navigator.onLine || !useConnectivityStore.getState().serverReachable
}
