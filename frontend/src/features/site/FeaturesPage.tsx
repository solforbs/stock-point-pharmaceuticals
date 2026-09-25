import { motion } from 'framer-motion'
import { Check } from 'lucide-react'
import { CallToAction } from './SiteLayout'
import { MODULES } from './content'
import { PageIntro } from './PageIntro'
import { Reveal, RevealItem } from './motion'

/** Module by module, what the system actually does. */
export default function FeaturesPage() {
  return (
    <>
      <PageIntro
        eyebrow="Features"
        title="Ten modules, one system"
        body="Hospital, laboratory and pharmacy share one patient record, one product list, one set of prices and one ledger. Nothing is retyped between them, and nothing disagrees."
        image="pills.jpg"
      />

      <section className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6">
        <Reveal className="grid gap-6 md:grid-cols-2" amount={0.1}>
          {MODULES.map((module) => (
            <RevealItem
              key={module.key}
              as="article"
              hover
              className="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs transition-shadow hover:shadow-lg sm:p-7"
            >
              <div className="flex items-start gap-4">
                <motion.span
                  whileHover={{ rotate: -8, scale: 1.06 }}
                  transition={{ type: 'spring', stiffness: 400, damping: 15 }}
                  className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white shadow-md shadow-blue-600/25"
                >
                  <module.icon className="h-5 w-5" aria-hidden />
                </motion.span>
                <div>
                  <h2 className="font-display text-xl font-bold tracking-tight text-slate-900">{module.name}</h2>
                  <p className="mt-1 text-slate-500">{module.summary}</p>
                </div>
              </div>
              <ul className="mt-5 space-y-2.5 border-t border-slate-100 pt-5">
                {module.points.map((point) => (
                  <li key={point} className="flex items-start gap-3 leading-relaxed text-slate-700">
                    <Check className="mt-1 h-5 w-5 shrink-0 text-emerald-600" aria-hidden />
                    {point}
                  </li>
                ))}
              </ul>
            </RevealItem>
          ))}
        </Reveal>
      </section>

      <CallToAction
        title="Want to see a particular module?"
        body="Tell us which part of the business hurts most and we will start the demo there."
      />
    </>
  )
}
