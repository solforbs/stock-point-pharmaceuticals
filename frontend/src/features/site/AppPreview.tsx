import { motion } from 'framer-motion'
import {
  Boxes,
  Receipt,
  ShieldCheck,
  Smartphone,
} from 'lucide-react'

type Screen = 'dashboard' | 'mobile' | 'pos' | 'stock'

export function AppPreview({
  screen = 'dashboard',
  className = '',
}: {
  screen?: Screen
  className?: string
}) {
  return (
    <div className={`w-full max-w-full overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xl shadow-blue-900/10 ${className}`}>
      {/* Modern Window Chrome Header */}
      <div className="flex items-center justify-between border-b border-slate-200/80 bg-slate-50/90 px-3 sm:px-4 py-2 sm:py-2.5 backdrop-blur-sm gap-2">
        <div className="flex items-center gap-2 shrink-0">
          <span className="flex gap-1.5" aria-hidden>
            <span className="h-2.5 w-2.5 rounded-full bg-rose-400 shadow-2xs" />
            <span className="h-2.5 w-2.5 rounded-full bg-amber-400 shadow-2xs" />
            <span className="h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-2xs" />
          </span>
          <span className="ml-2 hidden text-xs font-semibold text-slate-500 md:inline">
            Stockpoint Enterprise Pharmacy Cloud
          </span>
        </div>

        {/* Browser address bar */}
        <div className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-0.5 sm:px-3 sm:py-1 text-[11px] font-medium text-slate-600 shadow-2xs max-w-[200px] sm:max-w-none truncate">
          <ShieldCheck className="h-3.5 w-3.5 shrink-0 text-blue-600" aria-hidden />
          <span className="text-slate-900 font-semibold hidden sm:inline">stockpoint.solforbs.com</span>
          <span className="text-slate-900 font-semibold sm:hidden">app.stockpoint.co.ke</span>
          <span className="text-slate-300">/</span>
          <span className="text-blue-600 font-medium truncate">
            {screen === 'mobile' ? 'mobile' : screen === 'pos' ? 'sell/invoices' : screen === 'stock' ? 'inventory/batches' : 'dashboard'}
          </span>
        </div>

        {/* Live status badge */}
        <div className="flex items-center gap-1.5 rounded-full bg-blue-50 px-2 py-0.5 sm:px-2.5 text-[10px] font-bold text-blue-700 border border-blue-200/60 shrink-0">
          <span className="h-1.5 w-1.5 rounded-full bg-blue-600 animate-pulse" />
          <span className="hidden sm:inline">Live Production Ledger</span>
          <span className="sm:hidden">Live</span>
        </div>
      </div>

      {/* Main Screen Canvas */}
      <div className="relative bg-slate-100/60">
        {screen === 'dashboard' && (
          <div className="relative overflow-hidden">
            {/* Real Desktop Dashboard View */}
            <img
              src="/screenshots/dashboard_desktop.png"
              alt="Stockpoint Executive Pharmacy Dashboard Desktop"
              className="w-full h-auto object-contain select-none pointer-events-none"
              loading="eager"
            />
          </div>
        )}

        {screen === 'mobile' && (
          <div className="relative overflow-hidden py-6 sm:py-8 px-4 flex flex-col items-center justify-center bg-gradient-to-b from-slate-100/90 via-slate-50 to-slate-200/60">
            <motion.div
              initial={{ opacity: 0, y: 16, scale: 0.98 }}
              animate={{ opacity: 1, y: 0, scale: 1 }}
              transition={{ duration: 0.35 }}
              className="relative w-56 sm:w-64 md:w-72 rounded-[34px] sm:rounded-[38px] border-[6px] sm:border-[7px] border-slate-900 bg-slate-900 p-1 shadow-2xl shadow-blue-950/25 ring-1 ring-slate-900/10"
            >
              {/* Speaker Ear Notch */}
              <div className="absolute top-2.5 left-1/2 -translate-x-1/2 h-1.5 w-14 sm:w-16 rounded-full bg-slate-700/80 z-30" />

              {/* Mobile screen container */}
              <div className="overflow-hidden rounded-[26px] sm:rounded-[30px] bg-white border border-slate-800">
                <img
                  src="/screenshots/dashboard_mobile.png"
                  alt="Stockpoint Mobile Pharmacy POS & Dashboard"
                  className="w-full h-auto object-cover select-none pointer-events-none"
                  loading="eager"
                />
              </div>
            </motion.div>

            <div className="mt-4 flex items-center gap-2 rounded-full border border-blue-200 bg-white/95 px-3 py-1 text-xs font-semibold text-blue-700 shadow-2xs text-center">
              <Smartphone className="h-3.5 w-3.5 shrink-0 text-blue-600" />
              <span>Full Mobile & Tablet POS Support · Touch Friendly · 100% Offline</span>
            </div>
          </div>
        )}

        {screen === 'stock' && (
          <div className="relative overflow-hidden">
            <img
              src="/screenshots/batches_screen.png"
              alt="Stockpoint Batch Inventory and FEFO Expiry Tracking"
              className="w-full h-auto object-contain select-none pointer-events-none"
              loading="eager"
            />
            {/* Ambient Highlight Tag */}
            <div className="absolute top-3 right-3 sm:top-4 sm:right-4 rounded-xl border border-slate-200 bg-white/95 backdrop-blur-md px-2.5 py-1 sm:px-3 sm:py-1.5 text-[11px] sm:text-xs font-bold text-slate-800 shadow-md flex items-center gap-1.5 sm:gap-2">
              <Boxes className="h-3.5 w-3.5 sm:h-4 sm:w-4 text-blue-600" />
              <span>Real Batch Tracking & FEFO</span>
            </div>
          </div>
        )}

        {screen === 'pos' && (
          <div className="relative overflow-hidden">
            <img
              src="/screenshots/sales_screen.png"
              alt="Stockpoint Sales Invoices and POS Receipts"
              className="w-full h-auto object-contain select-none pointer-events-none"
              loading="eager"
            />
            {/* Ambient Highlight Tag */}
            <div className="absolute top-3 right-3 sm:top-4 sm:right-4 rounded-xl border border-slate-200 bg-white/95 backdrop-blur-md px-2.5 py-1 sm:px-3 sm:py-1.5 text-[11px] sm:text-xs font-bold text-slate-800 shadow-md flex items-center gap-1.5 sm:gap-2">
              <Receipt className="h-3.5 w-3.5 sm:h-4 sm:w-4 text-blue-600" />
              <span>Real Retail & Wholesale Ledger</span>
            </div>
          </div>
        )}
      </div>

      {/* Sub-bar explaining real connected features */}
      <div className="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/90 px-3 sm:px-4 py-2 text-[11px] sm:text-xs font-medium text-slate-600">
        <div className="flex items-center gap-2">
          <span className="flex h-2 w-2 rounded-full bg-emerald-500" />
          <span className="text-slate-700 font-semibold">Live System Data:</span>
          <span>Kenyan suppliers, batches, KRA eTIMS, and till balances</span>
        </div>
        <div className="text-[10px] sm:text-[11px] text-slate-500 hidden sm:inline">
          Showing authentic Stockpoint user interface
        </div>
      </div>
    </div>
  )
}
