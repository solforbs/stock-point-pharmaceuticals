import type { AccessState } from '../../lib/types'

/** How each subscription state reads on screen. */
export const ACCESS_LABEL: Record<AccessState, { label: string; tone: 'green' | 'blue' | 'amber' | 'red' }> = {
  ACTIVE: { label: 'Active', tone: 'green' },
  TRIAL: { label: 'Free trial', tone: 'blue' },
  LAPSED: { label: 'Read-only: choose a plan', tone: 'amber' },
  SUSPENDED: { label: 'Suspended', tone: 'red' },
}
