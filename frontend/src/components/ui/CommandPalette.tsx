import { useQuery } from '@tanstack/react-query'
import { AnimatePresence, motion } from 'framer-motion'
import {
  BarChart3,
  Boxes,
  History,
  LayoutDashboard,
  Pill,
  Search,
  Settings,
  ShieldCheck,
  ShoppingCart,
  Truck,
  Users,
  UserSquare2,
  Wallet,
  Warehouse,
  Zap,
  type LucideIcon,
} from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { apiGet } from '../../lib/api'
import { formatKes } from '../../lib/money'
import { NAV_ITEMS } from '../../lib/navigation'
import type { Customer, Paginated, Product } from '../../lib/types'

const SECTION_ICONS: Record<string, LucideIcon> = {
  dashboard: LayoutDashboard,
  sell: ShoppingCart,
  inventory: Boxes,
  buy: Truck,
  warehouse: Warehouse,
  customers: UserSquare2,
  finance: Wallet,
  quality: ShieldCheck,
  reports: BarChart3,
  people: Users,
  admin: Settings,
}

type SearchResult = {
  id: string
  label: string
  subtitle?: string
  path: string
  group: string
  icon: LucideIcon
  badge?: string
}

/** Flatten all nav items into searchable results */
function buildResults(): SearchResult[] {
  const results: SearchResult[] = []
  for (const item of NAV_ITEMS) {
    const Icon = SECTION_ICONS[item.key] ?? LayoutDashboard
    if (!item.children?.length) {
      results.push({ id: item.key, label: item.label, path: item.path, group: 'Navigation', icon: Icon, badge: 'Page' })
    } else {
      for (const child of item.children) {
        results.push({
          id: `${item.key}/${child.key}`,
          label: child.label,
          subtitle: item.label,
          path: child.path,
          group: item.label,
          icon: Icon,
          badge: 'Page',
        })
      }
    }
  }
  return results
}

const ALL_NAV_RESULTS = buildResults()

const QUICK_ACTIONS: SearchResult[] = [
  { id: 'qa-pos', label: 'Open POS Terminal', subtitle: 'Launch live retail checkout', path: '/sell/pos', group: 'Quick Actions', icon: ShoppingCart, badge: 'Terminal' },
  { id: 'qa-quotation', label: 'New Quotation', subtitle: 'Create wholesale quote with credit check', path: '/sell/quotations?new=1', group: 'Quick Actions', icon: Zap, badge: 'Action' },
  { id: 'qa-products', label: 'Products & Batches', subtitle: 'View catalog, expiry & inventory ledger', path: '/inventory/products', group: 'Quick Actions', icon: Boxes, badge: 'Stock' },
  { id: 'qa-adjustments', label: 'Stock Adjustment', subtitle: 'Record breakage, loss, or gain', path: '/inventory/adjustments', group: 'Quick Actions', icon: ShieldCheck, badge: 'Inventory' },
]

const RECENT_KEY = 'pharmapoint_recent_searches'

function getRecentSearches(): SearchResult[] {
  try {
    const raw = localStorage.getItem(RECENT_KEY)
    return raw ? JSON.parse(raw) : []
  } catch {
    return []
  }
}

function saveRecentSearch(item: SearchResult) {
  try {
    const current = getRecentSearches().filter((x) => x.id !== item.id)
    const updated = [item, ...current].slice(0, 5)
    localStorage.setItem(RECENT_KEY, JSON.stringify(updated))
  } catch {}
}

function fuzzy(str: string, query: string) {
  return str.toLowerCase().includes(query.toLowerCase())
}

