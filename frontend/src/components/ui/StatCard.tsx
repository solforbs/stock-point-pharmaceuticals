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
  tone = 'var(--color-navy)',
  trend,
  className,
  isLoading = false,
}: StatCardProps) {
  const content = (
    <div className="flex items-start justify-between gap-3">
      <div className="flex items-start gap-3 min-w-0 flex-1">
        <div
          className="p-2.5 rounded-xl shrink-0 transition-transform duration-200 group-hover:scale-105"
          style={{
            background: `color-mix(in srgb, ${tone} 12%, transparent)`,
            color: tone,
            boxShadow: `0 0 12px color-mix(in srgb, ${tone} 15%, transparent)`,
          }}
        >
          <Icon size={20} />
        </div>
        <div className="min-w-0 flex-1">
          <span className="ui-label !mb-1 text-[11px] font-bold tracking-wider">{label}</span>
          <div className="text-[24px] font-black tabular tracking-tight leading-tight text-[var(--text)]">
            {isLoading ? (
              <span className="inline-block w-24 h-7 bg-[var(--surface-3)] rounded animate-pulse" />
            ) : (
              value
            )}
          </div>
          {hint && (
            <div className="text-[11.5px] text-[var(--text-muted)] mt-1 font-medium leading-normal">
              {hint}
            </div>
          )}
        </div>
      </div>
      {trend && (
        <span
          className={cn(
            'inline-flex items-center text-[11px] font-bold px-2 py-0.5 rounded-full tabular shrink-0',
            trend.isPositive
              ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20'
              : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'
          )}
        >
          {trend.value}
        </span>
      )}
    </div>
  )

  const cardClasses = cn(
    'ui-card p-5 block relative overflow-hidden transition-all duration-200 border-[var(--border)] group',
    'hover:border-[var(--color-navy-light)] hover:shadow-md hover:shadow-black/5 dark:hover:shadow-black/20',
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
