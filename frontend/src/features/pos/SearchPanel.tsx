import { useQuery } from '@tanstack/react-query'
import { Plus, ScanBarcode, Sparkles } from 'lucide-react'
import { useState, type KeyboardEvent, type RefObject } from 'react'
import { lookupBarcode, ProductResultRow, useDebounced, useProductSearch } from '../../components/ProductSearch'
import { apiGet } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { formatMoney, formatQty } from '../../lib/money'
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
  const [catFilter, setCatFilter] = useState<string>('ALL')

  const storeId = useCartStore((s) => s.storeId)
  const addProduct = useCartStore((s) => s.addProduct)
  const posted = useCartStore((s) => s.status === 'POSTED')

  const { data, isFetching } = useProductSearch(query)
  const results = data?.data ?? []

  // Load popular/stocked products to eliminate the empty void
  const popular = useQuery({
    queryKey: ['products', 'pos-popular', storeId],
    queryFn: () => apiGet<Paginated<Product>>('/api/products', { per_page: 30, is_active: 1 }),
    staleTime: 60_000,
  })

  const popularList = popular.data?.data ?? []
  const categories = Array.from(
    new Set(popularList.map((p) => p.category?.name).filter((c): c is string => !!c))
  ).slice(0, 5)

  const displayedPopular = popularList.filter(
    (p) => catFilter === 'ALL' || p.category?.name === catFilter
  )

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
    setRecent((prev) => [product, ...prev.filter((p) => p.id !== product.id)].slice(0, 5))
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
    <div id="tour-pos-search" className="w-[330px] lg:w-[370px] shrink-0 border-r border-slate-200/80 bg-white flex flex-col min-h-0">
      {/* Search & Barcode Scan Bar */}
      <div className="p-3 border-b border-slate-100 bg-white">
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
            className="w-full h-9 pl-9 pr-10 rounded-2xl bg-slate-50/80 border border-slate-200/70 text-xs text-slate-800 placeholder:text-slate-400 focus:bg-white focus:outline-none focus:border-blue-500 transition-all font-medium shadow-2xs"
            autoComplete="off"
            autoFocus
          />
          <span className="absolute right-2.5 top-1/2 -translate-y-1/2 px-2 py-0.5 rounded-full bg-slate-200/60 text-[10px] font-mono font-bold text-slate-600 pointer-events-none tabular">
            F2
          </span>
        </div>
        {scanError && (
          <div role="alert" className="text-xs font-semibold text-rose-700 mt-1.5 px-1">
            {scanError}
          </div>
        )}
      </div>

      {/* Results or Fast-Moving OTC Grid */}
      <div className="flex-1 overflow-y-auto pos-scroll">
        {query.trim() ? (
          results.length === 0 ? (
            <div className="px-4 py-8 text-center text-xs text-slate-400">
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
                    <span className="tabular shrink-0 text-xs">
                      {row ? (
                        <>
                          <span
                            className={
                              Number(row.free_to_sell) > 0
                                ? 'text-emerald-700 font-semibold'
                                : 'text-rose-700 font-semibold'
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
                        <span className="text-rose-700 font-semibold">0 free</span>
                      )}
                    </span>
                  }
                />
              )
            })
          )
        ) : (
          <div className="p-3 space-y-3">
            {/* Recently added chips */}
            {recent.length > 0 && (
              <div>
                <div className="text-xs font-bold uppercase tracking-wider text-slate-500 mb-1.5">
                  Recently Added
                </div>
                <div className="flex flex-wrap gap-1.5">
                  {recent.map((product) => (
                    <button
                      key={product.id}
                      type="button"
                      onClick={() => add(product)}
                      className="px-2.5 py-1 rounded-full bg-blue-50/80 hover:bg-blue-100 text-blue-700 border border-blue-200/50 text-xs font-bold transition-colors cursor-pointer shadow-2xs"
                    >
                      + {product.name}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {/* Fast-moving category tabs & cards */}
            <div>
              <div className="flex items-center justify-between text-xs font-bold uppercase tracking-wider text-slate-500 mb-2 px-0.5">
                <span className="flex items-center gap-1.5 text-slate-800 font-bold">
                  <Sparkles size={13} className="text-amber-500" /> Fast-Moving Catalog
                </span>
                <span className="text-xs text-slate-400 font-normal">1-tap add</span>
              </div>

              {/* Category Filter Chips */}
              {categories.length > 0 && (
                <div className="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-1.5 mb-1.5">
                  <button
                    type="button"
                    onClick={() => setCatFilter('ALL')}
                    className={`px-3 py-1 rounded-full text-xs font-bold transition-colors cursor-pointer shrink-0 ${
                      catFilter === 'ALL'
                        ? 'bg-blue-600 text-white shadow-xs'
                        : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                    }`}
                  >
                    All
                  </button>
                  {categories.map((cat) => (
                    <button
                      key={cat}
                      type="button"
                      onClick={() => setCatFilter(cat)}
                      className={`px-3 py-1 rounded-full text-xs font-bold transition-colors cursor-pointer shrink-0 ${
                        catFilter === cat
                          ? 'bg-blue-600 text-white shadow-xs'
                          : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                      }`}
                    >
                      {cat}
                    </button>
                  ))}
                </div>
              )}

              {/* Fast-moving medicine cards */}
              <div className="grid grid-cols-1 gap-2">
                {displayedPopular.map((prod) => (
                  <button
                    key={prod.id}
                    type="button"
                    onClick={() => add(prod)}
                    className="p-3 rounded-2xl border border-slate-200/60 bg-white hover:bg-blue-50/30 hover:border-blue-300 text-left transition-all group flex items-center justify-between gap-2.5 cursor-pointer shadow-2xs hover:shadow-xs active:scale-[0.99]"
                  >
                    <div className="min-w-0 flex-1">
                      <div className="font-bold text-sm text-slate-900 group-hover:text-blue-700 line-clamp-2 leading-snug">
                        {prod.name}
                      </div>
                      <div className="flex items-center gap-2 mt-1 text-xs">
                        {prod.default_price && (
                          <span className="font-bold text-blue-700 tabular">
                            KES {formatMoney(prod.default_price)}
                          </span>
                        )}
                        {prod.strength && (
                          <span className="text-slate-600 font-medium truncate">
                            {prod.strength}
                          </span>
                        )}
                        <span className="text-slate-500 font-mono text-xs font-medium">
                          #{prod.code}
                        </span>
                      </div>
                    </div>

                    <span className="h-7 px-3 rounded-full bg-blue-50 group-hover:bg-blue-600 group-hover:text-white text-blue-700 flex items-center gap-1 text-xs font-bold transition-all shrink-0 shadow-2xs">
                      <Plus size={12} /> Add
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
