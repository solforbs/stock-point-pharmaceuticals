import type { HTMLAttributes, ReactNode } from 'react'
import { cn } from '../../lib/utils'

export type BadgeVariant = 'default' | 'outline' | 'subtle' | 'pill'
export type BadgeColor = 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'purple' | 'neutral'

export interface BadgeProps extends HTMLAttributes<HTMLSpanElement> {
  children: ReactNode
  variant?: BadgeVariant
  color?: BadgeColor
  dot?: boolean
  className?: string
}

const colorMap: Record<BadgeColor, { bg: string; text: string; border: string; dot: string }> = {
  primary: {
    bg: 'bg-[color-mix(in_srgb,var(--color-navy)_12%,transparent)]',
    text: 'text-[var(--color-navy)]',
    border: 'border-[color-mix(in_srgb,var(--color-navy)_25%,transparent)]',
    dot: 'bg-[var(--color-navy)]',
  },
  success: {
    bg: 'bg-emerald-500/12',
    text: 'text-emerald-700',
    border: 'border-emerald-500/25',
    dot: 'bg-emerald-500',
  },
  warning: {
    bg: 'bg-amber-500/15',
    text: 'text-amber-800',
    border: 'border-amber-500/25',
    dot: 'bg-amber-500',
  },
  danger: {
    bg: 'bg-rose-500/12',
    text: 'text-rose-700',
    border: 'border-rose-500/25',
    dot: 'bg-rose-500',
  },
  info: {
    bg: 'bg-sky-500/12',
    text: 'text-sky-700',
    border: 'border-sky-500/25',
    dot: 'bg-sky-500',
  },
  purple: {
    bg: 'bg-purple-500/12',
    text: 'text-purple-700',
    border: 'border-purple-500/25',
    dot: 'bg-purple-500',
  },
  neutral: {
    bg: 'bg-[var(--surface-2)]',
    text: 'text-[var(--text-secondary)]',
    border: 'border-[var(--border)]',
    dot: 'bg-[var(--text-muted)]',
  },
}

export function Badge({
  children,
  variant = 'subtle',
  color = 'neutral',
  dot = false,
  className,
  ...props
}: BadgeProps) {
  const c = colorMap[color]

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1.5 px-2 py-0.5 text-[11px] font-semibold tracking-wide transition-colors',
        variant === 'pill' ? 'rounded-full' : 'rounded-md',
        variant === 'outline'
          ? cn('bg-transparent border', c.text, c.border)
          : variant === 'subtle'
          ? cn('border border-transparent', c.bg, c.text)
          : cn('text-white', c.dot),
        className
      )}
      {...props}
    >
      {dot && <span className={cn('w-1.5 h-1.5 rounded-full shrink-0', c.dot)} />}
      {children}
    </span>
  )
}
