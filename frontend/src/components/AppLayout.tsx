import { Building2, Calendar, Search, ShoppingCart } from 'lucide-react'
import { useEffect } from 'react'
import { Link, Outlet } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { useBranchStore } from '../lib/branch'
import { formatDate, todayIso } from '../lib/format'
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
  const today = todayIso()

  return (
    <div className="flex min-h-svh bg-[#f8fafc]">
      <Sidebar />
      <div className="flex-1 min-w-0 flex flex-col">
        <header className="h-16 border-b border-slate-200/70 bg-white/90 backdrop-blur-md px-6 flex items-center justify-between sticky top-0 z-20">
          <div className="flex items-center gap-3 flex-1 max-w-md">
            <div className="relative w-full">
              <Search
                size={16}
                className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
              />
              <input
                type="text"
                placeholder="Search medicines, batches, customers..."
                className="w-full h-9 pl-10 pr-12 rounded-xl bg-slate-50 border border-slate-200 text-[12.5px] text-slate-800 placeholder:text-slate-400 focus:outline-none focus:border-blue-500 transition-colors"
              />
              <span className="absolute right-2.5 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-white border border-slate-200 text-[10px] font-bold text-slate-400 pointer-events-none tabular">
                Ctrl+K
              </span>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <span className="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200/80 text-[12px] font-semibold text-slate-600 tabular">
              <Calendar size={13} className="text-blue-600" />
              {formatDate(today)}
            </span>

            {activeBranch && (
              <span className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50/70 border border-blue-200/70 text-[12px] font-bold text-blue-700">
                <Building2 size={13} />
                {activeBranch.code} · {activeBranch.name}
              </span>
            )}

            <Link
              to="/sell/pos"
              className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-[12.5px] font-bold shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30 transition-all cursor-pointer"
            >
              <ShoppingCart size={15} />
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
