import { AnimatePresence, motion, useReducedMotion } from 'framer-motion'
import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Check, ChevronLeft, ChevronRight } from 'lucide-react'
import { AppPreview } from './AppPreview'
import { CallToAction } from './SiteLayout'
import { HEADLINE_POINTS, HOW_IT_WORKS, MODULES, SLIDES } from './content'
import { CountUp, Reveal, RevealItem } from './motion'

export default function HomePage() {
  return (
    <>
      <HeroSlider />
      <TrustStrip />
      <ModuleShelf />
      <SellingFloor />
      <Warehouse />
      <HowItWorks />
      <CallToAction />
    </>
  )
}

/** The rotating opening panel, in the shape a pharmacy storefront uses. */
function HeroSlider() {
  const quiet = useReducedMotion()
  const [index, setIndex] = useState(0)
  const [paused, setPaused] = useState(false)
  const slide = SLIDES[index]

  const go = useCallback((next: number) => setIndex((next + SLIDES.length) % SLIDES.length), [])

  useEffect(() => {
    if (paused || quiet) return
    const timer = setTimeout(() => go(index + 1), 7000)

    return () => clearTimeout(timer)
  }, [index, paused, quiet, go])

  return (
    <section
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      className="relative overflow-hidden bg-gradient-to-br from-sky-50 via-blue-50 to-cyan-50"
    >
      {/* The soft discs the pharmacy templates float behind the product. */}
      <span className="pointer-events-none absolute -left-24 top-10 h-72 w-72 rounded-full bg-white/50 blur-2xl" aria-hidden />
      <span className="pointer-events-none absolute right-1/3 -top-24 h-80 w-80 rounded-full bg-cyan-200/40 blur-3xl" aria-hidden />

      <div className="relative mx-auto grid w-full max-w-7xl items-center gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-2">
        <div className="min-h-[21rem]">
          <AnimatePresence mode="wait">
            <motion.div
              key={index}
              initial={quiet ? undefined : { opacity: 0, x: -28 }}
              animate={quiet ? undefined : { opacity: 1, x: 0 }}
              exit={quiet ? undefined : { opacity: 0, x: 28 }}
              transition={{ duration: 0.45, ease: [0.22, 1, 0.36, 1] }}
            >
              <span className="inline-flex items-center rounded-md bg-emerald-500 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-white">
                {slide.badge}
              </span>

              <h1 className="mt-5 font-display text-[2.6rem] font-extrabold uppercase leading-[0.98] tracking-tight text-slate-900 sm:text-6xl">
                {slide.title}
                <span className="block text-blue-600">{slide.highlight}</span>
              </h1>

              <p className="mt-5 max-w-xl text-lg leading-relaxed text-slate-600">{slide.body}</p>

              <p className="mt-6 text-slate-500">
                {slide.priceLabel}{' '}
                <span className="font-display text-3xl font-extrabold text-blue-600">{slide.price}</span>
              </p>

              <motion.div whileHover={quiet ? undefined : { y: -2 }} whileTap={{ scale: 0.97 }} className="mt-7 inline-block">
                <Link
                  to="/request-quote"
                  className="group inline-flex items-center gap-2 rounded-lg bg-blue-600 px-8 py-4 text-base font-bold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-700"
                >
                  {slide.cta}
                  <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" aria-hidden />
                </Link>
              </motion.div>
            </motion.div>
          </AnimatePresence>
        </div>

        <div className="relative">
          <AnimatePresence mode="wait">
            <motion.div
              key={index}
              initial={quiet ? undefined : { opacity: 0, scale: 0.94 }}
              animate={quiet ? undefined : { opacity: 1, scale: 1 }}
              exit={quiet ? undefined : { opacity: 0, scale: 1.04 }}
              transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
              className="overflow-hidden rounded-[2rem] bg-white shadow-2xl shadow-blue-900/10"
            >
              <img
                src={`${import.meta.env.BASE_URL}assets/${slide.image}`}
                alt=""
                aria-hidden
                className="h-[19rem] w-full object-cover sm:h-[26rem]"
                loading="eager"
              />
            </motion.div>
          </AnimatePresence>

          <motion.div
            initial={quiet ? undefined : { opacity: 0, y: 20 }}
            animate={quiet ? undefined : { opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.35 }}
            className="absolute -bottom-8 -left-4 hidden w-[20rem] sm:block lg:-left-14 lg:w-[23rem]"
          >
            <AppPreview screen="dashboard" />
          </motion.div>

          <div className="absolute right-3 top-3 flex gap-2 sm:right-4 sm:top-4">
            <SliderButton label="Previous slide" onClick={() => go(index - 1)}>
              <ChevronLeft className="h-5 w-5" aria-hidden />
            </SliderButton>
            <SliderButton label="Next slide" onClick={() => go(index + 1)}>
              <ChevronRight className="h-5 w-5" aria-hidden />
            </SliderButton>
          </div>
        </div>
      </div>

      <div className="relative flex justify-center gap-2 pb-8">
        {SLIDES.map((item, dot) => (
          <button
            key={item.title}
            type="button"
            onClick={() => go(dot)}
            aria-label={`Go to slide ${dot + 1}`}
            aria-current={dot === index}
            className={`h-2 rounded-full transition-all cursor-pointer ${dot === index ? 'w-8 bg-blue-600' : 'w-2 bg-blue-300 hover:bg-blue-400'}`}
          />
        ))}
      </div>
    </section>
  )
}

