import { Activity, ChevronUp, ChevronsLeft, ChevronsRight, Pill, X } from 'lucide-react'
import { AnimatePresence, motion } from 'framer-motion'
import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { NAV_ITEMS, withPlatformItems, type NavItem } from '../lib/navigation'
import { SyncStatusChip } from './SyncStatusChip'
import { SidebarBranchSelector } from './navigation/SidebarBranchSelector'
import { SidebarNavGroup } from './navigation/SidebarNavGroup'
import { SidebarUserProfile } from './navigation/SidebarUserProfile'

const COLLAPSE_KEY = 'sidebar-collapsed'

function readInitialCollapsed() {
  try {
    return localStorage.getItem(COLLAPSE_KEY) === '1'
  } catch {
    return false
  }
}

const SECTIONS: { title?: string; items: NavItem[] }[] = [
  { items: NAV_ITEMS.slice(0, 1) }, // Dashboard
  { title: 'Commerce & Stock', items: NAV_ITEMS.slice(1, 5) }, // Sales, Inventory, Procurement, Warehouse
  { title: 'Finance & Quality', items: NAV_ITEMS.slice(5, 8) }, // Customers, Finance, Quality
  { title: 'Management', items: NAV_ITEMS.slice(8, 11) }, // Reports, People, Admin
]

