import { create } from 'zustand'
import {
  DEFAULT_TOUR_STEPS,
  REGISTERED_TOURS,
  getTourForRoute,
  type TourDefinition,
  type TourStep,
} from './tourRegistry'

export type { TourDefinition, TourStep }
export { getTourForRoute, REGISTERED_TOURS }

interface TourState {
  isOpen: boolean
  currentStepIndex: number
  steps: TourStep[]
  activeTourId: string | null
  // In-memory dismissal set: resets on page refresh!
  dismissedInSession: Record<string, boolean>
  hasCompletedTour: (tourId: string) => boolean
  startTourById: (tourId: string) => void
  startTourForRoute: (pathname: string) => void
  startTour: () => void
  startPosTour: () => void
  endTour: () => void
  nextStep: () => void
  prevStep: () => void
  goToStep: (index: number) => void
  dismissTourPrompt: (tourId: string) => void
  isPromptDismissed: (tourId: string) => boolean
}

export const useTourStore = create<TourState>((set, get) => ({
  isOpen: false,
  currentStepIndex: 0,
  steps: DEFAULT_TOUR_STEPS,
  activeTourId: 'dashboard',
  dismissedInSession: {},

  hasCompletedTour: (tourId: string) => {
    try {
      return localStorage.getItem(`pharmapoint_tour_completed_${tourId}`) === 'true'
    } catch {
      return false
    }
  },

  isPromptDismissed: (tourId: string) => {
    return Boolean(get().dismissedInSession[tourId]) || get().hasCompletedTour(tourId)
  },

  dismissTourPrompt: (tourId: string) => {
    set((state) => ({
      dismissedInSession: {
        ...state.dismissedInSession,
        [tourId]: true,
      },
    }))
  },

  startTourById: (tourId: string) => {
    const tour = REGISTERED_TOURS.find((t) => t.id === tourId)
    if (!tour) return
    set({
      isOpen: true,
      currentStepIndex: 0,
      steps: tour.steps,
      activeTourId: tour.id,
    })
  },

  startTourForRoute: (pathname: string) => {
    const tour = getTourForRoute(pathname)
    if (tour) {
      set({
        isOpen: true,
        currentStepIndex: 0,
        steps: tour.steps,
        activeTourId: tour.id,
      })
    } else {
      set({
        isOpen: true,
        currentStepIndex: 0,
        steps: DEFAULT_TOUR_STEPS,
        activeTourId: 'dashboard',
      })
    }
  },

  startTour: () => {
    get().startTourById('dashboard')
  },

  startPosTour: () => {
    get().startTourById('pos')
  },

  endTour: () => {
    const { activeTourId } = get()
    if (activeTourId) {
      try {
        localStorage.setItem(`pharmapoint_tour_completed_${activeTourId}`, 'true')
      } catch {}
    }
    set({
      isOpen: false,
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
