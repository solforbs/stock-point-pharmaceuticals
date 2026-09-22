import { motion, useReducedMotion, useScroll, useTransform } from 'framer-motion'
import { useRef } from 'react'
import { Link } from 'react-router-dom'
import { ArrowRight, Check, PhoneCall } from 'lucide-react'
import { AppPreview } from './AppPreview'
import { CallToAction } from './SiteLayout'
import { HEADLINE_POINTS, HOW_IT_WORKS, MODULES, PHONE, PHONE_HREF } from './content'
import { Blob, CountUp, Reveal, RevealItem, riseItem, stagger } from './motion'

export default function HomePage() {
  return (
    <>
      <Hero />
      <Marquee />
      <Headlines />
      <SellingFloor />
      <Modules />
      <Warehouse />
      <HowItWorks />
      <CallToAction />
    </>
  )
}

function Hero() {
  const quiet = useReducedMotion()
  const ref = useRef<HTMLDivElement>(null)
  const { scrollYProgress } = useScroll({ target: ref, offset: ['start start', 'end start'] })
  const photoY = useTransform(scrollYProgress, [0, 1], ['0%', '14%'])

  return (
    <section ref={ref} className="relative overflow-hidden border-b border-slate-200/70">
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(65%_60%_at_15%_0%,rgba(37,99,235,0.12),transparent)]" aria-hidden />
      <Blob className="-left-28 top-20 h-80 w-80 bg-blue-400/20" />

      <div className="relative mx-auto grid w-full max-w-6xl items-center gap-14 px-4 py-16 sm:px-6 sm:py-24 lg:grid-cols-[1.05fr_1fr]">
        <motion.div variants={quiet ? undefined : stagger} initial={quiet ? undefined : 'hidden'} animate={quiet ? undefined : 'shown'}>
          <motion.span
            variants={quiet ? undefined : riseItem}
            className="inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3.5 py-1.5 text-sm font-semibold text-blue-700"
          >
            <span className="relative flex h-2 w-2">
              <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-blue-500 opacity-70" />
              <span className="relative inline-flex h-2 w-2 rounded-full bg-blue-600" />
            </span>
            Live in Lodwar, built for Kenya
          </motion.span>

          <motion.h1
            variants={quiet ? undefined : riseItem}
            className="mt-6 font-display text-[2.75rem] font-extrabold leading-[1.05] tracking-tight text-slate-900 sm:text-6xl"
          >
            The pharmacy runs itself.
            <span className="block text-blue-600">You run the pharmacy.</span>
          </motion.h1>

          <motion.p variants={quiet ? undefined : riseItem} className="mt-6 max-w-xl text-lg leading-relaxed text-slate-600 sm:text-xl">
            Counter sales, batch and expiry, procurement, the books and the regulator — one system, every branch, and it keeps
            selling when the line goes down.
          </motion.p>

          <motion.div variants={quiet ? undefined : riseItem} className="mt-9 flex flex-col gap-3 sm:flex-row">
            <motion.div whileHover={quiet ? undefined : { y: -2 }} whileTap={{ scale: 0.97 }}>
              <Link
                to="/request-quote"
                className="group inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-7 py-4 text-base font-semibold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-700"
              >
                Request a demo
                <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" aria-hidden />
              </Link>
            </motion.div>
            <motion.a
              href={PHONE_HREF}
              whileHover={quiet ? undefined : { y: -2 }}
              whileTap={{ scale: 0.97 }}
              className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-300 bg-white px-7 py-4 text-base font-semibold text-slate-700 transition-colors hover:border-slate-400 hover:bg-slate-50 sm:w-auto"
            >
              <PhoneCall className="h-4.5 w-4.5 text-blue-600" aria-hidden />
              {PHONE}
            </motion.a>
          </motion.div>

          <motion.dl variants={quiet ? undefined : riseItem} className="mt-12 grid max-w-lg grid-cols-3 gap-6 border-t border-slate-200 pt-7">
            {[
              ['8', 'modules, one login'],
              ['60+', 'reports ready to run'],
              ['0', 'sales lost offline'],
            ].map(([figure, label]) => (
              <div key={label}>
                <dt>
                  <CountUp value={figure} className="font-display text-3xl font-extrabold text-slate-900 sm:text-4xl" />
                </dt>
                <dd className="mt-1 text-sm leading-snug text-slate-500">{label}</dd>
              </div>
            ))}
          </motion.dl>
        </motion.div>

        {/* The photograph carries the trade; the panel in front of it carries the product. */}
        <motion.div
          initial={quiet ? undefined : { opacity: 0, scale: 0.96, y: 26 }}
          animate={quiet ? undefined : { opacity: 1, scale: 1, y: 0 }}
          transition={{ duration: 0.75, ease: [0.22, 1, 0.36, 1], delay: 0.12 }}
          className="relative"
        >
          <div className="overflow-hidden rounded-[2rem] border border-slate-200 bg-slate-100 shadow-2xl shadow-slate-900/15">
            <motion.img
              style={quiet ? undefined : { y: photoY }}
              src={`${import.meta.env.BASE_URL}assets/hero-pharmacist.jpg`}
              alt="A pharmacist checking stock behind the dispensary counter"
              className="h-[26rem] w-full scale-110 object-cover object-center sm:h-[34rem]"
              loading="eager"
            />
            <div className="pointer-events-none absolute inset-0 bg-gradient-to-t from-slate-950/45 via-transparent to-transparent" aria-hidden />
          </div>

          <motion.div
            initial={quiet ? undefined : { opacity: 0, y: 20 }}
            animate={quiet ? undefined : { opacity: 1, y: 0 }}
            transition={{ duration: 0.6, delay: 0.5 }}
            className="absolute -bottom-10 -left-4 w-[22rem] max-w-[92%] sm:-left-16 sm:w-[26rem]"
          >
            <AppPreview screen="dashboard" />
          </motion.div>
        </motion.div>
      </div>
    </section>
  )
}

