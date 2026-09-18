import { Building2, Calendar, ChevronLeft, Menu, Search, ShoppingCart } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, Outlet, useLocation } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { useBranchStore } from '../lib/branch'
import { formatDate, todayIso } from '../lib/format'
import Sidebar from './Sidebar'
import { CommandPalette } from './ui/CommandPalette'

export default function AppLayout() {
  const location = useLocation()
  const isPos = location.pathname.startsWith('/sell/pos')
  const { data: user } = useCurrentUser()
  const activeBranchId = useBranchStore((s) => s.activeBranchId)
  const setActiveBranch = useBranchStore((s) => s.setActiveBranch)
  const [mobileNavOpen, setMobileNavOpen] = useState(false)
  const [cmdOpen, setCmdOpen] = useState(false)

  useEffect(() => {
    if (!user) return
    if (user.active_branch_id && user.active_branch_id !== activeBranchId) {
      const known = user.branches.some((b) => b.id === activeBranchId)
      if (!known) setActiveBranch(user.active_branch_id)
    }
  }, [user, activeBranchId, setActiveBranch])

  // Close mobile nav on route change
  useEffect(() => {
    setMobileNavOpen(false)
  }, [location.pathname])

  // Global Ctrl+K / ⌘K shortcut
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault()
        setCmdOpen((o) => !o)
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const activeBranch = user?.active_branch
  const today = todayIso()

  return (
    <div className="flex min-h-svh bg-[#f8fafc]">
      <Sidebar mobileOpen={mobileNavOpen} onMobileClose={() => setMobileNavOpen(false)} />

      <div className="flex-1 min-w-0 flex flex-col">
        <header className="h-16 border-b border-slate-200/70 bg-white/90 backdrop-blur-md px-4 sm:px-6 flex items-center justify-between sticky top-0 z-20 gap-3">

          {/* Left section */}
          <div className="flex items-center gap-3 flex-1 min-w-0">
            {/* Hamburger — mobile only, non-POS */}
            {!isPos && (
              <button
                type="button"
                onClick={() => setMobileNavOpen(true)}
                aria-label="Open navigation"
                className="lg:hidden p-2 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer shrink-0"
              >
                <Menu size={20} />
              </button>
            )}

            {isPos ? (
              <div className="flex items-center gap-3">
                <span className="text-[15px] font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                  PharmaPoint POS
                </span>
                <span className="text-[11px] font-bold px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-600 border border-blue-200/60">
                  Live Terminal
                </span>
              </div>
            ) : (
              /* Search bar — clicking opens the Command Palette */
              <button
                type="button"
                onClick={() => setCmdOpen(true)}
                className="w-full max-w-md flex items-center gap-2.5 h-9 pl-3.5 pr-3 rounded-xl bg-slate-50 border border-slate-200 text-left cursor-pointer hover:bg-slate-100 hover:border-slate-300 transition-colors group"
              >
                <Search size={15} className="text-slate-400 group-hover:text-slate-600 shrink-0 transition-colors" />
                <span className="text-[13px] text-slate-400 font-medium flex-1 truncate group-hover:text-slate-500 transition-colors">
                  Search medicines, customers, pages...
                </span>
                <span className="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded-md bg-white border border-slate-200 text-[10px] font-bold text-slate-400 shrink-0">
                  Ctrl+K
                </span>
              </button>
            )}
          </div>

          {/* Right section */}
          <div className="flex items-center gap-2 sm:gap-3 shrink-0">
            <span className="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-50 border border-slate-200/80 text-[12px] font-semibold text-slate-600 tabular">
              <Calendar size={13} className="text-blue-600" />
              {formatDate(today)}
            </span>

            {activeBranch && (
              <span className="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-blue-50/70 border border-blue-200/70 text-[12px] font-bold text-blue-700">
                <Building2 size={13} />
                <span className="hidden lg:inline">{activeBranch.code} · {activeBranch.name}</span>
                <span className="lg:hidden">{activeBranch.code}</span>
              </span>
            )}

            {isPos ? (
              <Link
                to="/dashboard"
                className="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-[12px] font-bold transition-colors cursor-pointer"
              >
                <ChevronLeft size={14} /> Exit POS
              </Link>
            ) : (
              <Link
                to="/sell/pos"
                className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-[12.5px] font-bold shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30 transition-all cursor-pointer"
              >
                <ShoppingCart size={15} />
                <span className="hidden sm:inline">Open POS</span>
              </Link>
            )}
          </div>
        </header>

        <main className="flex-1 min-w-0 overflow-y-auto">
          <Outlet />
        </main>
      </div>

      {/* Global Command Palette */}
      <CommandPalette open={cmdOpen} onClose={() => setCmdOpen(false)} />
    </div>
  )
}
