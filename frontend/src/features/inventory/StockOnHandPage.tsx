import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useDebounced } from '../../components/ProductSearch'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Field, Input, Select } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { useStores } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import type { StockStateRow } from '../../lib/types'
import { ExpiryBadge } from './ExpiryBadge'
import { StockStatesTable } from './StockStatesTable'

export { ExpiryBadge, StockStatesTable }

export default function StockOnHandPage() {
  const showCost = usePermission('product.cost.view')
  const stores = useStores()
  const [storeId, setStoreId] = useState('')
  const [q, setQ] = useState('')
  const debouncedQ = useDebounced(q)

  const stock = useQuery({
    queryKey: ['inventory', 'stock', { store_id: storeId, q: debouncedQ }],
    queryFn: () =>
      apiGet<{ data: StockStateRow[] }>('/api/inventory/stock', {
        store_id: storeId || undefined,
        q: debouncedQ || undefined,
      }),
  })

  return (
    <Page>
      <PageHeader
        parent="Inventory"
        title="Stock on Hand"
        subtitle="The authoritative live view of stock across the 8 balance states, calculated per store."
      />
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
        <Field label="Search product">
          <Input
            placeholder="Name, code, barcode…"
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
        </Field>
      </FilterBar>
      <div className="ui-card overflow-hidden">
        <StockStatesTable
          rows={stock.data?.data}
          showCost={showCost}
          isLoading={stock.isLoading}
          error={stock.error}
        />
      </div>
    </Page>
  )
}
