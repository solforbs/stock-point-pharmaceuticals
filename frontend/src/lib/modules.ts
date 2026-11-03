import { Building2, FlaskConical, Microscope, Pill, Stethoscope, TestTubes, Users, type LucideIcon } from 'lucide-react'
import type { CurrentUser, ModuleKey } from './types'
import type { NavItem } from './navigation'
import { sessionModesFor } from './sessionMode'

/**
 * The healthcare platform's modules. Which of them a user may enter is
 * decided server-side (the `modules` list on /api/user, derived from
 * permissions — never from role names); everything here is presentation
 * plus the post-login routing rule:
 *   0 modules → access denied, 1 → straight in, 2+ → the module splash.
 * Only the modules in MODULES_IN_USE count for that rule, so while the
 * hospital and laboratory are not in use everyone lands in the pharmacy.
 */
export type ModuleInfo = {
  key: ModuleKey
  label: string
  description: string
  icon: LucideIcon
  home: string
  highlights: string[]
}

export const MODULES: ModuleInfo[] = [
  {
    key: 'HOSPITAL',
    label: 'Hospital',
    description: 'Patients, reception and clinical services',
    icon: Stethoscope,
    home: '/hospital/dashboard',
    highlights: ['Patients & encounters', 'Reception & queues', 'Consultations & referrals'],
  },
  {
    key: 'LABORATORY',
    label: 'Laboratory',
    description: 'Test orders, samples and results',
    icon: Microscope,
    home: '/laboratory/orders',
    highlights: ['Lab orders', 'Sample collection', 'Results & reports'],
  },
  {
    key: 'PHARMACY',
    label: 'Pharmacy',
    description: 'Stock, sales and dispensing',
    icon: Pill,
    home: '/dashboard',
    highlights: ['Stock & inventory', 'POS & sales', 'Prescriptions'],
  },
]

/** The modules sign-in and the module switcher offer. Hospital and laboratory stay reachable by URL for whoever holds them. */
export const MODULES_IN_USE: ModuleKey[] = ['PHARMACY']

/** The user's modules that are in use, or all of them when they hold none that are (so a hospital-only account still gets in). */
export function modulesInUse(user: CurrentUser | null | undefined): ModuleKey[] {
  const modules = user?.modules ?? []
  const inUse = modules.filter((m) => MODULES_IN_USE.includes(m))
  return inUse.length > 0 ? inUse : modules
}

export function moduleInfo(key: ModuleKey): ModuleInfo {
  return MODULES.find((m) => m.key === key) ?? MODULES[2]
}

/** Which module the current URL belongs to (pharmacy owns everything else). */
export function moduleForPath(pathname: string): ModuleKey {
  if (pathname.startsWith('/hospital')) return 'HOSPITAL'
  if (pathname.startsWith('/laboratory')) return 'LABORATORY'
  return 'PHARMACY'
}

/**
 * Where sign-in lands: denied, the only module's home, or the splash.
 * Platform-only admins are handled before this is consulted.
 */
export function postLoginTarget(user: CurrentUser): string {
  const modules = modulesInUse(user)
  if (modules.length === 0) return '/no-access'
  // A pharmacy that sells both retail and wholesale asks which one this session runs in.
  if (modules.length === 1 && (modules[0] !== 'PHARMACY' || sessionModesFor(user).length < 2)) return moduleInfo(modules[0]).home
  return '/select-module'
}

// ── Module navigation (the pharmacy keeps its existing NAV_ITEMS) ───────────

export const HOSPITAL_NAV: NavItem[] = [
  { key: 'hospital-dashboard', label: 'Dashboard', icon: Building2, path: '/hospital/dashboard' },
  { key: 'hospital-reception', label: 'Reception', icon: Users, path: '/hospital/reception' },
  { key: 'hospital-patients', label: 'Patients', icon: Users, path: '/hospital/patients' },
  { key: 'hospital-encounters', label: 'Encounters & Queue', icon: Stethoscope, path: '/hospital/encounters' },
  { key: 'hospital-setup', label: 'Facilities & Setup', icon: Building2, path: '/hospital/setup' },
]

export const LABORATORY_NAV: NavItem[] = [
  { key: 'lab-orders', label: 'Lab Orders', icon: TestTubes, path: '/laboratory/orders' },
  { key: 'lab-tests', label: 'Test Catalogue', icon: FlaskConical, path: '/laboratory/tests' },
]
