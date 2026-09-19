import { useQuery } from '@tanstack/react-query'
import axios from 'axios'
import { api } from '../lib/api'
import { isUnreachableError } from '../lib/offline/connectivity'
import type { CurrentUser } from '../lib/types'

const CACHE_KEY = 'last-signed-in-user'

function remember(user: CurrentUser) {
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify(user))
  } catch {
    // only needed to reopen the till during an outage
  }
}

function recall(): CurrentUser | null {
  try {
    const raw = localStorage.getItem(CACHE_KEY)
    return raw ? (JSON.parse(raw) as CurrentUser) : null
  } catch {
    return null
  }
}

/** Called on sign-out, so a shared till never reopens as the last cashier. */
export function forgetCachedUser() {
  try {
    localStorage.removeItem(CACHE_KEY)
  } catch {
    // ignore
  }
}

/**
 * The signed-in user. Part 16.7 — when the server cannot be reached at all,
 * the last user seen on this device is used instead, so a till reloaded in
 * an outage can still sell offline. It grants nothing: every request is
 * still checked against the session by the server.
 */
export function useCurrentUser() {
  return useQuery({
    queryKey: ['auth', 'user'],
    queryFn: async () => {
      try {
        const { data } = await api.get<CurrentUser>('/api/user')
        remember(data)
        return data
      } catch (error) {
        const cached = isUnreachableError(error) ? recall() : null
        if (cached) return cached
        if (axios.isAxiosError(error) && error.response?.status === 401) forgetCachedUser()
        throw error
      }
    },
    retry: false,
  })
}
