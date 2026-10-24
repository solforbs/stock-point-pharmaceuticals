import { useState } from 'react'
import { Link } from 'react-router-dom'
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion'
import {
  ArrowRight,
  BarChart3,
  Boxes,
  Check,
  Receipt,
  Smartphone,
} from 'lucide-react'
import { AppPreview } from './AppPreview'
import { CallToAction } from './SiteLayout'
import { HEADLINE_POINTS, HOW_IT_WORKS } from './content'

export default function HomePage() {
  return (
    <div className="bg-white">
      <HeroSection />
      <TrustStrip />
      <Coverage />
      <FacilityWorkflowExplorer />
      <ModuleSuiteGrid />
      <HowItWorksSection />
      <CallToAction />
    </div>
  )
}

const HERO = {
  badge: 'Built for Kenyan Retail & Wholesale Pharmacies',
  titleLead: 'The Pharmacy POS & Inventory System',
  titleHighlight: 'That Never Stops Selling',
  subtitle:
    'Rings up sales in seconds even when internet drops, stops expired drug losses with automatic FEFO alerts, and balances your M-Pesa till to the exact shilling every evening.',
  primaryCtaText: 'Explore System Features',
  primaryCtaLink: '#modules',
  secondaryCtaText: 'Start 1-Month Free Trial',
  secondaryCtaLink: '/request-quote',
}

/** The modern SaaS Hero Section with side-by-side app preview and official Kenyan regulatory badges */
function HeroSection() {
  const [activeTab, setActiveTab] = useState<'dashboard' | 'mobile' | 'stock' | 'pos'>('dashboard')

  const current = HERO

  const TABS = [
    { id: 'dashboard' as const, label: 'Executive Dashboard', icon: BarChart3 },
    { id: 'mobile' as const, label: 'Mobile & Tablet POS', icon: Smartphone },
    { id: 'stock' as const, label: 'Batches & FEFO', icon: Boxes },
    { id: 'pos' as const, label: 'Till & Invoices', icon: Receipt },
  ]

  return (
    <section
      className="relative overflow-hidden bg-gradient-to-b from-slate-50 via-white to-slate-50/50 pt-6 pb-12 sm:pt-8 sm:pb-16 border-b border-slate-200/80"
    >
      {/* Subtle modern grid pattern */}
      <div
        className="pointer-events-none absolute inset-0 opacity-[0.035]"
        style={{
          backgroundImage: 'radial-gradient(#1e293b 1px, transparent 1px)',
          backgroundSize: '24px 24px',
        }}
        aria-hidden
      />

      <div className="relative mx-auto max-w-7xl px-4 sm:px-6">
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-8 items-center">

          {/* Left Column: Value Prop, CTAs & Kenyan Regulatory Trust */}
          <div className="lg:col-span-5 xl:col-span-5 flex flex-col justify-center">
            {/* Clean natural text eyebrow */}
            <div className="inline-flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">
              <span className="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" aria-hidden />
              <span>{current.badge}</span>
              <span className="text-slate-300">•</span>
              <span className="font-bold text-blue-600">1-Month Free Trial</span>
            </div>

            {/* Headline with Space Grotesk font */}
            <h1 className="mt-3.5 font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl xl:text-5xl lg:leading-[1.12]">
              <span>{current.titleLead}</span>{' '}
              <span className="text-blue-600 block">{current.titleHighlight}</span>
            </h1>

            {/* Plain English Subtitle */}
            <p className="mt-3 text-sm sm:text-base leading-relaxed text-slate-600">
              {current.subtitle}
            </p>

            {/* Direct CTAs */}
            <div className="mt-5 flex flex-wrap items-center gap-3">
              <a
                href="#modules"
                className="group inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition-all hover:bg-blue-700 hover:shadow-xl hover:shadow-blue-600/25 cursor-pointer"
              >
                <span>Explore Modules</span>
                <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" aria-hidden />
              </a>

              <Link
                to="/request-quote"
                className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-xs transition-colors hover:bg-slate-50 hover:text-slate-900"
              >
                <span>Start 1-Month Free Trial</span>
              </Link>
            </div>

            {/* Official Kenyan Regulatory & Tax Trust Bar */}
            <div className="mt-6 pt-5 border-t border-slate-200/80">
              <p className="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                Official Kenyan Regulatory & Tax Integrations
              </p>

              <div className="mt-2.5 flex flex-wrap items-center gap-2 sm:gap-2.5">
                {/* PPB Logo */}
                <div className="flex items-center gap-2 rounded-xl border border-slate-200/90 bg-white px-2.5 py-1.5 shadow-2xs hover:border-blue-300 transition-colors" title="Pharmacy and Poisons Board Kenya Compliant">
                  <img
                    src="/pharmacypoison.png"
                    alt="Pharmacy and Poisons Board Kenya (PPB)"
                    className="h-6 sm:h-7 w-auto object-contain"
                  />
                </div>

                {/* KRA Logo */}
                <div className="flex items-center gap-2 rounded-xl border border-slate-200/90 bg-white px-2.5 py-1.5 shadow-2xs hover:border-blue-300 transition-colors" title="Kenya Revenue Authority Integration">
                  <img
                    src="/kra.png"
                    alt="Kenya Revenue Authority (KRA)"
                    className="h-6 sm:h-7 w-auto object-contain"
                  />
                </div>

                {/* eTIMS Logo */}
                <div className="flex items-center gap-2 rounded-xl border border-slate-200/90 bg-white px-2.5 py-1.5 shadow-2xs hover:border-blue-300 transition-colors" title="KRA eTIMS Tax Invoicing">
                  <img
                    src="/etims.png"
                    alt="KRA eTIMS Electronic Tax Invoicing"
                    className="h-6 sm:h-7 w-auto object-contain"
                  />
                </div>
              </div>

              {/* Natural trust indicators */}
              <div className="mt-3.5 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs font-medium text-slate-500">
                <span className="flex items-center gap-1.5">
                  <Check className="h-4 w-4 text-emerald-600" />
                  100% Offline Selling
                </span>
                <span className="text-slate-300">•</span>
                <span className="flex items-center gap-1.5">
                  <Check className="h-4 w-4 text-emerald-600" />
                  FEFO Expiry Picking
                </span>
                <span className="text-slate-300">•</span>
                <span className="flex items-center gap-1.5">
                  <Check className="h-4 w-4 text-emerald-600" />
                  M-Pesa Balanced
                </span>
              </div>
            </div>
          </div>

          {/* Right Column: Interactive App Preview with Tabs */}
          <div className="lg:col-span-7 xl:col-span-7 w-full max-w-full">
            {/* Clean Segmented Tab Switcher with smooth mobile horizontal scroll */}
            <div className="mb-3 flex justify-start sm:justify-end overflow-x-auto pb-1 scrollbar-none">
              <div className="inline-flex p-1 rounded-xl bg-slate-100/90 border border-slate-200/80 gap-1 shrink-0 max-w-full">
                {TABS.map((tab) => {
                  const isSelected = activeTab === tab.id
                  return (
                    <button
                      key={tab.id}
                      type="button"
                      onClick={() => setActiveTab(tab.id)}
                      className={`relative flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition-all cursor-pointer whitespace-nowrap ${
                        isSelected
                          ? 'text-slate-900'
                          : 'text-slate-500 hover:text-slate-800'
                      }`}
                    >
                      {isSelected && (
                        <motion.span
                          layoutId="hero-active-tab"
                          className="absolute inset-0 bg-white rounded-lg shadow-xs border border-slate-200/60"
                          transition={{ type: 'spring', stiffness: 450, damping: 32 }}
                        />
                      )}
                      <tab.icon className={`relative z-10 h-3.5 w-3.5 ${isSelected ? 'text-blue-600' : 'text-slate-400'}`} />
                      <span className="relative z-10">{tab.label}</span>
                    </button>
                  )
                })}
              </div>
            </div>

            {/* Elevated App Preview Frame */}
            <div className="rounded-2xl border border-slate-200/90 bg-white p-1.5 sm:p-2.5 shadow-2xl shadow-blue-900/10 ring-1 ring-slate-900/5 w-full max-w-full overflow-hidden">
              <AnimatePresence mode="wait">
                <motion.div
                  key={activeTab}
                  initial={{ opacity: 0, scale: 0.99 }}
                  animate={{ opacity: 1, scale: 1 }}
                  exit={{ opacity: 0, scale: 0.99 }}
                  transition={{ duration: 0.2, ease: 'easeOut' }}
                >
                  <AppPreview screen={activeTab} />
                </motion.div>
              </AnimatePresence>
            </div>
          </div>

        </div>
      </div>
    </section>
  )
}


