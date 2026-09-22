import type { ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'

export default function ProtectedRoute({ children }: { children: ReactNode }) {
  // Gate directly on the query's own state (same render, no lag) rather than
  // on a store mirror updated a render later via useEffect — that lag was
  // enough to flash-redirect to /login before the store caught up.
  const { data: user, isLoading, isError } = useCurrentUser()

  if (isLoading) {
    return <div className="min-h-svh flex items-center justify-center text-slate-400 text-sm">Loading…</div>
  }

  if (isError || !user) {
    return <Navigate to="/login" replace />
  }

  return children
}
