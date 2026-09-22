import { useQuery } from '@tanstack/react-query'
import { useMemo, useState } from 'react'
import { useDebounced } from '../../components/ProductSearch'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { dSum } from '../../lib/decimal'
import { useProductCategories, useStores } from '../../lib/hooks'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { ProductCategory, StockStateRow } from '../../lib/types'
import { ExpiryBadge } from './ExpiryBadge'
import { StockStatesTable } from './StockStatesTable'

export { ExpiryBadge, StockStatesTable }

const UNCATEGORISED = '__none__'

type Heading = { id: string; name: string; rows: StockStateRow[] }

export default function StockOnHandPage() {
  const showCost = usePermission('product.cost.view')
  const stores = useStores()
  const categories = useProductCategories()
  const [storeId, setStoreId] = useState('')
  const [categoryId, setCategoryId] = useState('')
  const [grouped, setGrouped] = useState(true)
  const [q, setQ] = useState('')
  const debouncedQ = useDebounced(q)

  const stock = useQuery({
    queryKey: ['inventory', 'stock', { store_id: storeId, q: debouncedQ, category_id: categoryId }],
    queryFn: () =>
      apiGet<{ data: StockStateRow[] }>('/api/inventory/stock', {
        store_id: storeId || undefined,
        q: debouncedQ || undefined,
        category_id: categoryId || undefined,
      }),
  })

  const tree = useMemo(() => categoryTree(categories.data ?? []), [categories.data])
  const headings = useMemo(() => groupByHeading(stock.data?.data ?? [], categories.data ?? []), [stock.data, categories.data])

  return (
    <Page>
      <PageHeader
        parent="Inventory"
        title="Stock on Hand"
        subtitle="The authoritative live view of stock across the 8 balance states, calculated per store and grouped under its stock heading."
      />
      <div id="tour-stock-filters">
        <FilterBar>
          <Field label="Store">
            <Select value={storeId} onChange={(e) => setStoreId(e.target.value)}>
              <option value="">All stores</option>
              {(stores.data ?? []).map((s) => (
                <option key={s.id} value={s.id}>
                  {s.code} · {s.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Heading">
            <Select value={categoryId} onChange={(e) => setCategoryId(e.target.value)}>
              <option value="">All headings</option>
              {tree.map(({ category, depth }) => (
                <option key={category.id} value={category.id}>
                  {'   '.repeat(depth)}{depth > 0 ? '└ ' : ''}{category.name}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Search product">
            <Input
              placeholder="Name, code, barcode…"
              value={q}
              onChange={(e) => setQ(e.target.value)}
            />
          </Field>
          <Field label="View">
            <div className="flex gap-1">
              <Button size="sm" variant={grouped ? 'primary' : 'secondary'} onClick={() => setGrouped(true)}>By heading</Button>
              <Button size="sm" variant={grouped ? 'secondary' : 'primary'} onClick={() => setGrouped(false)}>One list</Button>
            </div>
          </Field>
        </FilterBar>
      </div>

      {grouped && headings.length > 1 && (
        <div className="flex flex-wrap gap-2">
          {headings.map((h) => (
            <a key={h.id} href={`#heading-${h.id}`} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs hover:border-blue-300 hover:bg-blue-50/50">
              <div className="font-bold text-slate-800">{h.name}</div>
              <div className="text-slate-500 tabular">
                {new Set(h.rows.map((r) => r.product_id)).size} products
                {showCost && <> · {formatMoney(dSum(h.rows.map((r) => r.value_at_cost ?? '0')))}</>}
              </div>
            </a>
          ))}
        </div>
      )}

      {grouped && headings.length > 0 ? (
        <div className="space-y-4">
          {headings.map((h) => (
            <section key={h.id} id={`heading-${h.id}`} className="ui-card overflow-hidden scroll-mt-4">
              <header className="flex items-center justify-between gap-3 px-4 py-2.5 border-b border-slate-100 bg-slate-50/70">
                <h2 className="text-sm font-bold text-slate-900">{h.name}</h2>
                <span className="text-xs text-slate-500 tabular">
                  {h.rows.length} line{h.rows.length === 1 ? '' : 's'}
                  {showCost && <> · {formatMoney(dSum(h.rows.map((r) => r.value_at_cost ?? '0')))} at cost</>}
                </span>
              </header>
              <StockStatesTable rows={h.rows} showCost={showCost} />
            </section>
          ))}
        </div>
      ) : (
        <div id="tour-stock-table" className="ui-card overflow-hidden">
          <StockStatesTable
            rows={stock.data?.data}
            showCost={showCost}
            isLoading={stock.isLoading}
            error={stock.error}
          />
        </div>
      )}
    </Page>
  )
}

/** Categories in display order: each heading followed by its sub-headings. */
function categoryTree(categories: ProductCategory[]): { category: ProductCategory; depth: number }[] {
  const byParent = new Map<string | null, ProductCategory[]>()
  for (const c of categories) {
    const key = c.parent_id && categories.some((p) => p.id === c.parent_id) ? c.parent_id : null
    byParent.set(key, [...(byParent.get(key) ?? []), c])
  }
  const out: { category: ProductCategory; depth: number }[] = []
  const walk = (parentId: string | null, depth: number) => {
    for (const c of byParent.get(parentId) ?? []) {
      out.push({ category: c, depth })
      if (depth < 5) walk(c.id, depth + 1)
    }
  }
  walk(null, 0)
  return out
}

/**
 * Rows grouped under their top-level heading (Drugs, Topicals, Cold chain…);
 * a sub-heading such as Antibiotics is shown within Drugs. Products with no
 * category come last.
 */
function groupByHeading(rows: StockStateRow[], categories: ProductCategory[]): Heading[] {
  const byId = new Map(categories.map((c) => [c.id, c]))
  const rootOf = (id: string | null): ProductCategory | null => {
    let current = id ? byId.get(id) ?? null : null
    for (let guard = 0; current?.parent_id && byId.has(current.parent_id) && guard < 10; guard++) {
      current = byId.get(current.parent_id) ?? current
    }
    return current
  }

  const groups = new Map<string, Heading>()
  for (const row of rows) {
    const root = rootOf(row.category_id)
    const id = root?.id ?? (row.category_id ? row.category_id : UNCATEGORISED)
    const name = root?.name ?? row.category_name ?? 'No heading yet'
    const group = groups.get(id) ?? { id, name, rows: [] }
    group.rows.push(row)
    groups.set(id, group)
  }

  return [...groups.values()].sort((a, b) => {
    if (a.id === UNCATEGORISED) return 1
    if (b.id === UNCATEGORISED) return -1
    return a.name.localeCompare(b.name)
  })
}
