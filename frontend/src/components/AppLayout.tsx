import { useEffect } from 'react'
import { Outlet } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { useBranchStore } from '../lib/branch'
import Sidebar from './Sidebar'

export default function AppLayout() {
  const { data: user } = useCurrentUser()
  const activeBranchId = useBranchStore((s) => s.activeBranchId)
  const setActiveBranch = useBranchStore((s) => s.setActiveBranch)

  // Keep the locally remembered branch honest: the server already ignored
  // any X-Branch-Id the user is not assigned to, so mirror what it resolved.
  useEffect(() => {
    if (!user) return
    if (user.active_branch_id && user.active_branch_id !== activeBranchId) {
      const known = user.branches.some((b) => b.id === activeBranchId)
      if (!known) setActiveBranch(user.active_branch_id)
    }
  }, [user, activeBranchId, setActiveBranch])

  return (
    <div className="flex min-h-svh bg-[var(--bg)]">
      <Sidebar />
      <main className="flex-1 min-w-0 flex flex-col">
        <Outlet />
      </main>
    </div>
  )
}
