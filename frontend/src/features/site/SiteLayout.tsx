import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import { AnimatePresence, motion, useReducedMotion, useScroll, useSpring } from 'framer-motion'
import { ArrowRight, Mail, Menu, MessageCircle, PhoneCall, ShieldCheck, X } from 'lucide-react'
import AssistantWidget from '../assistant/AssistantWidget'
import { EMAIL, EMAIL_HREF, PHONE, PHONE_HREF, SITE_NAV, WHATSAPP_HREF } from './content'
import { Blob } from './motion'

/**
 * The frame every public page shares: a sticky header that tightens as you
 * scroll, the page itself, and a footer. Signed-in visitors are not sent
 * away — the site is the front door, and "Sign in" is always one click off.
 */
export default function SiteLayout() {
  const [menuOpen, setMenuOpen] = useState(false)
  const [scrolled, setScrolled] = useState(false)
  const { pathname } = useLocation()
  const quiet = useReducedMotion()

  // A thin progress bar along the top edge: cheap, and it makes a long page
  // feel navigable.
  const { scrollYProgress } = useScroll()
  const progress = useSpring(scrollYProgress, { stiffness: 140, damping: 30, restDelta: 0.001 })

  useEffect(() => {
    setMenuOpen(false)
    window.scrollTo({ top: 0 })
  }, [pathname])

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8)
    onScroll()
    window.addEventListener('scroll', onScroll, { passive: true })

    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  return (
    <div className="site-type min-h-svh bg-[#f8fafc] flex flex-col text-slate-700">
      <header
        className={`sticky top-0 z-40 border-b bg-white/85 backdrop-blur-md transition-all duration-300 ${
          scrolled ? 'border-slate-200 shadow-sm' : 'border-transparent'
        }`}
      >
        <motion.div className="absolute inset-x-0 top-0 h-0.5 origin-left bg-blue-600" style={{ scaleX: progress }} aria-hidden />

        <div className={`mx-auto flex w-full max-w-6xl items-center gap-4 px-4 transition-all duration-300 sm:px-6 ${scrolled ? 'h-14' : 'h-16'}`}>
          <Link to="/home" className="group flex shrink-0 items-center gap-2.5">
            <motion.span
              whileHover={quiet ? undefined : { rotate: -10, scale: 1.08 }}
              transition={{ type: 'spring', stiffness: 400, damping: 14 }}
              className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600 shadow-md shadow-blue-500/30"
            >
              <ShieldCheck className="h-5 w-5 text-white" aria-hidden />
            </motion.span>
            <span className="font-display text-base font-bold tracking-tight text-slate-900">Stockpoint Solforbs</span>
          </Link>

          <nav className="ml-auto hidden items-center gap-1 md:flex">
            {SITE_NAV.map((item) => (
              <NavLink key={item.to} to={item.to} className="group relative rounded-lg px-3.5 py-2 text-[15px] font-semibold">
                {({ isActive }) => (
                  <>
                    <span className={isActive ? 'text-blue-700' : 'text-slate-600 transition-colors group-hover:text-slate-900'}>
                      {item.label}
                    </span>
                    {isActive && (
                      <motion.span
                        layoutId="site-nav-active"
                        className="absolute inset-0 -z-10 rounded-lg bg-blue-50"
                        transition={{ type: 'spring', stiffness: 380, damping: 30 }}
                      />
                    )}
                  </>
                )}
              </NavLink>
            ))}
          </nav>

          <div className="ml-auto flex items-center gap-2 md:ml-0">
            <Link to="/login" className="hidden rounded-lg px-3 py-2 text-[15px] font-semibold text-slate-600 transition-colors hover:text-slate-900 sm:block">
              Sign in
            </Link>
            <motion.div whileHover={quiet ? undefined : { y: -2 }} whileTap={{ scale: 0.97 }} className="hidden sm:block">
              <Link
                to="/request-quote"
                className="block rounded-xl bg-blue-600 px-5 py-2.5 text-[15px] font-semibold text-white shadow-sm shadow-blue-600/25 transition-colors hover:bg-blue-700"
              >
                Request a demo
              </Link>
            </motion.div>
            <button
              type="button"
              onClick={() => setMenuOpen((open) => !open)}
              aria-label={menuOpen ? 'Close menu' : 'Open menu'}
              aria-expanded={menuOpen}
              className="rounded-lg p-2 text-slate-600 transition-colors hover:bg-slate-100 md:hidden cursor-pointer"
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
                  {menuOpen ? <X className="h-5 w-5" aria-hidden /> : <Menu className="h-5 w-5" aria-hidden />}
                </motion.span>
              </AnimatePresence>
            </button>
          </div>
        </div>

        <AnimatePresence initial={false}>
          {menuOpen && (
            <motion.nav
              initial={{ height: 0, opacity: 0 }}
              animate={{ height: 'auto', opacity: 1 }}
              exit={{ height: 0, opacity: 0 }}
              transition={{ duration: 0.25, ease: [0.22, 1, 0.36, 1] }}
              className="overflow-hidden border-t border-slate-200 bg-white md:hidden"
            >
              <div className="mx-auto flex max-w-6xl flex-col gap-1 px-4 py-3">
                {SITE_NAV.map((item, index) => (
                  <motion.div
                    key={item.to}
                    initial={{ opacity: 0, x: -12 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: 0.04 * index, duration: 0.25 }}
                  >
                    <NavLink
                      to={item.to}
                      className={({ isActive }) =>
                        `block rounded-lg px-3 py-2.5 text-sm font-medium ${isActive ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100'}`
                      }
                    >
                      {item.label}
                    </NavLink>
                  </motion.div>
                ))}
                <div className="mt-2 flex gap-2 border-t border-slate-100 pt-3">
                  <Link to="/login" className="flex-1 rounded-xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold text-slate-700">
                    Sign in
                  </Link>
                  <Link to="/request-quote" className="flex-1 rounded-xl bg-blue-600 px-4 py-2.5 text-center text-sm font-semibold text-white">
                    Request a demo
                  </Link>
                </div>
              </div>
            </motion.nav>
          )}
        </AnimatePresence>
      </header>

      <main className="flex-1">
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

      {/* The assistant belongs on the front door too: an administrator can ask
          for today's figures without signing in. */}
      <AssistantWidget />
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
    <section className="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 sm:py-20">
      <motion.div
        initial={quiet ? undefined : { opacity: 0, y: 24 }}
        whileInView={quiet ? undefined : { opacity: 1, y: 0 }}
        viewport={{ once: true, amount: 0.4 }}
        transition={{ duration: 0.55, ease: [0.22, 1, 0.36, 1] }}
        className="relative overflow-hidden rounded-3xl bg-slate-900 px-6 py-12 text-center sm:px-12 sm:py-16"
      >
        <Blob className="-right-20 -top-24 h-64 w-64 bg-blue-600/30" />
        <Blob className="-bottom-24 -left-16 h-64 w-64 bg-emerald-500/20" />
        <div className="relative">
          <h2 className="font-display text-3xl font-extrabold tracking-tight text-white sm:text-4xl">{title}</h2>
          <p className="mx-auto mt-4 max-w-xl text-lg text-slate-300">{body}</p>
          <div className="mt-7 flex flex-col items-center justify-center gap-3 sm:flex-row">
            <motion.div whileHover={quiet ? undefined : { y: -2 }} whileTap={{ scale: 0.97 }} className="w-full sm:w-auto">
              <Link
                to="/request-quote"
                className="group inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white px-7 py-3.5 text-base font-semibold text-slate-900 transition-colors hover:bg-slate-100"
              >
                Request a demo
                <ArrowRight className="h-4 w-4 transition-transform group-hover:translate-x-1" aria-hidden />
              </Link>
            </motion.div>
            <motion.div whileHover={quiet ? undefined : { y: -2 }} whileTap={{ scale: 0.97 }} className="w-full sm:w-auto">
              <Link
                to="/pricing"
                className="inline-flex w-full items-center justify-center rounded-xl border border-white/25 px-7 py-3.5 text-base font-semibold text-white transition-colors hover:bg-white/10"
              >
                See the plans
              </Link>
            </motion.div>
          </div>
        </div>
      </motion.div>
    </section>
  )
}

