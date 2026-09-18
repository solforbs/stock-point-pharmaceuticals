import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'
import type { LucideIcon } from 'lucide-react'
import { Plus } from 'lucide-react'

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'success' | 'outline'
export type ButtonSize = 'xs' | 'sm' | 'md' | 'lg'

const variantClass: Record<ButtonVariant, string> = {
  primary:
    'bg-gradient-to-r from-blue-600 via-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white shadow-sm shadow-blue-500/25 border border-blue-500/30 active:scale-[0.98]',
  success:
    'bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-700 hover:to-teal-700 text-white shadow-sm shadow-emerald-500/20 border border-emerald-500/30 active:scale-[0.98]',
  secondary:
    'bg-white text-slate-800 border-slate-200/90 hover:bg-slate-50/90 hover:border-slate-300 shadow-2xs text-slate-700 active:scale-[0.99]',
  danger:
    'bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-700 hover:to-red-700 text-white shadow-sm shadow-rose-500/20 border border-rose-500/30 active:scale-[0.98]',
  ghost:
    'bg-transparent text-slate-600 border-transparent hover:text-slate-900 hover:bg-slate-100/80',
  outline:
    'bg-transparent text-blue-600 border-blue-200 hover:bg-blue-50/80 hover:border-blue-300 font-bold',
}

const sizeClass: Record<ButtonSize, string> = {
  xs: 'h-7 px-2.5 text-[11.5px] font-semibold rounded-lg gap-1',
  sm: 'h-8 px-3 text-[12.5px] font-bold rounded-xl gap-1.5',
  md: 'h-9.5 px-4 text-[13.5px] font-bold rounded-xl gap-2',
  lg: 'h-11 px-5 text-[14.5px] font-extrabold rounded-2xl gap-2.5',
}

export function Button({
  variant = 'secondary',
  size = 'md',
  className = '',
  type = 'button',
  ...rest
}: ButtonHTMLAttributes<HTMLButtonElement> & { variant?: ButtonVariant; size?: ButtonSize }) {
  return (
    <button
      type={type}
      className={`inline-flex items-center justify-center border select-none whitespace-nowrap transition-all cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed disabled:pointer-events-none ${variantClass[variant]} ${sizeClass[size]} ${className}`}
      {...rest}
    />
  )
}

/** Prominent action button used at top of pages to open creation drawers (e.g. + New Purchase Order) */
export function PrimaryAction({
  children,
  icon: Icon = Plus,
  onClick,
  disabled,
  className = '',
  size = 'md',
}: {
  children: ReactNode
  icon?: LucideIcon
  onClick?: () => void
  disabled?: boolean
  className?: string
  size?: ButtonSize
}) {
  return (
    <Button
      variant="primary"
      size={size}
      onClick={onClick}
      disabled={disabled}
      className={`shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30 font-bold ${className}`}
    >
      <Icon size={size === 'lg' ? 18 : size === 'sm' ? 14 : 16} className="shrink-0" />
      <span>{children}</span>
    </Button>
  )
}

export function Input({ className = '', ...rest }: InputHTMLAttributes<HTMLInputElement>) {
  return <input className={`ui-input ${className}`} {...rest} />
}

export function Textarea({ className = '', ...rest }: TextareaHTMLAttributes<HTMLTextAreaElement>) {
  return <textarea className={`ui-input ${className}`} {...rest} />
}

export function Select({ className = '', children, ...rest }: SelectHTMLAttributes<HTMLSelectElement>) {
  return (
    <select className={`ui-input ${className}`} {...rest}>
      {children}
    </select>
  )
}

export function Field({
  label,
  error,
  hint,
  children,
  className = '',
  required,
}: {
  label: ReactNode
  error?: string | null
  hint?: ReactNode
  children: ReactNode
  className?: string
  required?: boolean
}) {
  return (
    <div className={`space-y-1.5 ${className}`}>
      <label className="ui-label flex items-center justify-between">
        <span className="flex items-center gap-1">
          {label}
          {required && <span className="text-rose-500 font-black">*</span>}
        </span>
      </label>
      {children}
      {error ? (
        <p className="text-[11.5px] font-bold text-rose-600 mt-1 flex items-center gap-1">{error}</p>
      ) : hint ? (
        <p className="text-[11.5px] text-slate-500 mt-1 font-medium leading-normal">{hint}</p>
      ) : null}
    </div>
  )
}

