import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Download, Save } from 'lucide-react'
import { useMemo, useState } from 'react'
import { Drawer } from '../../components/ui/Drawer'
import { InlineError } from '../../components/ui/States'
import { Button, Field, Input } from '../../components/ui/primitives'
import { api, apiGet, apiPut, ensureCsrfCookie } from '../../lib/api'
import { downloadBlob } from '../../lib/csv'
import { dEq, dIsPos } from '../../lib/decimal'
import { formatMoney, formatQty } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast, toastApiError } from '../../lib/toast'

type MasterPriceRow = {
  id: string
  code: string
  name: string
  brand_name: string | null
  base_uom: string | null
  qty_on_hand: string
  default_price: string | null
  master_price: string | null
}

type MasterPriceListData = {
  title: string
  list: { id: string; code: string; name: string; is_active: boolean; updated_at: string } | null
  products: MasterPriceRow[]
}

type SaveResult = { changed: number; unchanged: number }

const QUERY_KEY = ['inventory', 'master-price-list']
const SHOWN_ROWS = 300

/** A price as typed: digits with at most four decimals, above zero. */
const isValidPrice = (value: string) => /^\d+(\.\d{1,4})?$/.test(value) && dIsPos(value)

/** "2.5000" → "2.50", "3.1250" → "3.125": what a person would type. */
function toInput(value: string | null): string {
  if (value === null || value === '') return ''
  const [whole, fraction = ''] = value.split('.')
  return `${whole}.${fraction.replace(/0+$/, '').padEnd(2, '0')}`
}

/**
 * The master price list, built from the stock on hand: one selling price
 * per product, per base unit and before VAT. Saving it makes these the
 * prices the POS charges at every branch (customer contracts and
 * promotions still apply when cheaper); the PDF prints the saved list on
 * the institution's letterhead.
 */