function SiteFooter() {
  return (
    <footer className="border-t border-slate-200 bg-white">
      <div className="mx-auto grid w-full max-w-6xl gap-8 px-4 py-12 sm:px-6 md:grid-cols-4">
        <div className="md:col-span-2">
          <div className="flex items-center gap-2.5">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-600">
              <ShieldCheck className="h-5 w-5 text-white" aria-hidden />
            </span>
            <span className="font-display text-base font-bold tracking-tight text-slate-900">Stockpoint Solforbs</span>
          </div>
          <p className="mt-4 max-w-sm text-slate-500">
            Pharmaceutical ERP, point of sale and supply chain for pharmacies, wholesalers and hospital pharmacies in Kenya.
          </p>
          <div className="mt-5 flex flex-col gap-2.5">
            <a href={PHONE_HREF} className="group flex items-center gap-2.5 text-slate-700 transition-colors hover:text-blue-600">
              <PhoneCall className="h-4.5 w-4.5 text-blue-600" aria-hidden />
              <span className="font-semibold">{PHONE}</span>
            </a>
            <a href={EMAIL_HREF} className="group flex items-center gap-2.5 text-slate-700 transition-colors hover:text-blue-600">
              <Mail className="h-4.5 w-4.5 text-blue-600" aria-hidden />
              <span className="font-semibold">{EMAIL}</span>
            </a>
            <a
              href={WHATSAPP_HREF}
              target="_blank"
              rel="noreferrer"
              className="inline-flex w-fit items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700"
            >
              <MessageCircle className="h-4 w-4" aria-hidden /> Chat on WhatsApp
            </a>
          </div>
        </div>

        <div>
          <h3 className="text-sm font-bold uppercase tracking-wider text-slate-900">Product</h3>
          <ul className="mt-4 space-y-2.5">
            {SITE_NAV.filter((item) => item.to !== '/home').map((item) => (
              <li key={item.to}>
                <Link to={item.to} className="text-slate-500 transition-colors hover:text-blue-600">{item.label}</Link>
              </li>
            ))}
          </ul>
        </div>

        <div>
          <h3 className="text-sm font-bold uppercase tracking-wider text-slate-900">Get started</h3>
          <ul className="mt-4 space-y-2.5">
            <li><Link to="/request-quote" className="text-slate-500 transition-colors hover:text-blue-600">Request a demo</Link></li>
            <li><Link to="/login" className="text-slate-500 transition-colors hover:text-blue-600">Sign in</Link></li>
            <li><Link to="/contact" className="text-slate-500 transition-colors hover:text-blue-600">Talk to us</Link></li>
          </ul>
        </div>
      </div>
      <div className="border-t border-slate-100">
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-2 px-4 py-5 text-xs text-slate-400 sm:flex-row sm:items-center sm:justify-between sm:px-6">
          <span>© {new Date().getFullYear()} Stockpoint Solforbs. All rights reserved.</span>
          <span>Lodwar, Turkana County, Kenya</span>
        </div>
      </div>
    </footer>
  )
}
