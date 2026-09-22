import { motion, useReducedMotion, useScroll, useTransform } from 'framer-motion'
import { useRef } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Check, CircleDollarSign, Clock3, Sparkles } from 'lucide-react'
import { CallToAction } from './SiteLayout'
import { HEADLINE_POINTS, HOW_IT_WORKS, MODULES, TRUST_POINTS } from './content'
import { Blob, CountUp, Reveal, RevealItem, riseItem, stagger } from './motion'

export default function HomePage() {
  return (
    <>
      <Hero />
      <Headlines />
      <Modules />
      <HowItWorks />
      <Trust />
      <CallToAction />
    </>
  )
}

function Hero() {
  const quiet = useReducedMotion()
  const ref = useRef<HTMLDivElement>(null)
  // The picture drifts a little slower than the page, which gives the hero
  // depth without anything actually moving on its own.
  const { scrollYProgress } = useScroll({ target: ref, offset: ['start start', 'end start'] })
  const imageY = useTransform(scrollYProgress, [0, 1], ['0%', '12%'])

  return (
    <section ref={ref} className="relative overflow-hidden border-b border-slate-200/70">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_60%_at_50%_0%,rgba(37,99,235,0.10),transparent)]" aria-hidden />
      <Blob className="-left-24 top-10 h-72 w-72 bg-blue-400/20" />
      <Blob className="-right-16 bottom-0 h-80 w-80 bg-emerald-400/15" />

      <div className="relative mx-auto grid w-full max-w-6xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-2">
        <motion.div variants={quiet ? undefined : stagger} initial={quiet ? undefined : 'hidden'} animate={quiet ? undefined : 'shown'}>
          <motion.span
            variants={quiet ? undefined : riseItem}
            className="inline-flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700"
          >
            <Sparkles className="h-3.5 w-3.5" aria-hidden />
            Built for Kenyan pharmacy, not adapted to it
          </motion.span>

          <motion.h1
            variants={quiet ? undefined : riseItem}
            className="mt-5 font-display text-4xl font-bold leading-[1.1] tracking-tight text-slate-900 sm:text-5xl"
          >
            Run the whole pharmacy from one system.
          </motion.h1>

          <motion.p variants={quiet ? undefined : riseItem} className="mt-5 max-w-xl text-base leading-relaxed text-slate-600 sm:text-lg">
            Point of sale, stock with batch and expiry, procurement, finance and compliance — in one place, across every branch,
            and still selling when the internet is not.
          </motion.p>

          <motion.div variants={quiet ? undefined : riseItem} className="mt-8 flex flex-col gap-3 sm:flex-row">
            <motion.div whileHover={{ y: -2 }} whileTap={{ scale: 0.97 }} className="sm:w-auto">
              <Link
                to="/request-quote"
                className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-700"
              >
                Request a demo <ArrowRight className="h-4 w-4" aria-hidden />
              </Link>
            </motion.div>
            <motion.div whileHover={{ y: -2 }} whileTap={{ scale: 0.97 }}>
              <Link
                to="/features"
                className="inline-flex w-full items-center justify-center rounded-xl border border-slate-300 bg-white px-6 py-3.5 text-sm font-semibold text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50"
              >
                See what is inside
              </Link>
            </motion.div>
          </motion.div>

          <motion.dl variants={quiet ? undefined : riseItem} className="mt-10 grid max-w-md grid-cols-3 gap-4 border-t border-slate-200 pt-6">
            {[
              ['8', 'modules, one login'],
              ['60+', 'reports ready to run'],
              ['0', 'sales lost offline'],
            ].map(([figure, label]) => (
              <div key={label}>
                <dt>
                  <CountUp value={figure} className="font-display text-2xl font-bold text-slate-900" />
                </dt>
                <dd className="mt-0.5 text-xs leading-snug text-slate-500">{label}</dd>
              </div>
            ))}
          </motion.dl>
        </motion.div>

        <motion.div
          initial={quiet ? undefined : { opacity: 0, scale: 0.96, y: 24 }}
          animate={quiet ? undefined : { opacity: 1, scale: 1, y: 0 }}
          transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1], delay: 0.15 }}
          className="relative"
        >
          <div className="overflow-hidden rounded-3xl border border-slate-200 bg-slate-100 shadow-2xl shadow-slate-900/10">
            <motion.img
              style={quiet ? undefined : { y: imageY }}
              src={`${import.meta.env.BASE_URL}assets/login-hero.jpg`}
              alt="A pharmacy dispensary and warehouse workstation"
              className="h-72 w-full scale-110 object-cover sm:h-96"
            />
          </div>
          <FloatingCard className="-bottom-5 -left-3 sm:-left-8" delay={0.6} float={-6} icon={CircleDollarSign} label="Today's takings" value="KES 128,400" tone="emerald" />
          <FloatingCard className="-top-5 right-2 sm:-right-6" delay={0.85} float={6} icon={Clock3} label="Expiring in 90 days" value="14 batches" tone="amber" />
        </motion.div>
      </div>
    </section>
  )
}

