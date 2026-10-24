import { CheckIcon } from "lucide-react";
import { Link } from "react-router-dom";

export type PricingPlan = {
  title: string;
  popular?: boolean;
  description: string;
  /** Already formatted, e.g. "KES 4,500" or "Custom". */
  price: string;
  /** Shown after the price, e.g. "monthly". Omit for custom pricing. */
  period?: string;
  features: string[];
  ctaLabel?: string;
  ctaHref?: string;
};

type Pricing1Props = {
  heading?: string;
  plans: PricingPlan[];
};

/** Flat, bordered pricing columns. Plans are passed in so the live catalogue can drive them. */
const Pricing1 = ({ heading = "Simple and transparent pricing", plans }: Pricing1Props) => {
  return (
    <section className="flex flex-col items-center justify-center gap-14 w-[95%] mx-auto py-16 bg-white text-slate-900">
      <h2 className="font-display font-medium text-2xl leading-6 text-center">{heading}</h2>

      <div className="flex flex-col md:flex-row justify-between w-full max-w-5xl">
        {plans.map((plan, index) => (
          <div
            key={plan.title + index}
            className={`flex-1 border rounded-2xl md:rounded-none md:-ml-px first:md:ml-0 -mt-px first:mt-0 md:mt-0 transition-all ${
              plan.popular
                ? "border-blue-600 bg-blue-50/20 shadow-xl shadow-blue-600/5 relative z-10 md:scale-[1.02] border-2"
                : "border-slate-200 bg-white hover:border-slate-300"
            }`}
          >
            <div className="p-7 sm:p-8 flex flex-col h-full gap-6 justify-between">
              <div className="flex flex-col gap-6">
                <div className="p-0 flex flex-col gap-3">
                  <div className="flex items-center justify-between gap-2">
                    <span className="font-display font-bold text-xl text-slate-900">
                      {plan.title}
                    </span>
                    {plan.popular && (
                      <span className="inline-flex items-center rounded-full bg-blue-600 px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wider text-white shadow-2xs">
                        Most Popular
                      </span>
                    )}
                  </div>
                  <p className="text-slate-500 font-normal text-xs sm:text-sm leading-relaxed min-h-[40px]">
                    {plan.description}
                  </p>
                  <div className="pt-2">
                    <span className="font-display font-extrabold text-3xl sm:text-4xl text-slate-900 tracking-tight">
                      {plan.price}
                    </span>
                    {plan.period && <span className="text-xs text-slate-500 font-medium"> /{plan.period}</span>}
                  </div>
                </div>
                <hr className="border-slate-100" />

                <div className="flex flex-col gap-3">
                  {plan.features.map((feature) => (
                    <div key={feature} className="flex items-start gap-2.5 text-xs sm:text-sm text-slate-600 leading-normal">
                      <CheckIcon className="mt-0.5 w-4 h-4 shrink-0 text-emerald-600" />
                      <span>{feature}</span>
                    </div>
                  ))}
                </div>
              </div>
              <hr className="border-slate-100" />
              <div className="p-0">
                <Link
                  to={plan.ctaHref ?? "/request-quote"}
                  className={`w-full inline-flex items-center justify-center px-5 h-11 rounded-xl text-sm font-bold transition-all shadow-xs ${
                    plan.popular
                      ? "bg-blue-600 text-white hover:bg-blue-700 shadow-md shadow-blue-600/20"
                      : "bg-white text-slate-800 border border-slate-300 hover:bg-slate-50 hover:text-slate-900"
                  }`}
                >
                  {plan.ctaLabel ?? "Book a call"}
                </Link>
              </div>
            </div>
          </div>
        ))}
      </div>
    </section>
  );
};

export { Pricing1 };
