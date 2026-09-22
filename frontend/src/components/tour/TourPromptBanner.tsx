import { AnimatePresence, motion } from 'framer-motion'
import { ArrowRight, Compass, Sparkles, X } from 'lucide-react'
import { useLocation } from 'react-router-dom'
import { MovingBorderCard } from '../ui/moving-border'
import { getTourForRoute, useTourStore } from './useTourStore'

export function TourPromptBanner() {
  const location = useLocation()
  const tour = getTourForRoute(location.pathname)
  // Subscribe directly to dismissedInSession so Zustand triggers an instant re-render upon click!
  const dismissedInSession = useTourStore((s) => s.dismissedInSession)
  const hasCompletedTour = useTourStore((s) => s.hasCompletedTour)
  const dismissTourPrompt = useTourStore((s) => s.dismissTourPrompt)
  const startTourById = useTourStore((s) => s.startTourById)
  const isTourOpen = useTourStore((s) => s.isOpen)
  const isPos = location.pathname.startsWith('/sell/pos')

  const isDismissed = tour ? Boolean(dismissedInSession[tour.id]) || hasCompletedTour(tour.id) : false
  const isVisible = Boolean(tour && !isTourOpen && !isDismissed)

  return (
    <AnimatePresence>
      {isVisible && tour && (
        <motion.aside
          key={`tour-prompt-${tour.id}`}
          initial={{ opacity: 0, y: 16, scale: 0.96 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          exit={{ opacity: 0, y: 12, scale: 0.96 }}
          transition={{ duration: 0.18, ease: 'easeOut' }}
          aria-label="Interactive guide prompt"
          className={`fixed z-40 ${
            isPos ? 'bottom-5 left-5' : 'bottom-5 right-5'
          } w-[340px] max-w-[calc(100vw-2.5rem)] pointer-events-auto shadow-2xl`}
        >
          <MovingBorderCard borderRadius="1rem" className="p-4 bg-white text-slate-900 border-slate-200/90" duration={3000}>
          {/* Top bar: Badge & dismiss */}
          <div className="flex items-center justify-between gap-2 mb-2">
            <div className="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-blue-50 border border-blue-200/60 text-[11px] font-semibold text-blue-700">
              <Sparkles size={11} className="text-blue-600" />
              <span>Interactive Guide</span>
            </div>
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation()
                e.preventDefault()
                dismissTourPrompt(tour.id)
              }}
              aria-label="Dismiss guide for this session"
              title="Dismiss (reappears on refresh)"
              className="p-1 rounded-md text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
            >
              <X size={14} />
            </button>
          </div>

          {/* Content */}
          <div className="pr-1">
            <h4 className="text-sm font-bold text-slate-900 tracking-tight flex items-center gap-1.5">
              Tour the system to learn this part
            </h4>
            <p className="text-xs text-slate-500 mt-1 leading-relaxed line-clamp-2">
              {tour.subtitle}
            </p>
          </div>

          {/* Actions */}
          <div className="flex items-center justify-between gap-2 mt-3.5 pt-3 border-t border-slate-100">
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation()
                e.preventDefault()
                dismissTourPrompt(tour.id)
              }}
              className="text-xs font-medium text-slate-400 hover:text-slate-700 px-2 py-1 rounded-md hover:bg-slate-100 transition-colors cursor-pointer"
            >
              Not now
            </button>
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation()
                e.preventDefault()
                startTourById(tour.id)
              }}
              className="inline-flex items-center gap-1.5 px-3.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold transition-all shadow-xs hover:shadow cursor-pointer"
            >
              <Compass size={13} className="text-white" />
              <span>Start tour</span>
              <ArrowRight size={12} className="text-white/80" />
            </button>
          </div>
          </MovingBorderCard>
        </motion.aside>
      )}
    </AnimatePresence>
  )
}
