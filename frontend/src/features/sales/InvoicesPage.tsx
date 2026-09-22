import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Field, Input, Select } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import type { Paginated, Sale } from '../../lib/types'
import { SaleDrawer } from './SaleDrawer'

export default function InvoicesPage() {
  const [params, setParams] = useSearchParams()
  const [filters, setFilters] = useState({ sale_mode: '', status: '', from: '', to: '' })
  const [page, setPage] = useState(1)
  const selectedId = params.get('sale')

  const list = useQuery({
    queryKey: ['sales', 'list', filters, page],
    queryFn: () => apiGet<Paginated<Sale>>('/api/sales', { ...filters, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<Sale>[] = [
    { key: 'doc', header: 'Document', render: (s) => <span className="font-semibold tabular text-blue-600 hover:text-blue-700">{s.doc_number}</span>, sortValue: (s) => s.doc_number },
    { key: 'posted', header: 'Posted', render: (s) => formatDateTime(s.posted_at), sortValue: (s) => s.posted_at ?? '' },
    { key: 'mode', header: 'Mode', render: (s) => <StatusBadge status={s.sale_mode} /> },
    { key: 'customer', header: 'Customer', render: (s) => s.customer?.name ?? <span className="text-slate-400">Walk-in</span>, sortValue: (s) => s.customer?.name ?? '' },
    { key: 'status', header: 'Status', render: (s) => <StatusBadge status={s.status} /> },
    { key: 'total', header: 'Total', align: 'right', render: (s) => <MoneyCell value={s.grand_total} />, sortValue: (s) => Number(s.grand_total) },
    {
      key: 'pdf',
      header: '',
      align: 'right',
      render: (s) => <PdfDownloadButton url={`/api/sales/${s.id}/pdf`} filename={s.doc_number} label="Invoice" />,
    },
  ]

  return (
    <Page>
      <PageHeader parent="Sales" title="Invoices" subtitle="Every posted sale — retail receipts and wholesale invoices — for the active branch." />
      <FilterBar>
        <div id="tour-invoices-mode">
          <Field label="Mode">
            <Select value={filters.sale_mode} onChange={(e) => setFilters({ ...filters, sale_mode: e.target.value })}>
              <option value="">All</option>
              <option value="RETAIL">Retail</option>
              <option value="WHOLESALE">Wholesale</option>
              <option value="DISPENSING">Dispensing</option>
            </Select>
          </Field>
        </div>
        <div id="tour-invoices-filters" className="flex items-end gap-3 flex-wrap">
          <Field label="Status">
            <Select value={filters.status} onChange={(e) => setFilters({ ...filters, status: e.target.value })}>
              <option value="">All</option>
              <option value="POSTED">Posted</option>
              <option value="VOIDED">Voided</option>
            </Select>
          </Field>
          <Field label="From">
            <Input type="date" value={filters.from} onChange={(e) => setFilters({ ...filters, from: e.target.value })} />
          </Field>
          <Field label="To">
            <Input type="date" value={filters.to} onChange={(e) => setFilters({ ...filters, to: e.target.value })} />
          </Field>
        </div>
      </FilterBar>
      <div id="tour-invoices-table" className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data?.data}
          rowKey={(s) => s.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          onRowClick={(s) => setParams({ sale: s.id })}
          selectedKey={selectedId}
          emptyTitle="No sales match"
          rowClassName={(s) => (s.status === 'VOIDED' ? 'opacity-60' : '')}
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <SaleDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}
