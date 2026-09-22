import { Building2, Calendar, ChevronLeft, Compass, HelpCircle, Menu, Search, ShoppingCart } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, Outlet, useLocation } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { useBranchStore } from '../lib/branch'
import { formatDate, todayIso } from '../lib/format'
import { useOutboxReplayer } from '../lib/offline/useOutboxReplayer'
import { AlertBell } from './AlertBell'
import { MessagesBell } from './MessagesBell'
import Sidebar from './Sidebar'
import { ProductTour } from './tour/ProductTour'
import { TourPromptBanner } from './tour/TourPromptBanner'
import { useTourStore } from './tour/useTourStore'
import { CommandPalette } from './ui/CommandPalette'
import { KeyboardShortcutsModal } from './ui/KeyboardShortcutsModal'
import { OfflineBanner } from './ui/OfflineBanner'

export default function AppLayout() {
  const location = useLocation()
  const isPos = location.pathname.startsWith('/sell/pos')
  const { data: user } = useCurrentUser()
  const activeBranchId = useBranchStore((s) => s.activeBranchId)
  useOutboxReplayer(user?.id)
  const setActiveBranch = useBranchStore((s) => s.setActiveBranch)
  const [mobileNavOpen, setMobileNavOpen] = useState(false)
  const [cmdOpen, setCmdOpen] = useState(false)
  const [shortcutsOpen, setShortcutsOpen] = useState(false)
  const startTourForRoute = useTourStore((s) => s.startTourForRoute)

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

  // Global hotkeys: Ctrl+K (Search), ? / F1 (Shortcuts)
  useEffect(() => {
    function onKey(e: KeyboardEvent) {
      const target = e.target as HTMLElement
      const isInput = target?.tagName === 'INPUT' || target?.tagName === 'TEXTAREA' || target?.isContentEditable

      if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault()
        setCmdOpen((o) => !o)
      } else if (e.key === 'F1') {
        e.preventDefault()
        setShortcutsOpen((o) => !o)
      } else if (e.key === '?' && !isInput && !e.ctrlKey && !e.altKey && !e.metaKey) {
        e.preventDefault()
        setShortcutsOpen((o) => !o)
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const activeBranch = user?.active_branch
  const today = todayIso()

  return (
    <div className={`flex ${isPos ? 'h-screen max-h-screen overflow-hidden' : 'min-h-svh'} bg-[#f8fafc]`}>
      <Sidebar mobileOpen={mobileNavOpen} onMobileClose={() => setMobileNavOpen(false)} />

      <div className={`flex-1 min-w-0 flex flex-col ${isPos ? 'h-full max-h-full overflow-hidden' : ''}`}>
        <header className="h-14 border-b border-slate-200 bg-white/95 backdrop-blur-md px-4 sm:px-6 flex items-center justify-between sticky top-0 z-20 gap-3 shrink-0">

          {/* Left section */}
          <div className="flex items-center gap-3 flex-1 min-w-0">
            {/* Hamburger — mobile only, non-POS */}
            {!isPos && (
              <button
                type="button"
                onClick={() => setMobileNavOpen(true)}
                aria-label="Open navigation"
                className="lg:hidden p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 transition-colors cursor-pointer shrink-0"
              >
                <Menu size={18} />
              </button>
            )}

            {isPos ? (
              <div className="flex items-center gap-3">
                <span className="font-display text-[15px] font-extrabold text-slate-900 tracking-tight flex items-center gap-2">
                  PharmaPoint POS
                </span>
                <span className="text-xs font-medium px-2 py-0.5 rounded-md bg-emerald-50 text-emerald-800 border border-emerald-200/70">
                  Live Terminal
                </span>
              </div>
            ) : (
              /* Search bar — clicking opens the Command Palette */
              <button
                id="tour-search"
                type="button"
                onClick={() => setCmdOpen(true)}
                className="w-full max-w-md flex items-center gap-2 h-8.5 pl-3 pr-2.5 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-200 text-left cursor-pointer transition-colors group"
              >
                <Search size={14} className="text-slate-400 group-hover:text-slate-600 shrink-0 transition-colors" />
                <span className="text-sm text-slate-500 font-normal flex-1 truncate group-hover:text-slate-700 transition-colors">
                  Search medicines, customers, pages...
                </span>
                <span className="hidden sm:inline-flex items-center px-1.5 py-0.5 rounded bg-white border border-slate-200 text-xs font-semibold text-slate-400 shrink-0">
                  Ctrl+K
                </span>
              </button>
            )}
          </div>

          {/* Right section */}
          <div className="flex items-center gap-2 sm:gap-2.5 shrink-0">
            {/* Interactive Guided Tour Button */}
            <button
              type="button"
              onClick={() => startTourForRoute(location.pathname)}
              title="Start Guided Product Tour"
              className="hidden md:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 border border-slate-200 transition-all text-xs font-medium cursor-pointer"
            >
              <Compass size={14} className="text-slate-500" />
              <span>Tour</span>
            </button>

            <MessagesBell />
            <AlertBell />

            {/* Keyboard Shortcuts Help Button */}
            <button
              type="button"
              onClick={() => setShortcutsOpen(true)}
              title="Keyboard Shortcuts (?)"
              aria-label="Keyboard Shortcuts"
              className="p-1.5 rounded-lg text-slate-500 hover:text-slate-800 hover:bg-slate-100 border border-transparent hover:border-slate-200 transition-colors cursor-pointer"
            >
              <HelpCircle size={16} />
            </button>

            <span className="hidden lg:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-50 border border-slate-200 text-xs font-medium text-slate-600 tabular">
              <Calendar size={13} className="text-slate-400" />
              {formatDate(today)}
            </span>

            {activeBranch && (
              <span className="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-xs font-semibold text-slate-700">
                <Building2 size={13} className="text-slate-500" />
                <span className="hidden xl:inline">{activeBranch.code} · {activeBranch.name}</span>
                <span className="xl:hidden">{activeBranch.code}</span>
              </span>
            )}

            {isPos ? (
              <Link
                to="/dashboard"
                className="inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition-colors cursor-pointer"
              >
                <ChevronLeft size={14} /> Exit POS
              </Link>
            ) : (
              <Link
                id="tour-pos-button"
                to="/sell/pos"
                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold shadow-xs transition-all cursor-pointer"
              >
                <ShoppingCart size={14} />
                <span className="hidden sm:inline">Open POS</span>
              </Link>
            )}
          </div>
        </header>

        {/* Dynamic Guided Tour Prompt Banner (shows if not dismissed in this session) */}
        <TourPromptBanner />

        <main className={`flex-1 min-w-0 ${isPos ? 'flex flex-col min-h-0 overflow-hidden' : 'overflow-y-auto'}`}>
          <Outlet />
        </main>
      </div>

      {/* Global Command Palette */}
      <CommandPalette open={cmdOpen} onClose={() => setCmdOpen(false)} />

      {/* Global Keyboard Shortcuts Cheat Sheet */}
      <KeyboardShortcutsModal open={shortcutsOpen} onClose={() => setShortcutsOpen(false)} />

      {/* Interactive Guided Product Tour */}
      <ProductTour />

      {/* Global Offline / Online Status Guardian */}
      <OfflineBanner />
    </div>
  )
}
