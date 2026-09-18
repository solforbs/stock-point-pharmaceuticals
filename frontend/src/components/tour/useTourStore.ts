import { create } from 'zustand'

export interface TourStep {
  targetId: string
  title: string
  description: string
  placement?: 'bottom' | 'top' | 'left' | 'right'
  route?: string
}

export const DEFAULT_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-search',
    title: 'Universal Medicine & Action Search',
    description: 'Press Ctrl+K anytime to quickly look up medicines, batch stock, customers, or jump directly to any page across the entire system.',
    placement: 'bottom',
    route: '/dashboard',
  },
  {
    targetId: 'tour-pos-button',
    title: 'One-Tap Live POS Terminal',
    description: 'Launch the high-speed retail checkout counter with automated price-tiering, eTIMS compliance, and batch barcode scanning.',
    placement: 'bottom',
    route: '/dashboard',
  },
  {
    targetId: 'tour-branch-selector',
    title: 'Active Branch & Dispensary',
    description: 'Switch between retail stores and main warehouses. All inventory checks and sales transactions immediately bind to the selected active branch.',
    placement: 'right',
    route: '/dashboard',
  },
  {
    targetId: 'tour-sync-status',
    title: 'System Health & eTIMS Sync',
    description: 'Real-time status of your local database synchronization, background job queue, and Kenya Revenue Authority (eTIMS) transmissions.',
    placement: 'top',
    route: '/dashboard',
  },
  {
    targetId: 'tour-approvals-queue',
    title: 'Operational Approvals Hub',
    description: 'Review pending stock adjustments, supplier purchase orders, clinical quarantine releases, and customer credit over-limit approvals.',
    placement: 'top',
    route: '/dashboard',
  },
]

const STORAGE_KEY = 'pharmapoint_tour_completed'

interface TourState {
  isOpen: boolean
  currentStepIndex: number
  steps: TourStep[]
  startTour: () => void
  endTour: () => void
  nextStep: () => void
  prevStep: () => void
  goToStep: (index: number) => void
  hasSeenTour: boolean
}

export const useTourStore = create<TourState>((set, get) => ({
  isOpen: false,
  currentStepIndex: 0,
  steps: DEFAULT_TOUR_STEPS,
  hasSeenTour: (() => {
    try {
      return localStorage.getItem(STORAGE_KEY) === 'true'
    } catch {
      return false
    }
  })(),
  startTour: () => {
    set({ isOpen: true, currentStepIndex: 0 })
  },
  endTour: () => {
    try {
      localStorage.setItem(STORAGE_KEY, 'true')
    } catch {}
    set({ isOpen: false, hasSeenTour: true })
  },
  nextStep: () => {
    const { currentStepIndex, steps } = get()
    if (currentStepIndex < steps.length - 1) {
      set({ currentStepIndex: currentStepIndex + 1 })
    } else {
      get().endTour()
    }
  },
  prevStep: () => {
    const { currentStepIndex } = get()
    if (currentStepIndex > 0) {
      set({ currentStepIndex: currentStepIndex - 1 })
    }
  },
  goToStep: (index: number) => {
    const { steps } = get()
    if (index >= 0 && index < steps.length) {
      set({ currentStepIndex: index })
    }
  },
}))
