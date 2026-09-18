import { useQueryClient } from '@tanstack/react-query'
import { Building2 } from 'lucide-react'
import { useBranchStore } from '../../lib/branch'
import type { Branch, CurrentUser } from '../../lib/types'

export interface SidebarBranchSelectorProps {
  user: CurrentUser | null | undefined
}

export function SidebarBranchSelector({ user }: SidebarBranchSelectorProps) {
  const queryClient = useQueryClient()
  const setActiveBranch = useBranchStore((s) => s.setActiveBranch)

  const branches: Branch[] = user?.branches ?? []
  const activeBranch = user?.active_branch ?? null

  function switchBranch(id: string) {
    setActiveBranch(id)
    queryClient.invalidateQueries()
  }

  if (branches.length > 1) {
    return (
      <label className="block">
        <span className="text-[9.5px] uppercase tracking-wider text-slate-400 font-bold">
          Active Branch
        </span>
        <div className="relative mt-1">
          <Building2
            size={13}
            className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
          />
          <select
            value={activeBranch?.id ?? ''}
            onChange={(e) => switchBranch(e.target.value)}
            className="w-full h-8 pl-7 pr-2 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-slate-200 text-[11.5px] font-medium border border-slate-700/60 focus:border-blue-500 transition-colors outline-none cursor-pointer"
            aria-label="Active branch"
          >
            {branches.map((b) => (
              <option key={b.id} value={b.id} className="text-black bg-white">
                {b.code} · {b.name}
              </option>
            ))}
          </select>
        </div>
      </label>
    )
  }

  if (activeBranch) {
    return (
      <div className="flex items-center gap-2 px-2.5 py-1.5 rounded-lg bg-slate-800/60 border border-slate-700/40 text-[11px] text-slate-300 truncate">
        <Building2 size={12} className="shrink-0 text-blue-400" />
        <span className="truncate font-medium">
          {activeBranch.code} · {activeBranch.name}
        </span>
      </div>
    )
  }

  return null
}
