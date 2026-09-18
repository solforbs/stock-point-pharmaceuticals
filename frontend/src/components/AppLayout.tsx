import { Building2, ShoppingCart } from 'lucide-react'
import { useEffect } from 'react'
import { Link, Outlet } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { useBranchStore } from '../lib/branch'
import Sidebar from './Sidebar'

export default function AppLayout() {
  const { data: user } = useCurrentUser()
  const activeBranchId = useBranchStore((s) => s.activeBranchId)
  const setActiveBranch = useBranchStore((s) => s.setActiveBranch)

  useEffect(() => {
    if (!user) return
    if (user.active_branch_id && user.active_branch_id !== activeBranchId) {
      const known = user.branches.some((b) => b.id === activeBranchId)
      if (!known) setActiveBranch(user.active_branch_id)
    }
  }, [user, activeBranchId, setActiveBranch])

  const activeBranch = user?.active_branch

  return (
    <div className="flex min-h-svh bg-[var(--bg)]">
      <Sidebar />
      <div className="flex-1 min-w-0 flex flex-col">
        <header className="h-14 border-b border-[var(--border)] bg-[var(--card)]/80 backdrop-blur-md px-6 flex items-center justify-between sticky top-0 z-20">
          <div className="flex items-center gap-2">
            {activeBranch && (
              <span className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[var(--surface-2)] text-[var(--text)] border border-[var(--border)] text-[11.5px] font-bold">
                <Building2 size={13} className="text-[var(--color-navy)]" />
                {activeBranch.code} · {activeBranch.name}
              </span>
            )}
          </div>

          <div className="flex items-center gap-3">
            <Link
              to="/sell/pos"
              className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[var(--color-navy)] text-white text-[12px] font-bold shadow-xs hover:opacity-90 transition-opacity"
            >
              <ShoppingCart size={14} />
              Open POS
            </Link>
          </div>
        </header>

        <main className="flex-1 min-w-0 overflow-y-auto">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
