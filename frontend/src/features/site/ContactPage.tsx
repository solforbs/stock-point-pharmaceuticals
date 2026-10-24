import { Link } from 'react-router-dom'
import { ArrowRight, CalendarClock, Headphones, Mail, MapPin, MessageCircle, MessageSquare, PhoneCall } from 'lucide-react'
import { PageIntro } from './PageIntro'
import { Reveal, RevealItem } from './motion'
import {
  EMAIL,
  EMAIL_HREF,
  PHONE_SALES,
  PHONE_SALES_HREF,
  WHATSAPP_SALES_HREF,
  PHONE_SUPPORT,
  PHONE_SUPPORT_HREF,
  WHATSAPP_SUPPORT_HREF,
} from './content'

export default function ContactPage() {
  return (
    <>
      <PageIntro
        eyebrow="Contact"
        title="Talk to us, wherever you trade"
        body="Whether you are weighing the system up or already running on it, and whether your counter is in Nairobi or a county town, here is how to reach us."
        image="blisters.jpg"
      />

      {/* The contact channels */}
      <section className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6">
        <Reveal className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {/* Software Enquiries */}
          <RevealItem hover className="flex flex-col justify-between rounded-2xl border border-blue-100 bg-white p-7 shadow-xs">
            <div>
              <div className="flex items-center justify-between">
                <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                  <PhoneCall className="h-6 w-6" aria-hidden />
                </span>
                <span className="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">Demos & Sales</span>
              </div>
              <h2 className="mt-5 font-display text-lg font-bold text-slate-900">Software Enquiries</h2>
              <p className="mt-1 text-sm text-slate-600">Pricing, live demonstrations, institutional onboarding and new branch rollout.</p>
              <a href={PHONE_SALES_HREF} className="mt-3 block font-display text-2xl font-extrabold tracking-tight text-blue-600 hover:text-blue-700">
                {PHONE_SALES}
              </a>
            </div>
            <div className="mt-6 flex flex-wrap items-center gap-2.5">
              <a
                href={PHONE_SALES_HREF}
                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs font-semibold text-slate-800 transition hover:bg-slate-100"
              >
                <PhoneCall className="h-3.5 w-3.5 text-blue-600" /> Call Sales
              </a>
              <a
                href={WHATSAPP_SALES_HREF}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-700"
              >
                <MessageCircle className="h-3.5 w-3.5" /> WhatsApp Sales
              </a>
            </div>
          </RevealItem>

          {/* Customer Support */}
          <RevealItem hover className="flex flex-col justify-between rounded-2xl border border-blue-100 bg-white p-7 shadow-xs">
            <div>
              <div className="flex items-center justify-between">
                <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                  <Headphones className="h-6 w-6" aria-hidden />
                </span>
                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Active Facilities</span>
              </div>
              <h2 className="mt-5 font-display text-lg font-bold text-slate-900">Customer Support</h2>
              <p className="mt-1 text-sm text-slate-600">Till assistance, user permissions, sync questions and operational help.</p>
              <a href={PHONE_SUPPORT_HREF} className="mt-3 block font-display text-2xl font-extrabold tracking-tight text-blue-600 hover:text-blue-700">
                {PHONE_SUPPORT}
              </a>
            </div>
            <div className="mt-6 flex flex-wrap items-center gap-2.5">
              <a
                href={PHONE_SUPPORT_HREF}
                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs font-semibold text-slate-800 transition hover:bg-slate-100"
              >
                <PhoneCall className="h-3.5 w-3.5 text-blue-600" /> Call Support
              </a>
              <a
                href={WHATSAPP_SUPPORT_HREF}
                target="_blank"
                rel="noreferrer"
                className="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-700"
              >
                <MessageCircle className="h-3.5 w-3.5" /> WhatsApp Support
              </a>
            </div>
          </RevealItem>

          {/* Email */}
          <RevealItem hover className="flex flex-col justify-between rounded-2xl border border-slate-200/90 bg-white p-7 shadow-xs sm:col-span-2 lg:col-span-1">
            <div>
              <div className="flex items-center justify-between">
                <span className="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                  <Mail className="h-6 w-6" aria-hidden />
                </span>
                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">Official</span>
              </div>
              <h2 className="mt-5 font-display text-lg font-bold text-slate-900">Email us</h2>
              <p className="mt-1 text-sm text-slate-600">Send RFQs, tenders, vendor forms, partnership requests or inquiries anytime.</p>
              <a href={EMAIL_HREF} className="mt-3 block break-all font-display text-xl font-bold text-blue-600 hover:text-blue-700">
                {EMAIL}
              </a>
            </div>
            <div className="mt-6">
              <a
                href={EMAIL_HREF}
                className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2 text-xs font-semibold text-slate-800 transition hover:bg-slate-100"
              >
                <Mail className="h-3.5 w-3.5 text-blue-600" /> Send an Email
              </a>
            </div>
          </RevealItem>
        </Reveal>
      </section>

      <section className="border-y border-slate-200/70 bg-white">
        <div className="mx-auto grid w-full max-w-7xl gap-12 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2">
          <div>
            <h2 className="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Where we are</h2>
            <dl className="mt-7 space-y-6">
              <div className="flex gap-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white">
                  <MapPin className="h-5 w-5" aria-hidden />
                </span>
                <div>
                  <dt className="font-semibold text-slate-900">Anywhere in Kenya</dt>
                  <dd className="mt-1 text-slate-600">
                    We set up and support pharmacies in every county — remotely as standard, on site when it is worth the journey.
                  </dd>
                </div>
              </div>
              <div className="flex gap-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white">
                  <CalendarClock className="h-5 w-5" aria-hidden />
                </span>
                <div>
                  <dt className="font-semibold text-slate-900">Monday to Saturday</dt>
                  <dd className="mt-1 text-slate-600">Demos are arranged at a time that suits your counter, including after closing.</dd>
                </div>
              </div>
              <div className="flex gap-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-600 text-white">
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
