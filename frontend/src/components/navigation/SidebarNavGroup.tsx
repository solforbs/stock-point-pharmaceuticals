import { ChevronDown } from 'lucide-react'
import { NavLink, useLocation } from 'react-router-dom'
import type { NavItem } from '../../lib/navigation'

export interface SidebarNavGroupProps {
  item: NavItem
  collapsed: boolean
  isOpen: boolean
  onToggle: (key: string) => void
  onLinkClick?: () => void
}

export function SidebarNavGroup({ item, collapsed, isOpen, onToggle, onLinkClick }: SidebarNavGroupProps) {
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
          className={`w-full flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors text-left group cursor-pointer ${
            isActive
              ? 'text-blue-700 bg-blue-50/70 font-semibold'
              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-normal'
          }`}
        >
          <div
            className={`transition-colors shrink-0 ${
              isActive ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-600'
            }`}
          >
            <Icon size={18} />
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
          <div className="mt-0.5 space-y-0.5 pl-9 pr-1">
            {item.children!.map((child) => (
              <NavLink
                key={child.key}
                to={child.path}
                onClick={onLinkClick}
                className={({ isActive: childActive }) =>
                  `block px-2.5 py-1.5 rounded-md text-sm transition-colors truncate ${
                    childActive
                      ? 'text-blue-700 bg-blue-50/80 font-semibold'
                      : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/60 font-normal'
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
        onClick={onLinkClick}
        title={collapsed ? item.label : undefined}
        className={({ isActive: singleActive }) =>
          `flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors group ${
            singleActive
              ? 'bg-blue-50/70 text-blue-700 font-semibold'
              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 font-normal'
          }`
        }
      >
        <div
          className={`transition-colors shrink-0 ${
            isActive ? 'text-blue-600' : 'text-slate-400 group-hover:text-slate-600'
          }`}
        >
          <Icon size={18} />
        </div>
        {!collapsed && <span className="truncate">{item.label}</span>}
      </NavLink>
    </div>
  )
}