/** The inner sidebar content — shared between desktop pinned and mobile off-canvas */
function SidebarInner({
  collapsed,
  toggleCollapsed,
  onLinkClick,
}: {
  collapsed: boolean
  toggleCollapsed: () => void
  onLinkClick?: () => void
}) {
  const [showStatusPopup, setShowStatusPopup] = useState(false)
  const location = useLocation()
  const { data: user } = useCurrentUser()

  const activeGroupKey =
    NAV_ITEMS.find((item) => item.children?.length && location.pathname.startsWith(item.path))?.key ?? null
  const [openKey, setOpenKey] = useState<string | null>(activeGroupKey)
  const [seenGroupKey, setSeenGroupKey] = useState<string | null>(activeGroupKey)

  if (activeGroupKey !== seenGroupKey) {
    setSeenGroupKey(activeGroupKey)
    if (activeGroupKey) setOpenKey(activeGroupKey)
  }

  function toggleGroup(key: string) {
    if (collapsed) return
    setOpenKey((prev) => (prev === key ? null : key))
  }

  return (
    <>
      <header className="h-14 flex items-center justify-between px-4 shrink-0 border-b border-slate-100">
        {!collapsed && (
          <div className="flex items-center gap-2.5 min-w-0">
            <div className="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white shadow-xs">
              <Pill size={16} />
            </div>
            <div className="min-w-0 leading-tight">
              <div className="font-display font-extrabold tracking-tight text-[15px] text-slate-900 truncate">PharmaPoint</div>
              {user?.organisation && (
                <div className="text-[11px] font-semibold text-slate-500 truncate" title={user.organisation.legal_name ?? user.organisation.name}>
                  {user.organisation.name}
                </div>
              )}
            </div>
          </div>
        )}
        <div className="flex items-center gap-1 ml-auto">
          {/* Close button shown on mobile (when onLinkClick is defined = we're in mobile overlay) */}
          {onLinkClick && (
            <button
              onClick={onLinkClick}
              aria-label="Close navigation"
              className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer lg:hidden"
            >
              <X size={18} />
            </button>
          )}
          <button
            onClick={toggleCollapsed}
            aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
            className="text-slate-400 hover:text-slate-700 p-1.5 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer hidden lg:block"
          >
            {collapsed ? <ChevronsRight size={18} /> : <ChevronsLeft size={18} />}
          </button>
        </div>
      </header>

      <nav className="flex-1 overflow-y-auto px-3 py-2 space-y-2 nav-scroll">
        {SECTIONS.map((sec) => ({ ...sec, items: withPlatformItems(sec.items, !!user?.is_platform_admin) })).map((sec, idx) => (
          <div key={sec.title ?? idx} className="space-y-0.5">
            {!collapsed && sec.title && (
              <div className="px-3 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider text-slate-400">
                {sec.title}
              </div>
            )}
            {sec.items.map((item) => (
              <SidebarNavGroup
                key={item.key}
                item={item}
                collapsed={collapsed}
                isOpen={openKey === item.key}
                onToggle={toggleGroup}
                onLinkClick={onLinkClick}
              />
            ))}
          </div>
        ))}
      </nav>

      <footer className="shrink-0 p-3 space-y-2 border-t border-slate-100 bg-slate-50/50 relative">
        {!collapsed ? (
          <button
            id="tour-sync-status"
            type="button"
            onClick={() => setShowStatusPopup((prev) => !prev)}
            className="w-full flex items-center justify-between px-2.5 py-1.5 rounded-lg bg-white hover:bg-slate-50 border border-slate-200 transition-all text-left text-xs cursor-pointer shadow-2xs group"
            title="Click to view branch & sync status"
          >
            <div className="flex items-center gap-2 min-w-0">
              <span className="relative flex h-2 w-2 shrink-0">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
              </span>
              <span className="font-semibold text-slate-700 truncate group-hover:text-blue-700">
                {user?.active_branch?.code ?? 'LDW'} · Operational
              </span>
            </div>
            <ChevronUp
              size={13}
              className={`text-slate-400 transition-transform duration-200 group-hover:text-blue-600 shrink-0 ${
                showStatusPopup ? 'rotate-180' : ''
              }`}
            />
          </button>
        ) : (
          <button
            type="button"
            onClick={() => setShowStatusPopup((prev) => !prev)}
            className="w-full flex justify-center py-1.5 rounded-xl hover:bg-blue-50 text-slate-400 hover:text-blue-600 transition-colors cursor-pointer"
            title="Branch Operational · Click for status"
          >
            <span className="relative flex h-2.5 w-2.5">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
              <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500" />
            </span>
          </button>
        )}

        <AnimatePresence>
          {showStatusPopup && (
            <motion.div
              initial={{ opacity: 0, y: 8, scale: 0.96 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              exit={{ opacity: 0, y: 8, scale: 0.96 }}
              transition={{ duration: 0.15 }}
              className="absolute bottom-20 left-3 right-3 p-3.5 rounded-2xl bg-white border border-slate-200 shadow-xl shadow-slate-900/10 z-50 space-y-2.5"
            >
              <div className="flex items-center justify-between pb-1.5 border-b border-slate-100">
                <div className="flex items-center gap-1.5 text-xs font-bold text-slate-800">
                  <Activity size={13} className="text-emerald-500" />
                  Branch & Sync Status
                </div>
                <button
                  type="button"
                  onClick={() => setShowStatusPopup(false)}
                  className="text-slate-400 hover:text-slate-600 p-1 rounded-lg hover:bg-slate-100 cursor-pointer"
                  aria-label="Close status"
                >
                  <X size={13} />
                </button>
              </div>

              <div className="p-3 rounded-xl bg-blue-600 text-white shadow-xs">
                <div className="text-xs font-semibold uppercase tracking-wider text-blue-100">
                  Branch Operational
                </div>
                <div className="text-sm font-bold mt-0.5">
                  {user?.active_branch?.name ?? 'Lodwar Main Branch'}
                </div>
                <div className="mt-2 pt-2 border-t border-white/20 flex items-center justify-between text-xs">
                  <span className="text-blue-100">PPB Verified</span>
                  <SyncStatusChip invert />
                </div>
              </div>

              <SidebarBranchSelector user={user} />
            </motion.div>
          )}
        </AnimatePresence>

        <SidebarUserProfile user={user} collapsed={collapsed} />
      </footer>
    </>
  )
}

export default function Sidebar({
  mobileOpen = false,
  onMobileClose,
}: {
  mobileOpen?: boolean
  onMobileClose?: () => void
}) {
  const [collapsed, setCollapsed] = useState(readInitialCollapsed)

  function toggleCollapsed() {
    setCollapsed((prev) => {
      const next = !prev
      try {
        localStorage.setItem(COLLAPSE_KEY, next ? '1' : '0')
      } catch {
        // storage disabled
      }
      return next
    })
  }

  return (
    <>
      {/* ── Desktop sidebar: always visible, pinned, collapsible ────────── */}
      <aside
        className={`hidden lg:flex shrink-0 bg-white border-r border-slate-200/70 flex-col h-svh sticky top-0 transition-all duration-200 z-30 ${
          collapsed ? 'w-[72px]' : 'w-[260px]'
        }`}
      >
        <SidebarInner collapsed={collapsed} toggleCollapsed={toggleCollapsed} />
      </aside>

      {/* ── Mobile sidebar: off-canvas slide-over ───────────────────────── */}
      <AnimatePresence>
        {mobileOpen && (
          <>
            {/* Backdrop */}
            <motion.div
              key="mobile-backdrop"
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs lg:hidden"
              onClick={onMobileClose}
            />
            {/* Slide-over panel */}
            <motion.aside
              key="mobile-sidebar"
              initial={{ x: '-100%' }}
              animate={{ x: 0 }}
              exit={{ x: '-100%' }}
              transition={{ duration: 0.25, ease: [0.16, 1, 0.3, 1] }}
              className="fixed inset-y-0 left-0 z-50 w-[280px] bg-white border-r border-slate-200/70 flex flex-col h-full shadow-2xl lg:hidden"
            >
              <SidebarInner collapsed={false} toggleCollapsed={() => {}} onLinkClick={onMobileClose} />
            </motion.aside>
          </>
        )}
      </AnimatePresence>
    </>
  )
}