function FloatingCard({
  className,
  icon: Icon,
  label,
  value,
  tone,
  delay,
  float,
}: {
  className: string
  icon: typeof CircleDollarSign
  label: string
  value: string
  tone: 'emerald' | 'amber'
  delay: number
  float: number
}) {
  const quiet = useReducedMotion()
  const tones = { emerald: 'bg-emerald-50 text-emerald-600', amber: 'bg-amber-50 text-amber-600' }

  return (
    <motion.div
      initial={quiet ? undefined : { opacity: 0, scale: 0.9 }}
      animate={quiet ? undefined : { opacity: 1, scale: 1, y: [0, float, 0] }}
      transition={{
        opacity: { duration: 0.4, delay },
        scale: { duration: 0.4, delay },
        y: { duration: 5.5, repeat: Infinity, ease: 'easeInOut', delay },
      }}
      className={`absolute hidden items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3 shadow-xl shadow-slate-900/10 sm:flex ${className}`}
    >
      <span className={`flex h-9 w-9 items-center justify-center rounded-xl ${tones[tone]}`}>
        <Icon className="h-4.5 w-4.5" aria-hidden />
      </span>
      <div>
        <div className="text-[11px] font-medium text-slate-500">{label}</div>
        <div className="font-display text-sm font-bold text-slate-900">{value}</div>
      </div>
    </motion.div>
  )
}

function Headlines() {
  return (
    <section className="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
      <Reveal className="grid gap-6 sm:grid-cols-2">
        {HEADLINE_POINTS.map((point) => (
          <RevealItem
            key={point.title}
            hover
            className="group rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs transition-shadow hover:shadow-lg"
          >
            <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition-colors group-hover:bg-blue-600 group-hover:text-white">
              <point.icon className="h-5 w-5" aria-hidden />
            </span>
            <h3 className="mt-4 font-display text-lg font-semibold tracking-tight text-slate-900">{point.title}</h3>
            <p className="mt-2 text-sm leading-relaxed text-slate-600">{point.body}</p>
          </RevealItem>
        ))}
      </Reveal>
    </section>
  )
}

