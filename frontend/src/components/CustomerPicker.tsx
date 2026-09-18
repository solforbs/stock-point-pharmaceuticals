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
      <div className="flex items-center gap-2 ui-input !h-auto py-1.5">
        <UserSquare2 size={14} className="text-[var(--text-muted)] shrink-0" />
        <div className="flex-1 min-w-0">
          <div className="text-[12.5px] font-semibold truncate">{value.name}</div>
          <CustomerCreditLine customer={value} />
        </div>
        {!disabled && (
          <button type="button" aria-label="Clear customer" onClick={() => onChange(null)} className="text-[var(--text-muted)] hover:text-[var(--text)]">
            <X size={14} />
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
        className={`ui-input ${required ? '!border-[var(--status-amber)]' : ''}`}
        autoComplete="off"
      />
      {open && (
        <div className="absolute z-30 left-0 right-0 top-9 ui-card shadow-xl max-h-72 overflow-y-auto">
          {results.length === 0 ? (
            <div className="px-3 py-2 text-[11.5px] text-[var(--text-muted)]">{isFetching ? 'Searching…' : 'No customers match.'}</div>
          ) : (
            results.map((customer, i) => (
              <button
                key={customer.id}
                type="button"
                onClick={() => choose(customer)}
                onMouseEnter={() => setHighlight(i)}
                className={`w-full text-left px-3 py-1.5 border-b border-[var(--border)] last:border-b-0 ${i === highlight ? 'bg-[var(--surface-3)]' : 'hover:bg-[var(--surface-2)]'}`}
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="text-[12.5px] truncate">
                    {customer.name} <span className="text-[var(--text-muted)]">· {customer.code}</span>
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
    <div className={`text-[10.5px] text-[var(--text-muted)] tabular flex flex-wrap gap-x-2 ${className}`}>
      <span>Tier {customer.tier?.code ?? '—'}</span>
      <span>Limit {formatMoney(customer.credit?.credit_limit ?? '0')}</span>
      <span>Balance {formatMoney(customer.credit?.current_balance ?? '0')}</span>
      {available !== null && <span className={overLimit ? 'text-[var(--status-red)] font-bold' : ''}>Available {formatMoney(available)}</span>}
      {customer.credit?.on_hold && <span className="text-[var(--status-red)] font-bold">ON HOLD</span>}
    </div>
  )
}