function SliderButton({ label, onClick, children }: { label: string; onClick: () => void; children: React.ReactNode }) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={label}
      className="flex h-10 w-10 items-center justify-center rounded-full bg-white/85 text-slate-600 shadow-md backdrop-blur transition-colors hover:bg-white hover:text-blue-600 cursor-pointer"
    >
      {children}
    </button>
  )
}

/** The four-up promise strip that sits directly under the slider. */
function TrustStrip() {
  return (
    <section className="border-b border-slate-100 bg-white">
      <Reveal className="mx-auto grid w-full max-w-7xl gap-5 px-4 py-10 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
        {HEADLINE_POINTS.map((point) => (
          <RevealItem
            key={point.title}
            hover
            className="group flex items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 transition-colors hover:border-blue-300 hover:bg-blue-50/40"
          >
            <span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 transition-colors group-hover:bg-blue-600 group-hover:text-white">
              <point.icon className="h-7 w-7" aria-hidden />
            </span>
            <span>
              <span className="block font-display text-base font-bold text-slate-900">{point.title}</span>
              <span className="mt-1 block text-sm leading-relaxed text-slate-500">{point.body}</span>
            </span>
          </RevealItem>
        ))}
      </Reveal>
    </section>
  )
}

/** The module grid, laid out the way a storefront lays out its shelves. */
function ModuleShelf() {
  return (
    <section className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6 sm:py-16">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">What is inside</h2>
          <p className="mt-2 text-lg text-slate-500">Eight modules, one product list, one ledger.</p>
        </div>
        <span className="flex items-center gap-2 rounded-lg bg-blue-50 px-4 py-2.5 text-sm font-bold text-blue-700">
          <span className="h-2 w-2 animate-pulse rounded-full bg-blue-600" aria-hidden />
          Free 7-day trial · no card needed
        </span>
      </div>

      <Reveal className="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4" amount={0.1}>
        {MODULES.map((module) => (
          <RevealItem key={module.key} hover className="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-shadow hover:shadow-xl">
            <span className="text-[11px] font-bold uppercase tracking-wider text-blue-600">{module.points.length} capabilities</span>
            <motion.span
              whileHover={{ rotate: -8, scale: 1.06 }}
              transition={{ type: 'spring', stiffness: 400, damping: 15 }}
              className="mt-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-white group-hover:bg-blue-600"
            >
              <module.icon className="h-6 w-6" aria-hidden />
            </motion.span>
            <h3 className="mt-4 font-display text-lg font-bold text-slate-900">{module.name}</h3>
            <p className="mt-1.5 flex-1 text-sm leading-relaxed text-slate-500">{module.summary}</p>
            <Link to="/features" className="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-blue-600 hover:underline">
              See what it does <ArrowRight className="h-4 w-4" aria-hidden />
            </Link>
          </RevealItem>
        ))}
      </Reveal>
    </section>
  )
}

