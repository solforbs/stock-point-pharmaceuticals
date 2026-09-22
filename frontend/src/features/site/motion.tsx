import { animate, motion, useInView, useReducedMotion, type Variants } from 'framer-motion'
import { useEffect, useMemo, useRef, useState, type ReactNode } from 'react'

/**
 * The site's motion vocabulary in one place, so every page moves the same way.
 *
 * Everything here folds flat when the visitor has asked for less motion: the
 * content still appears, it simply appears without travelling.
 */

/** A parent that deals its children in, one after the other. */
export const stagger: Variants = {
  hidden: {},
  shown: { transition: { staggerChildren: 0.08, delayChildren: 0.05 } },
}

/** The child of a stagger: up and in. */
export const riseItem: Variants = {
  hidden: { opacity: 0, y: 22 },
  shown: { opacity: 1, y: 0, transition: { duration: 0.55, ease: [0.22, 1, 0.36, 1] } },
}

export const fadeItem: Variants = {
  hidden: { opacity: 0 },
  shown: { opacity: 1, transition: { duration: 0.6 } },
}

/** A card that lifts slightly under the pointer. */
export const lift = {
  whileHover: { y: -4, transition: { duration: 0.2 } },
} as const

/**
 * Reveals its children as they scroll into view, dealing them out in order.
 * Use `as` to keep the right element (a list stays a list).
 */
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

/** One revealed child. Outside a <Reveal> it simply renders. */
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
    <Tag className={className} variants={quiet ? undefined : riseItem} {...(hover && !quiet ? lift : {})}>
      {children}
    </Tag>
  )
}

/**
 * Counts up to a number when it scrolls into view. `value` may carry a
 * prefix or suffix ("60+", "0"); only the digits animate.
 */
export function CountUp({ value, className }: { value: string; className?: string }) {
  const quiet = useReducedMotion()
  const ref = useRef<HTMLSpanElement>(null)
  const inView = useInView(ref, { once: true, amount: 0.6 })
  // Parsed once per value. A fresh match array every render would make the
  // effect below re-run each frame, restarting the count from zero forever.
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
      ease: [0.22, 1, 0.36, 1],
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

/** A slow, endless drift for the decorative blobs behind a hero. */
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