export function MasterPriceListDrawer({ onClose }: { onClose: () => void }) {
  const canSave = usePermission('price.manage')
  const queryClient = useQueryClient()
  const data = useQuery({ queryKey: QUERY_KEY, queryFn: () => apiGet<MasterPriceListData>('/api/inventory/master-price-list') })
  /** Only what the user has typed; every other product shows the price it sells at now. */
  const [edits, setEdits] = useState<Record<string, string>>({})
  const [search, setSearch] = useState('')
  const [onlyMissing, setOnlyMissing] = useState(false)
  const [downloading, setDownloading] = useState(false)

  const products = useMemo(() => data.data?.products ?? [], [data.data])

  /** The price in the box: as typed, otherwise the saved master price, else the default price. */
  const valueOf = (p: MasterPriceRow) => edits[p.id] ?? toInput(p.master_price ?? p.default_price)
  const typed = (p: MasterPriceRow) => valueOf(p).trim()
  const invalid = products.filter((p) => typed(p) !== '' && !isValidPrice(typed(p)))
  const missing = products.filter((p) => typed(p) === '')
  const changed = products.filter((p) => isValidPrice(typed(p)) && (p.master_price === null || !dEq(typed(p), p.master_price)))
  const dirty = changed.length > 0

  const visible = useMemo(() => {
    const term = search.trim().toLowerCase()
    return products.filter((p) => (!onlyMissing || (edits[p.id] ?? toInput(p.master_price ?? p.default_price)).trim() === '')
      && (!term || [p.code, p.name, p.brand_name].some((v) => v && v.toLowerCase().includes(term))))
  }, [products, edits, search, onlyMissing])

  const save = useMutation({
    mutationFn: () => apiPut<SaveResult>('/api/inventory/master-price-list', {
      prices: products.filter((p) => isValidPrice(typed(p))).map((p) => ({ product_id: p.id, price: typed(p) })),
    }),
    onSuccess: (result) => {
      toast.success('Master price list saved', `${result.changed} price(s) changed. The POS now charges these prices.`)
      setEdits({})
      queryClient.invalidateQueries({ queryKey: QUERY_KEY })
      queryClient.invalidateQueries({ queryKey: ['products'] })
      queryClient.invalidateQueries({ queryKey: ['price-lists'] })
    },
    onError: (e) => toastApiError(e, 'The prices could not be saved'),
  })

  const downloadPdf = async () => {
    setDownloading(true)
    try {
      await ensureCsrfCookie()
      const { data: pdf } = await api.get<Blob>('/api/inventory/master-price-list/pdf', { responseType: 'blob' })
      downloadBlob(pdf, `${data.data?.title ?? 'Master price list'} ${new Date().toISOString().slice(0, 10)}.pdf`, 'application/pdf')
    } catch (e) {
      toastApiError(e, 'The PDF could not be made')
    } finally {
      setDownloading(false)
    }
  }

  const close = () => {
    if (dirty && !window.confirm('You have unsaved prices. Close and discard them?')) return
    onClose()
  }

  const savedOn = data.data?.list ? new Date(data.data.list.updated_at).toLocaleString() : null

  return (
    <Drawer
      open
      onClose={close}
      title="Master price list"
      subtitle={data.data ? `${products.length} products in stock · ${savedOn ? `last saved ${savedOn}` : 'not saved yet'}` : undefined}
      width={1040}
      footer={data.data && (
        <div className="w-full flex flex-wrap items-center justify-between gap-3">
          <div className="text-xs text-slate-600">
            {changed.length} price(s) to save
            {missing.length > 0 && <> · <span className="text-amber-700 font-medium">{missing.length} without a price (left out)</span></>}
            {invalid.length > 0 && <> · <span className="text-rose-600 font-medium">{invalid.length} not a valid price</span></>}
          </div>
          <div className="flex gap-2">
            <Button
              disabled={!data.data.list || dirty || downloading}
              title={!data.data.list ? 'Save the list first' : dirty ? 'Save your changes first' : undefined}
              onClick={() => void downloadPdf()}
            >
              <Download size={13} /> {downloading ? 'Preparing PDF…' : 'Download PDF'}
            </Button>
            {canSave && (
              <Button variant="primary" disabled={!dirty || invalid.length > 0 || save.isPending} onClick={() => save.mutate()}>
                <Save size={13} /> {save.isPending ? 'Saving…' : 'Save price list'}
              </Button>
            )}
          </div>
        </div>
      )}
    >
      <div className="space-y-4">
        {data.isLoading && <p className="text-sm text-slate-600">Loading products in stock…</p>}
        {data.isError && <InlineError error={data.error} />}
        {data.data && (
          <>
            <p className="text-xs text-slate-600 leading-relaxed">
              Set the selling price for each product in stock. Prices are per base unit (for example per tablet) and before VAT; packs are priced from it automatically.
              Once saved, the POS charges these prices at every branch. Customer contract prices and promotions still apply when they are cheaper.
              {!canSave && <span className="block mt-1 text-amber-700">You can view this list, but saving prices needs the price management permission.</span>}
            </p>
            {data.data.list && !data.data.list.is_active && (
              <p className="text-xs text-amber-700 font-medium">This list has been switched off under Customers → Tiers &amp; Price Lists, so the POS is not using it.</p>
            )}
            <div className="flex flex-wrap items-end gap-3">
              <Field label="Find a product"><Input value={search} placeholder="Code, name or brand" onChange={(e) => setSearch(e.target.value)} /></Field>
              <label className="flex items-center gap-2 text-sm pb-2 cursor-pointer">
                <input type="checkbox" checked={onlyMissing} onChange={(e) => setOnlyMissing(e.target.checked)} />
                Only products without a price ({missing.length})
              </label>
            </div>
            {products.length === 0 ? (
              <p className="text-sm text-slate-600">No products are in stock yet. Receive stock first, then build the price list.</p>
            ) : (
              <div className="max-h-[520px] overflow-auto rounded-xl border border-slate-200">
                <table className="ui-table">
                  <thead>
                    <tr>
                      <th>Code</th>
                      <th>Product</th>
                      <th>Brand</th>
                      <th className="text-right">In stock</th>
                      <th className="text-right">Current price</th>
                      <th className="text-right">New price (KES)</th>
                    </tr>
                  </thead>
                  <tbody>
                    {visible.slice(0, SHOWN_ROWS).map((p) => {
                      const value = valueOf(p)
                      const bad = value.trim() !== '' && !isValidPrice(value.trim())
                      const current = p.master_price ?? p.default_price
                      return (
                        <tr key={p.id} className={bad ? 'bg-rose-50/70' : value.trim() === '' ? 'bg-amber-50/60' : ''}>
                          <td className="tabular font-mono text-xs font-semibold">{p.code}</td>
                          <td>{p.name}</td>
                          <td>{p.brand_name ?? '—'}</td>
                          <td className="text-right tabular">{formatQty(p.qty_on_hand)} {p.base_uom ?? ''}</td>
                          <td className="text-right tabular text-slate-500">
                            {current === null ? '—' : formatMoney(current)}
                            {current !== null && p.master_price === null && <span className="block text-[10px]">default price</span>}
                          </td>
                          <td className="text-right">
                            <input
                              value={value}
                              placeholder="enter price"
                              inputMode="decimal"
                              aria-label={`New price for ${p.name}`}
                              disabled={!canSave}
                              onChange={(e) => setEdits({ ...edits, [p.id]: e.target.value.replace(/[^\d.]/g, '') })}
                              className={`ui-input h-7 w-28 tabular text-right ${bad ? 'border-rose-400' : ''}`}
                            />
                          </td>
                        </tr>
                      )
                    })}
                  </tbody>
                </table>
                {visible.length > SHOWN_ROWS && <div className="p-2 text-xs text-slate-500">Showing {SHOWN_ROWS} of {visible.length}. Search to find others; every product is saved.</div>}
              </div>
            )}
          </>
        )}
      </div>
    </Drawer>
  )
}
