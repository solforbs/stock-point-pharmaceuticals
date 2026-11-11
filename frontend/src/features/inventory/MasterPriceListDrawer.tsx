import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Drawer } from '../../components/ui/Drawer'
import { Button, DrawerFooter, Input } from '../../components/ui/primitives'
import { ErrorState, InlineError, LoadingSkeleton } from '../../components/ui/States'
import { api, apiGet, apiPut } from '../../lib/api'
import { downloadBlob } from '../../lib/csv'
import { dAdd, dCmp, dDiv, dIsPos, dMul, dRound2, isValidDecimal } from '../../lib/decimal'
import { formatMoney, formatQty } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'

type MasterRow = {
  product_id: string
  code: string
  name: string
  brand_name: string | null
  strength: string | null
  uom_id: string
  uom_code: string | null
  factor_to_base: string
  on_hand: string
  unit_cost: string | null
  master_price: string | null
  catalogue_price: string | null
  price: string | null
}

type MasterSheet = { price_list: { id: string; updated_at: string } | null; rows: MasterRow[] }

const isPrice = (value: string | undefined): value is string => !!value && isValidDecimal(value.trim()) && dIsPos(value.trim())

/** Downloads the saved master price list on the institution's letterhead. */
export async function downloadMasterPriceList(): Promise<void> {
  try {
    const { data } = await api.get<Blob>('/api/price-lists/master/pdf', { responseType: 'blob' })
    downloadBlob(data, `Master price list ${new Date().toISOString().slice(0, 10)}.pdf`, 'application/pdf')
  } catch {
    toast.error('Could not download the price list', 'Save the master price list first, then try again.')
  }
}

/**
 * The master price list, built from the stock at hand: every product in
 * stock with what it cost and its price now. Prices are per selling unit
 * and include VAT; once saved, the POS charges them in every mode.
 */
