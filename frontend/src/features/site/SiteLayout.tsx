import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, NavLink, Outlet, useLocation, useNavigate } from 'react-router-dom'
import { AnimatePresence, motion, useReducedMotion, useScroll, useSpring } from 'framer-motion'
import {
  ArrowRight,
  ChevronDown,
  Headset,
  Mail,
  Menu,
  MessageCircle,
  PhoneCall,
  Search,
  ShieldCheck,
  UserRound,
  X,
} from 'lucide-react'
import AssistantWidget from '../assistant/AssistantWidget'
import { EMAIL, EMAIL_HREF, MODULES, PHONE, PHONE_HREF, SITE_NAV, SOCIALS, WHATSAPP_HREF } from './content'
import { Blob } from './motion'

/**
 * The public site's frame, in three bands like a high-street pharmacy site:
 * a thin utility strip, the masthead with the search, and the category bar
 * that stays stuck to the top as the page scrolls.
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
    <div className="site-type min-h-svh bg-white flex flex-col text-slate-700">
      <UtilityBar />

      <header className="border-b border-slate-100">
        <div className="mx-auto flex w-full max-w-7xl items-center gap-6 px-4 py-5 sm:px-6">
          <Brand />
          <SiteSearch className="hidden flex-1 lg:block" />
          <HeaderActions />
          <button
            type="button"
            onClick={() => setMenuOpen((open) => !open)}
            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
            aria-expanded={menuOpen}
            className="ml-auto rounded-lg p-2 text-slate-600 transition-colors hover:bg-slate-100 lg:hidden cursor-pointer"
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
        <div className="mx-auto w-full max-w-7xl px-4 pb-4 sm:px-6 lg:hidden">
          <SiteSearch />
        </div>
      </header>

      <CategoryBar scrolled={scrolled} progress={progress} />

      <AnimatePresence initial={false}>
        {menuOpen && (
          <motion.nav
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
            className="overflow-hidden border-b border-slate-200 bg-white lg:hidden"
          >
            <div className="mx-auto flex max-w-7xl flex-col gap-1 px-4 py-4 sm:px-6">
              {SITE_NAV.map((item, index) => (
                <motion.div key={item.to} initial={{ opacity: 0, x: -12 }} animate={{ opacity: 1, x: 0 }} transition={{ delay: 0.04 * index }}>
                  <NavLink
                    to={item.to}
                    className={({ isActive }) =>
                      `block rounded-lg px-3 py-3 font-semibold ${isActive ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100'}`
                    }
                  >
                    {item.label}
                  </NavLink>
                </motion.div>
              ))}
              <div className="mt-2 flex gap-2 border-t border-slate-100 pt-3">
                <Link to="/login" className="flex-1 rounded-xl border border-slate-200 px-4 py-3 text-center font-semibold text-slate-700">
                  Sign in
                </Link>
                <Link to="/request-quote" className="flex-1 rounded-xl bg-blue-600 px-4 py-3 text-center font-semibold text-white">
                  Request a demo
                </Link>
              </div>
            </div>
          </motion.nav>
        )}
      </AnimatePresence>

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
      <AssistantWidget />
    </div>
  )
}

/** The thin strip above the masthead: how to reach us, and the way in. */
function UtilityBar() {
  const socials = SOCIALS.filter((social) => social.href)

  return (
    <div className="border-b border-slate-100 bg-white">
      <div className="mx-auto flex w-full max-w-7xl flex-wrap items-center gap-x-6 gap-y-1.5 px-4 py-2 text-[13px] text-slate-500 sm:px-6">
        {socials.length > 0 && (
          <div className="flex items-center gap-3">
            <span className="font-semibold text-slate-700">Follow us</span>
            {socials.map((social) => (
              <a
                key={social.label}
                href={social.href}
                target="_blank"
                rel="noreferrer"
                aria-label={social.label}
                className="text-slate-400 transition-colors hover:text-blue-600"
              >
                <social.icon className="h-4 w-4" aria-hidden />
              </a>
            ))}
          </div>
        )}

        <span className="hidden items-center gap-1.5 sm:flex">
          <Headset className="h-4 w-4 text-blue-600" aria-hidden />
          Support
          <a href={PHONE_HREF} className="font-semibold text-slate-800 hover:text-blue-600">{PHONE}</a>
        </span>

        <a href={EMAIL_HREF} className="hidden items-center gap-1.5 hover:text-blue-600 md:flex">
          <Mail className="h-4 w-4 text-blue-600" aria-hidden />
          {EMAIL}
        </a>

        <div className="ml-auto flex items-center gap-4">
          <span className="hidden text-slate-400 sm:inline">Serving pharmacies countrywide</span>
          <Link to="/login" className="flex items-center gap-1.5 font-semibold text-slate-700 hover:text-blue-600">
            <UserRound className="h-4 w-4" aria-hidden />
            Sign in
          </Link>
          <span className="text-slate-300" aria-hidden>/</span>
          <Link to="/request-quote" className="font-semibold text-slate-700 hover:text-blue-600">Register</Link>
        </div>
      </div>
    </div>
  )
}

