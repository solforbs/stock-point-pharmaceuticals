import { useQuery } from '@tanstack/react-query'
import { Download, FileText } from 'lucide-react'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { NoAccess } from '../../components/ui/States'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { api, apiGet } from '../../lib/api'
import { downloadBlob } from '../../lib/csv'
import { toastApiError } from '../../lib/toast'
import { buildXlsx } from '../../lib/xlsxWriter'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { formatDateTime } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { formatKes } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { ReportResult } from '../../lib/types'
import { formatCell, isNumericType } from '../reports/reportCells'

const REPORT_KEY = 'inventory.valuation'

/** Part 20 — the stock valuation report as its own screen; the catalogue entry needs both report.view and product.cost.view. */
export default function ValuationPage() {
  const canReport = usePermission('report.view')
  const canCost = usePermission('product.cost.view')
  const canView = canReport && canCost
  const stores = useStores()
  const [storeId, setStoreId] = useState('')
  // Blank means the institution's setting (transport & handling 0%, margin 20% unless set).
  const [handlingPct, setHandlingPct] = useState('')
  const [marginPct, setMarginPct] = useState('')
  const [exporting, setExporting] = useState<string | null>(null)
  const user = useCurrentUser()
  const params = { store_id: storeId || undefined, handling_pct: handlingPct || undefined, margin_pct: marginPct || undefined }

  const result = useQuery({
    queryKey: ['reports', REPORT_KEY, params],
    queryFn: () => apiGet<ReportResult>(`/api/reports/${REPORT_KEY}`, params),
    enabled: canView,
    placeholderData: (prev) => prev,
  })
  const r = result.data

  const columns: Column<Record<string, unknown>>[] = (r?.columns ?? []).map((c) => ({
    key: c.key,
    header: c.label,
    align: isNumericType(c.type) ? 'right' : 'left',
    render: (row) => formatCell(row[c.key], c),
    sortValue: (row) => {
      const v = row[c.key]
      return isNumericType(c.type) ? Number(v ?? 0) : v === null || v === undefined ? null : String(v)
    },
  }))
  const totals = r?.totals ?? {}
  const totalCost = totals.value_at_cost
  const totalPurchase = totals.value_at_retail
  const totalEstimated = totals.estimated_sale_value
  const assumptions = (r as (ReportResult & { assumptions?: { handling_pct: string; margin_pct: string; formula: string } }) | undefined)?.assumptions
  const title = `${user.data?.organisation?.name ?? 'Stock'} - Stock valuation`

  /** PDF and Word come from the server under the letterhead; Excel is built here from the same rows. */
  const exportAs = async (format: 'pdf' | 'doc' | 'xlsx') => {
    if (!r) return
    setExporting(format)
    try {
      const stamp = new Date().toISOString().slice(0, 10)
      if (format === 'xlsx') {
        const cols = r.columns
        const value = (row: Record<string, unknown>, key: string, type: string) => {
          const v = row[key]
          return isNumericType(type) ? (v === null || v === undefined || v === '' ? null : Number(v)) : (v === null || v === undefined ? '' : String(v))
        }
        const blob = buildXlsx({
          sheetName: 'Stock valuation',
          title: assumptions ? `${title} (${assumptions.formula})` : title,
          header: ['No.', ...cols.map((c) => c.label)],
          rows: [
            ...r.rows.map((row, i) => [String(i + 1), ...cols.map((c) => value(row, c.key, c.type ?? ''))]),
            ['Totals', ...cols.map((c) => (c.key in totals ? Number(totals[c.key]) : ''))],
          ],
          widths: [6, ...cols.map((c) => (c.key === 'name' ? 44 : 16))],
        })
        downloadBlob(blob, `${title} ${stamp}.xlsx`, blob.type)
        return
      }
      const { data } = await api.get<Blob>(`/api/reports/${REPORT_KEY}`, { params: { ...params, format }, responseType: 'blob' })
      downloadBlob(data, `${title} ${stamp}.${format}`, format === 'pdf' ? 'application/pdf' : 'application/msword')
    } catch (e) {
      toastApiError(e, 'The export could not be made')
    } finally {
      setExporting(null)
    }
  }

  return (
    <Page>
      <PageHeader
        parent="Inventory"
        title="Valuation"
        subtitle="On-hand value at weighted average cost, at wholesale, and the estimated sale value, per product and store. Reads posted stock balances only."
        actions={canView && r ? (
          <div className="flex flex-wrap gap-2">
            <Button disabled={exporting !== null} onClick={() => void exportAs('pdf')}><FileText size={13} /> {exporting === 'pdf' ? 'Preparing…' : 'PDF'}</Button>
            <Button disabled={exporting !== null} onClick={() => void exportAs('doc')}><FileText size={13} /> {exporting === 'doc' ? 'Preparing…' : 'Word'}</Button>
            <Button disabled={exporting !== null} onClick={() => void exportAs('xlsx')}><Download size={13} /> {exporting === 'xlsx' ? 'Preparing…' : 'Excel'}</Button>
          </div>
        ) : undefined}
      />
      {!canView ? (
        <div className="ui-card"><NoAccess permission={canReport ? 'product.cost.view' : 'report.view'} /></div>
      ) : (
        <>
          <div id="tour-valuation-filter">
            <FilterBar>
              <Field label="Store">
                <Select value={storeId} onChange={(e) => setStoreId(e.target.value)}>
                  <option value="">All stores in branch</option>
                  {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
                </Select>
              </Field>
              <Field label="Transport & handling %" hint="Added to the wholesale value">
                <Input inputMode="decimal" className="tabular text-right w-28" placeholder={assumptions?.handling_pct ? String(Number(assumptions.handling_pct)) : '0'} value={handlingPct} onChange={(e) => setHandlingPct(e.target.value.replace(/[^\d.]/g, ''))} />
              </Field>
              <Field label="Profit margin %" hint="On top of cost + transport">
                <Input inputMode="decimal" className="tabular text-right w-28" placeholder={assumptions?.margin_pct ? String(Number(assumptions.margin_pct)) : '20'} value={marginPct} onChange={(e) => setMarginPct(e.target.value.replace(/[^\d.]/g, ''))} />
              </Field>
            </FilterBar>
          </div>
          <div id="tour-valuation-totals" className="grid gap-3 sm:grid-cols-3 mb-4 max-w-4xl">
            <div className="ui-card p-4">
              <div className="ui-label !mb-1 text-slate-500 font-semibold">Total at cost (WAC)</div>
              <div className="text-2xl font-bold tracking-tight text-slate-900 tabular leading-tight">{result.isLoading ? '…' : formatKes(totalCost === undefined ? null : String(totalCost))}</div>
            </div>
            <div className="ui-card p-4">
              <div className="ui-label !mb-1 text-slate-500 font-semibold">Total at wholesale</div>
              <div className="text-2xl font-bold tracking-tight text-slate-900 tabular leading-tight">{result.isLoading ? '…' : formatKes(totalPurchase === undefined ? null : String(totalPurchase))}</div>
            </div>
            <div className="ui-card p-4">
              <div className="ui-label !mb-1 text-slate-500 font-semibold">Estimated sale value</div>
              <div className="text-2xl font-bold tracking-tight text-emerald-700 tabular leading-tight">{result.isLoading ? '…' : formatKes(totalEstimated === undefined ? null : String(totalEstimated))}</div>
            </div>
          </div>
          {assumptions && <p className="text-xs text-slate-500 -mt-2 mb-3">{assumptions.formula}</p>}
          <div id="tour-valuation-table" className="ui-card">
            {r && (
              <div className="px-4 py-2.5 text-xs text-slate-500 border-b border-slate-200 tabular font-medium">
                {r.rows.length} rows · generated {formatDateTime(r.generated_at)}
              </div>
            )}
            <DataTable
              columns={columns}
              rows={r?.rows}
              rowKey={(row) => `${String(row.code)}|${String(row.store)}`}
              isLoading={result.isLoading}
              error={result.error}
              onRetry={() => result.refetch()}
              emptyTitle="No stock on hand"
              maxHeight="65vh"
              footer={
                r && Object.keys(totals).length > 0 ? (
                  <tr className="font-bold bg-slate-50">
                    {r.columns.map((c, i) => (
                      <td key={c.key} className={isNumericType(c.type) ? 'text-right' : ''}>
                        {c.key in totals ? formatCell(totals[c.key], c) : i === 0 ? 'Totals' : ''}
                      </td>
                    ))}
                  </tr>
                ) : null
              }
            />
          </div>
        </>
      )}
    </Page>
  )
}
