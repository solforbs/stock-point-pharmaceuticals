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
      <div className="mb-0.5">
        <button
          onClick={() => onToggle(item.key)}
          title={collapsed ? item.label : undefined}
          className={`w-full flex items-center gap-3 px-3 py-2 rounded-lg text-[12.5px] font-medium transition-all text-left group ${
            isActive
              ? 'text-white bg-slate-800/90 shadow-xs'
              : 'text-slate-300 hover:text-white hover:bg-slate-800/50'
          }`}
        >
          <Icon
            size={17}
            className={`shrink-0 transition-colors ${
              isActive ? 'text-blue-400' : 'text-slate-400 group-hover:text-slate-200'
            }`}
          />
          {!collapsed && (
            <>
              <span className="flex-1 truncate">{item.label}</span>
              <ChevronDown
                size={13}
                className={`shrink-0 transition-transform text-slate-400 duration-200 ${
                  isOpen ? 'rotate-180' : ''
                }`}
              />
            </>
          )}
        </button>

        {!collapsed && isOpen && (
          <div className="mt-1 space-y-0.5 pl-9 pr-2">
            {item.children!.map((child) => (
              <NavLink
                key={child.key}
                to={child.path}
                className={({ isActive: childActive }) =>
                  `block px-2.5 py-1.5 rounded-md text-[11.5px] font-medium transition-colors truncate ${
                    childActive
                      ? 'text-emerald-400 bg-emerald-500/10 font-bold'
                      : 'text-slate-400 hover:text-slate-100 hover:bg-slate-800/40'
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
    <div className="mb-0.5">
      <NavLink
        to={item.path}
        title={collapsed ? item.label : undefined}
        className={({ isActive: singleActive }) =>
          `flex items-center gap-3 px-3 py-2 rounded-lg text-[12.5px] font-medium transition-all group ${
            singleActive
              ? 'bg-[var(--color-navy)] text-white shadow-sm'
              : 'text-slate-300 hover:text-white hover:bg-slate-800/50'
          }`
        }
      >
        <Icon
          size={17}
          className={`shrink-0 transition-colors ${
            isActive ? 'text-white' : 'text-slate-400 group-hover:text-slate-200'
          }`}
        />
        {!collapsed && <span className="truncate">{item.label}</span>}
      </NavLink>
    </div>
  )
}