/** The four-up promise strip that sits directly under the hero. */
function TrustStrip() {
  return (
    <section className="border-b border-slate-200/80 bg-white">
      <div className="mx-auto grid w-full max-w-7xl gap-5 px-4 py-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-4">
        {HEADLINE_POINTS.map((point) => (
          <div
            key={point.title}
            className="group flex items-start gap-4 rounded-2xl border border-slate-200/90 bg-white p-5 transition-all hover:border-blue-300 hover:shadow-md hover:shadow-blue-900/5"
          >
            <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition-colors group-hover:bg-blue-600 group-hover:text-white">
              <point.icon className="h-6 w-6" aria-hidden />
            </span>
            <div>
              <span className="block font-display text-base font-bold text-slate-900">{point.title}</span>
              <span className="mt-1 block text-sm leading-relaxed text-slate-500">{point.body}</span>
            </div>
          </div>
        ))}
      </div>
    </section>
  )
}

/** Where the system fits in the Kenyan pharmacy trade */
function Coverage() {
  const INSTITUTIONS = [
    {
      id: 'chemists',
      category: 'Retail Pharmacy',
      borderHover: 'hover:border-blue-300',
      title: 'Retail Chemists & Community Pharmacies',
      tagline: 'Fast Walk-in Counters',
      desc: 'Never let network loss or power cuts stop your sales. Scan barcodes, sell older batches first so drugs do not expire, and reconcile cash and M-Pesa automatically.',
      highlights: [
        '100% offline till that keeps selling without internet',
        'FEFO batch alerts stop expired drug waste on shelves',
        'Auto M-Pesa & cash balancing with instant receipts',
      ],
      link: '/request-quote',
      linkText: 'Explore Retail Features',
    },
    {
      id: 'chains',
      category: 'Multi-Branch Operations',
      borderHover: 'hover:border-emerald-300',
      title: 'Pharmacy Chains & Branch Networks',
      tagline: 'From 2 to 50 Branches',
      desc: 'See sales, daily profits, and stock levels across every branch in one central dashboard. Move stock between outlets with signed digital transfer notes.',
      highlights: [
        'Separate cashier permissions and prices per branch',
        'Inter-branch stock transfer with dispatch & receiving check',
        'Live owner dashboard accessible from your phone or PC',
      ],
      link: '/request-quote',
      linkText: 'Explore Multi-Branch',
    },
    {
      id: 'wholesalers',
      category: 'Wholesale Distribution',
      borderHover: 'hover:border-amber-300',
      title: 'Pharmaceutical Wholesalers & Depots',
      tagline: 'Suppliers & Distributors',
      desc: 'Serve retail chemists with tiered wholesale pricing, track credit limits so clients do not exceed terms, and verify supplier invoices against delivery notes.',
      highlights: [
        'Custom price lists and automated credit limits per client',
        'Printed delivery notes, gate passes, and packing lists',
        'Match delivery vs supplier invoice before paying',
      ],
      link: '/request-quote',
      linkText: 'Explore Wholesale Features',
    },
  ]

  return (
    <section className="border-b border-slate-200/80 bg-slate-50/70 py-16 sm:py-20">
      <div className="mx-auto w-full max-w-7xl px-4 sm:px-6">
        {/* Section Header with Real Product Capability Stats */}
        <div className="flex flex-col md:flex-row md:items-end justify-between gap-6">
          <div className="max-w-2xl">
            <p className="text-xs font-bold uppercase tracking-wider text-blue-600">
              Tailored For Your Setup
            </p>
            <h2 className="mt-2 font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
              Whether You Run a Single Chemist or a 10-Branch Chain
            </h2>
            <p className="mt-2 text-base text-slate-600 leading-relaxed">
              Every pharmacy business has unique needs. Stockpoint gives each counter the exact tools they need to dispense quickly and manage stock cleanly.
            </p>
          </div>

          <div className="flex items-center gap-3 shrink-0">
            <div className="flex items-center gap-3 rounded-2xl bg-white border border-slate-200/90 px-4 py-2.5 shadow-xs">
              <img
                src="/etims.png"
                alt="KRA eTIMS Invoicing"
                className="h-7 w-auto object-contain"
              />
              <div>
                <div className="text-xs font-bold text-slate-900">KRA Invoicing</div>
                <div className="text-[11px] text-slate-500">16% VAT & QR receipts</div>
              </div>
            </div>

            <div className="flex items-center gap-3 rounded-2xl bg-white border border-slate-200/90 px-4 py-3 shadow-xs">
              <div className="font-extrabold text-sm text-emerald-700">
                100%
              </div>
              <div>
                <div className="text-xs font-bold text-slate-900">Offline Resilient</div>
                <div className="text-[11px] text-slate-500">Zero sales lost at till</div>
              </div>
            </div>
          </div>
        </div>

        {/* 3 Clear Pharmacy Customer Cards */}
        <div className="mt-10 grid gap-6 md:grid-cols-3">
          {INSTITUTIONS.map((item) => (
            <div
              key={item.id}
              className={`group flex flex-col justify-between rounded-3xl border border-slate-200 bg-white p-7 shadow-xs transition-all duration-300 ${item.borderHover} hover:shadow-xl hover:-translate-y-1`}
            >
              <div>
                {/* Category Header */}
                <p className="text-xs font-bold uppercase tracking-wider text-blue-600">
                  {item.tagline}
                </p>

                {/* Title */}
                <h3 className="mt-2 font-display text-lg font-extrabold text-slate-900 group-hover:text-blue-600 transition-colors">
                  {item.title}
                </h3>

                {/* Summary */}
                <p className="mt-2 text-xs text-slate-500 leading-relaxed">
                  {item.desc}
                </p>

                {/* Key Capability Highlights */}
                <div className="mt-4 space-y-2 border-t border-slate-100 pt-3.5">
                  {item.highlights.map((hl, idx) => (
                    <div key={idx} className="flex items-start gap-2 text-xs text-slate-600">
                      <Check className="h-3.5 w-3.5 shrink-0 text-blue-600 mt-0.5" aria-hidden />
                      <span>{hl}</span>
                    </div>
                  ))}
                </div>
              </div>

              {/* Bottom Action */}
              <div className="mt-6 border-t border-slate-100 pt-3.5 flex items-center justify-between">
                <Link
                  to={item.link}
                  className="inline-flex items-center gap-1.5 text-xs font-bold text-blue-600 group-hover:text-blue-700 hover:underline"
                >
                  <span>{item.linkText}</span>
                  <ArrowRight className="h-3.5 w-3.5 transition-transform group-hover:translate-x-1" aria-hidden />
                </Link>
                <span className="text-[11px] font-semibold text-slate-400">
                  {item.category}
                </span>
              </div>
            </div>
          ))}
        </div>

        {/* Regulatory & Systems Compliance Ribbon */}
        <div className="mt-8 rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-xs flex flex-col md:flex-row items-center justify-between gap-4">
          <div className="flex items-center gap-3 text-xs sm:text-sm text-slate-600 text-center sm:text-left">
            <span className="flex h-2 w-2 shrink-0 rounded-full bg-blue-600" aria-hidden />
            <div>
              <span className="font-bold text-slate-900">Built for Kenyan Health Facilities: </span>
              <span className="text-slate-500">Standards-compliant for community chemists, dispensing pharmacies, pharmacy chains, and pharmaceutical distributors.</span>
            </div>
          </div>
          <div className="flex flex-wrap items-center justify-center gap-3 text-xs font-semibold text-slate-600 shrink-0">
            <span>KRA eTIMS Ready</span>
            <span className="text-slate-300">•</span>
            <span>PPB Audit Standards</span>
            <span className="text-slate-300">•</span>
            <span className="text-emerald-700 font-bold">M-Pesa & Cash Tracking</span>
          </div>
        </div>
      </div>
    </section>
  )
}

