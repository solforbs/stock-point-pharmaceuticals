import { Activity, ChevronsLeft, ChevronsRight, Pill } from 'lucide-react'
import { useState } from 'react'
import { useLocation } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { NAV_ITEMS } from '../lib/navigation'
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

export default function Sidebar() {
  const [collapsed, setCollapsed] = useState(readInitialCollapsed)
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

  function toggleGroup(key: string) {
    if (collapsed) return
    setOpenKey((prev) => (prev === key ? null : key))
  }

  return (
    <aside
      className={`shrink-0 bg-white dark:bg-slate-900 border-r border-slate-200/70 dark:border-slate-800 flex flex-col h-svh sticky top-0 transition-all duration-200 z-30 ${
        collapsed ? 'w-[72px]' : 'w-[250px]'
      }`}
    >
      <header className="h-16 flex items-center justify-between px-4 shrink-0 border-b border-slate-100 dark:border-slate-800/80">
        {!collapsed && (
          <div className="flex items-center gap-2.5 min-w-0">
            <div className="w-9 h-9 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white shadow-md shadow-blue-500/20">
              <Pill size={18} />
            </div>
            <span className="font-extrabold tracking-tight text-[15px] text-slate-900 dark:text-white truncate">
              PharmaPoint
            </span>
          </div>
        )}
        <button
          onClick={toggleCollapsed}
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          className="text-slate-400 hover:text-slate-700 dark:hover:text-white p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors mx-auto cursor-pointer"
        >
          {collapsed ? <ChevronsRight size={18} /> : <ChevronsLeft size={18} />}
        </button>
      </header>

      <nav className="flex-1 overflow-y-auto px-3 py-3 space-y-0.5">
        {NAV_ITEMS.map((item) => (
          <SidebarNavGroup
            key={item.key}
            item={item}
            collapsed={collapsed}
            isOpen={openKey === item.key}
            onToggle={toggleGroup}
          />
        ))}
      </nav>

      <footer className="shrink-0 p-3 space-y-3 border-t border-slate-100 dark:border-slate-800/80 bg-slate-50/50 dark:bg-slate-950/20">
        {!collapsed && (
          <div className="space-y-2.5">
            <SidebarBranchSelector user={user} />
            <div className="p-3 rounded-2xl bg-gradient-to-br from-blue-600 to-indigo-700 text-white shadow-md shadow-blue-500/15">
              <div className="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-blue-100">
                <Activity size={12} className="text-emerald-300 animate-pulse" />
                Branch Operational
              </div>
              <p className="text-[11px] text-blue-100/80 mt-0.5">PPB License verified & active</p>
              <div className="mt-2 pt-2 border-t border-white/15">
                <SyncStatusChip />
              </div>
            </div>
          </div>
        )}
        <SidebarUserProfile user={user} collapsed={collapsed} />
      </footer>
    </aside>
  )
}
