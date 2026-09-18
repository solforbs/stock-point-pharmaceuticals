import { useCurrentUser } from '../hooks/useCurrentUser'

/**
 * Part 16.5 — permissions gate rendering for usability only. Hidden buttons
 * are not security; the server checks every request.
 */
export function usePermission(name: string): boolean {
  const { data: user } = useCurrentUser()
  return !!user?.permissions?.includes(name)
}

export function usePermissions(): Set<string> {
  const { data: user } = useCurrentUser()
  return new Set(user?.permissions ?? [])
}
