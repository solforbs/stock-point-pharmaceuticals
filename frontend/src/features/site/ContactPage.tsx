import { Link } from 'react-router-dom'
import { ArrowRight, CalendarClock, LifeBuoy, MapPin, MessageSquare } from 'lucide-react'
import { PageIntro } from './PageIntro'
import { Reveal, RevealItem } from './motion'

const ROUTES_IN = [
  {
    icon: ArrowRight,
    title: 'Request a demo',
    body: 'The fastest way in. Tell us about your pharmacy and we will set up a walkthrough on your own products and prices.',
    action: { to: '/request-quote', label: 'Start here' },
  },
  {
    icon: MessageSquare,
    title: 'Ask the assistant',
    body: 'Already a customer? Open the chat on the sign-in page, verify your email with a code and ask for today’s figures without signing in.',
    action: { to: '/login', label: 'Go to sign-in' },
  },
  {
    icon: LifeBuoy,
    title: 'Support',
    body: 'Live customers reach us through the messages screen inside the system, so your question arrives with the branch and the user attached.',
    action: { to: '/login', label: 'Sign in' },
  },
]

export default function ContactPage() {
  return (
    <>
      <PageIntro
        eyebrow="Contact"
        title="Talk to us"
        body="Whether you are weighing the system up or already running on it, here is how to reach us."
      />

      <section className="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6">
        <Reveal className="grid gap-6 md:grid-cols-3">
          {ROUTES_IN.map((route) => (
            <RevealItem key={route.title} hover className="flex flex-col rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs">
              <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <route.icon className="h-5 w-5" aria-hidden />
              </span>
              <h2 className="mt-4 font-display text-lg font-semibold tracking-tight text-slate-900">{route.title}</h2>
              <p className="mt-2 flex-1 text-sm leading-relaxed text-slate-600">{route.body}</p>
              <Link to={route.action.to} className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-blue-600 hover:underline">
                {route.action.label} <ArrowRight className="h-4 w-4" aria-hidden />
              </Link>
            </RevealItem>
          ))}
        </Reveal>
      </section>

      <section className="border-y border-slate-200/70 bg-white">
        <div className="mx-auto grid w-full max-w-6xl gap-10 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2">
          <div>
            <h2 className="font-display text-3xl font-bold tracking-tight text-slate-900">Where we are</h2>
            <dl className="mt-6 space-y-5">
              <div className="flex gap-4">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white">
                  <MapPin className="h-4.5 w-4.5" aria-hidden />
                </span>
                <div>
                  <dt className="text-sm font-semibold text-slate-900">Lodwar, Turkana County</dt>
                  <dd className="mt-0.5 text-sm text-slate-600">Kenya. We work with pharmacies across the country, on site and remotely.</dd>
                </div>
              </div>
              <div className="flex gap-4">
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white">
                  <CalendarClock className="h-4.5 w-4.5" aria-hidden />
                </span>
                <div>
                  <dt className="text-sm font-semibold text-slate-900">Monday to Saturday</dt>
                  <dd className="mt-0.5 text-sm text-slate-600">Demos are arranged at a time that suits your counter, including after closing.</dd>
                </div>
              </div>
            </dl>
          </div>

          <div className="rounded-3xl bg-slate-50 p-7">
            <h3 className="font-display text-lg font-semibold tracking-tight text-slate-900">What to have ready</h3>
            <p className="mt-2 text-sm text-slate-600">A demo is far more convincing on your own figures. If you can, bring:</p>
            <ul className="mt-4 space-y-2.5 text-sm text-slate-700">
              {[
                'Roughly how many products you carry',
                'How many branches, stores and tills you run',
                'Whether you sell wholesale as well as retail',
                'Your current price list, in any form at all',
              ].map((item) => (
                <li key={item} className="flex items-start gap-2.5">
                  <span className="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600" aria-hidden />
                  {item}
                </li>
              ))}
            </ul>
            <Link
              to="/request-quote"
              className="mt-6 inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-700"
            >
              Request a demo <ArrowRight className="h-4 w-4" aria-hidden />
            </Link>
          </div>
        </div>
      </section>
    </>
  )
}
