import type { ButtonHTMLAttributes, InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react'

type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'success'
type ButtonSize = 'sm' | 'md' | 'lg'

const variantClass: Record<ButtonVariant, string> = {
  primary: 'bg-[var(--color-navy)] text-white hover:bg-[var(--color-navy-dark)] border-transparent',
  success: 'bg-[var(--status-green)] text-white hover:brightness-95 border-transparent',
  secondary: 'bg-[var(--card)] text-[var(--text)] border-[var(--border-strong)] hover:bg-[var(--surface-2)]',
  danger: 'bg-[var(--status-red)] text-white hover:brightness-95 border-transparent',
  ghost: 'bg-transparent text-[var(--text-secondary)] border-transparent hover:bg-[var(--surface-2)]',
}

const sizeClass: Record<ButtonSize, string> = {
  sm: 'h-7 px-2.5 text-[11.5px]',
  md: 'h-8 px-3.5 text-[12.5px]',
  lg: 'h-10 px-5 text-[13px] font-bold',
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
      className={`inline-flex items-center justify-center gap-1.5 rounded-md border font-semibold whitespace-nowrap transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${variantClass[variant]} ${sizeClass[size]} ${className}`}
      {...rest}
    />
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
    <div className={className}>
      <label className="ui-label">
        {label}
        {required && <span className="text-[var(--status-red)] ml-0.5">*</span>}
      </label>
      {children}
      {error ? (
        <p className="text-[11px] text-[var(--status-red)] mt-1">{error}</p>
      ) : hint ? (
        <p className="text-[11px] text-[var(--text-muted)] mt-1">{hint}</p>
      ) : null}
    </div>
  )
}

export function Card({ children, className = '', title, actions }: { children: ReactNode; className?: string; title?: ReactNode; actions?: ReactNode }) {
  return (
    <section className={`ui-card ${className}`}>
      {(title || actions) && (
        <header className="flex items-center justify-between px-4 py-2.5 border-b border-[var(--border)]">
          <h2 className="text-[13px] font-bold text-[var(--text)]">{title}</h2>
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

export function DescriptionList({ items, className = '' }: { items: { label: ReactNode; value: ReactNode }[]; className?: string }) {
  return (
    <dl className={`grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-[12px] ${className}`}>
      {items.map((item, i) => (
        <div key={i} className="contents">
          <dt className="text-[var(--text-muted)] whitespace-nowrap">{item.label}</dt>
          <dd className="text-[var(--text)] min-w-0 break-words">{item.value ?? '—'}</dd>
        </div>
      ))}
    </dl>
  )
}
