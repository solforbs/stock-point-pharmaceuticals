import { useQuery } from '@tanstack/react-query'
import { X } from 'lucide-react'
import { useState } from 'react'
import { ProductSearch } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Field, Input, Select } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import type { LedgerRow, Paginated, Product, ProductBatch } from '../../lib/types'

const TXN_TYPES = [
  'GRN_RECEIPT', 'PURCHASE_RETURN', 'SALE', 'SALE_BONUS', 'SALE_VOID', 'DISPENSING', 'CUSTOMER_RETURN', 'TRANSFER_OUT', 'TRANSFER_IN',
  'ADJUSTMENT_UP', 'ADJUSTMENT_DOWN', 'COUNT_VARIANCE', 'QUARANTINE_IN', 'QUARANTINE_OUT', 'EXPIRY_WRITE_OFF', 'DAMAGE_WRITE_OFF',
  'RECALL_BLOCK', 'REPACK_OUT', 'REPACK_IN', 'OPENING_BALANCE',
]

type LedgerResponse = Paginated<LedgerRow> | { data: LedgerRow[] }

/** Part 21.8 — the real ledger, with a running balance when batch + store are chosen. */
export default function StockLedgerPage() {
  const showCost = usePermission('product.cost.view')
  const stores = useStores()
  const [product, setProduct] = useState<Product | null>(null)
  const [batchId, setBatchId] = useState('')
  const [storeId, setStoreId] = useState('')
  const [txnType, setTxnType] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)

  const batches = useQuery({
    queryKey: ['batches', 'for-product', product?.id],
    queryFn: () => apiGet<Paginated<ProductBatch>>('/api/batches', { product_id: product?.id, per_page: 200 }),
    enabled: !!product,
  })

  const filters = { product_id: product?.id, batch_id: batchId, store_id: storeId, txn_type: txnType, from, to }
  const ledger = useQuery({
    queryKey: ['inventory', 'ledger', filters, page],
    queryFn: () => apiGet<LedgerResponse>('/api/inventory/ledger', { ...filters, page, per_page: 100 }),
    placeholderData: (prev) => prev,
  })
  const running = !!batchId && !!storeId
  const rows = ledger.data?.data
  const paginated = ledger.data && 'current_page' in ledger.data ? ledger.data : undefined

  const columns: Column<LedgerRow>[] = [
    { key: 'when', header: 'When', render: (r) => <span className="tabular whitespace-nowrap">{formatDateTime(r.txn_datetime)}</span>, sortValue: (r) => r.txn_datetime },
    { key: 'type', header: 'Movement', render: (r) => <StatusBadge status={r.txn_type} tone={Number(r.qty_base) < 0 ? 'red' : 'green'} label={titleCase(r.txn_type)} /> },
    { key: 'product', header: 'Product', render: (r) => r.product?.name ?? r.product_id.slice(0, 8) },
    { key: 'batch', header: 'Batch', render: (r) => <span className="tabular">{r.batch?.batch_number ?? r.batch_id.slice(0, 8)}</span> },
    { key: 'store', header: 'Store', render: (r) => r.store?.code ?? '—' },
    { key: 'qty', header: 'Qty (base)', align: 'right', render: (r) => <QtyCell value={r.qty_base} className="font-semibold" />, sortValue: (r) => Number(r.qty_base) },
    ...(running ? [{ key: 'balance', header: 'Running balance', align: 'right' as const, render: (r: LedgerRow) => <QtyCell value={r.running_balance} className="font-bold" /> }] : []),
    ...(showCost
      ? [
          { key: 'unit_cost', header: 'Unit cost', align: 'right' as const, render: (r: LedgerRow) => <MoneyCell value={r.unit_cost} /> },
          { key: 'total_cost', header: 'Total cost', align: 'right' as const, render: (r: LedgerRow) => <MoneyCell value={r.total_cost} /> },
        ]
      : []),
    { key: 'source', header: 'Source', render: (r) => <span className="text-slate-500 tabular">{r.source_doc_type ? `${titleCase(r.source_doc_type)} ${r.source_doc_id?.slice(0, 8) ?? ''}` : '—'}</span> },
  ]

  return (
    <Page>
      <PageHeader parent="Inventory" title="Stock Ledger" subtitle="Every movement, append-only. Choose a batch and a store to see the running balance." />
      <div id="tour-ledger-filters">
        <FilterBar>
          <Field label="Product" className="w-80">
            {product ? (
              <div className="ui-input flex items-center gap-2">
                <span className="flex-1 truncate">{product.name} · {product.code}</span>
                <button type="button" aria-label="Clear product" onClick={() => { setProduct(null); setBatchId('') }}><X size={13} /></button>
              </div>
            ) : (
              <ProductSearch onSelect={(p) => { setProduct(p); setBatchId(''); setPage(1) }} placeholder="Search product…" />
            )}
          </Field>
          <Field label="Batch">
            <Select value={batchId} disabled={!product} onChange={(e) => { setBatchId(e.target.value); setPage(1) }}>
              <option value="">All batches</option>
              {(batches.data?.data ?? []).map((b) => (
                <option key={b.id} value={b.id}>{b.batch_number} · exp {b.expiry_date}</option>
              ))}
            </Select>
          </Field>
          <Field label="Store">
            <Select value={storeId} onChange={(e) => { setStoreId(e.target.value); setPage(1) }}>
              <option value="">All stores</option>
              {(stores.data ?? []).map((s) => (
                <option key={s.id} value={s.id}>{s.code}</option>
              ))}
            </Select>
          </Field>
          <Field label="Movement">
            <Select value={txnType} onChange={(e) => { setTxnType(e.target.value); setPage(1) }}>
              <option value="">All</option>
              {TXN_TYPES.map((t) => (
                <option key={t} value={t}>{titleCase(t)}</option>
              ))}
            </Select>
          </Field>
          <Field label="From"><Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} /></Field>
          <Field label="To"><Input type="date" value={to} onChange={(e) => setTo(e.target.value)} /></Field>
        </FilterBar>
      </div>
      {running && <p className="text-xs text-slate-500 mb-2">Running balance mode: every movement of this batch in this store, oldest first, unpaginated.</p>}
      <div id="tour-ledger-table" className="ui-card">
        <DataTable columns={columns} rows={rows} rowKey={(r) => r.id} isLoading={ledger.isLoading} error={ledger.error} onRetry={() => ledger.refetch()} emptyTitle="No movements match" />
        <Pagination page={paginated} onPage={setPage} />
      </div>
    </Page>
  )
}