function SellingFloor() {
  return (
    <section className="border-y border-slate-100 bg-slate-50">
      <div className="mx-auto grid w-full max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2">
        <Reveal>
          <RevealItem>
            <span className="text-sm font-bold uppercase tracking-[0.18em] text-blue-600">At the counter</span>
          </RevealItem>
          <RevealItem>
            <h2 className="mt-3 font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
              A till your cashier learns in an afternoon
            </h2>
          </RevealItem>
          <RevealItem>
            <p className="mt-4 text-lg leading-relaxed text-slate-600">
              Scan or search, take cash or M-PESA, print the receipt. Behind it the batch is allocated first-expired-first-out, the
              stock moves and the sale posts to the books — without anybody thinking about it.
            </p>
          </RevealItem>
          <RevealItem as="div">
            <ul className="mt-6 space-y-3">
              {[
                'Holds, discounts and keyboard shortcuts for a busy counter',
                'Retail and wholesale on the same screen, on different price lists',
                'Sells offline and syncs every receipt when the line returns',
              ].map((item) => (
                <li key={item} className="flex items-start gap-3 text-slate-700">
                  <Check className="mt-1 h-5 w-5 shrink-0 text-emerald-600" aria-hidden />
                  {item}
                </li>
              ))}
            </ul>
          </RevealItem>
          <RevealItem as="div">
            <dl className="mt-8 grid max-w-md grid-cols-3 gap-6 border-t border-slate-200 pt-6">
              {[
                ['8', 'modules, one login'],
                ['60+', 'reports ready to run'],
                ['0', 'sales lost offline'],
              ].map(([figure, label]) => (
                <div key={label}>
                  <dt>
                    <CountUp value={figure} className="font-display text-3xl font-extrabold text-slate-900" />
                  </dt>
                  <dd className="mt-1 text-sm text-slate-500">{label}</dd>
                </div>
              ))}
            </dl>
          </RevealItem>
        </Reveal>

        <motion.div initial={{ opacity: 0, x: 24 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true, amount: 0.3 }} transition={{ duration: 0.6 }}>
          <AppPreview screen="pos" />
        </motion.div>
      </div>
    </section>
  )
}

function Warehouse() {
  return (
    <section className="relative overflow-hidden bg-slate-900">
      <img
        src={`${import.meta.env.BASE_URL}assets/warehouse.jpg`}
        alt=""
        aria-hidden
        className="absolute inset-0 h-full w-full object-cover opacity-25"
        loading="lazy"
      />
      <div className="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/85 to-slate-950/40" aria-hidden />

      <div className="relative mx-auto grid w-full max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2">
        <Reveal>
          <RevealItem>
            <span className="text-sm font-bold uppercase tracking-[0.18em] text-blue-400">In the store</span>
          </RevealItem>
          <RevealItem>
            <h2 className="mt-3 font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">
              You always know what you hold, and when it dies
            </h2>
          </RevealItem>
          <RevealItem>
            <p className="mt-4 text-lg leading-relaxed text-slate-300">
              Every unit carries its batch and expiry from the goods receipt to the customer's hand. Quarantine is real, cold chain
              is logged, and nothing is picked out of order without somebody's name against the override.
            </p>
          </RevealItem>
          <RevealItem as="div">
            <dl className="mt-8 grid grid-cols-3 gap-6 border-t border-white/15 pt-6">
              {[
                ['90 days', 'expiry warning'],
                ['FEFO', 'enforced on picking'],
                ['Per branch', 'stock and prices'],
              ].map(([figure, label]) => (
                <div key={label}>
                  <dt className="font-display text-xl font-bold text-white sm:text-2xl">{figure}</dt>
                  <dd className="mt-1 text-sm text-slate-400">{label}</dd>
                </div>
              ))}
            </dl>
          </RevealItem>
        </Reveal>

        <motion.div initial={{ opacity: 0, y: 24 }} whileInView={{ opacity: 1, y: 0 }} viewport={{ once: true, amount: 0.3 }} transition={{ duration: 0.6 }}>
          <AppPreview screen="stock" />
        </motion.div>
      </div>
    </section>
  )
}

function HowItWorks() {
  return (
    <section className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 sm:py-20">
      <Reveal className="max-w-2xl">
        <RevealItem>
          <h2 className="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">From today to going live</h2>
        </RevealItem>
        <RevealItem>
          <p className="mt-4 text-lg text-slate-600">No long implementation. The longest part is counting what you already have.</p>
        </RevealItem>
      </Reveal>

      <Reveal as="ol" className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4" amount={0.1}>
        {HOW_IT_WORKS.map((step) => (
          <RevealItem key={step.number} as="li" hover className="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-7">
            <span className="absolute -right-3 -top-4 font-display text-7xl font-extrabold text-blue-600/10 transition-colors group-hover:text-blue-600/20">
              {step.number}
            </span>
            <span className="relative font-display text-3xl font-extrabold text-blue-600/40">{step.number}</span>
            <h3 className="relative mt-3 font-display text-lg font-bold text-slate-900">{step.title}</h3>
            <p className="relative mt-2 leading-relaxed text-slate-600">{step.body}</p>
          </RevealItem>
        ))}
      </Reveal>
    </section>
  )
}