/**
 * Clean, high-converting 3-part visual story:
 * 1. The Counter (Till that sells offline & balances M-Pesa)
 * 2. The Shelves (Zero expired stock via FEFO alerts)
 * 3. The Owner (Live cash, profits & supplier debts)
 * Uses fluid scroll slide-in animations (left/right stagger).
 */
function FacilityWorkflowExplorer() {
  const quiet = useReducedMotion()

  const STORIES = [
    {
      id: 'counter',
      tag: 'Step 1 · Counter & Checkout',
      headline: 'Rings Up Customers in Seconds — Even Without Internet',
      summary:
        'When power drops or Safaricom cuts out, your pharmacy till keeps right on selling. Scan barcodes, accept cash or M-Pesa, and hand over a clean receipt with zero waiting time.',
      points: [
        '100% offline selling: saves every transaction locally and auto-syncs when you are reconnected.',
        'Direct M-Pesa STK push and code entry: your end-of-day till balances to the exact shilling.',
        'Barcode search & instant generic alternatives: never lose a customer because an exact brand is out of stock.',
      ],
      previewScreen: 'pos' as const,
      reversed: false,
    },
    {
      id: 'shelves',
      tag: 'Step 2 · Batch & Stock Control',
      headline: 'Stop Throwing Expired Medicine in the Waste Bin',
      summary:
        'Expired medicines eat away thousands of shillings each month. Stockpoint enforces First-Expired First-Out (FEFO) so your staff automatically dispense the oldest safe stock first.',
      points: [
        'Automated FEFO picking: the till warns cashiers if they pick a batch that is not the earliest expiring.',
        '90-day expiry early warning dashboard: discount or return stock to distributors before it goes dead.',
        'Low-stock alerts: see exactly what you need to reorder based on real daily sales velocity.',
      ],
      previewScreen: 'stock' as const,
      reversed: true,
    },
    {
      id: 'owner',
      tag: 'Step 3 · Owner Control & Books',
      headline: 'See Your Real Daily Profits and Cash From Anywhere',
      summary:
        'No more staying at the pharmacy until 10 PM doing manual paperwork. Check your gross margins, cash in hand, and unpaid distributor bills straight from your phone or laptop.',
      points: [
        'Live owner dashboard: check today’s sales, gross margins, and till cash across any branch anytime.',
        'Distributor debt tracking: know exactly what you owe Harley’s, Laborex, or Phillips before ordering.',
        '1-click PPB & KRA compliance: pre-formatted inspection reports, batch audit trails, and eTIMS QR receipts.',
      ],
      previewScreen: 'dashboard' as const,
      reversed: false,
    },
  ]

  return (
    <section className="mx-auto w-full max-w-7xl px-4 py-20 sm:px-6 sm:py-28">
      {/* Clean, Non-Technical Section Header */}
      <div className="text-center max-w-3xl mx-auto">
        <p className="text-xs sm:text-sm font-bold uppercase tracking-wider text-blue-600">
          How It Actually Works
        </p>
        <h2 className="mt-3 font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl lg:text-5xl">
          Everything Your Pharmacy Needs. Zero Complexity.
        </h2>
        <p className="mt-4 text-base sm:text-lg text-slate-600 leading-relaxed">
          From the moment a patient walks up to your counter to your evening cash reconciliation, Stockpoint keeps your staff fast and your money safe.
        </p>
      </div>

      {/* 3 Alternating Story Rows with Scroll Slide-in Animations */}
      <div className="mt-16 sm:mt-24 space-y-24 sm:space-y-32">
        {STORIES.map((story) => (
          <div
            key={story.id}
            className={`grid gap-10 lg:grid-cols-12 items-center ${
              story.reversed ? 'lg:grid-flow-dense' : ''
            }`}
          >
            {/* Story Text Column with Slide-In */}
            <motion.div
              initial={quiet ? undefined : { opacity: 0, x: story.reversed ? 32 : -32 }}
              whileInView={quiet ? undefined : { opacity: 1, x: 0 }}
              viewport={{ once: true, margin: '-100px' }}
              transition={{ duration: 0.5, ease: [0.22, 1, 0.36, 1] }}
              className={`lg:col-span-5 space-y-5 ${story.reversed ? 'lg:col-start-8' : ''}`}
            >
              <p className="text-xs font-bold uppercase tracking-wider text-blue-600">
                {story.tag}
              </p>

              <h3 className="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight leading-snug">
                {story.headline}
              </h3>

              <p className="text-base text-slate-600 leading-relaxed">
                {story.summary}
              </p>

              <div className="space-y-3 pt-2">
                {story.points.map((pt, idx) => (
                  <div key={idx} className="flex items-start gap-3">
                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 mt-0.5">
                      <Check className="h-3.5 w-3.5" />
                    </span>
                    <span className="text-sm text-slate-700 leading-relaxed">{pt}</span>
                  </div>
                ))}
              </div>
            </motion.div>

            {/* Interactive Real Screen Column with Slide-In */}
            <motion.div
              initial={quiet ? undefined : { opacity: 0, x: story.reversed ? -32 : 32, scale: 0.98 }}
              whileInView={quiet ? undefined : { opacity: 1, x: 0, scale: 1 }}
              viewport={{ once: true, margin: '-100px' }}
              transition={{ duration: 0.55, ease: [0.22, 1, 0.36, 1] }}
              className={`lg:col-span-7 ${story.reversed ? 'lg:col-start-1' : ''}`}
            >
              <div className="rounded-3xl border border-slate-200/90 bg-slate-50/70 p-2 sm:p-3.5 shadow-xl shadow-slate-900/5">
                <AppPreview screen={story.previewScreen} />
              </div>
            </motion.div>
          </div>
        ))}
      </div>
    </section>
  )
}

