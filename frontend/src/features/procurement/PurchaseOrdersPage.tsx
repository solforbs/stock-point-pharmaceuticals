import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Field, PrimaryAction, Select } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { useSuppliers } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, PurchaseOrder } from '../../lib/types'
import { NewPurchaseOrderDrawer, type PoLine } from './NewPurchaseOrderDrawer'
import { ImportPurchaseOrdersButton } from './PurchaseOrderImport'
import { PurchaseOrderDrawer } from './PurchaseOrderDrawer'

const STATUSES = ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED', 'CANCELLED']

export default function PurchaseOrdersPage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const canCreate = usePermission('po.create')
  const suppliers = useSuppliers()
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(!!params.get('supplier'))
  const [supplierId, setSupplierId] = useState(params.get('supplier') ?? '')
  const [expectedDate, setExpectedDate] = useState('')
  const [lines, setLines] = useState<PoLine[]>([])
  const selectedId = params.get('po')

  const list = useQuery({
    queryKey: ['purchase-orders', 'list', status, page],
    queryFn: () => apiGet<Paginated<PurchaseOrder>>('/api/purchase-orders', { status, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<PurchaseOrder>('/api/purchase-orders', {
        supplier_id: supplierId,
        expected_date: expectedDate || null,
        lines: lines.map((l) => ({
          product_id: l.product.id,
          uom_id: l.uom_id,
          qty_ordered: l.qty_ordered,
          unit_price: l.unit_price,
          trade_price: l.trade_price || null,
          discount_pct: l.trade_price ? l.discount_pct || '0' : null,
        })),
      }),
    onSuccess: (po) => {
      toast.success(`Purchase order ${po.doc_number} created`)
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
      setCreating(false)
      setLines([])
      setParams({ po: po.id })
    },
  })

  const columns: Column<PurchaseOrder>[] = [
    { key: 'doc', header: 'Document', render: (po) => <span className="font-semibold tabular text-blue-600 font-mono">{po.doc_number}</span>, sortValue: (po) => po.doc_number },
    { key: 'supplier', header: 'Supplier', render: (po) => po.supplier?.name ?? '—', sortValue: (po) => po.supplier?.name ?? '' },
    { key: 'status', header: 'Status', render: (po) => <StatusBadge status={po.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (po) => <span className="tabular">{po.lines_count ?? '—'}</span> },
    { key: 'expected', header: 'Expected', render: (po) => formatDate(po.expected_date), sortValue: (po) => po.expected_date ?? '' },
    { key: 'created', header: 'Created', render: (po) => formatDateTime(po.created_at), sortValue: (po) => po.created_at ?? '' },
    {
      key: 'actions',
      header: 'PDF',
      align: 'right',
      render: (po) => (
        <span onClick={(e) => e.stopPropagation()}>
          <PdfDownloadButton url={`/api/purchase-orders/${po.id}/pdf`} filename={po.doc_number} label="PDF" />
        </span>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        parent="Procurement"
        title="Purchase Orders"
        subtitle="DRAFT → APPROVED → SENT. Goods receipting completes procurement."
        actions={
          canCreate ? (
            <div id="tour-po-new" className="flex items-center gap-2">
              <ImportPurchaseOrdersButton />
              <PrimaryAction onClick={() => setCreating(true)}>
                New Purchase Order
              </PrimaryAction>
            </div>
          ) : null
        }
      />
      <FilterBar>
        <div id="tour-po-status" className="w-full sm:w-60">
          <Field label="Status">
            <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
              <option value="">All</option>
              {STATUSES.map((s) => (
                <option key={s} value={s}>{s}</option>
              ))}
            </Select>
          </Field>
        </div>
      </FilterBar>
      <div id="tour-po-table" className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data?.data}
          rowKey={(po) => po.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          onRowClick={(po) => setParams({ po: po.id })}
          selectedKey={selectedId}
          emptyTitle="No purchase orders match"
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <NewPurchaseOrderDrawer
        open={creating}
        onClose={() => setCreating(false)}
        suppliers={suppliers.data?.data ?? []}
        supplierId={supplierId}
        setSupplierId={setSupplierId}
        expectedDate={expectedDate}
        setExpectedDate={setExpectedDate}
        lines={lines}
        setLines={setLines}
        onSubmit={() => create.mutate()}
        isSubmitting={create.isPending}
        error={create.error}
      />

      <PurchaseOrderDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}
