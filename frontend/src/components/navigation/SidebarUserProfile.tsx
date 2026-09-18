import { useMutation, useQueryClient } from '@tanstack/react-query'
import { LogOut, ShieldCheck, User } from 'lucide-react'
import { NavLink, useNavigate } from 'react-router-dom'
import { api } from '../../lib/api'
import type { CurrentUser } from '../../lib/types'

export interface SidebarUserProfileProps {
  user: CurrentUser | null | undefined
  collapsed: boolean
}

export function SidebarUserProfile({ user, collapsed }: SidebarUserProfileProps) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const logout = useMutation({
    mutationFn: () => api.post('/auth/logout'),
    onSuccess: () => {
      queryClient.clear()
      queryClient.setQueryData(['auth', 'user'], null)
      navigate('/login')
    },
  })

  return (
    <div className="pt-3 border-t border-slate-100 dark:border-slate-800 space-y-2">
      {!collapsed && (
        <div className="flex items-center gap-3 px-2 py-1">
          <div className="w-9 h-9 rounded-full bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800 flex items-center justify-center text-blue-600 dark:text-blue-400 shrink-0 font-bold text-[13px] shadow-xs">
            {user?.name ? user.name.charAt(0).toUpperCase() : <User size={15} />}
          </div>
          <div className="min-w-0 flex-1">
            <div className="text-[12.5px] font-bold text-slate-800 dark:text-slate-100 truncate">
              {user?.name ?? 'Pharmacist'}
            </div>
            <div className="text-[11px] text-slate-400 truncate">
              {user?.email ?? 'active session'}
            </div>
          </div>
        </div>
      )}

      <div className="flex flex-col gap-0.5">
        <NavLink
          to="/admin/security"
          title={collapsed ? 'Security' : undefined}
          className={({ isActive }) =>
            `w-full flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-[12px] font-semibold transition-colors ${
              isActive
                ? 'text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40'
                : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800/40'
            }`
          }
        >
          <ShieldCheck size={16} className="shrink-0 text-emerald-500" />
          {!collapsed && <span>Security{user?.mfa_required ? ' · MFA On' : ''}</span>}
        </NavLink>

        <button
          onClick={() => logout.mutate()}
          title={collapsed ? 'Sign Out' : undefined}
          disabled={logout.isPending}
          className="w-full flex items-center gap-2.5 px-3 py-1.5 rounded-xl text-[12px] font-semibold text-slate-500 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30 transition-colors cursor-pointer"
        >
          <LogOut size={16} className="shrink-0" />
          {!collapsed && <span>{logout.isPending ? 'Signing out…' : 'Sign out'}</span>}
        </button>
      </div>
    </div>
  )
}