interface CapabilityPillar {
  id: 'sourcing' | 'transfers' | 'wholesale' | 'ppb' | 'finance'
  tabLabel: string
  badge: string
  headline: string
  summary: string
  metrics: { label: string; value: string }[]
  points: string[]
}

const CAPABILITY_PILLARS: CapabilityPillar[] = [
  {
    id: 'sourcing',
    tabLabel: 'Supplier Sourcing & Quotes',
    badge: 'Smart Sourcing & Margin Defense',
    headline: 'Compare distributor quotes side-by-side. Never overpay for pharmaceuticals.',
    summary:
      'Send requests for quotation (RFQs) to multiple distributors (e.g. Harley’s, Laborex, Phillips) in one go. The system highlights the lowest price per drug so you protect your gross margins before placing orders.',
    metrics: [
      { label: 'Average purchasing savings', value: '14%' },
      { label: 'Quote comparison speed', value: '3 mins' },
      { label: 'Delivery bill verification', value: '100%' },
    ],
    points: [
      'Multi-distributor RFQ dispatch with automated comparative bid analysis (CBA)',
      'Automated lowest-price recommendation to safeguard your medicine margins',
      'Delivery note verification against approved purchase orders to stop overbilling',
      'Free bonus pack tracking to calculate true landed cost per tablet or bottle',
    ],
  },
  {
    id: 'transfers',
    tabLabel: 'Multi-Branch & Transfers',
    badge: 'Chain & Wholesale Control',
    headline: 'Move stock across branches with digital dispatch and verified receipts.',
    summary:
      'Whether you operate 2 counters or a network across Kenya, track every pack in transit. Stop inventory shrinkage with audited dispatch notes, QR seals, and receiving branch confirmations.',
    metrics: [
      { label: 'Transit loss reduction', value: 'Zero' },
      { label: 'Live branch visibility', value: 'Instant' },
      { label: 'Dispatch chain of custody', value: 'Audited' },
    ],
    points: [
      'Real-time stock visibility across all branches and central warehouses from one screen',
      'Digital dispatch notes: stock only moves when approved by the dispatching pharmacist',
      'Receiving branch barcode confirmation before goods are added to local active stock',
      'Consolidated owner reports show sales and stock per branch or for the entire group',
    ],
  },
  {
    id: 'wholesale',
    tabLabel: 'Retail & Wholesale Dual-Mode',
    badge: 'Flexible Selling Architecture',
    headline: 'Sell loose strips to walk-ins and bulk cartons to wholesale clinics.',
    summary:
      'Stockpoint automatically separates retail dispensing units from wholesale outer packaging, calculates contracted tier discounts, and exports formatted Excel price lists in one click.',
    metrics: [
      { label: 'Dual packaging per SKU', value: 'Yes' },
      { label: 'Client tier pricing', value: 'Automated' },
      { label: 'Excel catalogue export', value: '1 Click' },
    ],
    points: [
      'Unified packaging engine: track cartons, boxes, and blister strips under a single SKU',
      'Custom customer price tiers: automatically apply contracted wholesale or clinic rates',
      'Instant Excel price list export for distributing your wholesale catalogue to buyers',
      'Credit limit enforcement: automatically blocks sales when wholesale credit terms expire',
    ],
  },
  {
    id: 'ppb',
    tabLabel: 'PPB Audit & 12 SOPs',
    badge: 'Kenyan Regulatory Defense',
    headline: 'Ready for PPB inspection on day one. Pre-loaded with official SOPs.',
    summary:
      'Pharmacy and Poisons Board audits don’t have to be stressful. Stockpoint comes pre-loaded with 12 official standard operating procedures, licence renewal countdowns, and tamper-proof registers.',
    metrics: [
      { label: 'Pre-loaded Kenyan SOPs', value: '12 / 12' },
      { label: 'Licence countdown alerts', value: 'Active' },
      { label: 'Inspection audit log', value: 'Tamper-proof' },
    ],
    points: [
      '12 pre-loaded Kenyan pharmacy SOPs (Cold chain, safe disposal, dispensing, recalls)',
      'Premise licence and superintendent practicing licence renewal countdown timers',
      'Controlled substance prescription register with prescriber and patient tracking',
      'Safe medicine disposal register: generate official destruction certificates easily',
    ],
  },
  {
    id: 'finance',
    tabLabel: 'Books, Debts & KRA Tax',
    badge: 'Automated Pharmacy Accounts',
    headline: 'Double-entry books that write themselves from your daily counter sales.',
    summary:
      'Eliminate manual bookkeeping. Every customer sale, M-Pesa payment, and supplier delivery automatically updates your profit & loss, customer credit balances, and KRA 16% VAT ledger.',
    metrics: [
      { label: 'Manual bookkeeping', value: '0 Hours' },
      { label: 'KRA eTIMS VAT filing', value: 'Ready' },
      { label: 'Evening profit reports', value: '9:00 PM' },
    ],
    points: [
      'Automatic 16% VAT and exempt medicine categorization for accurate tax filing',
      'Supplier Accounts Payable ledger: know exactly which distributor bills are due this week',
      'Customer debt tracker with payment history and credit aging analysis',
      'Daily net profit and gross margin reports delivered to the owner’s email every evening',
    ],
  },
]

