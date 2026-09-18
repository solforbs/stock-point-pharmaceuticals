import { ChevronsLeft, ChevronsRight, Pill } from 'lucide-react'
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
      className={`shrink-0 bg-[#0b1120] text-slate-300 flex flex-col h-svh sticky top-0 border-r border-slate-800/80 transition-all duration-200 z-30 ${
        collapsed ? 'w-[64px]' : 'w-[240px]'
      }`}
    >
      <header className="h-14 flex items-center justify-between px-3.5 shrink-0 border-b border-slate-800/80">
        {!collapsed && (
          <div className="flex items-center gap-2.5 min-w-0">
            <div className="w-8 h-8 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-700 flex items-center justify-center text-white shadow-md shadow-blue-500/20">
              <Pill size={16} />
            </div>
            <span className="text-white font-black tracking-wider text-[13px] truncate">
              PHARMAPOINT
            </span>
          </div>
        )}
        <button
          onClick={toggleCollapsed}
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          className="text-slate-400 hover:text-white p-1.5 rounded-lg hover:bg-slate-800 transition-colors mx-auto cursor-pointer"
        >
          {collapsed ? <ChevronsRight size={18} /> : <ChevronsLeft size={18} />}
        </button>
      </header>

      <nav className="flex-1 overflow-y-auto p-2.5 space-y-1">
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

      <footer className="shrink-0 p-3 space-y-3 bg-[#080d19]/60 border-t border-slate-800/80">
        {!collapsed && (
          <div className="space-y-2">
            <SidebarBranchSelector user={user} />
            <SyncStatusChip />
          </div>
        )}
        <SidebarUserProfile user={user} collapsed={collapsed} />
      </footer>
    </aside>
  )
}
