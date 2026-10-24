import { animate, motion, useInView, useReducedMotion, type Variants } from 'framer-motion'
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react'

/**
 * Universal Motion & Animation Primitives for Stockpoint
 * Based on the Premium Website Design & Animation Guide.
 * 
 * Features:
 * - 5 Core Scroll-Triggered Wrappers (FadeIn, SlideUp, SlideIn, ScaleIn, StaggerContainer)
 * - Micro-interactions (whileHover, whileTap, lift)
 * - Standard Animated Card Primitive with group hover gradient reveal
 * - Respects prefers-reduced-motion automatically
 */

export const EASING_SLIDE = [0.25, 0.46, 0.45, 0.94] as const
export const EASING_BOUNCE = [0.34, 1.56, 0.64, 1] as const

/** 1. FADE IN — opacity only */
export function FadeIn({
  children,
  delay = 0,
  duration = 0.6,
  className = '',
}: {
  children: ReactNode
  delay?: number
  duration?: number
  className?: string
}) {
  const quiet = useReducedMotion()

  return (
    <motion.div
      className={className}
      initial={quiet ? undefined : { opacity: 0 }}
      whileInView={quiet ? undefined : { opacity: 1 }}
      viewport={{ once: true, margin: '-50px' }}
      transition={{ duration, delay, ease: 'easeOut' }}
    >
      {children}
    </motion.div>
  )
}

