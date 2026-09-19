import { useMutation, useQueryClient } from '@tanstack/react-query'
import { LogOut, ShieldCheck, User } from 'lucide-react'
import { Link, useNavigate } from 'react-router-dom'
import { forgetCachedUser } from '../../hooks/useCurrentUser'
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
      forgetCachedUser()
      queryClient.clear()
      queryClient.setQueryData(['auth', 'user'], null)
      navigate('/login')
    },
  })

  const firstRole = user?.roles?.[0]
  const roleName = typeof firstRole === 'object' && firstRole !== null ? firstRole.name : (firstRole ?? 'Admin')

  if (collapsed) {
    return (
      <div className="pt-2 border-t border-slate-100 flex flex-col items-center gap-2">
        <div className="w-8 h-8 rounded-full bg-blue-50 border border-blue-200 text-blue-600 font-bold text-[12px] flex items-center justify-center">
          {user?.name ? user.name.charAt(0).toUpperCase() : <User size={14} />}
        </div>
        <button
          onClick={() => logout.mutate()}
          title="Sign Out"
          className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
        >
          <LogOut size={16} />
        </button>
      </div>
    )
  }

  return (
    <div className="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
      <div className="flex items-center gap-2.5 min-w-0 flex-1">
        <div className="w-8 h-8 rounded-full bg-blue-50 border border-blue-200 text-blue-600 font-bold text-[12px] flex items-center justify-center shrink-0 shadow-xs">
          {user?.name ? user.name.charAt(0).toUpperCase() : <User size={14} />}
        </div>
        <div className="min-w-0 flex-1">
          <div className="text-[12px] font-bold text-slate-900 truncate">
            {user?.name ?? 'Administrator'}
          </div>
          <div className="text-[10.5px] text-slate-400 truncate font-medium">
            {roleName}
          </div>
        </div>
      </div>

      <div className="flex items-center gap-1 shrink-0">
        <Link
          to="/admin/security"
          title="Security & MFA"
          className="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors"
        >
          <ShieldCheck size={16} />
        </Link>
        <button
          onClick={() => logout.mutate()}
          title="Sign Out"
          disabled={logout.isPending}
          className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
        >
          <LogOut size={16} />
        </button>
      </div>
    </div>
  )
}