/** Visually structured sub-section inside drawers and modals */
export function FormSection({
  title,
  description,
  icon: Icon,
  badge,
  children,
  className = '',
}: {
  title: string
  description?: string
  icon?: LucideIcon
  badge?: string
  children: ReactNode
  className?: string
}) {
  return (
    <div className={`p-4 sm:p-5 bg-white rounded-2xl border border-slate-200/80 shadow-2xs space-y-3.5 ${className}`}>
      <div className="flex items-start justify-between gap-3 pb-2.5 border-b border-slate-100">
        <div className="flex items-center gap-2.5">
          {Icon && (
            <div className="w-7 h-7 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
              <Icon size={14} />
            </div>
          )}
          <div>
            <h3 className="text-[13.5px] font-extrabold text-slate-900 leading-none">{title}</h3>
            {description && <p className="text-[11.5px] text-slate-500 font-medium mt-1">{description}</p>}
          </div>
        </div>
        {badge && (
          <span className="text-[10.5px] font-bold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
            {badge}
          </span>
        )}
      </div>
      <div className="space-y-3">{children}</div>
    </div>
  )
}

/** Standardized sticky glassmorphic footer for all slide-over drawers */
export function DrawerFooter({
  onCancel,
  onSubmit,
  submitLabel = 'Save changes',
  cancelLabel = 'Cancel',
  isSubmitting,
  isPending,
  disabled = false,
  variant = 'primary',
  badge,
  summary,
  children,
}: {
  onCancel: () => void
  onSubmit?: () => void
  submitLabel?: string
  cancelLabel?: string
  isSubmitting?: boolean
  isPending?: boolean
  disabled?: boolean
  variant?: ButtonVariant
  badge?: ReactNode
  summary?: ReactNode
  children?: ReactNode
}) {
  const pending = isSubmitting ?? isPending ?? false
  return (
    <div className="sticky bottom-0 z-10 -mx-4 sm:-mx-6 -mb-4 sm:-mb-6 p-4 sm:px-6 bg-white/95 backdrop-blur-md border-t border-slate-200/90 flex flex-col-reverse sm:flex-row items-center justify-between gap-3 mt-6 shadow-[0_-4px_16px_rgba(0,0,0,0.03)]">
      <div className="flex items-center gap-2.5 min-w-0 w-full sm:w-auto">
        {badge && (
          <span className="inline-flex items-center justify-center h-6 px-2.5 rounded-full bg-slate-100 border border-slate-200 text-slate-700 text-[11px] font-bold tabular shrink-0">
            {badge}
          </span>
        )}
        {summary && (
          <div className="text-xs text-slate-600 truncate">{summary}</div>
        )}
        {children}
      </div>
      <div className="flex items-center justify-end gap-2 w-full sm:w-auto shrink-0">
        <Button onClick={onCancel} variant="secondary" size="md" className="w-full sm:w-auto font-semibold">
          {cancelLabel}
        </Button>
        {onSubmit && (
          <Button
            type="submit"
            variant={variant}
            size="md"
            disabled={disabled || pending}
            onClick={onSubmit}
            className="w-full sm:w-auto font-extrabold shadow-sm"
          >
            {pending ? 'Saving…' : submitLabel}
          </Button>
        )}
      </div>
    </div>
  )
}

export function Card({
  children,
  className = '',
  title,
  actions,
}: {
  children: ReactNode
  className?: string
  title?: ReactNode
  actions?: ReactNode
}) {
  return (
    <section className={`ui-card ${className}`}>
      {(title || actions) && (
        <header className="flex items-center justify-between px-5 py-3.5 border-b border-[var(--border)]">
          <h2 className="text-[14px] font-bold text-[var(--text)]">{title}</h2>
          <div className="flex items-center gap-2">{actions}</div>
        </header>
      )}
      {children}
    </section>
  )
}

export function Kbd({ children }: { children: ReactNode }) {
  return <kbd className="ui-kbd">{children}</kbd>
}

export function DescriptionList({
  items,
  className = '',
}: {
  items: { label: ReactNode; value: ReactNode }[]
  className?: string
}) {
  return (
    <dl className={`grid grid-cols-[auto_1fr] gap-x-5 gap-y-2 text-[13px] ${className}`}>
      {items.map((item, i) => (
        <div key={i} className="contents">
          <dt className="text-slate-500 font-semibold whitespace-nowrap">{item.label}</dt>
          <dd className="text-slate-900 font-medium min-w-0 break-words">{item.value ?? '—'}</dd>
        </div>
      ))}
    </dl>
  )
}
