import { useQuery } from '@tanstack/react-query'
import { ScanBarcode, Sparkles } from 'lucide-react'
import { useState, type KeyboardEvent, type RefObject } from 'react'
import { lookupBarcode, ProductResultRow, useDebounced, useProductSearch } from '../../components/ProductSearch'
import { apiGet } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { formatQty } from '../../lib/money'
import type { Paginated, Product, StockStateRow } from '../../lib/types'
import { useCartStore } from './cartStore'

export function SearchPanel({
  inputRef,
  onAdded,
}: {
  inputRef: RefObject<HTMLInputElement | null>
  onAdded: (lineRef: string) => void
}) {
  const [query, setQuery] = useState('')
  const [highlight, setHighlight] = useState(-1)
  const [scanError, setScanError] = useState<string | null>(null)
  const [recent, setRecent] = useState<Product[]>([])
  const storeId = useCartStore((s) => s.storeId)
  const addProduct = useCartStore((s) => s.addProduct)
  const posted = useCartStore((s) => s.status === 'POSTED')

  const { data, isFetching } = useProductSearch(query)
  const results = data?.data ?? []

  // Load popular/stocked products to eliminate the empty void
  const popular = useQuery({
    queryKey: ['products', 'pos-popular', storeId],
    queryFn: () => apiGet<Paginated<Product>>('/api/products', { per_page: 24, is_active: 1 }),
    staleTime: 60_000,
  })

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
    <div className="w-[330px] lg:w-[360px] shrink-0 border-r border-slate-200/80 bg-white flex flex-col min-h-0">
      {/* Search & Barcode Scan Bar */}
      <div className="p-3.5 border-b border-slate-100 bg-white">
        <div className="relative">
          <ScanBarcode
            size={16}
            className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"
          />
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
            placeholder="Scan barcode or type name…"
            className="w-full h-10 pl-10 pr-10 rounded-xl bg-slate-50 border border-slate-200 text-[12.5px] text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:border-blue-500 transition-all font-medium"
            autoComplete="off"
            autoFocus
          />
          <span className="absolute right-2.5 top-1/2 -translate-y-1/2 px-1.5 py-0.5 rounded-md bg-white border border-slate-200 text-[10px] font-bold text-slate-400 pointer-events-none tabular">
            F2
          </span>
        </div>
        {scanError && (
          <div role="alert" className="text-[11px] font-bold text-rose-600 mt-2 px-1">
            {scanError}
          </div>
        )}
      </div>

      {/* Results or Fast-Moving OTC Grid */}
      <div className="flex-1 overflow-y-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        {query.trim() ? (
          results.length === 0 ? (
            <div className="px-4 py-8 text-center text-[12.5px] text-slate-400">
              {isFetching ? 'Searching catalog…' : 'No products match. Press Enter for exact barcode scan.'}
            </div>
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
                    <span className="tabular shrink-0 text-[11.5px]">
                      {row ? (
                        <>
                          <span
                            className={
                              Number(row.free_to_sell) > 0
                                ? 'text-emerald-600 font-bold'
                                : 'text-rose-600 font-bold'
                            }
                          >
                            {formatQty(row.free_to_sell)} free
                          </span>
                          {row.nearest_expiry && (
                            <span className="text-slate-400"> · exp {formatDate(row.nearest_expiry)}</span>
                          )}
                        </>
                      ) : stock.isFetching ? (
                        '…'
                      ) : (
                        <span className="text-rose-600 font-bold">0 free</span>
                      )}
                    </span>
                  }
                />
              )
            })
          )
        ) : (
          <div className="p-3.5 space-y-4">
            {recent.length > 0 && (
              <div>
                <div className="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 mb-2">
                  Recently Added
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {recent.map((product) => (
                    <button
                      key={product.id}
                      type="button"
                      onClick={() => add(product)}
                      className="px-2.5 py-1 rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200/60 text-[11px] font-bold transition-colors cursor-pointer"
                    >
                      + {product.name}
                    </button>
                  ))}
                </div>
              </div>
            )}

            <div>
              <div className="flex items-center justify-between text-[10.5px] font-extrabold uppercase tracking-wider text-slate-400 mb-2.5 px-0.5">
                <span className="flex items-center gap-1.5 text-slate-600 font-bold">
                  <Sparkles size={12} className="text-amber-500" /> Fast-Moving Medicines
                </span>
                <span className="text-[10px] text-slate-400 font-normal">1-tap add</span>
              </div>

              <div className="grid grid-cols-1 gap-1.5">
                {(popular.data?.data ?? []).map((prod) => (
                  <button
                    key={prod.id}
                    type="button"
                    onClick={() => add(prod)}
                    className="p-2.5 rounded-xl border border-slate-200/70 bg-white hover:border-blue-400 hover:bg-blue-50/40 text-left transition-all group flex items-center justify-between gap-2.5 cursor-pointer shadow-2xs"
                  >
                    <div className="min-w-0 flex-1">
                      <div className="font-bold text-[12.5px] text-slate-900 group-hover:text-blue-700 truncate">
                        {prod.name}
                      </div>
                      <div className="text-[11px] font-medium text-slate-400 truncate mt-0.5">
                        {prod.strength ? `${prod.strength} · ` : ''}
                        <span className="tabular font-semibold text-slate-500">{prod.code}</span>
                      </div>
                    </div>
                    <span className="w-6 h-6 rounded-lg bg-slate-100 group-hover:bg-blue-600 group-hover:text-white text-slate-600 flex items-center justify-center text-[13px] font-black transition-colors shrink-0">
                      +
                    </span>
                  </button>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
