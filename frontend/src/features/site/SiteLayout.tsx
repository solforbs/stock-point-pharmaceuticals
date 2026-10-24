import { useEffect, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { AnimatePresence, motion, useReducedMotion, useScroll, useSpring } from 'framer-motion'
import {
  ArrowRight,
  Mail,
  Menu,
  MessageCircle,
  PhoneCall,
  X,
} from 'lucide-react'
import AssistantWidget from '../assistant/AssistantWidget'
import {
  EMAIL,
  EMAIL_HREF,
  MODULES,
  PHONE_SALES,
  PHONE_SALES_HREF,
  PHONE_SUPPORT,
  PHONE_SUPPORT_HREF,
  SITE_NAV,
  SOCIALS,
  WHATSAPP_HREF,
  WHATSAPP_SALES_HREF,
  WHATSAPP_SUPPORT_HREF,
} from './content'
import { Blob } from './motion'

/**
 * The public site's frame: clean, modern sticky header and full-page layout.
 */
export default function SiteLayout() {
  const [menuOpen, setMenuOpen] = useState(false)
  const [scrolled, setScrolled] = useState(false)
  const { pathname } = useLocation()
  const quiet = useReducedMotion()
  const { scrollYProgress } = useScroll()
  const progress = useSpring(scrollYProgress, { stiffness: 140, damping: 30, restDelta: 0.001 })

  useEffect(() => {
    setMenuOpen(false)
    window.scrollTo({ top: 0 })
  }, [pathname])

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 120)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })

    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  return (
    <div className="site-type min-h-svh bg-white flex flex-col text-slate-700 overflow-x-hidden">

      <header className={`sticky top-0 z-40 border-b border-slate-200/80 bg-white/95 backdrop-blur-md transition-shadow ${scrolled ? 'shadow-sm' : ''}`}>
        <motion.div className="absolute inset-x-0 top-0 h-0.5 origin-left bg-blue-600" style={{ scaleX: progress }} aria-hidden />

        <div className="mx-auto flex w-full max-w-7xl items-center justify-between gap-6 px-4 py-3.5 sm:px-6">
          <Brand />

          {/* Desktop Navigation */}
          <nav className="hidden items-center gap-1 lg:flex">
            {SITE_NAV.filter(item => item.to !== '/home').map((item) => (
              <NavLink
                key={item.to}
                to={item.to}
                className={({ isActive }) =>
                  `rounded-lg px-3.5 py-2 text-sm font-semibold transition-colors ${
                    isActive ? 'bg-blue-50 text-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
                  }`
                }
              >
                {item.label}
              </NavLink>
            ))}
          </nav>

          {/* Header Actions */}
          <div className="hidden items-center gap-4 lg:flex">
            <a
              href={WHATSAPP_HREF}
              target="_blank"
              rel="noreferrer"
              className="flex items-center gap-1.5 text-xs font-semibold text-emerald-700 hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-3 py-1.5 rounded-full transition-colors"
            >
              <MessageCircle className="h-3.5 w-3.5" aria-hidden />
              <span>WhatsApp</span>
            </a>

            <Link
              to="/login"
              className="text-sm font-semibold text-slate-700 transition-colors hover:text-blue-600 px-3 py-2"
            >
              Sign in
            </Link>

            <motion.div whileHover={quiet ? undefined : { y: -1 }} whileTap={{ scale: 0.98 }}>
              <Link
                to="/request-quote"
                className="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-blue-600/20 transition-colors hover:bg-blue-700"
              >
                <span>Request a demo</span>
                <ArrowRight className="h-4 w-4" aria-hidden />
              </Link>
            </motion.div>
          </div>

          {/* Mobile hamburger */}
          <button
            type="button"
            onClick={() => setMenuOpen((open) => !open)}
            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
            aria-expanded={menuOpen}
            className="rounded-lg p-2 text-slate-600 transition-colors hover:bg-slate-100 lg:hidden cursor-pointer"
          >
            <AnimatePresence mode="wait" initial={false}>
              <motion.span
                key={menuOpen ? 'close' : 'open'}
                initial={{ opacity: 0, rotate: -90 }}
                animate={{ opacity: 1, rotate: 0 }}
                exit={{ opacity: 0, rotate: 90 }}
                transition={{ duration: 0.15 }}
                className="block"
              >
                {menuOpen ? <X className="h-6 w-6" aria-hidden /> : <Menu className="h-6 w-6" aria-hidden />}
              </motion.span>
            </AnimatePresence>
          </button>
        </div>

        {/* Mobile menu dropdown */}
        <AnimatePresence initial={false}>
          {menuOpen && (
            <motion.nav
              initial={{ height: 0, opacity: 0 }}
              animate={{ height: 'auto', opacity: 1 }}
              exit={{ height: 0, opacity: 0 }}
              transition={{ duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
              className="overflow-hidden border-t border-slate-200 bg-white lg:hidden"
            >
              <div className="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4 sm:px-6">
                <div className="mb-2 px-3 text-xs font-bold uppercase tracking-wider text-slate-400">Navigation</div>
                {SITE_NAV.map((item, index) => (
                  <motion.div key={item.to} initial={{ opacity: 0, x: -12 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: 0.04 * index }}>
                    <NavLink
                      to={item.to}
                      onClick={() => setMenuOpen(false)}
                      className={({ isActive }) =>
                        `block rounded-lg px-3 py-2.5 font-semibold ${isActive ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100'}`
                      }
                    >
                      {item.label}
                    </NavLink>
                  </motion.div>
                ))}

                <div className="mt-3 flex flex-col gap-2 border-t border-slate-100 pt-3">
                  <Link
                    to="/login"
                    onClick={() => setMenuOpen(false)}
                    className="w-full rounded-xl border border-slate-200 px-4 py-2.5 text-center font-semibold text-slate-700 hover:bg-slate-50"
                  >
                    Sign in to your facility
                  </Link>
                  <Link
                    to="/request-quote"
                    onClick={() => setMenuOpen(false)}
                    className="w-full rounded-xl bg-blue-600 px-4 py-2.5 text-center font-semibold text-white shadow-md shadow-blue-600/20"
                  >
                    Request a live demo
                  </Link>
                </div>
              </div>
            </motion.nav>
          )}
        </AnimatePresence>
      </header>

      <main className="flex-1 bg-white">
        <AnimatePresence mode="wait">
          <motion.div
            key={pathname}
            initial={quiet ? undefined : { opacity: 0, y: 8 }}
            animate={quiet ? undefined : { opacity: 1, y: 0 }}
            exit={quiet ? undefined : { opacity: 0, y: -8 }}
            transition={{ duration: 0.28, ease: [0.22, 1, 0.36, 1] }}
          >
            <Outlet />
          </motion.div>
        </AnimatePresence>
      </main>

      <SiteFooter />
      <FloatingContactStack />
      <AssistantWidget />
    </div>
  )
}

/**
 * Floating Quick Contact Stack:
 * Directly above the chatbot in the bottom right corner.
 * Provides floating WhatsApp (Enquiries: 0725 & Support: 0759) and floating Email.
 */
function FloatingContactStack() {
  const [waMenuOpen, setWaMenuOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const onClick = (e: MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) {
        setWaMenuOpen(false)
      }
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  return (
    <div ref={ref} className="fixed bottom-20 right-5 z-40 flex flex-col items-end gap-2.5">
      {/* WhatsApp Menu Popover */}
      <AnimatePresence>
        {waMenuOpen && (
          <motion.div
            initial={{ opacity: 0, y: 10, scale: 0.95 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 10, scale: 0.95 }}
            transition={{ duration: 0.18 }}
            className="mb-1 w-72 rounded-2xl border border-slate-200/90 bg-white p-4 shadow-2xl shadow-slate-900/15"
          >
            <div className="flex items-center justify-between border-b border-slate-100 pb-2.5">
              <div className="flex items-center gap-2">
                <span className="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                  <MessageCircle className="h-4 w-4" />
                </span>
                <span className="text-xs font-bold text-slate-900">Direct WhatsApp</span>
              </div>
              <button
                type="button"
                onClick={() => setWaMenuOpen(false)}
                className="text-slate-400 hover:text-slate-600 cursor-pointer p-0.5"
                aria-label="Close"
              >
                <X className="h-4 w-4" />
              </button>
            </div>

            <div className="mt-3 space-y-2">
              {/* Software Enquiries */}
              <a
                href={WHATSAPP_SALES_HREF}
                target="_blank"
                rel="noreferrer"
                className="group flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/70 p-2.5 transition-all hover:border-emerald-300 hover:bg-emerald-50/50"
              >
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500 text-white shadow-xs">
                  <MessageCircle className="h-4 w-4" />
                </span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-slate-900">Software Enquiries</span>
                    <span className="text-[11px] font-bold text-emerald-600">0725 996 394</span>
                  </div>
                  <p className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                    Facility setup, quotes & live onboarding
                  </p>
                </div>
              </a>

              {/* Customer Support */}
              <a
                href={WHATSAPP_SUPPORT_HREF}
                target="_blank"
                rel="noreferrer"
                className="group flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/70 p-2.5 transition-all hover:border-blue-300 hover:bg-blue-50/50"
              >
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-600 text-white shadow-xs">
                  <PhoneCall className="h-4 w-4" />
                </span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between">
                    <span className="text-xs font-bold text-slate-900">Customer Support</span>
                    <span className="text-[11px] font-bold text-blue-600">0759 900 802</span>
                  </div>
                  <p className="text-[11px] text-slate-500 mt-0.5 leading-snug">
                    Technical assistance, till sync & operations
                  </p>
                </div>
              </a>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      {/* Floating Email Button */}
      <a
        href={EMAIL_HREF}
        className="group relative flex h-11 w-11 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-700 shadow-md shadow-slate-900/10 transition-all hover:scale-105 hover:border-blue-300 hover:bg-blue-50 hover:text-blue-600"
        title={`Email: ${EMAIL}`}
        aria-label={`Email ${EMAIL}`}
      >
        <Mail className="h-5 w-5" />
        <span className="pointer-events-none absolute right-13 whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white opacity-0 shadow-md transition-opacity group-hover:opacity-100">
          Email: {EMAIL}
        </span>
      </a>

      {/* Floating WhatsApp Button */}
      <button
        type="button"
        onClick={() => setWaMenuOpen((v) => !v)}
        className="group relative flex h-11 w-11 items-center justify-center rounded-full bg-[#25D366] text-white shadow-lg shadow-emerald-600/30 transition-all hover:scale-105 hover:bg-[#20bd5a] cursor-pointer"
        aria-label="Contact us on WhatsApp"
        aria-expanded={waMenuOpen}
      >
        <MessageCircle className="h-5 w-5" />
        <span className="pointer-events-none absolute right-13 whitespace-nowrap rounded-lg bg-slate-900 px-2.5 py-1 text-xs font-semibold text-white opacity-0 shadow-md transition-opacity group-hover:opacity-100">
          WhatsApp: Enquiries (0725) & Support (0759)
        </span>
      </button>
    </div>
  )
}

function Brand() {
  const quiet = useReducedMotion()

  return (
    <Link to="/home" className="flex shrink-0 items-center gap-2.5 group">
      <motion.img
        whileHover={quiet ? undefined : { scale: 1.05 }}
        transition={{ type: 'spring', stiffness: 400, damping: 14 }}
        src={`${import.meta.env.BASE_URL}assets/solforbs-logo.png`}
        alt="Stockpoint Logo"
        className="h-9 w-9 object-contain"
      />
      <span className="font-display text-2xl font-extrabold tracking-tight text-slate-900 group-hover:text-blue-600 transition-colors">
        Stockpoint
      </span>
    </Link>
  )
}


/** The closing band every page ends on, above the footer proper. */
export function CallToAction({
  title = 'Start Your 1-Month Free Trial',
  body = 'Experience the complete pharmacy operating system. Sells offline, connects to the cloud, and configures for your chemist, pharmacy, or wholesale depot in days.',
}: {
  title?: string
  body?: string
}) {
  const quiet = useReducedMotion()

  return (
    <section className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 sm:py-20">
      <motion.div
        initial={quiet ? undefined : { opacity: 0, y: 16 }}
        whileInView={quiet ? undefined : { opacity: 1, y: 0 }}
        viewport={{ once: true, amount: 0.2 }}
        transition={{ duration: 0.45 }}
        className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-blue-600 via-blue-700 to-indigo-800 px-6 py-14 text-center sm:px-12 sm:py-16 shadow-xl shadow-blue-900/15"
      >
        <Blob className="-right-20 -top-24 h-64 w-64 bg-white/10" />
        <Blob className="-bottom-24 -left-16 h-64 w-64 bg-emerald-400/15" />
        <div className="relative">
          <p className="text-xs sm:text-sm font-bold uppercase tracking-wider text-blue-200">
            Full Institutional Access · 30 Days Free
          </p>
          <h2 className="mt-3 font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">{title}</h2>
          <p className="mx-auto mt-3.5 max-w-xl text-base sm:text-lg text-blue-100">{body}</p>

          <div className="mt-8 flex flex-col items-center justify-center gap-3.5 sm:flex-row">
            <motion.div whileHover={quiet ? undefined : { y: -2 }} whileTap={{ scale: 0.97 }} className="w-full sm:w-auto">
              <Link
                to="/request-quote"
                className="group inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-7 py-3.5 text-base font-bold text-blue-700 shadow-md transition-all hover:bg-blue-50"
              >
                Claim 1-Month Free Trial
                <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" aria-hidden />
              </Link>
            </motion.div>
            <a
              href={WHATSAPP_HREF}
              target="_blank"
              rel="noreferrer"
              className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-white/30 bg-white/10 px-7 py-3.5 text-base font-semibold text-white transition-colors hover:bg-white/20 sm:w-auto"
            >
              <MessageCircle className="h-5 w-5" aria-hidden /> Chat on WhatsApp
            </a>
          </div>

          <div className="mt-7 flex flex-wrap items-center justify-center gap-6 text-xs text-blue-100/90">
            <span className="flex items-center gap-1.5">✓ 30-Day Unrestricted Access</span>
            <span className="flex items-center gap-1.5">✓ Hybrid Cloud & 100% Offline</span>
            <span className="flex items-center gap-1.5">✓ No Credit Card Required</span>
          </div>
        </div>
      </motion.div>
    </section>
  )
}

function SiteFooter() {
  const socials = SOCIALS.filter((social) => social.href)

  return (
    <footer className="border-t border-slate-200 bg-slate-50">
      <div className="mx-auto grid w-full max-w-7xl gap-10 px-4 py-14 sm:px-6 md:grid-cols-4">
        <div className="md:col-span-2">
          <Brand />
          <p className="mt-5 max-w-sm text-slate-500">
            Pharmacy management ERP for retail chemists, dispensing pharmacies, pharmacy chains and wholesalers — in
            every county in Kenya, from a single counter to a multi-branch chain.
          </p>
          <div className="mt-6 flex flex-col gap-2.5">
            <div>
              <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Software Enquiries:</span>
              <a href={PHONE_SALES_HREF} className="flex items-center gap-2 text-slate-800 transition-colors hover:text-blue-600 font-display font-bold">
                <PhoneCall className="h-4 w-4 text-emerald-600" aria-hidden />
                <span>{PHONE_SALES}</span>
              </a>
            </div>
            <div>
              <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Customer Support:</span>
              <a href={PHONE_SUPPORT_HREF} className="flex items-center gap-2 text-slate-800 transition-colors hover:text-blue-600 font-display font-bold">
                <PhoneCall className="h-4 w-4 text-blue-600" aria-hidden />
                <span>{PHONE_SUPPORT}</span>
              </a>
            </div>
            <div>
              <span className="text-[11px] font-bold uppercase tracking-wider text-slate-400">Email:</span>
              <a href={EMAIL_HREF} className="flex items-center gap-2 text-slate-700 transition-colors hover:text-blue-600 font-medium text-sm">
                <Mail className="h-4 w-4 text-blue-600" aria-hidden />
                <span>{EMAIL}</span>
              </a>
            </div>
            <a
              href={WHATSAPP_SALES_HREF}
              target="_blank"
              rel="noreferrer"
              className="mt-2 inline-flex w-fit items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 font-semibold text-white transition-colors hover:bg-emerald-700"
            >
              <MessageCircle className="h-4 w-4" aria-hidden /> Chat on WhatsApp (0725 996 394)
            </a>
          </div>
          {socials.length > 0 && (
            <div className="mt-6 flex items-center gap-3">
              {socials.map((social) => (
                <a
                  key={social.label}
                  href={social.href}
                  target="_blank"
                  rel="noreferrer"
                  aria-label={social.label}
                  className="flex h-10 w-10 items-center justify-center rounded-full bg-white text-slate-500 shadow-sm transition-colors hover:bg-blue-600 hover:text-white"
                >
                  <social.icon className="h-4.5 w-4.5" aria-hidden />
                </a>
              ))}
            </div>
          )}
        </div>

        <div>
          <h3 className="font-display text-base font-bold uppercase tracking-wider text-slate-900">The system</h3>
          <ul className="mt-4 space-y-2.5">
            {SITE_NAV.filter((item) => item.to !== '/home').map((item) => (
              <li key={item.to}>
                <Link to={item.to} className="text-slate-500 transition-colors hover:text-blue-600">{item.label}</Link>
              </li>
            ))}
          </ul>
        </div>

        <div>
          <h3 className="font-display text-base font-bold uppercase tracking-wider text-slate-900">Modules</h3>
          <ul className="mt-4 space-y-2.5">
            {MODULES.slice(0, 6).map((module) => (
              <li key={module.key}>
                <Link
                  to={module.href ?? '/request-quote'}
                  className="text-slate-500 transition-colors hover:text-blue-600"
                >
                  {module.name}
                </Link>
              </li>
            ))}
          </ul>
        </div>
      </div>

      <div className="border-t border-slate-200">
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-2 px-4 py-5 text-sm text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <span>© {new Date().getFullYear()} Stockpoint Solforbs. All rights reserved.</span>
          <span>Every county in Kenya · set up remotely or on site</span>
        </div>
      </div>
    </footer>
  )
}
