import { useRef, useState, type ButtonHTMLAttributes, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react'
import type { LucideIcon } from 'lucide-react'
import { FileSpreadsheet, Plus, UploadCloud, X } from 'lucide-react'

export type ButtonVariant = 'primary' | 'secondary' | 'danger' | 'ghost' | 'success' | 'outline'
export type ButtonSize = 'xs' | 'sm' | 'md' | 'lg'

const variantClass: Record<ButtonVariant, string> = {
  primary:
    'bg-blue-600 hover:bg-blue-700 text-white shadow-xs shadow-blue-500/20 border border-blue-600 active:scale-[0.98]',
  success:
    'bg-emerald-600 hover:bg-emerald-700 text-white shadow-xs shadow-emerald-500/20 border border-emerald-600 active:scale-[0.98]',
  secondary:
    'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border border-slate-200 hover:border-slate-300 shadow-2xs font-semibold active:scale-[0.98]',
  danger:
    'bg-rose-600 hover:bg-rose-700 text-white shadow-xs shadow-rose-500/20 border border-rose-600 active:scale-[0.98]',
  ghost:
    'bg-transparent text-slate-600 border-transparent hover:text-slate-900 hover:bg-slate-100/80 font-semibold',
  outline:
    'bg-transparent text-slate-700 border border-slate-200 hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900 font-semibold',
}

const sizeClass: Record<ButtonSize, string> = {
  xs: 'h-7 px-2.5 text-xs font-semibold rounded-lg gap-1',
  sm: 'h-8 px-3 text-xs font-semibold rounded-lg gap-1.5',
  md: 'h-9 px-3.5 text-xs sm:text-sm font-semibold rounded-xl gap-2',
  lg: 'h-11 px-5 text-sm font-semibold rounded-xl gap-2.5',
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
      className={`font-semibold shadow-xs ${className}`}
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
  id,
}: {
  label: ReactNode
  error?: string | null
  hint?: ReactNode
  children: ReactNode
  className?: string
  required?: boolean
  id?: string
}) {
  return (
    <div id={id} className={`space-y-1.5 ${className}`}>
      <label className="ui-label flex items-center justify-between">
        <span className="flex items-center gap-1">
          {label}
          {required && <span className="text-rose-500 font-bold">*</span>}
        </span>
      </label>
      {children}
      {error ? (
        <p className="text-xs font-medium text-rose-600 mt-1 flex items-center gap-1">{error}</p>
      ) : hint ? (
        <p className="text-xs text-slate-500 mt-1 font-normal leading-normal">{hint}</p>
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
    <div className={`p-4 sm:p-5 bg-white rounded-xl border border-slate-200 shadow-xs space-y-3.5 ${className}`}>
      <div className="flex items-start justify-between gap-3 pb-2.5 border-b border-slate-100">
        <div className="flex items-center gap-2.5">
          {Icon && (
            <div className="w-7 h-7 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center shrink-0">
              <Icon size={14} />
            </div>
          )}
          <div>
            <h3 className="text-sm font-semibold text-slate-900 leading-none">{title}</h3>
            {description && <p className="text-xs text-slate-500 font-normal mt-1">{description}</p>}
          </div>
        </div>
        {badge && (
          <span className="text-xs font-medium px-2 py-0.5 rounded-md bg-slate-100 text-slate-600 border border-slate-200">
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
    <div className="sticky bottom-0 z-10 -mx-4 sm:-mx-6 -mb-4 sm:-mb-6 p-4 sm:px-6 bg-white/95 backdrop-blur-md border-t border-slate-200 flex flex-col-reverse sm:flex-row items-center justify-between gap-3 mt-6 shadow-[0_-4px_16px_rgba(0,0,0,0.03)]">
      <div className="flex items-center gap-2.5 min-w-0 w-full sm:w-auto">
        {badge && (
          <span className="inline-flex items-center justify-center h-6 px-2.5 rounded-md bg-slate-100 border border-slate-200 text-slate-700 text-xs font-medium tabular shrink-0">
            {badge}
          </span>
        )}
        {summary && (
          <div className="text-xs text-slate-600 truncate">{summary}</div>
        )}
        {children}
      </div>
      <div className="flex items-center justify-end gap-2 w-full sm:w-auto shrink-0">
        <Button onClick={onCancel} variant="secondary" size="md" className="w-full sm:w-auto font-medium">
          {cancelLabel}
        </Button>
        {onSubmit && (
          <Button
            type="submit"
            variant={variant}
            size="md"
            disabled={disabled || pending}
            onClick={onSubmit}
            className="w-full sm:w-auto font-semibold shadow-xs"
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
  padded = false,
}: {
  children: ReactNode
  className?: string
  title?: ReactNode
  actions?: ReactNode
  padded?: boolean
}) {
  return (
    <section className={`ui-card ${className}`}>
      {(title || actions) && (
        <header className="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
          <h2 className="text-sm font-semibold text-slate-900 tracking-tight">{title}</h2>
          {actions && <div className="flex items-center gap-2">{actions}</div>}
        </header>
      )}
      {padded ? <div className="p-5 sm:p-6">{children}</div> : children}
    </section>
  )
}

export function FileDropzone({
  accept = '.csv,text/csv',
  fileName,
  hint,
  onFileSelect,
  onClear,
  className = '',
}: {
  accept?: string
  fileName?: string
  hint?: ReactNode
  onFileSelect: (file: File | undefined) => void
  onClear?: () => void
  className?: string
}) {
  const [isDragOver, setIsDragOver] = useState(false)
  const inputRef = useRef<HTMLInputElement>(null)

  return (
    <div
      onClick={() => inputRef.current?.click()}
      onDragOver={(e) => {
        e.preventDefault()
        setIsDragOver(true)
      }}
      onDragLeave={() => setIsDragOver(false)}
      onDrop={(e) => {
        e.preventDefault()
        setIsDragOver(false)
        const file = e.dataTransfer.files?.[0]
        if (file) onFileSelect(file)
      }}
      className={`group relative flex flex-col items-center justify-center p-5 sm:p-6 border-2 border-dashed rounded-xl transition-all cursor-pointer select-none text-center ${
        isDragOver
          ? 'border-blue-500 bg-blue-50/70'
          : fileName
            ? 'border-emerald-300 bg-emerald-50/30 hover:border-emerald-400'
            : 'border-slate-200/90 hover:border-blue-400 bg-slate-50/50 hover:bg-blue-50/30'
      } ${className}`}
    >
      <input
        ref={inputRef}
        type="file"
        accept={accept}
        className="sr-only"
        onChange={(e) => {
          const file = e.target.files?.[0]
          if (file) onFileSelect(file)
          e.target.value = ''
        }}
      />
      {fileName ? (
        <div className="flex items-center justify-between w-full gap-3">
          <div className="flex items-center gap-3 min-w-0">
            <div className="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
              <FileSpreadsheet size={20} />
            </div>
            <div className="text-left min-w-0">
              <p className="text-sm font-semibold text-slate-800 truncate">{fileName}</p>
              <p className="text-xs text-emerald-600 font-medium mt-0.5">Ready for checking · Click to change file</p>
            </div>
          </div>
          {onClear && (
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation()
                onClear()
              }}
              className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-200/60 transition-colors"
              title="Remove file"
            >
              <X size={16} />
            </button>
          )}
        </div>
      ) : (
        <div className="flex flex-col items-center gap-2 py-1">
          <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 group-hover:bg-blue-100 group-hover:scale-105 transition-all flex items-center justify-center shadow-xs">
            <UploadCloud size={20} />
          </div>
          <div>
            <p className="text-sm font-semibold text-slate-700 group-hover:text-blue-600 transition-colors">
              Click to browse or drop your CSV file here
            </p>
            {hint && <p className="text-xs text-slate-500 mt-1 max-w-md font-normal leading-normal">{hint}</p>}
          </div>
        </div>
      )}
    </div>
  )
}

export { MovingBorder, MovingBorderButton, MovingBorderCard } from './moving-border'

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
    <dl className={`grid grid-cols-[auto_1fr] gap-x-5 gap-y-2.5 text-sm ${className}`}>
      {items.map((item, i) => (
        <div key={i} className="contents">
          <dt className="text-slate-500 font-medium whitespace-nowrap">{item.label}</dt>
          <dd className="text-slate-900 font-medium min-w-0 break-words">{item.value ?? '—'}</dd>
        </div>
      ))}
    </dl>
  )
}