interface ModuleScreenshotProps {
  src: string
  alt: string
  route: string
  title: string
  badgeText: string
  badgeColor?: string
}

function ModuleScreenshotPreview({
  src,
  alt,
  route,
  title,
  badgeText,
  badgeColor = 'text-blue-700 bg-blue-50 border-blue-200/70',
}: ModuleScreenshotProps) {
  return (
    <div className="w-full max-w-full overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-xl shadow-slate-900/5 transition-all">
      {/* Top Application Browser Bar */}
      <div className="flex items-center justify-between border-b border-slate-200/80 bg-slate-50/95 px-3 sm:px-3.5 py-2 sm:py-2.5 backdrop-blur-sm gap-2">
        <div className="flex items-center gap-2 shrink-0">
          <span className="flex gap-1.5" aria-hidden>
            <span className="h-2.5 w-2.5 rounded-full bg-rose-400 shadow-2xs" />
            <span className="h-2.5 w-2.5 rounded-full bg-amber-400 shadow-2xs" />
            <span className="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-2xs" />
          </span>
          <span className="hidden sm:inline font-mono text-[11px] font-semibold text-slate-500">
            {title}
          </span>
        </div>

        {/* Real App Route */}
        <div className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2 py-0.5 sm:px-2.5 sm:py-1 text-[10px] sm:text-[11px] font-medium text-slate-600 shadow-2xs max-w-[150px] sm:max-w-none truncate">
          <span className="text-slate-400 text-[10px] hidden sm:inline">https://</span>
          <span className="font-semibold text-slate-800 hidden sm:inline">app.stockpoint.co.ke</span>
          <span className="text-slate-300 hidden sm:inline">/</span>
          <span className="font-bold text-blue-600 truncate">{route}</span>
        </div>

        {/* Live Badge */}
        <div className={`flex items-center gap-1 rounded-full px-2 py-0.5 sm:px-2.5 text-[10px] font-bold border ${badgeColor} shrink-0`}>
          <span className="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse" />
          <span className="hidden sm:inline">{badgeText}</span>
          <span className="sm:hidden">Live</span>
        </div>
      </div>

      {/* Actual Screenshot Canvas */}
      <div className="relative bg-slate-100/50 overflow-hidden group">
        <img
          src={src}
          alt={alt}
          className="w-full h-auto max-w-full object-contain select-none pointer-events-none transition-transform duration-300 group-hover:scale-[1.01]"
          loading="lazy"
        />
      </div>

      {/* Footer verifying connected production data */}
      <div className="flex flex-wrap items-center justify-between border-t border-slate-100 bg-white px-3 sm:px-4 py-2 text-[10px] sm:text-[11px] text-slate-500 gap-2">
        <span className="flex items-center gap-1.5">
          <span className="h-2 w-2 rounded-full bg-emerald-500 shrink-0" />
          Live production screenshot · Connected Kenyan database
        </span>
        <span className="font-semibold text-blue-600 hidden sm:inline">
          100% Solforbs Stockpoint Engine
        </span>
      </div>
    </div>
  )
}

