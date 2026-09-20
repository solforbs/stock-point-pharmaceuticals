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

export const POS_TOUR_STEPS: TourStep[] = [
  {
    targetId: 'tour-pos-mode-banner',
    title: 'Sale Mode & Stock Location',
    description: 'Switch between Retail for walk-in patients (cash/M-Pesa) and Wholesale for bulk clinic orders. The Stock Room dropdown tells you which physical counter or warehouse shelf stock is deducted from.',
    placement: 'bottom',
    route: '/sell/pos',
  },
  {
    targetId: 'tour-pos-search',
    title: 'Scan Barcodes & Quick Catalog (F2)',
    description: 'Scan medicine barcodes directly with your barcode reader, search by drug or generic name, or tap any fast-moving medicine from the catalog for 1-tap addition.',
    placement: 'right',
    route: '/sell/pos',
  },
  {
    targetId: 'tour-pos-customer',
    title: 'Patient & Customer Account (F5)',
    description: 'Walk-ins are selected by default. For wholesale orders to clinics, chemists, or hospitals, choose their customer account here to apply special tier pricing and 30-day credit terms.',
    placement: 'bottom',
    route: '/sell/pos',
  },
  {
    targetId: 'tour-pos-cart-lines',
    title: 'Cart Items & Automated Expiry Batches',
    description: 'Adjust quantities with (+) and (-), change units (e.g. from Tablet to Pack or Box), and see automated FEFO batch expiration dates to guarantee dispensing oldest stock first.',
    placement: 'left',
    route: '/sell/pos',
  },
  {
    targetId: 'tour-pos-totals',
    title: 'Instant Total & Payment Checkout (F10)',
    description: 'Prices and taxes are automatically verified with a guaranteed price lock. Click Proceed to Payment or press F10 to take Cash, M-Pesa, or invoice on Credit.',
    placement: 'top',
    route: '/sell/pos',
  },
]

const STORAGE_KEY = 'pharmapoint_tour_completed'
const POS_STORAGE_KEY = 'pharmapoint_pos_tour_completed'

interface TourState {
  isOpen: boolean
  currentStepIndex: number
  steps: TourStep[]
  activeTourType: 'dashboard' | 'pos'
  startTour: () => void
  startPosTour: () => void
  endTour: () => void
  nextStep: () => void
  prevStep: () => void
  goToStep: (index: number) => void
  hasSeenTour: boolean
  hasSeenPosTour: boolean
}

export const useTourStore = create<TourState>((set, get) => ({
  isOpen: false,
  currentStepIndex: 0,
  steps: DEFAULT_TOUR_STEPS,
  activeTourType: 'dashboard',
  hasSeenTour: (() => {
    try {
      return localStorage.getItem(STORAGE_KEY) === 'true'
    } catch {
      return false
    }
  })(),
  hasSeenPosTour: (() => {
    try {
      return localStorage.getItem(POS_STORAGE_KEY) === 'true'
    } catch {
      return false
    }
  })(),
  startTour: () => {
    set({ isOpen: true, currentStepIndex: 0, steps: DEFAULT_TOUR_STEPS, activeTourType: 'dashboard' })
  },
  startPosTour: () => {
    set({ isOpen: true, currentStepIndex: 0, steps: POS_TOUR_STEPS, activeTourType: 'pos' })
  },
  endTour: () => {
    const { activeTourType } = get()
    try {
      if (activeTourType === 'pos') {
        localStorage.setItem(POS_STORAGE_KEY, 'true')
      } else {
        localStorage.setItem(STORAGE_KEY, 'true')
      }
    } catch {}
    set({
      isOpen: false,
      hasSeenTour: activeTourType === 'dashboard' ? true : get().hasSeenTour,
      hasSeenPosTour: activeTourType === 'pos' ? true : get().hasSeenPosTour,
    })
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
