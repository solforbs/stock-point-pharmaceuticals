import { motion } from 'framer-motion'
import type { ReactNode } from 'react'

/** The heading band each inner page opens with. */
export function PageIntro({ eyebrow, title, body }: { eyebrow: string; title: string; body: ReactNode }) {
  return (
    <section className="relative overflow-hidden border-b border-slate-200/70 bg-white">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(50%_70%_at_50%_0%,rgba(37,99,235,0.08),transparent)]" aria-hidden />
      <motion.div
        initial={{ opacity: 0, y: 14 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.45 }}
        className="relative mx-auto w-full max-w-6xl px-4 py-14 sm:px-6 sm:py-20"
      >
        <span className="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">{eyebrow}</span>
        <h1 className="mt-3 max-w-3xl font-display text-3xl font-bold leading-tight tracking-tight text-slate-900 sm:text-4xl">{title}</h1>
        <p className="mt-4 max-w-2xl text-base leading-relaxed text-slate-600">{body}</p>
      </motion.div>
    </section>
  )
}
