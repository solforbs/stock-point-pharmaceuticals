import type { ReactNode } from 'react'
import { Navigate, useLocation } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'

export default function ProtectedRoute({ children }: { children: ReactNode }) {
  // Gate directly on the query's own state (same render, no lag) rather than
  // on a store mirror updated a render later via useEffect — that lag was
  // enough to flash-redirect to /login before the store caught up.
  const { data: user, isLoading, isError } = useCurrentUser()
  const location = useLocation()

  if (isLoading) {
    return <div className="min-h-svh flex items-center justify-center text-slate-400 text-sm">Loading…</div>
  }

  if (isError || !user) {
    return <Navigate to="/login" replace state={{ from: location.pathname + location.search }} />
  }

  // A platform-only account belongs to no institution: its home is the console.
  if (!user.organisation && user.is_platform_admin && location.pathname !== '/platform') {
    return <Navigate to="/platform" replace />
  }

  return children
}