/** A quiet band of the things the system deals with all day. */
function Marquee() {
  const items = [
    'Batch & expiry', 'FEFO picking', 'M-PESA', 'Wholesale invoices', 'PPB licences', 'eTIMS-ready',
    'Cold chain', 'Three-way match', 'Multi-branch', 'Offline selling',
  ]

  return (
    <div className="mt-24 overflow-hidden border-y border-slate-200 bg-white py-4 sm:mt-12">
      <div className="flex w-max animate-[site-marquee_38s_linear_infinite] gap-10 pr-10 motion-reduce:animate-none">
        {[...items, ...items].map((item, index) => (
          <span key={`${item}-${index}`} className="flex items-center gap-10 whitespace-nowrap text-sm font-semibold uppercase tracking-wider text-slate-400">
            {item}
            <span className="h-1 w-1 rounded-full bg-slate-300" aria-hidden />
          </span>
        ))}
      </div>
    </div>
  )
}

function Headlines() {
  return (
    <section className="mx-auto w-full max-w-6xl px-4 py-20 sm:px-6 sm:py-24">
      <Reveal className="grid gap-6 sm:grid-cols-2">
        {HEADLINE_POINTS.map((point) => (
          <RevealItem
            key={point.title}
            hover
            className="group rounded-2xl border border-slate-200/90 bg-white p-7 shadow-xs transition-shadow hover:shadow-lg"
          >
            <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition-colors group-hover:bg-blue-600 group-hover:text-white">
              <point.icon className="h-6 w-6" aria-hidden />
            </span>
            <h3 className="mt-5 font-display text-xl font-bold tracking-tight text-slate-900">{point.title}</h3>
            <p className="mt-2.5 leading-relaxed text-slate-600">{point.body}</p>
          </RevealItem>
        ))}
      </Reveal>
    </section>
  )
}