function Brand() {
  const quiet = useReducedMotion()

  return (
    <Link to="/home" className="flex shrink-0 items-center gap-3">
      <motion.span
        whileHover={quiet ? undefined : { rotate: -10, scale: 1.08 }}
        transition={{ type: 'spring', stiffness: 400, damping: 14 }}
        className="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-600 shadow-lg shadow-blue-600/25"
      >
        <ShieldCheck className="h-6 w-6 text-white" aria-hidden />
      </motion.span>
      <span className="leading-none">
        <span className="block font-display text-2xl font-extrabold tracking-tight text-slate-900">STOCKPOINT</span>
        <span className="mt-1 block text-[11px] font-bold uppercase tracking-[0.34em] text-blue-600">Solforbs</span>
      </span>
    </Link>
  )
}

/**
 * Searches what the site actually says — the modules and what they do —
 * rather than pretending to be a product search over a shop that does not
 * exist.
 */
function SiteSearch({ className = '' }: { className?: string }) {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const navigate = useNavigate()
  const boxRef = useRef<HTMLDivElement>(null)

  const matches = useMemo(() => {
    const needle = query.trim().toLowerCase()
    if (needle.length < 2) return []

    return MODULES.filter(
      (module) =>
        module.name.toLowerCase().includes(needle) ||
        module.summary.toLowerCase().includes(needle) ||
        module.points.some((point) => point.toLowerCase().includes(needle)),
    ).slice(0, 5)
  }, [query])

  useEffect(() => {
    const onClick = (event: MouseEvent) => {
      if (boxRef.current && !boxRef.current.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onClick)

    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  return (
    <div ref={boxRef} className={`relative ${className}`}>
      <form
        onSubmit={(event) => {
          event.preventDefault()
          setOpen(false)
          navigate('/features')
        }}
        className="flex items-stretch overflow-hidden rounded-xl border-2 border-blue-600/15 bg-white focus-within:border-blue-600"
      >
        <input
          value={query}
          onChange={(event) => {
            setQuery(event.target.value)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
          placeholder="Search what the system does…"
          aria-label="Search the site"
          className="min-w-0 flex-1 px-4 py-3 text-[15px] text-slate-800 outline-none placeholder:text-slate-400"
        />
        <button type="submit" className="flex items-center gap-2 bg-blue-600 px-6 font-semibold text-white transition-colors hover:bg-blue-700 cursor-pointer">
          <Search className="h-4 w-4" aria-hidden />
          <span className="hidden sm:inline">Search</span>
        </button>
      </form>

      <AnimatePresence>
        {open && matches.length > 0 && (
          <motion.ul
            initial={{ opacity: 0, y: -6 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -6 }}
            transition={{ duration: 0.15 }}
            className="absolute z-50 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
          >
            {matches.map((module) => (
              <li key={module.key}>
                <Link to="/features" onClick={() => setOpen(false)} className="flex items-start gap-3 px-4 py-3 transition-colors hover:bg-blue-50">
                  <module.icon className="mt-0.5 h-5 w-5 shrink-0 text-blue-600" aria-hidden />
                  <span>
                    <span className="block font-semibold text-slate-900">{module.name}</span>
                    <span className="block text-sm text-slate-500">{module.summary}</span>
                  </span>
                </Link>
              </li>
            ))}
          </motion.ul>
        )}
      </AnimatePresence>
    </div>
  )
}

function HeaderActions() {
  const quiet = useReducedMotion()

  return (
    <div className="ml-auto hidden items-center gap-5 lg:flex">
      <a href={PHONE_HREF} className="flex items-center gap-2.5">
        <span className="flex h-11 w-11 items-center justify-center rounded-full bg-blue-50 text-blue-600">
          <PhoneCall className="h-5 w-5" aria-hidden />
        </span>
        <span className="leading-tight">
          <span className="block text-xs text-slate-400">Talk to us</span>
          <span className="block font-display text-lg font-bold text-slate-900">{PHONE}</span>
        </span>
      </a>
      <motion.div whileHover={quiet ? undefined : { y: -2 }} whileTap={{ scale: 0.97 }}>
        <Link
          to="/request-quote"
          className="block rounded-xl bg-blue-600 px-6 py-3.5 font-semibold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-700"
        >
          Request a demo
        </Link>
      </motion.div>
    </div>
  )
}

/** The blue-buttoned category bar, which is also the sticky header. */
function CategoryBar({ scrolled, progress }: { scrolled: boolean; progress: ReturnType<typeof useSpring> }) {
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const onClick = (event: MouseEvent) => {
      if (ref.current && !ref.current.contains(event.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onClick)

    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  return (
    <div className={`sticky top-0 z-40 border-b border-slate-100 bg-white transition-shadow ${scrolled ? 'shadow-md' : ''}`}>
      <motion.div className="absolute inset-x-0 top-0 h-0.5 origin-left bg-blue-600" style={{ scaleX: progress }} aria-hidden />

      <div className="mx-auto flex w-full max-w-7xl items-center gap-6 px-4 py-2.5 sm:px-6">
        <div ref={ref} className="relative hidden lg:block">
          <button
            type="button"
            onClick={() => setOpen((value) => !value)}
            aria-expanded={open}
            className="flex items-center gap-3 rounded-xl bg-blue-600 px-5 py-3 font-semibold text-white transition-colors hover:bg-blue-700 cursor-pointer"
          >
            <Menu className="h-5 w-5" aria-hidden />
            Browse the modules
            <ChevronDown className={`h-4 w-4 transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden />
          </button>

          <AnimatePresence>
            {open && (
              <motion.ul
                initial={{ opacity: 0, y: -8 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -8 }}
                transition={{ duration: 0.18 }}
                className="absolute left-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white py-1.5 shadow-xl"
              >
                {MODULES.map((module) => (
                  <li key={module.key}>
                    <Link to="/features" onClick={() => setOpen(false)} className="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-blue-50">
                      <module.icon className="h-5 w-5 shrink-0 text-blue-600" aria-hidden />
                      <span className="font-semibold text-slate-800">{module.name}</span>
                      <ArrowRight className="ml-auto h-4 w-4 text-slate-300" aria-hidden />
                    </Link>
                  </li>
                ))}
              </motion.ul>
            )}
          </AnimatePresence>
        </div>

        <nav className="flex flex-1 items-center gap-1 overflow-x-auto overflow-y-hidden">
          {SITE_NAV.map((item) => (
            <NavLink key={item.to} to={item.to} className="group relative shrink-0 rounded-lg px-4 py-2.5 font-semibold">
              {({ isActive }) => (
                <>
                  <span className={isActive ? 'text-blue-600' : 'text-slate-700 transition-colors group-hover:text-blue-600'}>
                    {item.label}
                  </span>
                  {isActive && (
                    <motion.span
                      layoutId="site-nav-active"
                      className="absolute inset-x-3 bottom-0 h-0.5 rounded-full bg-blue-600"
                      transition={{ type: 'spring', stiffness: 380, damping: 30 }}
                    />
                  )}
                </>
              )}
            </NavLink>
          ))}
        </nav>

        <span className="hidden shrink-0 items-center gap-2 text-sm font-semibold text-slate-500 xl:flex">
          <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" aria-hidden />
          Setup, data loading and training included
        </span>
      </div>
    </div>
  )
}

/** The closing band every page ends on, above the footer proper. */
export function CallToAction({
  title = 'See it running on your own stock',
  body = 'Tell us about your pharmacy and we will set up a demo with your products, your prices and your branches.',
}: {
  title?: string
  body?: string
}) {
  const quiet = useReducedMotion()

  return (
    <section className="mx-auto w-full max-w-7xl px-4 py-16 sm:px-6 sm:py-20">
      <motion.div
        initial={quiet ? undefined : { opacity: 0, y: 24 }}
        whileInView={quiet ? undefined : { opacity: 1, y: 0 }}
        viewport={{ once: true, amount: 0.4 }}
        transition={{ duration: 0.55, ease: [0.22, 1, 0.36, 1] }}
        className="relative overflow-hidden rounded-3xl bg-slate-900 px-6 py-14 text-center sm:px-12 sm:py-16"
      >
        <Blob className="-right-20 -top-24 h-64 w-64 bg-blue-600/30" />
        <Blob className="-bottom-24 -left-16 h-64 w-64 bg-emerald-500/20" />
        <div className="relative">
          <h2 className="font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">{title}</h2>
          <p className="mx-auto mt-4 max-w-xl text-lg text-slate-300">{body}</p>
          <div className="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <motion.div whileHover={quiet ? undefined : { y: -2 }} whileTap={{ scale: 0.97 }} className="w-full sm:w-auto">
              <Link
                to="/request-quote"
                className="group inline-flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-7 py-3.5 text-base font-semibold text-white transition-colors hover:bg-blue-700"
              >
                Request a demo
                <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" aria-hidden />
              </Link>
            </motion.div>
            <a
              href={WHATSAPP_HREF}
              target="_blank"
              rel="noreferrer"
              className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-white/25 px-7 py-3.5 text-base font-semibold text-white transition-colors hover:bg-white/10 sm:w-auto"
            >
              <MessageCircle className="h-5 w-5" aria-hidden /> WhatsApp us
            </a>
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
            Pharmaceutical ERP, point of sale and supply chain for pharmacies, wholesalers and hospital pharmacies — in every
            county in Kenya, from a single counter to a national distributor.
          </p>
          <div className="mt-6 flex flex-col gap-3">
            <a href={PHONE_HREF} className="flex items-center gap-2.5 text-slate-700 transition-colors hover:text-blue-600">
              <PhoneCall className="h-5 w-5 text-blue-600" aria-hidden />
              <span className="font-display text-lg font-bold">{PHONE}</span>
            </a>
            <a href={EMAIL_HREF} className="flex items-center gap-2.5 text-slate-700 transition-colors hover:text-blue-600">
              <Mail className="h-5 w-5 text-blue-600" aria-hidden />
              <span className="font-semibold">{EMAIL}</span>
            </a>
            <a
              href={WHATSAPP_HREF}
              target="_blank"
              rel="noreferrer"
              className="inline-flex w-fit items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 font-semibold text-white transition-colors hover:bg-emerald-700"
            >
              <MessageCircle className="h-4 w-4" aria-hidden /> Chat on WhatsApp
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
                <Link to="/features" className="text-slate-500 transition-colors hover:text-blue-600">{module.name}</Link>
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