export function MasterPriceListDrawer({ open, onClose }: { open: boolean; onClose: () => void }) {
  const canSave = usePermission('price.manage')
  const queryClient = useQueryClient()
  const [prices, setPrices] = useState<Record<string, string>>({})
  const [q, setQ] = useState('')
  const [markup, setMarkup] = useState('')

  const sheet = useQuery({
    queryKey: ['price-lists', 'master'],
    queryFn: () => apiGet<MasterSheet>('/api/price-lists/master'),
    enabled: open,
  })
  const rows = useMemo(() => sheet.data?.rows ?? [], [sheet.data])
  const hasCost = rows.some((r) => r.unit_cost !== null)

  // Start every row from its price now; the user adjusts from there.
  useEffect(() => {
    if (!open || !sheet.data) return
    setPrices(Object.fromEntries(sheet.data.rows.map((r) => [r.product_id, r.price ? dRound2(r.price) : ''])))
  }, [open, sheet.data])

  const shown = useMemo(() => {
    const term = q.trim().toLowerCase()
    if (!term) return rows
    return rows.filter((r) => [r.name, r.code, r.brand_name ?? ''].some((v) => v.toLowerCase().includes(term)))
  }, [rows, q])

  const priced = rows.filter((r) => isPrice(prices[r.product_id]))
  const changed = priced.filter((r) => !r.master_price || dCmp(prices[r.product_id].trim(), r.master_price) !== 0).length
  const missing = rows.length - priced.length

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPut<{ products: number; prices_changed: number }>('/api/price-lists/master', {
        items: priced.map((r) => ({ product_id: r.product_id, price: prices[r.product_id].trim() })),
      }),
    onSuccess: (result) => {
      toast.success('Master price list saved', `${result.products} products · the POS now charges these prices.`)
      queryClient.invalidateQueries({ queryKey: ['price-lists'] })
      queryClient.invalidateQueries({ queryKey: ['products'] })
    },
  })

  /** Sets every shown product that has a cost to cost plus the mark-up. */
  function applyMarkup() {
    if (!isValidDecimal(markup.trim())) return
    const factor = dAdd('1', dDiv(markup.trim(), '100'))
    setPrices((current) => {
      const next = { ...current }
      for (const r of shown) {
        if (r.unit_cost && dIsPos(r.unit_cost)) next[r.product_id] = dRound2(dMul(r.unit_cost, factor))
      }
      return next
    })
  }

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="Master price list"
      subtitle="Every product in stock, priced per selling unit with VAT included. Once saved, the POS charges these prices in every mode."
      width={1040}
      actions={
        sheet.data?.price_list ? (
          <Button size="sm" onClick={() => void downloadMasterPriceList()}>
            <Download size={14} /> Download PDF
          </Button>
        ) : null
      }
    >
      <div className="space-y-4">
        <div className="flex flex-wrap items-end gap-3">
          <label className="block w-full sm:w-72">
            <span className="ui-label">Search</span>
            <Input className="mt-1" placeholder="Name, code or brand" value={q} onChange={(e) => setQ(e.target.value)} />
          </label>
          {hasCost && canSave && (
            <div className="flex items-end gap-2">
              <label className="block">
                <span className="ui-label">Mark-up on cost</span>
                <div className="mt-1 flex items-center gap-1">
                  <Input
                    className="w-20 text-right tabular"
                    inputMode="decimal"
                    placeholder="25"
                    value={markup}
                    onChange={(e) => setMarkup(e.target.value.replace(/[^\d.]/g, ''))}
                  />
                  <span className="text-sm text-slate-500">%</span>
                </div>
              </label>
              <Button size="sm" disabled={!isValidDecimal(markup.trim())} onClick={applyMarkup}>
                Apply to {q.trim() ? 'shown' : 'all'}
              </Button>
            </div>
          )}
          <div className="ml-auto text-xs text-slate-500 tabular">
            {rows.length} products in stock · {changed} changed{missing > 0 ? ` · ${missing} without a price` : ''}
          </div>
        </div>

        {sheet.isLoading ? (
          <LoadingSkeleton rows={8} />
        ) : sheet.error ? (
          <ErrorState error={sheet.error} onRetry={() => sheet.refetch()} />
        ) : rows.length === 0 ? (
          <div className="p-6 text-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 text-sm text-slate-500">
            Nothing is in stock yet, so there is nothing to price.
          </div>
        ) : (
          <div className="overflow-x-auto rounded-xl border border-slate-200">
            <table className="ui-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Unit</th>
                  <th className="text-right">On hand</th>
                  {hasCost && <th className="text-right">Cost / unit</th>}
                  <th className="text-right">Current price</th>
                  <th className="text-right">New price (KES)</th>
                </tr>
              </thead>
              <tbody>
                {shown.map((r) => {
                  const value = prices[r.product_id] ?? ''
                  const valid = isPrice(value)
                  const belowCost = valid && !!r.unit_cost && dCmp(value.trim(), r.unit_cost) < 0
                  const isChanged = valid && (!r.price || dCmp(value.trim(), r.price) !== 0)
                  const factor = Number(r.factor_to_base) || 1
                  return (
                    <tr key={r.product_id}>
                      <td>
                        <div className="font-bold text-sm text-slate-900">{r.name}</div>
                        <div className="text-xs text-slate-500 font-mono">
                          #{r.code}
                          {r.brand_name ? ` · ${r.brand_name}` : ''}
                        </div>
                      </td>
                      <td className="text-xs font-bold text-slate-700">
                        {r.uom_code ?? '—'}
                        {factor !== 1 && <span className="text-slate-400 font-normal"> ×{factor}</span>}
                      </td>
                      <td className="text-right tabular text-xs">{formatQty(dDiv(r.on_hand, r.factor_to_base))}</td>
                      {hasCost && <td className="text-right tabular text-xs text-slate-600">{r.unit_cost ? formatMoney(r.unit_cost) : '—'}</td>}
                      <td className="text-right tabular text-xs text-slate-600">
                        {r.price ? formatMoney(r.price) : '—'}
                        {!r.master_price && r.price && <div className="text-[10px] text-slate-400">catalogue</div>}
                      </td>
                      <td className="text-right">
                        <input
                          type="text"
                          inputMode="decimal"
                          aria-label={`New price for ${r.name}`}
                          value={value}
                          disabled={!canSave}
                          onChange={(e) => {
                            const next = e.target.value.replace(/[^\d.]/g, '')
                            setPrices((current) => ({ ...current, [r.product_id]: next }))
                          }}
                          className={`h-8 w-28 px-2 rounded-lg border text-sm font-bold tabular text-right outline-none ${
                            belowCost
                              ? 'border-amber-400 bg-amber-50 text-amber-800'
                              : isChanged
                                ? 'border-blue-400 bg-blue-50/60 text-blue-800'
                                : 'border-slate-200 bg-slate-50 text-slate-800'
                          } disabled:opacity-60`}
                        />
                        {belowCost && <div className="text-[11px] font-semibold text-amber-700 mt-0.5">Below cost</div>}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}

        {save.isError && <InlineError error={save.error} />}

        <DrawerFooter
          onCancel={onClose}
          onSubmit={canSave ? () => save.mutate() : undefined}
          submitLabel="Save price list"
          isSubmitting={save.isPending}
          disabled={!canSave || priced.length === 0}
        >
          <span className="text-xs text-slate-600 hidden sm:inline">
            {canSave ? 'Saved prices become the POS price for these products.' : 'Changing prices needs the price management permission.'}
          </span>
        </DrawerFooter>
      </div>
    </Drawer>
  )
}