export function CommandPalette({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [query, setQuery] = useState('')
  const [selectedIdx, setSelectedIdx] = useState(0)
  const inputRef = useRef<HTMLInputElement>(null)
  const navigate = useNavigate()

  // Reset when opened
  useEffect(() => {
    if (open) {
      setQuery('')
      setSelectedIdx(0)
      setTimeout(() => inputRef.current?.focus(), 50)
    }
  }, [open])

  // Live Products Query
  const productsQuery = useQuery({
    queryKey: ['command-products', query.trim()],
    queryFn: () => apiGet<Paginated<Product>>('/api/products', { search: query.trim(), per_page: 5 }),
    enabled: open && query.trim().length >= 2,
    staleTime: 30_000,
  })

  // Live Customers Query
  const customersQuery = useQuery({
    queryKey: ['command-customers', query.trim()],
    queryFn: () => apiGet<Paginated<Customer>>('/api/customers', { search: query.trim(), per_page: 4 }),
    enabled: open && query.trim().length >= 2,
    staleTime: 30_000,
  })

  // Filtered Navigation
  const filteredNav = useMemo(
    () => (query.trim() ? ALL_NAV_RESULTS.filter((r) => fuzzy(r.label, query) || fuzzy(r.group, query) || (r.subtitle && fuzzy(r.subtitle, query))) : []),
    [query],
  )

  // Dynamic Product items
  const productResults = useMemo<SearchResult[]>(() => {
    const list = productsQuery.data?.data ?? []
    return list.map((p) => ({
      id: `prod-${p.id}`,
      label: p.name,
      subtitle: `${p.code} · ${p.category?.name ?? 'Pharmaceutical'}${p.default_price ? ` · Price: ${formatKes(p.default_price)}` : ''}`,
      path: `/inventory/products?search=${encodeURIComponent(p.code || p.name)}`,
      group: 'Medicines & Catalog',
      icon: Pill,
      badge: 'Product',
    }))
  }, [productsQuery.data])

  // Dynamic Customer items
  const customerResults = useMemo<SearchResult[]>(() => {
    const list = customersQuery.data?.data ?? []
    return list.map((c) => ({
      id: `cust-${c.id}`,
      label: c.name,
      subtitle: `${c.code} · ${c.tier?.name ?? c.customer_type}${c.available_credit ? ` · Credit: ${formatKes(c.available_credit)}` : ''}`,
      path: `/customers/list?search=${encodeURIComponent(c.code || c.name)}`,
      group: 'Customers & Accounts',
      icon: UserSquare2,
      badge: 'Customer',
    }))
  }, [customersQuery.data])

  const recentSearches = useMemo(() => getRecentSearches(), [open])

  // Aggregate results based on query
  const displayedResults = useMemo(() => {
    if (!query.trim()) {
      const list: SearchResult[] = []
      if (recentSearches.length > 0) {
        list.push(...recentSearches.map((r) => ({ ...r, group: 'Recently Visited' })))
      }
      list.push(...QUICK_ACTIONS)
      return list
    }

    return [...productResults, ...customerResults, ...filteredNav]
  }, [query, productResults, customerResults, filteredNav, recentSearches])

  // Group filtered results
  const grouped = useMemo(() => {
    const map = new Map<string, SearchResult[]>()
    for (const r of displayedResults) {
      if (!map.has(r.group)) map.set(r.group, [])
      map.get(r.group)!.push(r)
    }
    return map
  }, [displayedResults])

  // Flat list for keyboard navigation
  const flat = useMemo(() => {
    const arr: SearchResult[] = []
    for (const items of grouped.values()) arr.push(...items)
    return arr
  }, [grouped])

  const selected = flat[selectedIdx]

  function go(result: SearchResult) {
    saveRecentSearch(result)
    navigate(result.path)
    onClose()
  }

  function handleKeyDown(e: React.KeyboardEvent) {
    if (e.key === 'ArrowDown') {
      e.preventDefault()
      setSelectedIdx((i) => Math.min(i + 1, flat.length - 1))
    } else if (e.key === 'ArrowUp') {
      e.preventDefault()
      setSelectedIdx((i) => Math.max(i - 1, 0))
    } else if (e.key === 'Enter' && selected) {
      go(selected)
    } else if (e.key === 'Escape') {
      onClose()
    }
  }

  // Reset selection when query changes
  useEffect(() => {
    setSelectedIdx(0)
  }, [query])

  let globalIdx = 0

  return (
    <AnimatePresence>
      {open && (
        <>
          {/* Backdrop */}
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            exit={{ opacity: 0 }}
            transition={{ duration: 0.15 }}
            className="fixed inset-0 z-[100] bg-slate-900/50 backdrop-blur-sm"
            onClick={onClose}
          />

          {/* Palette modal */}
          <motion.div
            initial={{ opacity: 0, scale: 0.96, y: -12 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.96, y: -12 }}
            transition={{ duration: 0.18, ease: [0.16, 1, 0.3, 1] }}
            className="fixed left-1/2 top-[12vh] z-[101] w-[calc(100vw-32px)] max-w-2xl -translate-x-1/2"
            onKeyDown={handleKeyDown}
          >
            <div className="bg-white rounded-3xl border border-slate-200/90 shadow-2xl shadow-slate-900/25 overflow-hidden flex flex-col">
              {/* Search input */}
              <div className="flex items-center gap-3 px-5 py-4 border-b border-slate-100 bg-slate-50/40">
                <Search size={19} className="text-slate-400 shrink-0" />
                <input
                  ref={inputRef}
                  type="text"
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="Search medicines, batches, customers, pages, or commands..."
                  className="flex-1 text-base text-slate-900 font-semibold placeholder:text-slate-400 bg-transparent outline-none"
                />
                {(productsQuery.isLoading || customersQuery.isLoading) && query.length >= 2 && (
                  <span className="text-xs font-semibold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md animate-pulse">
                    Searching...
                  </span>
                )}
                <kbd className="hidden sm:inline-flex items-center px-2 py-0.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-500 shadow-2xs">
                  ESC
                </kbd>
              </div>

              {/* Results list */}
              <div className="max-h-[min(65vh,440px)] overflow-y-auto py-2 divide-y divide-slate-50 [scrollbar-width:none]">
                {flat.length === 0 && query.trim() && !productsQuery.isLoading && (
                  <div className="px-5 py-10 text-center text-slate-400 text-sm">
                    No medicines, customers, or pages matching <span className="font-bold text-slate-700">"{query}"</span>
                  </div>
                )}

                {[...grouped.entries()].map(([groupName, items]) => (
                  <div key={groupName} className="py-1">
                    <div className="px-5 pt-2 pb-1 text-xs font-extrabold uppercase tracking-widest text-slate-400 flex items-center justify-between">
                      <span>{groupName}</span>
                      {groupName === 'Recently Visited' && (
                        <History size={12} className="text-slate-400" />
                      )}
                    </div>
                    {items.map((result) => {
                      const Icon = result.icon
                      const isSelected = flat[selectedIdx]?.id === result.id
                      const idx = globalIdx++
                      return (
                        <button
                          key={result.id}
                          type="button"
                          onClick={() => go(result)}
                          onMouseEnter={() => setSelectedIdx(idx)}
                          className={`w-full flex items-center gap-3.5 px-5 py-2.5 text-left cursor-pointer transition-colors ${
                            isSelected ? 'bg-blue-50/80' : 'hover:bg-slate-50'
                          }`}
                        >
                          <div
                            className={`h-8 w-8 rounded-xl flex items-center justify-center shrink-0 transition-colors ${
                              isSelected
                                ? 'bg-blue-600 text-white shadow-xs shadow-blue-500/30'
                                : 'bg-slate-100 text-slate-600'
                            }`}
                          >
                            <Icon size={16} />
                          </div>
                          <div className="flex-1 min-w-0">
                            <div className="flex items-center gap-2">
                              <span
                                className={`text-sm font-semibold truncate ${
                                  isSelected ? 'text-blue-700' : 'text-slate-900'
                                }`}
                              >
                                {result.label}
                              </span>
                              {result.badge && (
                                <span className="text-xs font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
                                  {result.badge}
                                </span>
                              )}
                            </div>
                            {result.subtitle && (
                              <div className="text-xs text-slate-500 truncate font-medium mt-0.5">
                                {result.subtitle}
                              </div>
                            )}
                          </div>
                          {isSelected && (
                            <kbd className="shrink-0 text-xs font-semibold text-blue-600 border border-blue-200 bg-white px-1.5 py-0.5 rounded-md shadow-2xs">
                              ↵
                            </kbd>
                          )}
                        </button>
                      )
                    })}
                  </div>
                ))}
              </div>

              {/* Footer hint */}
              <div className="px-5 py-3 border-t border-slate-100 bg-slate-50/70 flex items-center justify-between text-xs text-slate-500 font-medium">
                <div className="flex items-center gap-4">
                  <span className="flex items-center gap-1">
                    <kbd className="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-xs font-semibold shadow-2xs">↑↓</kbd>
                    Navigate
                  </span>
                  <span className="flex items-center gap-1">
                    <kbd className="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-xs font-semibold shadow-2xs">↵</kbd>
                    Select
                  </span>
                  <span className="flex items-center gap-1">
                    <kbd className="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-xs font-semibold shadow-2xs">ESC</kbd>
                    Close
                  </span>
                </div>
                <span className="text-slate-400 hidden sm:inline">PharmaPoint Global Search</span>
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}
