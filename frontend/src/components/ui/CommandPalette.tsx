import { AnimatePresence, motion } from 'framer-motion'
import {
  BarChart3,
  Boxes,
  LayoutDashboard,
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
import { NAV_ITEMS } from '../../lib/navigation'

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
  path: string
  group: string
  icon: LucideIcon
}

/** Flatten all nav items into searchable results */
function buildResults(): SearchResult[] {
  const results: SearchResult[] = []
  for (const item of NAV_ITEMS) {
    const Icon = SECTION_ICONS[item.key] ?? LayoutDashboard
    if (!item.children?.length) {
      results.push({ id: item.key, label: item.label, path: item.path, group: 'Navigation', icon: Icon })
    } else {
      for (const child of item.children) {
        results.push({
          id: `${item.key}/${child.key}`,
          label: child.label,
          path: child.path,
          group: item.label,
          icon: Icon,
        })
      }
    }
  }
  return results
}

const ALL_RESULTS = buildResults()

const QUICK_ACTIONS: SearchResult[] = [
  { id: 'qa-pos', label: 'Open POS', path: '/sell/pos', group: 'Quick Actions', icon: ShoppingCart },
  { id: 'qa-quotation', label: 'New Quotation', path: '/sell/quotations?new=1', group: 'Quick Actions', icon: Zap },
  { id: 'qa-products', label: 'Products Catalogue', path: '/inventory/products', group: 'Quick Actions', icon: Boxes },
]

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

  const filteredNav = useMemo(
    () => (query.trim() ? ALL_RESULTS.filter((r) => fuzzy(r.label, query) || fuzzy(r.group, query)) : []),
    [query],
  )
  const showQuickActions = !query.trim()
  const displayedResults = showQuickActions ? QUICK_ACTIONS : filteredNav

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
            className="fixed left-1/2 top-[15vh] z-[101] w-full max-w-xl -translate-x-1/2"
            onKeyDown={handleKeyDown}
          >
            <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl shadow-slate-900/20 overflow-hidden">
              {/* Search input */}
              <div className="flex items-center gap-3 px-4 py-3.5 border-b border-slate-100">
                <Search size={18} className="text-slate-400 shrink-0" />
                <input
                  ref={inputRef}
                  type="text"
                  value={query}
                  onChange={(e) => setQuery(e.target.value)}
                  placeholder="Search pages, actions, medicines..."
                  className="flex-1 text-[15px] text-slate-900 font-medium placeholder:text-slate-400 bg-transparent outline-none"
                />
                <kbd className="hidden sm:inline-flex items-center px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 text-[11px] font-bold text-slate-500">
                  ESC
                </kbd>
              </div>

              {/* Results */}
              <div className="max-h-[min(60vh,400px)] overflow-y-auto py-2">
                {flat.length === 0 && query.trim() && (
                  <div className="px-4 py-8 text-center text-slate-400 text-[13.5px] font-medium">
                    No results for <span className="font-bold text-slate-600">"{query}"</span>
                  </div>
                )}

                {[...grouped.entries()].map(([groupName, items]) => (
                  <div key={groupName} className="mb-1">
                    <div className="px-4 pt-2 pb-1 text-[10.5px] font-extrabold uppercase tracking-widest text-slate-400">
                      {groupName}
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
                          className={`w-full flex items-center gap-3 px-4 py-2.5 text-left cursor-pointer transition-colors ${
                            isSelected ? 'bg-blue-50' : 'hover:bg-slate-50'
                          }`}
                        >
                          <div
                            className={`h-7 w-7 rounded-lg flex items-center justify-center shrink-0 transition-colors ${
                              isSelected
                                ? 'bg-blue-600 text-white shadow-xs shadow-blue-500/30'
                                : 'bg-slate-100 text-slate-500'
                            }`}
                          >
                            <Icon size={14} />
                          </div>
                          <div className="flex-1 min-w-0">
                            <div
                              className={`text-[13.5px] font-semibold truncate ${
                                isSelected ? 'text-blue-700' : 'text-slate-800'
                              }`}
                            >
                              {result.label}
                            </div>
                          </div>
                          {isSelected && (
                            <kbd className="shrink-0 text-[10.5px] font-bold text-blue-500 border border-blue-200 bg-blue-50 px-1.5 py-0.5 rounded-md">
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
              <div className="px-4 py-2.5 border-t border-slate-100 flex items-center gap-4 text-[11px] text-slate-400 font-medium">
                <span className="flex items-center gap-1">
                  <kbd className="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-[10px] font-bold">↑↓</kbd>
                  Navigate
                </span>
                <span className="flex items-center gap-1">
                  <kbd className="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-[10px] font-bold">↵</kbd>
                  Open
                </span>
                <span className="flex items-center gap-1">
                  <kbd className="px-1.5 py-0.5 rounded bg-slate-100 border border-slate-200 text-[10px] font-bold">ESC</kbd>
                  Close
                </span>
              </div>
            </div>
          </motion.div>
        </>
      )}
    </AnimatePresence>
  )
}
