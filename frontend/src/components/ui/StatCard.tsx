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
  tone = '#2563eb',
  trend,
  className,
  isLoading = false,
}: StatCardProps) {
  const content = (
    <div className="flex flex-col justify-between h-full space-y-3">
      <div className="flex items-center justify-between">
        <span className="text-[12.5px] font-bold text-slate-500">
          {label}
        </span>
        <div
          className="p-2.5 rounded-2xl transition-transform duration-200 group-hover:scale-105"
          style={{
            background: `color-mix(in srgb, ${tone} 10%, transparent)`,
            color: tone,
          }}
        >
          <Icon size={18} />
        </div>
      </div>

      <div>
        <div className="text-[26px] font-black tabular tracking-tight text-slate-900 leading-tight">
          {isLoading ? (
            <span className="inline-block w-28 h-8 bg-slate-100 rounded-xl animate-pulse" />
          ) : (
            value
          )}
        </div>
      </div>

      <div className="flex items-center justify-between gap-2 pt-1">
        {hint && (
          <div className="text-[11.5px] text-slate-500 font-medium truncate">
            {hint}
          </div>
        )}
        {trend && (
          <span
            className={cn(
              'inline-flex items-center text-[11px] font-bold px-2 py-0.5 rounded-full tabular shrink-0 ml-auto',
              trend.isPositive
                ? 'bg-emerald-50 text-emerald-600 border border-emerald-200/60'
                : 'bg-rose-50 text-rose-600 border border-rose-200/60'
            )}
          >
            {trend.value}
          </span>
        )}
      </div>
    </div>
  )

  const cardClasses = cn(
    'ui-card p-5 block transition-all duration-200 group cursor-pointer',
    className
  )

  if (to) {
    return (
      <motion.div whileHover={{ y: -3 }} whileTap={{ scale: 0.99 }}>
        <Link to={to} className={cardClasses}>
          {content}
        </Link>
      </motion.div>
    )
  }

  return <div className={cardClasses}>{content}</div>
}
