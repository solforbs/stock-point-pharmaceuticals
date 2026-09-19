import { useQueryClient } from '@tanstack/react-query'
import { useEffect } from 'react'
import { toast } from '../toast'
import { useIsOffline } from './connectivity'
import { pruneSynced, replayOutbox } from './outbox'

/** A safety net for a reconnect the browser never announced. */
const RETRY_MS = 60_000

/**
 * Part 16.7 — sends this user's waiting offline sales as soon as the server
 * answers again, and keeps trying every minute while any are left.
 */
export function useOutboxReplayer(userId: number | null | undefined) {
  const offline = useIsOffline()
  const queryClient = useQueryClient()

  useEffect(() => {
    if (!userId || offline) return

    const run = async () => {
      const taken = await replayOutbox(userId)
      if (taken > 0) {
        toast.success(`${taken} offline ${taken === 1 ? 'sale' : 'sales'} synced`, 'Check the Sync Centre for any that need a supervisor.')
        for (const key of ['sales', 'inventory', 'products', 'offline-sales']) {
          void queryClient.invalidateQueries({ queryKey: [key] })
        }
      }
    }

    void run()
    void pruneSynced()
    const timer = window.setInterval(() => void run(), RETRY_MS)
    return () => window.clearInterval(timer)
  }, [userId, offline, queryClient])
}
