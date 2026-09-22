import { motion } from 'framer-motion'
import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { cn } from '../../lib/utils'

export interface StatCardProps {
  icon: LucideIcon
  label: string
  value: ReactNode
  hint?: ReactNode
  to?: string
  tone?: string
  trend?: {
    value: string
    isPositive?: boolean
  }
  className?: string
  isLoading?: boolean
}

export function StatCard({
  icon: Icon,
  label,
  value,
  hint,
  to,
  tone,
  trend,
  className,
  isLoading = false,
}: StatCardProps) {
  const content = (
    <div className="flex flex-col justify-between h-full space-y-3">
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
          {label}
        </span>
        <div
          className="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors"
          style={{
            background: tone ? `color-mix(in srgb, ${tone} 10%, transparent)` : '#f1f5f9',
            color: tone || '#475569',
          }}
        >
          <Icon size={16} />
        </div>
      </div>

      <div>
        <div className="text-2xl sm:text-3xl font-bold tabular tracking-tight text-slate-900 leading-tight">
          {isLoading ? (
            <span className="inline-block w-32 h-8 bg-slate-100 rounded-md animate-pulse" />
          ) : (
            value
          )}
        </div>
      </div>

      <div className="flex items-center justify-between gap-2 pt-0.5">
        {hint && (
          <div className="text-xs text-slate-500 font-normal truncate">
            {hint}
          </div>
        )}
        {trend && (
          <span
            className={cn(
              'inline-flex items-center text-xs font-medium px-2 py-0.5 rounded-md tabular shrink-0 ml-auto border',
              trend.isPositive
                ? 'bg-emerald-50 text-emerald-800 border-emerald-200/70'
                : 'bg-rose-50 text-rose-800 border-rose-200/70'
            )}
          >
            {trend.value}
          </span>
        )}
      </div>
    </div>
  )

  const cardClasses = cn(
    'bg-white rounded-xl p-4 sm:p-5 border border-slate-200 shadow-xs hover:border-slate-300 hover:shadow-sm transition-all duration-200 block group',
    className
  )

  if (to) {
    return (
      <motion.div whileHover={{ y: -2 }} whileTap={{ scale: 0.99 }}>
        <Link to={to} className={cardClasses}>
          {content}
        </Link>
      </motion.div>
    )
  }

  return <div className={cardClasses}>{content}</div>
}
