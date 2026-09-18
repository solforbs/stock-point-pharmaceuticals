import { useQuery } from '@tanstack/react-query'
import { Search } from 'lucide-react'
import { useEffect, useRef, useState, type KeyboardEvent, type Ref } from 'react'
import { api, apiGet } from '../lib/api'
import type { Paginated, Product } from '../lib/types'

export function useDebounced<T>(value: T, delay = 250): T {
  const [debounced, setDebounced] = useState(value)
  useEffect(() => {
    const t = window.setTimeout(() => setDebounced(value), delay)
    return () => window.clearTimeout(t)
  }, [value, delay])
  return debounced
}

export function useProductSearch(query: string, enabled = true) {
  const q = useDebounced(query.trim(), 200)
  return useQuery({
    queryKey: ['products', 'search', q],
    queryFn: () => apiGet<Paginated<Product>>('/api/products', { q, per_page: 20, is_active: 1 }),
    enabled: enabled && q.length > 0,
    staleTime: 60_000,
    placeholderData: (prev) => prev,
  })
}

/** Exact barcode match only (Part 24.2): a scan never fuzzy-matches. */
export async function lookupBarcode(barcode: string): Promise<Product | null> {
  const { data } = await api.get<Paginated<Product>>('/api/products', { params: { barcode, per_page: 1 } })
  return data.data[0] ?? null
}

export function defaultSalesUom(product: Product) {
  const uoms = product.uoms ?? []
  return uoms.find((u) => u.is_default_sales && u.is_sales) ?? uoms.find((u) => u.is_sales) ?? uoms.find((u) => u.is_base) ?? uoms[0]
}

/**
 * Part 1.5 — ProductSearch (dropdown form): fuzzy search, keyboard
 * navigation, never auto-selects; Enter with no highlighted row attempts an
 * exact barcode match. Strength shown in bold beside the name.
 */
export function ProductSearch({
  onSelect,
  placeholder = 'Search products by name, code, SKU or barcode…',
  inputRef,
  autoFocus,
  clearOnSelect = true,
  disabled,
}: {
  onSelect: (product: Product) => void
  placeholder?: string
  inputRef?: Ref<HTMLInputElement>
  autoFocus?: boolean
  clearOnSelect?: boolean
  disabled?: boolean
}) {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [highlight, setHighlight] = useState(-1)
  const [scanError, setScanError] = useState<string | null>(null)
  const wrapRef = useRef<HTMLDivElement>(null)
  const { data, isFetching } = useProductSearch(query, open)
  const results = data?.data ?? []

  useEffect(() => {
    function onDown(e: MouseEvent) {
      if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', onDown)
    return () => document.removeEventListener('mousedown', onDown)
  }, [])

  function choose(product: Product) {
    onSelect(product)
    setOpen(false)
    setHighlight(-1)
    setScanError(null)
    if (clearOnSelect) setQuery('')
  }

  async function onKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setOpen(true)
      setHighlight((h) => Math.min(h + 1, results.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setHighlight((h) => Math.max(h - 1, -1))
    } else if (e.key === 'Enter') {
      e.preventDefault()
      if (highlight >= 0 && results[highlight]) {
        choose(results[highlight])
        return
      }
      const term = query.trim()
      if (!term) return
      const product = await lookupBarcode(term)
      if (product) choose(product)
      else setScanError(`Unknown barcode "${term}"`)
    } else if (e.key === 'Escape') {
      setOpen(false)
      setQuery('')
      setScanError(null)
    }
  }

  return (
    <div ref={wrapRef} className="relative">
      <div className="relative">
        <Search size={15} className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none" />
        <input
          ref={inputRef}
          type="text"
          value={query}
          disabled={disabled}
          autoFocus={autoFocus}
          placeholder={placeholder}
          onChange={(e) => {
            setQuery(e.target.value)
            setOpen(true)
            setHighlight(-1)
            setScanError(null)
          }}
          onFocus={() => setOpen(true)}
          onKeyDown={onKeyDown}
          className="ui-input pl-10"
          autoComplete="off"
        />
      </div>
      {scanError && <div className="text-[12px] font-bold text-rose-600 mt-1.5 px-1">{scanError}</div>}
      {open && query.trim() && (
        <div className="absolute z-30 left-0 right-0 top-full mt-1.5 ui-card shadow-2xl max-h-72 overflow-y-auto border border-slate-200">
          {results.length === 0 ? (
            <div className="px-4 py-3 text-xs text-slate-500">{isFetching ? 'Searching…' : 'No products match.'}</div>
          ) : (
            results.map((product, i) => (
              <ProductResultRow key={product.id} product={product} active={i === highlight} onClick={() => choose(product)} onHover={() => setHighlight(i)} />
            ))
          )}
        </div>
      )}
    </div>
  )
}

export function ProductResultRow({
  product,
  active,
  onClick,
  onHover,
  extra,
}: {
  product: Product
  active?: boolean
  onClick: () => void
  onHover?: () => void
  extra?: React.ReactNode
}) {
  const uom = defaultSalesUom(product)
  return (
    <button
      type="button"
      onClick={onClick}
      onMouseEnter={onHover}
      className={`w-full text-left px-3.5 py-2 border-b border-slate-100 last:border-b-0 transition-colors cursor-pointer ${
        active ? 'bg-blue-50 text-blue-900' : 'hover:bg-slate-50 text-slate-800'
      }`}
    >
      <div className="flex items-baseline justify-between gap-2">
        <span className="text-[13.5px] font-bold text-slate-900 truncate">
          {product.name}
          {product.strength && <b className="ml-1 text-slate-700">{product.strength}</b>}
        </span>
        {uom && <span className="text-[11.5px] font-bold text-blue-700 bg-blue-100/60 px-1.5 py-0.2 rounded shrink-0">{uom.uom?.code ?? ''}</span>}
      </div>
      <div className="flex items-center justify-between gap-2 text-[11.5px] text-slate-500 mt-0.5">
        <span className="truncate">
          #{product.code}
          {product.generic_name ? ` · ${product.generic_name}` : ''}
        </span>
        {extra}
      </div>
    </button>
  )
}