/** An editorial row: the till, shown rather than described. */
function SellingFloor() {
  return (
    <section className="border-y border-slate-200/70 bg-white">
      <div className="mx-auto grid w-full max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 sm:py-24 lg:grid-cols-2">
        <Reveal>
          <RevealItem>
            <span className="text-sm font-semibold uppercase tracking-[0.18em] text-blue-600">At the counter</span>
          </RevealItem>
          <RevealItem>
            <h2 className="mt-3 font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
              A till your cashier learns in an afternoon
            </h2>
          </RevealItem>
          <RevealItem>
            <p className="mt-4 text-lg leading-relaxed text-slate-600">
              Scan or search, take cash or M-PESA, print the receipt. Behind it the batch is allocated first-expired-first-out,
              the stock moves, and the sale posts to the books — without anybody thinking about it.
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
        </Reveal>

        <motion.div initial={{ opacity: 0, x: 24 }} whileInView={{ opacity: 1, x: 0 }} viewport={{ once: true, amount: 0.3 }} transition={{ duration: 0.6 }}>
          <AppPreview screen="pos" />
        </motion.div>
      </div>
    </section>
  )
}

function Modules() {
  return (
    <section className="relative overflow-hidden">
      <div className="mx-auto w-full max-w-6xl px-4 py-20 sm:px-6 sm:py-24">
        <Reveal className="max-w-2xl">
          <RevealItem>
            <h2 className="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
              Everything the pharmacy does, in one place
            </h2>
          </RevealItem>
          <RevealItem>
            <p className="mt-4 text-lg text-slate-600">
              Eight modules sharing one product list, one set of prices and one ledger — so a sale at the counter reaches the books
              without anybody retyping it.
            </p>
          </RevealItem>
        </Reveal>

        <Reveal className="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4" amount={0.1}>
          {MODULES.map((module) => (
            <RevealItem
              key={module.key}
              hover
              className="group rounded-2xl border border-slate-200 bg-white p-6 transition-colors hover:border-blue-300 hover:bg-blue-50/40"
            >
              <motion.span
                whileHover={{ rotate: -8, scale: 1.06 }}
                transition={{ type: 'spring', stiffness: 400, damping: 15 }}
                className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-900 text-white group-hover:bg-blue-600"
              >
                <module.icon className="h-5 w-5" aria-hidden />
              </motion.span>
              <h3 className="mt-4 font-display text-lg font-bold text-slate-900">{module.name}</h3>
              <p className="mt-1.5 text-sm leading-relaxed text-slate-500">{module.summary}</p>
            </RevealItem>
          ))}
        </Reveal>

        <div className="mt-10">
          <Link to="/features" className="group inline-flex items-center gap-2 text-base font-semibold text-blue-600 hover:underline">
            The full list, module by module
            <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" aria-hidden />
          </Link>
        </div>
      </div>
    </section>
  )
}

/** The other editorial row: the store, shown on a photograph. */
function Warehouse() {
  return (
    <section className="relative overflow-hidden bg-slate-900">
      <img
        src={`${import.meta.env.BASE_URL}assets/warehouse.jpg`}
        alt="Aisles of a pharmaceutical distribution warehouse"
        className="absolute inset-0 h-full w-full object-cover opacity-25"
        loading="lazy"
      />
      <div className="absolute inset-0 bg-gradient-to-r from-slate-950 via-slate-950/85 to-slate-950/40" aria-hidden />

      <div className="relative mx-auto grid w-full max-w-6xl items-center gap-12 px-4 py-20 sm:px-6 sm:py-24 lg:grid-cols-2">
        <Reveal>
          <RevealItem>
            <span className="text-sm font-semibold uppercase tracking-[0.18em] text-blue-400">In the store</span>
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
    <section className="mx-auto w-full max-w-6xl px-4 py-20 sm:px-6 sm:py-24">
      <Reveal className="max-w-2xl">
        <RevealItem>
          <h2 className="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">From today to going live</h2>
        </RevealItem>
        <RevealItem>
          <p className="mt-4 text-lg text-slate-600">No long implementation. The longest part is counting what you already have.</p>
        </RevealItem>
      </Reveal>

      <Reveal as="ol" className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4" amount={0.1}>
        {HOW_IT_WORKS.map((step) => (
          <RevealItem key={step.number} as="li" hover className="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-7 shadow-xs">
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