/** 2. SLIDE UP — moves up from below + fades in */
export function SlideUp({
  children,
  delay = 0,
  duration = 0.6,
  distance = 32,
  className = '',
}: {
  children: ReactNode
  delay?: number
  duration?: number
  distance?: number
  className?: string
}) {
  const quiet = useReducedMotion()

  return (
    <motion.div
      className={className}
      initial={quiet ? undefined : { opacity: 0, y: distance }}
      whileInView={quiet ? undefined : { opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-50px' }}
      transition={{ duration, delay, ease: EASING_SLIDE }}
    >
      {children}
    </motion.div>
  )
}

/** 3. SLIDE IN — slides from left or right + fades in */
export function SlideIn({
  children,
  direction = 'left',
  delay = 0,
  duration = 0.7,
  distance = 50,
  className = '',
}: {
  children: ReactNode
  direction?: 'left' | 'right'
  delay?: number
  duration?: number
  distance?: number
  className?: string
}) {
  const quiet = useReducedMotion()

  return (
    <motion.div
      className={className}
      initial={quiet ? undefined : { opacity: 0, x: direction === 'left' ? -distance : distance }}
      whileInView={quiet ? undefined : { opacity: 1, x: 0 }}
      viewport={{ once: true, margin: '-60px' }}
      transition={{ duration, delay, ease: EASING_SLIDE }}
    >
      {children}
    </motion.div>
  )
}

/** 4. SCALE IN — grows from slightly small with subtle spring bounce */
export function ScaleIn({
  children,
  delay = 0,
  duration = 0.5,
  className = '',
}: {
  children: ReactNode
  delay?: number
  duration?: number
  className?: string
}) {
  const quiet = useReducedMotion()

  return (
    <motion.div
      className={className}
      initial={quiet ? undefined : { opacity: 0, scale: 0.92 }}
      whileInView={quiet ? undefined : { opacity: 1, scale: 1 }}
      viewport={{ once: true, margin: '-50px' }}
      transition={{ duration, delay, ease: EASING_BOUNCE }}
    >
      {children}
    </motion.div>
  )
}

/** 5. STAGGER CONTAINER & ITEM — animates children one by one */
export function StaggerContainer({
  children,
  staggerDelay = 0.08,
  className = '',
}: {
  children: ReactNode
  staggerDelay?: number
  className?: string
}) {
  const quiet = useReducedMotion()

  const containerVariants: Variants = {
    hidden: {},
    visible: { transition: { staggerChildren: staggerDelay, delayChildren: 0.04 } },
  }

  return (
    <motion.div
      className={className}
      initial={quiet ? undefined : 'hidden'}
      whileInView={quiet ? undefined : 'visible'}
      viewport={{ once: true, margin: '-50px' }}
      variants={containerVariants}
    >
      {children}
    </motion.div>
  )
}

const itemVariants: Variants = {
  hidden: { opacity: 0, y: 24 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.5, ease: EASING_SLIDE } },
}

export function StaggerItem({
  children,
  className = '',
}: {
  children: ReactNode
  className?: string
}) {
  const quiet = useReducedMotion()

  return (
    <motion.div className={className} variants={quiet ? undefined : itemVariants}>
      {children}
    </motion.div>
  )
}

/**
 * Standard Animated Card Primitive
 * Follows the guide's card pattern:
 * - group container with overflow-hidden
 * - subtle hover lift and border transition
 * - optional hover gradient reveal
 * - optional ghost number watermark
 */
export function AnimatedCard({
  children,
  ghostNumber,
  className = '',
}: {
  children: ReactNode
  ghostNumber?: string | number
  className?: string
}) {
  const quiet = useReducedMotion()

  return (
    <motion.div
      className={`group relative flex flex-col rounded-2xl border border-slate-200 bg-white p-6 sm:p-7 overflow-hidden transition-colors ${className}`}
      whileHover={
        quiet
          ? undefined
          : {
              y: -5,
              borderColor: 'rgba(37, 99, 235, 0.35)',
              boxShadow: '0 16px 36px rgba(15, 23, 42, 0.06)',
            }
      }
      transition={{ duration: 0.22, ease: 'easeOut' }}
    >
      {/* 1. Subtle Hover Gradient Reveal */}
      <div
        className="pointer-events-none absolute inset-0 bg-gradient-to-br from-blue-500/[0.04] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-300"
        aria-hidden
      />

      {/* 2. Ghost Number Watermark */}
      {ghostNumber !== undefined && (
        <span
          className="pointer-events-none absolute top-4 right-5 font-display font-extrabold text-5xl sm:text-6xl text-slate-900 opacity-[0.03] select-none"
          aria-hidden
        >
          {ghostNumber}
        </span>
      )}

      {/* 3. Card Content */}
      <div className="relative z-10 flex-1 flex flex-col">{children}</div>
    </motion.div>
  )
}

/** Section Border Glow (Divider) */
export function SectionGlowDivider({ className = '' }: { className?: string }) {
  return (
    <div
      className={`h-px w-full bg-gradient-to-r from-transparent via-blue-500/25 to-transparent ${className}`}
      aria-hidden
    />
  )
}

/** Backward-compatible Reveal and RevealItem */
export const stagger: Variants = {
  hidden: {},
  shown: { transition: { staggerChildren: 0.08, delayChildren: 0.05 } },
}

export const riseItem: Variants = {
  hidden: { opacity: 0, y: 22 },
  shown: { opacity: 1, y: 0, transition: { duration: 0.55, ease: EASING_SLIDE } },
}

export function Reveal({
  children,
  className,
  as = 'div',
  amount = 0.2,
}: {
  children: ReactNode
  className?: string
  as?: 'div' | 'ul' | 'ol' | 'section'
  amount?: number
}) {
  const quiet = useReducedMotion()
  const Tag = motion[as]

  return (
    <Tag
      className={className}
      variants={quiet ? undefined : stagger}
      initial={quiet ? undefined : 'hidden'}
      whileInView={quiet ? undefined : 'shown'}
      viewport={{ once: true, amount }}
    >
      {children}
    </Tag>
  )
}

export function RevealItem({
  children,
  className,
  as = 'div',
  hover = false,
}: {
  children: ReactNode
  className?: string
  as?: 'div' | 'li' | 'article'
  hover?: boolean
}) {
  const quiet = useReducedMotion()
  const Tag = motion[as]

  return (
    <Tag
      className={className}
      variants={quiet ? undefined : riseItem}
      {...(hover && !quiet
        ? {
            whileHover: { y: -4, transition: { duration: 0.2 } },
          }
        : {})}
    >
      {children}
    </Tag>
  )
}

/** Animated number count up */
export function CountUp({ value, className }: { value: string; className?: string }) {
  const quiet = useReducedMotion()
  const ref = useRef<HTMLSpanElement>(null)
  const inView = useInView(ref, { once: true, amount: 0.6 })
  const { prefix, target, suffix } = useMemo(() => {
    const match = value.match(/^(\D*)(\d+)(\D*)$/)

    return match
      ? { prefix: match[1], target: Number(match[2]), suffix: match[3] }
      : { prefix: '', target: null as number | null, suffix: '' }
  }, [value])
  const [shown, setShown] = useState(0)

  useEffect(() => {
    if (target === null || !inView || quiet) return
    const controls = animate(0, target, {
      duration: 1.1,
      ease: EASING_SLIDE,
      onUpdate: (latest) => setShown(Math.round(latest)),
    })

    return () => controls.stop()
  }, [inView, target, quiet])

  if (target === null) return <span className={className}>{value}</span>

  return (
    <span ref={ref} className={className}>
      {prefix}
      {quiet ? target : inView ? shown : 0}
      {suffix}
    </span>
  )
}

/** Ambient floating orb */
export function Blob({ className }: { className: string }) {
  const quiet = useReducedMotion()

  return (
    <motion.div
      aria-hidden
      className={`pointer-events-none absolute rounded-full blur-3xl ${className}`}
      animate={quiet ? undefined : { scale: [1, 1.12, 1], opacity: [0.7, 1, 0.7] }}
      transition={{ duration: 11, repeat: Infinity, ease: 'easeInOut' }}
    />
  )
}
