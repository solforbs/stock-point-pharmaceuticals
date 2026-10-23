import { create } from 'zustand'
import type { CurrentUser, SaleMode } from './types'

const SESSION_MODE_KEY = 'pharmacy-session-mode'

/**
 * The mode the pharmacy runs in for this sign-in: chosen once after login
 * (retail or wholesale) and kept until the user changes it or signs out.
 * Held in sessionStorage, so it belongs to this tab's session only.
 */
type SessionModeState = {
  mode: SaleMode | null
  choose: (mode: SaleMode) => void
}

function readSessionMode(): SaleMode | null {
  try {
    const mode = sessionStorage.getItem(SESSION_MODE_KEY)
    return mode === 'RETAIL' || mode === 'WHOLESALE' ? mode : null
  } catch {
    return null
  }
}

export const useSessionMode = create<SessionModeState>((set) => ({
  mode: readSessionMode(),
  choose: (mode) => {
    try {
      sessionStorage.setItem(SESSION_MODE_KEY, mode)
    } catch {
      // the choice still holds until the page is reloaded
    }
    set({ mode })
  },
}))

/** Called on sign-out, so the next sign-in is asked again. */
export function forgetSessionMode() {
  try {
    sessionStorage.removeItem(SESSION_MODE_KEY)
  } catch {
    // ignore
  }
  useSessionMode.setState({ mode: null })
}

/** The modes a user may run the pharmacy in; a choice is offered when both are on. */
export function sessionModesFor(user: CurrentUser | null | undefined): SaleMode[] {
  return (user?.sale_modes ?? []).filter((m) => m === 'RETAIL' || m === 'WHOLESALE')
}

export const SESSION_MODE_LABEL: Record<SaleMode, string> = { RETAIL: 'Retail', WHOLESALE: 'Wholesale', DISPENSING: 'Dispensing' }
