import { Link } from 'react-router-dom'
import { ArrowRight, CalendarClock, Mail, MapPin, MessageCircle, MessageSquare, PhoneCall } from 'lucide-react'
import { PageIntro } from './PageIntro'
import { Reveal, RevealItem } from './motion'
import { EMAIL, EMAIL_HREF, PHONE, PHONE_HREF, WHATSAPP_HREF } from './content'

export default function ContactPage() {
  return (
    <>
      <PageIntro
        eyebrow="Contact"
        title="Talk to us"
        body="Whether you are weighing the system up or already running on it, here is how to reach us."
        image="blisters.jpg"
      />

      {/* The three details people actually came for, before anything else. */}
      <section className="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6">
        <Reveal className="grid gap-5 md:grid-cols-3">
          <RevealItem hover className="rounded-2xl border border-slate-200/90 bg-white p-7 shadow-xs">
            <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
              <PhoneCall className="h-6 w-6" aria-hidden />
            </span>
            <h2 className="mt-5 font-display text-lg font-bold text-slate-900">Call us</h2>
            <a href={PHONE_HREF} className="mt-1.5 block font-display text-2xl font-extrabold tracking-tight text-slate-900 hover:text-blue-600">
              {PHONE}
            </a>
          </RevealItem>

          <RevealItem hover className="rounded-2xl border border-slate-200/90 bg-white p-7 shadow-xs">
            <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
              <MessageCircle className="h-6 w-6" aria-hidden />
            </span>
            <h2 className="mt-5 font-display text-lg font-bold text-slate-900">WhatsApp</h2>
            <p className="mt-1.5 text-slate-600">Send a message and we will reply with times for a demo.</p>
            <a
              href={WHATSAPP_HREF}
              target="_blank"
              rel="noreferrer"
              className="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2.5 font-semibold text-white transition-colors hover:bg-emerald-700"
            >
              Open WhatsApp <ArrowRight className="h-4 w-4" aria-hidden />
            </a>
          </RevealItem>

          <RevealItem hover className="rounded-2xl border border-slate-200/90 bg-white p-7 shadow-xs">
            <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
              <Mail className="h-6 w-6" aria-hidden />
            </span>
            <h2 className="mt-5 font-display text-lg font-bold text-slate-900">Email</h2>
            <a href={EMAIL_HREF} className="mt-1.5 block break-all font-display text-lg font-bold text-slate-900 hover:text-blue-600">
              {EMAIL}
            </a>
          </RevealItem>
        </Reveal>
      </section>

      <section className="border-y border-slate-200/70 bg-white">
        <div className="mx-auto grid w-full max-w-6xl gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2">
          <div>
            <h2 className="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Where we are</h2>
            <dl className="mt-7 space-y-6">
              <div className="flex gap-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white">
                  <MapPin className="h-5 w-5" aria-hidden />
                </span>
                <div>
                  <dt className="font-semibold text-slate-900">Lodwar, Turkana County</dt>
                  <dd className="mt-1 text-slate-600">Kenya. We work with pharmacies across the country, on site and remotely.</dd>
                </div>
              </div>
              <div className="flex gap-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white">
                  <CalendarClock className="h-5 w-5" aria-hidden />
                </span>
                <div>
                  <dt className="font-semibold text-slate-900">Monday to Saturday</dt>
                  <dd className="mt-1 text-slate-600">Demos are arranged at a time that suits your counter, including after closing.</dd>
                </div>
              </div>
              <div className="flex gap-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-white">
                  <MessageSquare className="h-5 w-5" aria-hidden />
                </span>
                <div>
                  <dt className="font-semibold text-slate-900">Already a customer?</dt>
                  <dd className="mt-1 text-slate-600">
                    Ask the assistant on this page for today's figures, or raise it from the messages screen inside the system so it
                    arrives with your branch attached.
                  </dd>
                </div>
              </div>
            </dl>
          </div>

          <div className="rounded-3xl bg-slate-50 p-8">
            <h3 className="font-display text-xl font-bold tracking-tight text-slate-900">What to have ready</h3>
            <p className="mt-2.5 text-slate-600">A demo is far more convincing on your own figures. If you can, bring:</p>
            <ul className="mt-5 space-y-3 text-slate-700">
              {[
                'Roughly how many products you carry',
                'How many branches, stores and tills you run',
                'Whether you sell wholesale as well as retail',
                'Your current price list, in any form at all',
              ].map((item) => (
                <li key={item} className="flex items-start gap-3">
                  <span className="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-blue-600" aria-hidden />
                  {item}
                </li>
              ))}
            </ul>
            <Link
              to="/request-quote"
              className="group mt-7 inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-6 py-3.5 text-base font-semibold text-white shadow-lg shadow-blue-600/25 transition-colors hover:bg-blue-700"
            >
              Request a demo
              <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" aria-hidden />
            </Link>
          </div>
        </div>
      </section>
    </>
  )
}
