import { ChevronDown } from 'lucide-react'
import { NavLink, useLocation } from 'react-router-dom'
import type { NavItem } from '../../lib/navigation'

export interface SidebarNavGroupProps {
  item: NavItem
  collapsed: boolean
  isOpen: boolean
  onToggle: (key: string) => void
}

export function SidebarNavGroup({ item, collapsed, isOpen, onToggle }: SidebarNavGroupProps) {
  const location = useLocation()
  const Icon = item.icon
  const isActive = location.pathname.startsWith(item.path)
  const hasChildren = !!item.children?.length

  if (hasChildren) {
    return (
      <div className="mb-1">
        <button
          onClick={() => onToggle(item.key)}
          title={collapsed ? item.label : undefined}
          className={`w-full flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-[13px] font-semibold transition-all text-left group cursor-pointer ${
            isActive
              ? 'text-blue-600 dark:text-blue-400 bg-blue-50/80 dark:bg-blue-950/40 font-bold'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-800/60'
          }`}
        >
          <div
            className={`p-1 rounded-lg transition-colors ${
              isActive
                ? 'bg-blue-600 text-white shadow-xs shadow-blue-500/30'
                : 'text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-200'
            }`}
          >
            <Icon size={16} />
          </div>
          {!collapsed && (
            <>
              <span className="flex-1 truncate">{item.label}</span>
              <ChevronDown
                size={14}
                className={`shrink-0 transition-transform text-slate-400 duration-200 ${
                  isOpen ? 'rotate-180' : ''
                }`}
              />
            </>
          )}
        </button>

        {!collapsed && isOpen && (
          <div className="mt-1 space-y-0.5 pl-10 pr-2">
            {item.children!.map((child) => (
              <NavLink
                key={child.key}
                to={child.path}
                className={({ isActive: childActive }) =>
                  `block px-3 py-1.5 rounded-lg text-[12px] font-medium transition-colors truncate ${
                    childActive
                      ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 font-bold'
                      : 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-800/40'
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
  }

  return (
    <div className="mb-1">
      <NavLink
        to={item.path}
        title={collapsed ? item.label : undefined}
        className={({ isActive: singleActive }) =>
          `flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-[13px] font-semibold transition-all group ${
            singleActive
              ? 'bg-blue-50/80 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 font-bold shadow-xs'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-800/60'
          }`
        }
      >
        <div
          className={`p-1 rounded-lg transition-colors ${
            isActive
              ? 'bg-blue-600 text-white shadow-xs shadow-blue-500/30'
              : 'text-slate-400 group-hover:text-slate-700 dark:group-hover:text-slate-200'
          }`}
        >
          <Icon size={16} />
        </div>
        {!collapsed && <span className="truncate">{item.label}</span>}
      </NavLink>
    </div>
  )
}