function SourcingScreenshotPreview() {
  return (
    <ModuleScreenshotPreview
      src="/screenshots/rfq_cba.png"
      alt="Stockpoint Distributor RFQ & Comparative Bid Analysis Screenshot"
      route="procurement/rfqs/RFQ-2026-0001"
      title="Procurement · Live Comparative Quotes"
      badgeText="3 Distributor Quotes"
      badgeColor="text-emerald-700 bg-emerald-50 border-emerald-200/70"
    />
  )
}

function TransfersScreenshotPreview() {
  return (
    <ModuleScreenshotPreview
      src="/screenshots/transfers.png"
      alt="Stockpoint Inter-Store Transfers and Multi-Branch Movement Screenshot"
      route="inventory/transfers"
      title="Inventory · Inter-Store Transfer Ledger"
      badgeText="In Transit Dispatched"
      badgeColor="text-blue-700 bg-blue-50 border-blue-200/70"
    />
  )
}

function WholesaleScreenshotPreview() {
  return (
    <ModuleScreenshotPreview
      src="/screenshots/batches_screen.png"
      alt="Stockpoint Batches, Expiry and Dual Retail/Wholesale Pricing Screenshot"
      route="inventory/batches"
      title="Inventory · Batches & Unit Economics"
      badgeText="FEFO Verified"
      badgeColor="text-blue-700 bg-blue-50 border-blue-200/70"
    />
  )
}

