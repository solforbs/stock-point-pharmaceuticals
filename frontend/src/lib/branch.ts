import { create } from 'zustand'

const KEY = 'active-branch-id'

function readInitial(): string | null {
  try {
    return localStorage.getItem(KEY)
  } catch {
    return null
  }
}

type BranchState = {
  activeBranchId: string | null
  setActiveBranch: (id: string | null) => void
}

// The branch the SPA sends as X-Branch-Id on every request. The server
// validates it against the user's own role assignments and falls back to
// their first branch, so a stale value here is harmless.
export const useBranchStore = create<BranchState>((set) => ({
  activeBranchId: readInitial(),
  setActiveBranch: (id) => {
    try {
      if (id) localStorage.setItem(KEY, id)
      else localStorage.removeItem(KEY)
    } catch {
      // per-viewer convenience only
    }
    set({ activeBranchId: id })
  },
}))
