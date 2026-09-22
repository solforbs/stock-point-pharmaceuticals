import { motion } from 'framer-motion'
import type { ReactNode } from 'react'

/**
  * The heading band each inner page opens with: a photograph behind the
  * darkened plate, so an inner page opens with the trade rather than with a
  * gradient.
  */
export function PageIntro({ eyebrow, title, body, image }: { eyebrow: string; title: string; body: ReactNode; image: string }) {
  return (
    <section className="relative overflow-hidden bg-slate-900">
      <img
        src={`${import.meta.env.BASE_URL}assets/${image}`}
        alt=""
        aria-hidden
        className="absolute inset-0 h-full w-full object-cover opacity-30"
        loading="eager"
      />
      <div className="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/85 to-slate-950/45" aria-hidden />
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
        className="relative mx-auto w-full max-w-6xl px-4 py-20 sm:px-6 sm:py-28"
      >
        <span className="text-sm font-bold uppercase tracking-[0.2em] text-blue-400">{eyebrow}</span>
        <h1 className="mt-4 max-w-3xl font-display text-4xl font-extrabold leading-[1.08] tracking-tight text-white sm:text-5xl">{title}</h1>
        <p className="mt-5 max-w-2xl text-lg leading-relaxed text-slate-300">{body}</p>
      </motion.div>
    </section>
  )
}
