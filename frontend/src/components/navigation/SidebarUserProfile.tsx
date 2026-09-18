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
    <div className="pt-2 border-t border-slate-700/60 space-y-2">
      {!collapsed && (
        <div className="flex items-center gap-2.5 px-1 py-1">
          <div className="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-300 shrink-0 font-bold text-[12px]">
            {user?.name ? user.name.charAt(0).toUpperCase() : <User size={14} />}
          </div>
          <div className="min-w-0 flex-1">
            <div className="text-[12px] font-bold text-white truncate">{user?.name}</div>
            <div className="text-[10px] text-slate-400 truncate">{user?.email}</div>
          </div>
        </div>
      )}

      <NavLink
        to="/admin/security"
        title={collapsed ? 'Security & MFA' : undefined}
        className={({ isActive }) =>
          `w-full flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-[11.5px] font-medium transition-colors ${
            isActive ? 'text-white bg-slate-800' : 'text-slate-400 hover:text-white hover:bg-slate-800/40'
          }`
        }
      >
        <ShieldCheck size={15} className="shrink-0 text-emerald-400" />
        {!collapsed && <span>Security{user?.mfa_required ? ' · MFA on' : ''}</span>}
      </NavLink>

      <button
        onClick={() => logout.mutate()}
        title={collapsed ? 'Sign Out' : undefined}
        disabled={logout.isPending}
        className="w-full flex items-center gap-2.5 px-2.5 py-1.5 rounded-lg text-[11.5px] font-medium text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors cursor-pointer"
      >
        <LogOut size={15} className="shrink-0" />
        {!collapsed && <span>{logout.isPending ? 'Signing out…' : 'Sign out'}</span>}
      </button>
    </div>
  )
}
