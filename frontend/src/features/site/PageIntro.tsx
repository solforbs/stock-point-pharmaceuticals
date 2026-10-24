import { motion } from 'framer-motion'
import type { ReactNode } from 'react'

/**
 * The heading band each inner page opens with:
 * A sleek dark slate canvas with subtle ambient cyan/blue glows and clean typography.
 */
export function PageIntro({
  eyebrow,
  title,
  body,
}: {
  eyebrow: string
  title: string
  body: ReactNode
  image?: string
}) {
  return (
    <section className="relative overflow-hidden bg-gradient-to-b from-slate-50 via-white to-slate-50/60 border-b border-slate-200/80">
      {/* Subtle modern dot grid */}
      <div
        className="pointer-events-none absolute inset-0 opacity-[0.035]"
        style={{
          backgroundImage: 'radial-gradient(#1e293b 1px, transparent 1px)',
          backgroundSize: '24px 24px',
        }}
        aria-hidden
      />

      {/* Ambient gradient glow */}
      <div
        className="pointer-events-none absolute -left-20 -top-20 h-72 w-72 rounded-full bg-blue-500/10 blur-3xl"
        aria-hidden
      />
      <div
        className="pointer-events-none absolute right-0 bottom-0 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl"
        aria-hidden
      />

      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
        className="relative mx-auto w-full max-w-7xl px-4 py-12 sm:px-6 sm:py-16 text-center flex flex-col items-center"
      >
        <p className="text-xs sm:text-sm font-bold uppercase tracking-wider text-blue-600">
          {eyebrow}
        </p>
        <h1 className="mt-4 max-w-3xl font-display text-3xl font-extrabold leading-[1.15] tracking-tight text-slate-900 sm:text-5xl">
          {title}
        </h1>
        <p className="mt-3.5 max-w-2xl text-base sm:text-lg leading-relaxed text-slate-600">
          {body}
        </p>
      </motion.div>
    </section>
  )
}