function PpbScreenshotPreview() {
  const [view, setView] = useState<'licences' | 'sops'>('licences')
  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
        <div className="flex items-center gap-2 rounded-xl bg-white border border-slate-200/90 px-2.5 py-1 shadow-2xs">
          <img
            src="/pharmacypoison.png"
            alt="Pharmacy and Poisons Board Kenya"
            className="h-5 sm:h-6 w-auto object-contain"
          />
          <span className="text-[11px] font-bold text-slate-700">Official PPB Formats</span>
        </div>
        <div className="flex items-center gap-1.5">
          <span className="text-slate-500 font-medium">Switch view:</span>
          <button
            type="button"
            onClick={() => setView('licences')}
            className={`px-3 py-1 rounded-lg font-bold text-xs transition-colors cursor-pointer ${
              view === 'licences'
                ? 'bg-blue-600 text-white shadow-2xs'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            Premise & Practice Licences
          </button>
          <button
            type="button"
            onClick={() => setView('sops')}
            className={`px-3 py-1 rounded-lg font-bold text-xs transition-colors cursor-pointer ${
              view === 'sops'
                ? 'bg-blue-600 text-white shadow-2xs'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            12 Pharmacy SOPs
          </button>
        </div>
      </div>
      <ModuleScreenshotPreview
        src={view === 'licences' ? '/screenshots/licences.png' : '/screenshots/sops.png'}
        alt="Stockpoint PPB Regulatory Compliance and Official Pharmacy SOPs Screenshot"
        route={view === 'licences' ? 'compliance/licences' : 'compliance/sops'}
        title={view === 'licences' ? 'Compliance · PPB Licences & Retention' : 'Compliance · 12 Pre-loaded Pharmacy SOPs'}
        badgeText={view === 'licences' ? 'All Licences Valid' : '12/12 Pre-loaded SOPs'}
        badgeColor="text-emerald-700 bg-emerald-50 border-emerald-200/70"
      />
    </div>
  )
}

function FinanceScreenshotPreview() {
  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
        <div className="flex items-center gap-2">
          <div className="flex items-center gap-1.5 rounded-xl bg-white border border-slate-200/90 px-2.5 py-1 shadow-2xs">
            <img
              src="/kra.png"
              alt="KRA"
              className="h-5 sm:h-6 w-auto object-contain"
            />
            <span className="text-[11px] font-bold text-slate-700">KRA 16% VAT</span>
          </div>
          <div className="flex items-center gap-1.5 rounded-xl bg-white border border-slate-200/90 px-2.5 py-1 shadow-2xs">
            <img
              src="/etims.png"
              alt="eTIMS"
              className="h-5 sm:h-6 w-auto object-contain"
            />
            <span className="text-[11px] font-bold text-slate-700">eTIMS Synced</span>
          </div>
        </div>
        <span className="text-[11px] font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200/70">
          Automated Shift Reconciliation
        </span>
      </div>
      <ModuleScreenshotPreview
        src="/screenshots/finance_payables.png"
        alt="Stockpoint Accounts Payable and Double-Entry Finance Screenshot"
        route="accounting/payables"
        title="Accounting · Supplier Payables & Balancing"
        badgeText="KRA eTIMS Reconciled"
        badgeColor="text-emerald-700 bg-emerald-50 border-emerald-200/70"
      />
    </div>
  )
}

