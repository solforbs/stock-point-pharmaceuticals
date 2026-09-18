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
      <div id="tour-branch-selector" className="space-y-1.5">
        <label className="text-[11.5px] uppercase tracking-wider text-slate-500 font-bold block px-1">
          Branch Location
        </label>
        <div className="relative">
          <Building2
            size={14}
            className="absolute left-3 top-1/2 -translate-y-1/2 text-blue-600 pointer-events-none"
          />
          <select
            value={activeBranch?.id ?? ''}
            onChange={(e) => switchBranch(e.target.value)}
            className="w-full h-9 pl-8 pr-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-900 text-[13px] font-bold border border-slate-200 focus:border-blue-500 transition-colors outline-none cursor-pointer"
            aria-label="Active branch"
          >
            {branches.map((b) => (
              <option key={b.id} value={b.id}>
                {b.code} · {b.name}
              </option>
            ))}
          </select>
        </div>
      </div>
    )
  }

  if (activeBranch) {
    return (
      <div className="flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-50 border border-slate-200/80 text-[11.5px] text-slate-700">
        <Building2 size={13} className="shrink-0 text-blue-600" />
        <span className="truncate font-bold">
          {activeBranch.code} · {activeBranch.name}
        </span>
      </div>
    )
  }

  return null
}
