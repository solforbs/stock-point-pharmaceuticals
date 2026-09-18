import { useQuery } from '@tanstack/react-query'
import { ScanBarcode } from 'lucide-react'
import { useState, type KeyboardEvent, type RefObject } from 'react'
import { lookupBarcode, ProductResultRow, useDebounced, useProductSearch } from '../../components/ProductSearch'
import { Kbd } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { formatQty } from '../../lib/money'
import type { Product, StockStateRow } from '../../lib/types'
import { useCartStore } from './cartStore'

/**
 * Part 24.1 left panel — [F2] scan or search. Exact match only on scan;
 * fuzzy search never auto-selects; free-to-sell and nearest expiry inline.
 */
export function SearchPanel({ inputRef, onAdded }: { inputRef: RefObject<HTMLInputElement | null>; onAdded: (lineRef: string) => void }) {
  const [query, setQuery] = useState('')
  const [highlight, setHighlight] = useState(-1)
  const [scanError, setScanError] = useState<string | null>(null)
  const [recent, setRecent] = useState<Product[]>([])
  const storeId = useCartStore((s) => s.storeId)
  const addProduct = useCartStore((s) => s.addProduct)
  const posted = useCartStore((s) => s.status === 'POSTED')

  const { data, isFetching } = useProductSearch(query)
  const results = data?.data ?? []

  const debouncedQ = useDebounced(query.trim(), 250)
  const stock = useQuery({
    queryKey: ['inventory', 'stock', { q: debouncedQ, store_id: storeId }],
    queryFn: () => apiGet<{ data: StockStateRow[] }>('/api/inventory/stock', { q: debouncedQ, store_id: storeId }),
    enabled: debouncedQ.length > 0 && !!storeId,
    staleTime: 15_000,
    placeholderData: (prev) => prev,
  })
  const stockByProduct = new Map((stock.data?.data ?? []).map((row) => [row.product_id, row]))

  function add(product: Product) {
    const lineRef = addProduct(product)
    if (!lineRef) {
      setScanError(`${product.name} has no sales unit configured.`)
      return
    }
    setRecent((prev) => [product, ...prev.filter((p) => p.id !== product.id)].slice(0, 6))
    setQuery('')
    setHighlight(-1)
    setScanError(null)
    onAdded(lineRef)
  }

  async function onKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setHighlight((h) => Math.min(h + 1, results.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setHighlight((h) => Math.max(h - 1, -1))
    } else if (e.key === 'Enter') {
      e.preventDefault()
      if (highlight >= 0 && results[highlight]) {
        add(results[highlight])
        return
      }
      const term = query.trim()
      if (!term) return
      const product = await lookupBarcode(term)
      if (product) add(product)
      else setScanError(`Unknown barcode "${term}". Search by name instead, or check the product's barcodes.`)
    } else if (e.key === 'Escape') {
      setQuery('')
      setHighlight(-1)
      setScanError(null)
    }
  }

  return (
    <div className="w-[300px] shrink-0 border-r border-[var(--border)] bg-[var(--card)] flex flex-col min-h-0">
      <div className="p-3 border-b border-[var(--border)]">
        <div className="relative">
          <ScanBarcode size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-[var(--text-muted)]" />
          <input
            ref={inputRef}
            type="text"
            value={query}
            disabled={posted}
            onChange={(e) => {
              setQuery(e.target.value)
              setHighlight(-1)
              setScanError(null)
            }}
            onKeyDown={onKeyDown}
            placeholder="Scan or search…"
            className="ui-input pl-8 pr-9 h-9 text-[13px]"
            autoComplete="off"
            autoFocus
          />
          <span className="absolute right-2 top-1/2 -translate-y-1/2">
            <Kbd>F2</Kbd>
          </span>
        </div>
        {scanError && (
          <div role="alert" className="text-[11px] text-[var(--status-red)] mt-1.5">
            {scanError}
          </div>
        )}
      </div>

      <div className="flex-1 overflow-y-auto pos-scroll">
        {query.trim() ? (
          results.length === 0 ? (
            <div className="px-3 py-3 text-[11.5px] text-[var(--text-muted)]">{isFetching ? 'Searching…' : 'No products match. Enter tries an exact barcode.'}</div>
          ) : (
            results.map((product, i) => {
              const row = stockByProduct.get(product.id)
              return (
                <ProductResultRow
                  key={product.id}
                  product={product}
                  active={i === highlight}
                  onClick={() => add(product)}
                  onHover={() => setHighlight(i)}
                  extra={
                    <span className="tabular shrink-0">
                      {row ? (
                        <>
                          <span className={Number(row.free_to_sell) > 0 ? 'text-[var(--status-green)] font-semibold' : 'text-[var(--status-red)] font-semibold'}>
                            {formatQty(row.free_to_sell)} free
                          </span>
                          {row.nearest_expiry && <span> · exp {formatDate(row.nearest_expiry)}</span>}
                        </>
                      ) : stock.isFetching ? (
                        '…'
                      ) : (
                        <span className="text-[var(--status-red)]">0 free</span>
                      )}
                    </span>
                  }
                />
              )
            })
          )
        ) : (
          <div className="p-3">
            <div className="ui-label">Recent</div>
            {recent.length === 0 ? (
              <p className="text-[11px] text-[var(--text-muted)]">Products you add appear here for one-click re-adding.</p>
            ) : (
              <div className="flex flex-wrap gap-1.5">
                {recent.map((product) => (
                  <button
                    key={product.id}
                    type="button"
                    onClick={() => add(product)}
                    className="px-2 py-1 rounded border border-[var(--border)] bg-[var(--surface-2)] text-[11px] hover:border-[var(--color-navy)]"
                  >
                    {product.name}
                  </button>
                ))}
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  )
}
