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
    <div className="flex flex-col justify-between h-full space-y-3.5">
      <div className="flex items-center justify-between">
        <span className="text-[13px] font-bold text-slate-500">
          {label}
        </span>
        <div
          className="w-10 h-10 rounded-2xl flex items-center justify-center transition-transform duration-200 group-hover:scale-105 shrink-0"
          style={{
            background: `color-mix(in srgb, ${tone} 10%, transparent)`,
            color: tone,
          }}
        >
          <Icon size={19} />
        </div>
      </div>

      <div>
        <div className="text-[28px] font-black tabular tracking-tight text-slate-900 leading-tight">
          {isLoading ? (
            <span className="inline-block w-32 h-8 bg-slate-100/80 rounded-xl animate-pulse" />
          ) : (
            value
          )}
        </div>
      </div>

      <div className="flex items-center justify-between gap-2 pt-0.5">
        {hint && (
          <div className="text-[12px] text-slate-400 font-medium truncate">
            {hint}
          </div>
        )}
        {trend && (
          <span
            className={cn(
              'inline-flex items-center text-[11px] font-bold px-2.5 py-0.5 rounded-full tabular shrink-0 ml-auto',
              trend.isPositive
                ? 'bg-emerald-50 text-emerald-700'
                : 'bg-rose-50 text-rose-700'
            )}
          >
            {trend.value}
          </span>
        )}
      </div>
    </div>
  )

  const cardClasses = cn(
    'bg-white rounded-[24px] p-6 shadow-[0_2px_12px_-2px_rgba(15,23,42,0.03),0_10px_28px_-6px_rgba(15,23,42,0.03)] hover:shadow-[0_6px_24px_-4px_rgba(15,23,42,0.06),0_16px_36px_-6px_rgba(15,23,42,0.05)] transition-all duration-300 block group',
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
