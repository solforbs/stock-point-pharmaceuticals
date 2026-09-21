import { AnimatePresence, motion } from 'framer-motion'
import { ArrowLeft, ArrowRight, Check, Compass, X } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { useTourStore } from './useTourStore'

interface TargetRect {
  top: number
  left: number
  width: number
  height: number
}

export function ProductTour() {
  const { isOpen, currentStepIndex, steps, endTour, nextStep, prevStep, goToStep } = useTourStore()
  const [targetRect, setTargetRect] = useState<TargetRect | null>(null)
  const currentStep = steps[currentStepIndex]
  const navigate = useNavigate()
  const location = useLocation()

  // Ensure user is on the right route for the step
  useEffect(() => {
    if (!isOpen || !currentStep) return
    if (currentStep.route && location.pathname !== currentStep.route) {
      navigate(currentStep.route)
    }
  }, [isOpen, currentStep, location.pathname, navigate])

  // Measure and track target element bounding box
  useEffect(() => {
    if (!isOpen || !currentStep) {
      setTargetRect(null)
      return
    }

    function updateRect() {
      const cleanId = currentStep.targetId.replace(/^#/, '')
      const el = document.getElementById(cleanId) || document.querySelector(currentStep.targetId)
      if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' })
        const rect = el.getBoundingClientRect()
        setTargetRect({
          top: rect.top,
          left: rect.left,
          width: rect.width,
          height: rect.height,
        })
      } else {
        setTargetRect(null)
      }
    }

    // Measure after layout stabilizes
    const timer = setTimeout(updateRect, 120)
    window.addEventListener('resize', updateRect)
    window.addEventListener('scroll', updateRect, true)

    return () => {
      clearTimeout(timer)
      window.removeEventListener('resize', updateRect)
      window.removeEventListener('scroll', updateRect, true)
    }
  }, [isOpen, currentStepIndex, currentStep])

  // Global keyboard shortcuts for tour
  useEffect(() => {
    if (!isOpen) return

    function handleKeyDown(e: KeyboardEvent) {
      if (e.key === 'Escape') {
        endTour()
      } else if (e.key === 'ArrowRight' || e.key === 'Enter') {
        nextStep()
      } else if (e.key === 'ArrowLeft') {
        prevStep()
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [isOpen, nextStep, prevStep, endTour])

  if (!isOpen || !currentStep) return null

  const isFirst = currentStepIndex === 0
  const isLast = currentStepIndex === steps.length - 1
  const padding = 8
  const cardWidth = 380
  const cardHeight = 220

  // Calculate tooltip placement
  let cardTop = 100
  let cardLeft = Math.max(16, Math.min(window.innerWidth - cardWidth - 16, window.innerWidth / 2 - cardWidth / 2))

  if (targetRect) {
    const isMobile = window.innerWidth < 640
    if (isMobile) {
      cardLeft = 16
      cardTop = Math.min(Math.max(16, targetRect.top + targetRect.height + 16), window.innerHeight - cardHeight - 20)
    } else {
      const placement = currentStep.placement ?? 'bottom'
      if (placement === 'bottom') {
        cardTop = Math.min(window.innerHeight - cardHeight - 20, targetRect.top + targetRect.height + 14)
        cardLeft = Math.max(16, Math.min(window.innerWidth - cardWidth - 16, targetRect.left + targetRect.width / 2 - cardWidth / 2))
      } else if (placement === 'top') {
        cardTop = Math.max(16, targetRect.top - cardHeight - 16)
        cardLeft = Math.max(16, Math.min(window.innerWidth - cardWidth - 16, targetRect.left + targetRect.width / 2 - cardWidth / 2))
      } else if (placement === 'right') {
        cardTop = Math.max(16, Math.min(window.innerHeight - cardHeight - 20, targetRect.top + targetRect.height / 2 - cardHeight / 2))
        cardLeft = Math.min(window.innerWidth - cardWidth - 16, targetRect.left + targetRect.width + 16)
      } else {
        cardTop = Math.max(16, Math.min(window.innerHeight - cardHeight - 20, targetRect.top + targetRect.height / 2 - cardHeight / 2))
        cardLeft = Math.max(16, targetRect.left - cardWidth - 16)
      }
    }
  }

  return (
    <AnimatePresence>
      <div className="fixed inset-0 z-50 pointer-events-auto overflow-hidden">
        {/* SVG Mask: Punches a 100% transparent hole directly over targetRect so target is crystal clear */}
        <svg className="fixed inset-0 w-full h-full pointer-events-none z-40">
          <defs>
            <mask id="tour-spotlight-mask">
              {/* White = overlay is visible */}
              <rect x="0" y="0" width="100%" height="100%" fill="white" />
              {/* Black cutout = hole is 100% transparent and reveals the underlying page with zero darkness or blur */}
              {targetRect && (
                <rect
                  x={targetRect.left - padding}
                  y={targetRect.top - padding}
                  width={targetRect.width + padding * 2}
                  height={targetRect.height + padding * 2}
                  rx="14"
                  ry="14"
                  fill="black"
                />
              )}
            </mask>
          </defs>
          {/* Dimmed backdrop covering everything EXCEPT the cutout hole */}
          <rect
            x="0"
            y="0"
            width="100%"
            height="100%"
            fill="rgba(15, 23, 42, 0.65)"
            mask="url(#tour-spotlight-mask)"
            className="pointer-events-auto cursor-pointer"
            onClick={endTour}
          />
        </svg>

        {/* Crisp Spotlight Ring with subtle blue high-tech glow */}
        {targetRect && (
          <motion.div
            layoutId="tour-spotlight-ring"
            transition={{ type: 'spring', damping: 30, stiffness: 350 }}
            className="fixed z-45 rounded-2xl pointer-events-none border-2 border-blue-500 shadow-[0_0_0_4px_rgba(59,130,246,0.3),0_0_28px_rgba(59,130,246,0.3)]"
            style={{
              top: targetRect.top - padding,
              left: targetRect.left - padding,
              width: targetRect.width + padding * 2,
              height: targetRect.height + padding * 2,
            }}
          />
        )}

        {/* Guided Step Card */}
        <motion.div
          initial={{ opacity: 0, y: 12, scale: 0.95 }}
          animate={{ opacity: 1, y: 0, scale: 1 }}
          exit={{ opacity: 0, scale: 0.95 }}
          transition={{ duration: 0.2 }}
          className="fixed z-50 w-[calc(100vw-32px)] sm:w-[380px] bg-white rounded-2xl shadow-2xl border border-slate-200/90 p-5 overflow-hidden"
          style={{ top: `${cardTop}px`, left: `${cardLeft}px` }}
        >
          {/* Header */}
          <div className="flex items-center justify-between gap-3 mb-3">
            <div className="flex items-center gap-2">
              <span className="flex items-center justify-center w-7 h-7 rounded-xl bg-blue-50 text-blue-600">
                <Compass size={16} />
              </span>
              <span className="text-xs font-bold uppercase tracking-wider text-blue-600 bg-blue-50 px-2 py-0.5 rounded-full">
                Step {currentStepIndex + 1} of {steps.length}
              </span>
            </div>
            <button
              onClick={endTour}
              aria-label="Exit tour"
              className="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
            >
              <X size={16} />
            </button>
          </div>

          {/* Title & Body */}
          <h3 className="text-base font-bold text-slate-900 leading-snug mb-1.5 flex items-center gap-1.5">
            {currentStep.title}
          </h3>
          <p className="text-xs text-slate-600 leading-relaxed mb-5">
            {currentStep.description}
          </p>

          {/* Footer Controls */}
          <div className="flex items-center justify-between gap-2 pt-3 border-t border-slate-100">
            {/* Step indicator dots */}
            <div className="flex items-center gap-1.5">
              {steps.map((_, idx) => (
                <button
                  key={idx}
                  onClick={() => goToStep(idx)}
                  className={`h-1.5 rounded-full transition-all cursor-pointer ${
                    idx === currentStepIndex
                      ? 'w-5 bg-blue-600'
                      : 'w-1.5 bg-slate-200 hover:bg-slate-300'
                  }`}
                  aria-label={`Go to step ${idx + 1}`}
                />
              ))}
            </div>

            <div className="flex items-center gap-2">
              {!isFirst && (
                <button
                  onClick={prevStep}
                  className="px-2.5 py-1.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-semibold inline-flex items-center gap-1 cursor-pointer transition-colors"
                >
                  <ArrowLeft size={13} /> Back
                </button>
              )}

              <button
                onClick={nextStep}
                className="px-3.5 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold inline-flex items-center gap-1.5 shadow-md shadow-blue-500/20 cursor-pointer transition-all"
              >
                {isLast ? (
                  <>
                    <Check size={14} /> Finish
                  </>
                ) : (
                  <>
                    Next <ArrowRight size={13} />
                  </>
                )}
              </button>
            </div>
          </div>
        </motion.div>
      </div>
    </AnimatePresence>
  )
}
