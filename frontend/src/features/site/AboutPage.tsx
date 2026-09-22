import { Building2, HeartPulse, MapPin, Wrench } from 'lucide-react'
import { CallToAction } from './SiteLayout'
import { PageIntro } from './PageIntro'
import { Reveal, RevealItem } from './motion'

const BELIEFS = [
  {
    icon: HeartPulse,
    title: 'A pharmacy is not a shop',
    body: 'Medicines expire, arrive in batches, get recalled and get inspected. A system that treats them as ordinary stock lines will fail you on the day it matters.',
  },
  {
    icon: MapPin,
    title: 'Built where it is used',
    body: 'Lodwar is not Nairobi. The connection drops, the power goes, and the nearest support desk is far away. The software has to keep working anyway.',
  },
  {
    icon: Wrench,
    title: 'Finished, not almost',
    body: 'Every figure on a screen can be traced to the posting behind it. If a feature cannot be trusted at close of business, it is not ready to ship.',
  },
  {
    icon: Building2,
    title: 'Grows with the business',
    body: 'One counter today, several branches and a distribution arm later — on the same system, without a migration and without losing history.',
  },
]

export default function AboutPage() {
  return (
    <>
      <PageIntro
        eyebrow="About"
        title="Software for the pharmacy business, written where pharmacies work"
        body="Stockpoint Solforbs builds the system a Kenyan pharmacy actually needs: the counter, the store, the supplier, the regulator and the books — in one place, and dependable on the days the line is down."
        image="pharmacy-counter.jpg"
      />

      <section className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6">
        <Reveal className="grid gap-6 sm:grid-cols-2">
          {BELIEFS.map((belief) => (
            <RevealItem
              key={belief.title}
              hover
              className="group rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs transition-shadow hover:shadow-lg"
            >
              <span className="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-50 text-blue-600 transition-colors group-hover:bg-blue-600 group-hover:text-white">
                <belief.icon className="h-5 w-5" aria-hidden />
              </span>
              <h2 className="mt-5 font-display text-xl font-bold tracking-tight text-slate-900">{belief.title}</h2>
              <p className="mt-2.5 leading-relaxed text-slate-600">{belief.body}</p>
            </RevealItem>
          ))}
        </Reveal>
      </section>

      <section className="border-y border-slate-200/70 bg-white">
        <div className="mx-auto grid w-full max-w-7xl gap-10 px-4 py-16 sm:px-6 sm:py-20 lg:grid-cols-2">
          <div>
            <h2 className="font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">What we do</h2>
            <p className="mt-4 leading-relaxed text-slate-600">
              We set the system up with your own products, prices and opening stock, train your team inside it, and stay with you
              through the first months of live trading. Support is not a separate contract you discover later — it is how the
              product is delivered.
            </p>
            <p className="mt-4 leading-relaxed text-slate-600">
              The platform is hosted and maintained for you: backups before every update, security patches applied, and new
              features added as the regulations and the business change.
            </p>
          </div>
          <Reveal as="div" className="grid grid-cols-2 gap-5 self-start">
            {[
              ['Retail & wholesale', 'One system, both trades'],
              ['Multi-branch', 'Separate, then consolidated'],
              ['Offline-capable', 'The till never stops'],
              ['KRA-ready', 'eTIMS when you are'],
            ].map(([title, detail]) => (
              <RevealItem key={title} hover className="rounded-2xl bg-slate-50 p-5 transition-colors hover:bg-blue-50">
                <div className="font-display text-lg font-bold text-slate-900">{title}</div>
                <div className="mt-1 text-sm text-slate-500">{detail}</div>
              </RevealItem>
            ))}
          </Reveal>
        </div>
      </section>

      <CallToAction title="Come and see it" body="The quickest way to judge it is to watch a sale, a receipt and a report on your own products." />
    </>
  )
}