function Modules() {
  return (
    <section className="border-y border-slate-200/70 bg-white">
      <div className="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
        <Reveal className="max-w-2xl">
          <RevealItem>
            <h2 className="font-display text-3xl font-bold tracking-tight text-slate-900">Everything the pharmacy does, in one place</h2>
          </RevealItem>
          <RevealItem>
            <p className="mt-3 text-slate-600">
              Eight modules that share one product list, one set of prices and one ledger — so a sale at the counter reaches the
              books without anybody retyping it.
            </p>
          </RevealItem>
        </Reveal>

        <Reveal className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4" amount={0.1}>
          {MODULES.map((module) => (
            <RevealItem
              key={module.key}
              hover
              className="group rounded-2xl border border-slate-200 p-5 transition-colors hover:border-blue-300 hover:bg-blue-50/40"
            >
              <motion.span
                whileHover={{ rotate: -8, scale: 1.06 }}
                transition={{ type: 'spring', stiffness: 400, damping: 15 }}
                className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white group-hover:bg-blue-600"
              >
                <module.icon className="h-4.5 w-4.5" aria-hidden />
              </motion.span>
              <h3 className="mt-3.5 font-display text-base font-semibold text-slate-900">{module.name}</h3>
              <p className="mt-1 text-xs leading-relaxed text-slate-500">{module.summary}</p>
            </RevealItem>
          ))}
        </Reveal>

        <div className="mt-8">
          <Link to="/features" className="group inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 hover:underline">
            The full list, module by module
            <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" aria-hidden />
          </Link>
        </div>
      </div>
    </section>
  )
}

function HowItWorks() {
  return (
    <section className="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
      <Reveal className="max-w-2xl">
        <RevealItem>
          <h2 className="font-display text-3xl font-bold tracking-tight text-slate-900">From today to going live</h2>
        </RevealItem>
        <RevealItem>
          <p className="mt-3 text-slate-600">No long implementation. The longest part is counting what you already have.</p>
        </RevealItem>
      </Reveal>

      <Reveal as="ol" className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4" amount={0.1}>
        {HOW_IT_WORKS.map((step) => (
          <RevealItem key={step.number} as="li" hover className="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
            <span className="absolute -right-2 -top-3 font-display text-6xl font-bold text-blue-600/10 transition-colors group-hover:text-blue-600/20">
              {step.number}
            </span>
            <span className="relative font-display text-3xl font-bold text-blue-600/30">{step.number}</span>
            <h3 className="relative mt-2 font-display text-base font-semibold text-slate-900">{step.title}</h3>
            <p className="relative mt-2 text-sm leading-relaxed text-slate-600">{step.body}</p>
          </RevealItem>
        ))}
      </Reveal>
    </section>
  )
}

function Trust() {
  return (
    <section className="border-t border-slate-200/70 bg-white">
      <div className="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
        <div className="grid items-center gap-12 lg:grid-cols-2">
          <Reveal>
            <RevealItem>
              <h2 className="font-display text-3xl font-bold tracking-tight text-slate-900">Safe to hand to your team</h2>
            </RevealItem>
            <RevealItem>
              <p className="mt-3 text-slate-600">
                A system that holds medicines and money has to be careful about both. Permissions decide what each person sees, the
                audit log remembers everything, and your institution's data never mixes with anyone else's.
              </p>
            </RevealItem>
            <RevealItem as="div">
              <ul className="mt-6 space-y-3">
                {[
                  'Roles assigned per branch, down to the single button',
                  'Every posting, void and discount attributed to a person',
                  'Backups taken before every update',
                  'Two-factor sign-in for the accounts that need it',
                ].map((item) => (
                  <li key={item} className="flex items-start gap-2.5 text-sm text-slate-700">
                    <Check className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden />
                    {item}
                  </li>
                ))}
              </ul>
            </RevealItem>
          </Reveal>

          <Reveal className="grid gap-4 sm:grid-cols-2">
            {TRUST_POINTS.map((point) => (
              <RevealItem key={point.title} hover className="rounded-2xl bg-slate-50 p-5 transition-colors hover:bg-blue-50">
                <point.icon className="h-5 w-5 text-blue-600" aria-hidden />
                <h3 className="mt-3 text-sm font-semibold text-slate-900">{point.title}</h3>
                <p className="mt-1 text-xs leading-relaxed text-slate-500">{point.body}</p>
              </RevealItem>
            ))}
          </Reveal>
        </div>
      </div>
    </section>
  )
}
