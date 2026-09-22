import { useQuery } from '@tanstack/react-query'
import { UserSquare2, X } from 'lucide-react'
import { useEffect, useRef, useState, type KeyboardEvent, type Ref } from 'react'
import { apiGet } from '../lib/api'
import { dIsNeg } from '../lib/decimal'
import { formatMoney } from '../lib/money'
import type { Customer, Paginated } from '../lib/types'
import { useDebounced } from './ProductSearch'
import { StatusBadge } from './ui/StatusBadge'

export function useCustomerSearch(query: string, enabled = true) {
  const q = useDebounced(query.trim(), 200)
  return useQuery({
    queryKey: ['customers', 'search', q],
    queryFn: () => apiGet<Paginated<Customer>>('/api/customers', { q, per_page: 20 }),
    enabled,
    staleTime: 60_000,
    placeholderData: (prev) => prev,
  })
}

export function useCustomer(id: string | null | undefined) {
  return useQuery({
    queryKey: ['customers', id],
    queryFn: () => apiGet<Customer>(`/api/customers/${id}`),
    enabled: !!id,
  })
}

/**
 * Part 1.5 — CustomerPicker: shows tier, credit limit, current exposure and
 * available credit inline, with an over-limit / on-hold warning.
 */
export function CustomerPicker({
  value,
  onChange,
  inputRef,
  required,
  disabled,
  placeholder = 'Search customers by name, code or phone…',
}: {
  value: Customer | null
  onChange: (customer: Customer | null) => void
  inputRef?: Ref<HTMLInputElement>
  required?: boolean
  disabled?: boolean
  placeholder?: string
}) {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [highlight, setHighlight] = useState(-1)
  const wrapRef = useRef<HTMLDivElement>(null)
  const { data, isFetching } = useCustomerSearch(query, open)
  const results = data?.data ?? []

  useEffect(() => {
    function onDown(e: MouseEvent) {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    return () => document.removeEventListener('mousedown', onDown)
  }, [])

  function choose(customer: Customer) {
    onChange(customer)
    setOpen(false)
    setQuery('')
    setHighlight(-1)
  }

  function onKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setOpen(true)
      setHighlight((h) => Math.min(h + 1, results.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setHighlight((h) => Math.max(h - 1, -1))
    } else if (e.key === 'Enter') {
      e.preventDefault()
      const pick = highlight >= 0 ? results[highlight] : results.length === 1 ? results[0] : undefined
      if (pick) choose(pick)
    } else if (e.key === 'Escape') {
      setOpen(false)
    }
  }

  if (value) {
    return (
      <div className="flex items-center justify-between gap-3 px-3.5 py-2.5 rounded-2xl bg-slate-50/90 border border-slate-200/70 shadow-2xs">
        <div className="flex items-center gap-2.5 min-w-0 flex-1">
          <div className="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-xs">
            <UserSquare2 size={16} />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="text-sm font-bold text-slate-900 truncate">{value.name}</span>
              {value.tier?.code && (
                <span className="px-2.5 py-0.5 rounded-full bg-blue-50 text-blue-700 border border-blue-200/60 text-xs font-bold">
                  Tier {value.tier.code}
                </span>
              )}
            </div>
            <CustomerCreditLine customer={value} className="mt-0.5" />
          </div>
        </div>
        {!disabled && (
          <button
            type="button"
            aria-label="Clear customer"
            onClick={() => onChange(null)}
            className="p-1.5 rounded-full text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer shrink-0"
            title="Clear customer (F5 to reselect)"
          >
            <X size={15} />
          </button>
        )}
      </div>
    )
  }

  return (
    <div ref={wrapRef} className="relative">
      <input
        ref={inputRef}
        type="text"
        value={query}
        disabled={disabled}
        placeholder={placeholder}
        onChange={(e) => {
          setQuery(e.target.value)
          setOpen(true)
          setHighlight(-1)
        }}
        onFocus={() => setOpen(true)}
        onKeyDown={onKeyDown}
        className={`ui-input rounded-2xl ${required ? '!border-amber-400' : ''}`}
        autoComplete="off"
      />
      {open && (
        <div className="absolute z-30 left-0 right-0 top-10 rounded-2xl bg-white border border-slate-200 shadow-xl max-h-72 overflow-y-auto">
          {results.length === 0 ? (
            <div className="px-3 py-2.5 text-xs text-slate-500">{isFetching ? 'Searching…' : 'No customers match.'}</div>
          ) : (
            results.map((customer, i) => (
              <button
                key={customer.id}
                type="button"
                onClick={() => choose(customer)}
                onMouseEnter={() => setHighlight(i)}
                className={`w-full text-left px-3.5 py-2.5 border-b border-slate-100 last:border-b-0 transition-colors ${i === highlight ? 'bg-slate-100' : 'hover:bg-slate-50'}`}
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="text-sm font-medium text-slate-900 truncate">
                    {customer.name} <span className="text-slate-500 font-mono text-xs">· {customer.code}</span>
                  </span>
                  {customer.credit?.on_hold && <StatusBadge status="ON_HOLD" label="Credit hold" />}
                </div>
                <CustomerCreditLine customer={customer} />
              </button>
            ))
          )}
        </div>
      )}
    </div>
  )
}

export function CustomerCreditLine({ customer, className = '' }: { customer: Customer; className?: string }) {
  const available = customer.available_credit ?? null
  const overLimit = available !== null && dIsNeg(available)
  return (
    <div className={`text-xs tabular flex flex-wrap items-center gap-x-2 text-slate-600 font-medium ${className}`}>
      <span>Credit Limit: <strong className="text-slate-800 font-semibold">{formatMoney(customer.credit?.credit_limit ?? '0')}</strong></span>
      <span className="text-slate-300">·</span>
      <span>Balance: <strong className="text-slate-800 font-semibold">{formatMoney(customer.credit?.current_balance ?? '0')}</strong></span>
      <span className="text-slate-300">·</span>
      {available !== null && (
        <span className={overLimit ? 'text-rose-600 font-bold' : 'text-emerald-700 font-bold'}>
          Available: {formatMoney(available)}
        </span>
      )}
      {customer.credit?.on_hold && (
        <span className="px-2 py-0.5 rounded-full bg-rose-50 text-rose-700 border border-rose-200 font-bold text-xs uppercase tracking-wide">
          Credit Hold
        </span>
      )}
    </div>
  )
}
