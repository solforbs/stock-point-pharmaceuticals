import { motion } from 'framer-motion'

export function PageLoadingSkeleton() {
  return (
    <motion.div
      initial={{ opacity: 0 }}
      animate={{ opacity: 1 }}
      exit={{ opacity: 0 }}
      transition={{ duration: 0.15 }}
      className="p-6 md:p-8 space-y-6 max-w-[1600px] mx-auto animate-pulse"
    >
      {/* Top Header skeleton */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div className="space-y-2">
          <div className="h-7 w-48 bg-slate-200/80 rounded-lg" />
          <div className="h-4 w-72 bg-slate-100 rounded-md" />
        </div>
        <div className="flex gap-2">
          <div className="h-9 w-24 bg-slate-200/80 rounded-xl" />
          <div className="h-9 w-32 bg-slate-200/80 rounded-xl" />
        </div>
      </div>

      {/* KPI Stats Cards skeleton */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="p-5 rounded-2xl bg-white border border-slate-200/70 shadow-2xs space-y-3">
            <div className="flex justify-between items-center">
              <div className="h-4 w-20 bg-slate-100 rounded" />
              <div className="w-8 h-8 rounded-xl bg-slate-100" />
            </div>
            <div className="h-7 w-32 bg-slate-200/80 rounded" />
            <div className="h-3 w-28 bg-slate-100 rounded" />
          </div>
        ))}
      </div>

      {/* Main Content Table/Grid skeleton */}
      <div className="rounded-2xl bg-white border border-slate-200/70 shadow-2xs p-5 space-y-4">
        <div className="flex justify-between items-center pb-3 border-b border-slate-100">
          <div className="h-5 w-36 bg-slate-200/80 rounded" />
          <div className="h-8 w-48 bg-slate-100 rounded-xl" />
        </div>
        <div className="space-y-3">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <div key={i} className="flex items-center justify-between py-2 border-b border-slate-50">
              <div className="flex items-center gap-3">
                <div className="w-7 h-7 rounded-lg bg-slate-100" />
                <div className="space-y-1">
                  <div className="h-4 w-40 bg-slate-200/70 rounded" />
                  <div className="h-3 w-24 bg-slate-100 rounded" />
                </div>
              </div>
              <div className="h-4 w-20 bg-slate-100 rounded" />
              <div className="h-4 w-28 bg-slate-200/70 rounded" />
              <div className="h-6 w-16 bg-slate-100 rounded-full" />
            </div>
          ))}
        </div>
      </div>
    </motion.div>
  )
}
