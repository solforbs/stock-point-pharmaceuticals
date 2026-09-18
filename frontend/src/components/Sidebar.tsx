import { Building2, ChevronDown, ChevronsLeft, ChevronsRight, LogOut, ShieldCheck } from 'lucide-react'
import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { NavLink, useLocation, useNavigate } from 'react-router-dom'
import { api } from '../lib/api'
import { useBranchStore } from '../lib/branch'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { NAV_ITEMS } from '../lib/navigation'
import { SyncStatusChip } from './SyncStatusChip'

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

  // The group that owns the current page — so a reload on /inventory/products
  // (or a deep link into it) shows the Inventory sub-navigation open.
  const activeGroupKey =
    NAV_ITEMS.find((item) => item.children?.length && location.pathname.startsWith(item.path))?.key ?? null
  const [openKey, setOpenKey] = useState<string | null>(activeGroupKey)
  const [seenGroupKey, setSeenGroupKey] = useState<string | null>(activeGroupKey)

  // Navigating into a different group opens it (derived during render, per
  // React's "adjust state on prop change" pattern — no effect needed).
  if (activeGroupKey !== seenGroupKey) {
    setSeenGroupKey(activeGroupKey)
    if (activeGroupKey) setOpenKey(activeGroupKey)
  }

  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: user } = useCurrentUser()
  const setActiveBranch = useBranchStore((s) => s.setActiveBranch)

  const logout = useMutation({
    mutationFn: () => api.post('/auth/logout'),
    onSuccess: () => {
      queryClient.clear()
      queryClient.setQueryData(['auth', 'user'], null)
      navigate('/login')
    },
  })

  function toggleCollapsed() {
    setCollapsed((prev) => {
      const next = !prev
      try {
        localStorage.setItem(COLLAPSE_KEY, next ? '1' : '0')
      } catch {
        // per-viewer convenience only — fine if it can't persist
      }
      return next
    })
  }

  function toggleGroup(key: string) {
    if (collapsed) return
    setOpenKey((prev) => (prev === key ? null : key))
  }

  // Part 18.2 — switching branch re-scopes every query (permissions, stores,
  // documents), so the whole cache is refetched under the new X-Branch-Id.
  function switchBranch(id: string) {
    setActiveBranch(id)
    queryClient.invalidateQueries()
  }

  const branches = user?.branches ?? []
  const activeBranch = user?.active_branch ?? null

  return (
    <aside
      className={`shrink-0 bg-[#111827] text-gray-300 flex flex-col h-svh sticky top-0 transition-[width] duration-150 ${
        collapsed ? 'w-[60px]' : 'w-[230px]'
      }`}
    >
      <div className="h-13 flex items-center px-3 shrink-0">
        {!collapsed && <span className="text-white font-extrabold text-[13px] flex-1 truncate">STOCKPOINT</span>}
        <button
          onClick={toggleCollapsed}
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
          className="text-gray-400 hover:text-white p-1 rounded"
        >
          {collapsed ? <ChevronsRight size={16} /> : <ChevronsLeft size={16} />}
        </button>
      </div>

      <nav className="flex-1 overflow-y-auto py-2">
        {NAV_ITEMS.map((item) => {
          const Icon = item.icon
          const isActive = location.pathname.startsWith(item.path)
          const hasChildren = !!item.children?.length
          const isOpen = openKey === item.key

          return (
            <div key={item.key}>
              {hasChildren ? (
                <button
                  onClick={() => toggleGroup(item.key)}
                  title={collapsed ? item.label : undefined}
                  className={`w-full flex items-center gap-2.5 px-3.5 py-2 text-[12px] text-left ${
                    isActive ? 'text-white' : 'text-gray-300 hover:text-white'
                  }`}
                >
                  <Icon size={16} className="shrink-0" />
                  {!collapsed && (
                    <>
                      <span className="flex-1 truncate">{item.label}</span>
                      <ChevronDown
                        size={13}
                        className={`shrink-0 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                      />
                    </>
                  )}
                </button>
              ) : (
                <NavLink
                  to={item.path}
                  title={collapsed ? item.label : undefined}
                  className={({ isActive }) =>
                    `flex items-center gap-2.5 px-3.5 py-2 text-[12px] ${
                      isActive ? 'bg-[var(--color-navy)] text-white' : 'text-gray-300 hover:text-white'
                    }`
                  }
                >
                  <Icon size={16} className="shrink-0" />
                  {!collapsed && <span className="truncate">{item.label}</span>}
                </NavLink>
              )}

              {hasChildren && !collapsed && isOpen && (
                <div className="pb-1">
                  {item.children!.map((child) => (
                    <NavLink
                      key={child.key}
                      to={child.path}
                      className={({ isActive }) =>
                        `block pl-10 pr-3 py-1.5 text-[11.5px] truncate ${
                          isActive
                            ? 'text-[var(--color-green-light,#66966d)] font-semibold'
                            : 'text-gray-400 hover:text-white'
                        }`
                      }
                    >
                      {child.label}
                    </NavLink>
                  ))}
                </div>
              )}
            </div>
          )
        })}
      </nav>

      <div className="shrink-0 border-t border-white/10 p-3 space-y-2">
        {!collapsed && (
          <>
            <div className="min-w-0">
              <div className="text-[11.5px] text-white truncate">{user?.name}</div>
              <div className="text-[10px] text-gray-500 truncate">{user?.email}</div>
            </div>

            {branches.length > 1 ? (
              <label className="block">
                <span className="text-[9.5px] uppercase tracking-wide text-gray-500">Branch</span>
                <div className="relative">
                  <Building2 size={12} className="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none" />
                  <select
                    value={activeBranch?.id ?? ''}
                    onChange={(e) => switchBranch(e.target.value)}
                    className="w-full h-7 pl-6 pr-2 rounded bg-white/10 text-white text-[11.5px] border border-white/10 outline-none"
                    aria-label="Active branch"
                  >
                    {branches.map((b) => (
                      <option key={b.id} value={b.id} className="text-black">
                        {b.code} · {b.name}
                      </option>
                    ))}
                  </select>
                </div>
              </label>
            ) : activeBranch ? (
              <div className="flex items-center gap-1.5 text-[10.5px] text-gray-400 truncate">
                <Building2 size={11} className="shrink-0" />
                {activeBranch.code} · {activeBranch.name}
              </div>
            ) : null}

            <SyncStatusChip />
          </>
        )}

        <NavLink
          to="/admin/security"
          title={collapsed ? 'Security' : undefined}
          className={({ isActive }) =>
            `w-full flex items-center gap-2 text-[11.5px] ${isActive ? 'text-white' : 'text-gray-400 hover:text-white'}`
          }
        >
          <ShieldCheck size={15} className="shrink-0" />
          {!collapsed && <span>Security{user?.mfa_required ? ' · MFA on' : ''}</span>}
        </NavLink>

        <button
          onClick={() => logout.mutate()}
          title={collapsed ? 'Sign out' : undefined}
          className="w-full flex items-center gap-2 text-[11.5px] text-gray-400 hover:text-white"
        >
          <LogOut size={15} className="shrink-0" />
          {!collapsed && <span>Sign out</span>}
        </button>
      </div>
    </aside>
  )
}