/** The Interactive Capabilities Console */
function ModuleSuiteGrid() {
  const [activeTabId, setActiveTabId] = useState<CapabilityPillar['id']>('sourcing')
  const activePillar = CAPABILITY_PILLARS.find((p) => p.id === activeTabId) ?? CAPABILITY_PILLARS[0]

  return (
    <section id="modules" className="mx-auto w-full max-w-7xl scroll-mt-20 px-4 py-16 sm:px-6 sm:py-24 border-t border-slate-200/80 bg-slate-50/50">
      {/* Section Header */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div className="max-w-2xl">
          <p className="text-xs font-bold uppercase tracking-wider text-blue-600">
            Complete Pharmacy Infrastructure
          </p>
          <h2 className="mt-2 font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
            Beyond the Cash Till. Built for Serious Pharmacy Operations.
          </h2>
          <p className="mt-2 text-base text-slate-600 leading-relaxed">
            Most POS systems stop at counter sales. Stockpoint connects distributor quotation comparison, inter-branch transfers, wholesale trade tiers, and PPB compliance into one automated ledger.
          </p>
        </div>

        <div className="flex items-center gap-3 shrink-0">
          <span className="text-xs sm:text-sm font-semibold text-slate-600">
            100% Kenyan PPB & eTIMS Ready
          </span>
          <Link
            to="/request-quote"
            className="hidden sm:inline-flex items-center gap-1.5 rounded-2xl bg-blue-600 px-4 py-2.5 text-xs sm:text-sm font-bold text-white hover:bg-blue-700 shadow-xs transition-colors"
          >
            Request Full Demo <ArrowRight className="h-3.5 w-3.5" />
          </Link>
        </div>
      </div>

      {/* Clean Segmented Tab Switcher */}
      <div className="mt-10 flex items-center justify-start sm:justify-center overflow-x-auto pb-2 scrollbar-none">
        <div className="inline-flex p-1.5 rounded-2xl bg-slate-200/70 border border-slate-200 gap-1.5">
          {CAPABILITY_PILLARS.map((pillar) => {
            const isSelected = activeTabId === pillar.id
            return (
              <button
                key={pillar.id}
                type="button"
                onClick={() => setActiveTabId(pillar.id)}
                className={`relative px-4 py-2.5 rounded-xl text-xs sm:text-sm font-semibold transition-all cursor-pointer whitespace-nowrap ${
                  isSelected ? 'text-blue-700 shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-white/40'
                }`}
              >
                {isSelected && (
                  <motion.span
                    layoutId="active-capability-tab"
                    className="absolute inset-0 bg-white rounded-xl shadow-xs"
                    transition={{ type: 'spring', stiffness: 450, damping: 32 }}
                  />
                )}
                <span className="relative z-10">{pillar.tabLabel}</span>
              </button>
            )
          })}
        </div>
      </div>

      {/* Active Pillar Showcase (2 Columns) */}
      <div className="mt-8 rounded-3xl border border-slate-200 bg-white p-6 sm:p-10 shadow-sm">
        <AnimatePresence mode="wait">
          <motion.div
            key={activePillar.id}
            initial={{ opacity: 0, y: 12 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -12 }}
            transition={{ duration: 0.25 }}
            className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12 items-center"
          >
            {/* Left Column: Business Problem & Capabilities */}
            <div className="lg:col-span-5 space-y-4">
              <p className="text-xs font-bold uppercase tracking-wider text-blue-600">
                {activePillar.badge}
              </p>

              <h3 className="font-display text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight leading-snug">
                {activePillar.headline}
              </h3>

              <p className="text-sm sm:text-base text-slate-600 leading-relaxed">
                {activePillar.summary}
              </p>

              {/* Metrics */}
              <div className="grid grid-cols-3 gap-3 pt-3 border-t border-slate-100">
                {activePillar.metrics.map((m, idx) => (
                  <div key={idx} className="text-left">
                    <span className="block font-mono text-xl font-extrabold text-blue-600">{m.value}</span>
                    <span className="block text-xs font-medium text-slate-500 leading-tight mt-0.5">{m.label}</span>
                  </div>
                ))}
              </div>

              {/* Points Checklist */}
              <div className="space-y-2.5 pt-3">
                {activePillar.points.map((pt, idx) => (
                  <div key={idx} className="flex items-start gap-2.5 text-xs sm:text-sm text-slate-700 leading-relaxed">
                    <span className="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 mt-0.5">
                      <Check className="h-3.5 w-3.5" />
                    </span>
                    <span>{pt}</span>
                  </div>
                ))}
              </div>

              <div className="pt-3">
                <Link
                  to="/request-quote"
                  className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-xs sm:text-sm font-bold text-white hover:bg-blue-700 shadow-xs transition-colors"
                >
                  <span>See This Workflow Live</span>
                  <ArrowRight className="h-4 w-4" />
                </Link>
              </div>
            </div>

            {/* Right Column: Authentic Application Screenshot Preview */}
            <div className="lg:col-span-7">
              <div className="rounded-3xl border border-slate-200 bg-slate-50/80 p-2 sm:p-3.5 shadow-inner">
                {activePillar.id === 'sourcing' && <SourcingScreenshotPreview />}
                {activePillar.id === 'transfers' && <TransfersScreenshotPreview />}
                {activePillar.id === 'wholesale' && <WholesaleScreenshotPreview />}
                {activePillar.id === 'ppb' && <PpbScreenshotPreview />}
                {activePillar.id === 'finance' && <FinanceScreenshotPreview />}
              </div>
            </div>
          </motion.div>
        </AnimatePresence>
      </div>

      {/* Architecture Promise Ribbon */}
      <div className="mt-10 rounded-3xl border border-blue-200/70 bg-gradient-to-r from-blue-50/80 via-white to-blue-50/80 p-6 sm:p-8 shadow-xs">
        <div className="flex flex-col lg:flex-row items-center justify-between gap-6">
          <div className="space-y-1 text-center lg:text-left">
            <span className="text-xs font-bold uppercase tracking-wider text-blue-700">Centralized Cloud Architecture</span>
            <h4 className="font-display text-lg sm:text-xl font-extrabold text-slate-900">
              One Single Database. Enter Data Once.
            </h4>
            <p className="text-xs sm:text-sm text-slate-500 max-w-2xl">
              When medicines are received at the store, they appear at the till priced and ready to dispense. Every customer checkout deducts FEFO stock, updates supplier debts, and posts to your double-entry accounts in real time.
            </p>
          </div>

          <div className="shrink-0">
            <Link
              to="/request-quote"
              className="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-6 py-3.5 text-xs sm:text-sm font-bold text-white hover:bg-blue-700 shadow-md shadow-blue-600/20 transition-all"
            >
              <span>Book a 15-Minute System Walkthrough</span>
              <ArrowRight className="h-4 w-4" />
            </Link>
          </div>
        </div>
      </div>
    </section>
  )
}

/** Deployment & Go-Live Timeline in Light Theme */
function HowItWorksSection() {
  return (
    <section className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 sm:py-20 border-t border-slate-200/80 bg-white">
      <div className="max-w-2xl">
        <p className="text-xs font-bold uppercase tracking-wider text-blue-600">Onboarding & Deployment</p>
        <h2 className="mt-2 font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">
          From Sign-Up to Live Trading in Under 7 Days
        </h2>
        <p className="mt-3 text-base sm:text-lg text-slate-600">
          No months-long implementation headache. Our Kenyan onboarding team loads your inventory, sets your price lists, and trains your staff.
        </p>
      </div>

      <div className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        {HOW_IT_WORKS.map((step) => (
          <div
            key={step.number}
            className="group relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-7 shadow-xs hover:shadow-lg transition-all"
          >
            <span className="absolute -right-3 -top-4 font-display text-7xl font-extrabold text-blue-600/10 transition-colors group-hover:text-blue-600/15">
              {step.number}
            </span>
            <span className="relative font-display text-2xl font-extrabold text-blue-600/70">{step.number}</span>
            <h3 className="relative mt-3 font-display text-lg font-bold text-slate-900">{step.title}</h3>
            <p className="relative mt-2 text-sm leading-relaxed text-slate-600">{step.body}</p>
          </div>
        ))}
      </div>
    </section>
  )
}
