import { useMutation } from '@tanstack/react-query'
import { Play } from 'lucide-react'
import { useState } from 'react'
import { CustomerPicker } from '../../../components/CustomerPicker'
import { MoneyCell } from '../../../components/ui/MoneyCell'
import { EmptyState, InlineError, NoAccess } from '../../../components/ui/States'
import { StatusBadge } from '../../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, Field, Input, Select } from '../../../components/ui/primitives'
import { useCurrentUser } from '../../../hooks/useCurrentUser'
import { apiPost } from '../../../lib/api'
import { formatDate, titleCase, todayIso } from '../../../lib/format'
import { useStores } from '../../../lib/hooks'
import { formatPct, formatQty } from '../../../lib/money'
import { usePermission } from '../../../lib/permissions'
import type { Customer } from '../../../lib/types'
import { ProductField, UomSelect } from './fields'
import { decimalInput, defaultUomId } from './shared'

type TestLine = {
  product_name: string
  uom_code: string
  quantity: string
  price_source: string
  list_price: string
  break_price: string
  unit_price: string
  discount_pct: string
  discount_capped_by: string | null
  line_subtotal: string
  tax_code: string | null
  tax_amount: string
  line_total: string
  bonus_qty: string
  unit_cost: string | null
  margin_pct: string | null
  floor_price: string | null
  floor_breached: boolean
  approval_required: boolean
  explain: string[]
}
type TestQuote = { lines: TestLine[]; totals: { grand_total: string }; approval_required: boolean }

type WhatIf = Record<string, string | boolean | null>

/**
 * Part 4.12 — test a rule: the real seven-step quote for one line, as the
 * till would price it right now (or on a chosen date), with the engine's
 * explanation. Nothing is saved.
 */
export default function SimulatorTab() {
  const canSimulate = usePermission('price.simulate')
  const canCost = usePermission('product.cost.view')
  const { data: me } = useCurrentUser()
  const stores = useStores()
  const modes = me?.sale_modes?.length ? me.sale_modes : ['RETAIL', 'WHOLESALE']
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [form, setForm] = useState({ sale_mode: (me?.default_sale_mode ?? 'RETAIL') as string, store_id: '', product_id: '', uom_id: '', quantity: '1', requested_discount_pct: '', quote_date: todayIso() })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const storeId = form.store_id || stores.data?.find((s) => s.is_sellable)?.id || ''

  const run = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<TestQuote>('/api/pricing-rules/test', {
        ...form,
        store_id: storeId,
        customer_id: customer?.id ?? null,
        requested_discount_pct: form.requested_discount_pct || null,
        quote_date: form.quote_date || null,
      }),
  })

  if (!canSimulate) return <NoAccess permission="price.simulate" />
  const line = run.data?.lines[0]

  return (
    <div className="space-y-4">
      <div className="grid gap-4 lg:grid-cols-[380px_1fr]">
        <Card title="Test a price">
          <div className="p-4 space-y-3">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <Field label="Sale mode">
                <Select value={form.sale_mode} onChange={(e) => set({ sale_mode: e.target.value })}>
                  {modes.map((m) => <option key={m} value={m}>{titleCase(m)}</option>)}
                </Select>
              </Field>
              <Field label="Store">
                <Select value={storeId} onChange={(e) => set({ store_id: e.target.value })}>
                  {(stores.data ?? []).map((s) => <option key={s.id} value={s.id}>{s.code}</option>)}
                </Select>
              </Field>
            </div>
            <Field label="Customer" hint={form.sale_mode === 'WHOLESALE' ? 'Contract prices and tier lists need a customer.' : 'Optional.'}><CustomerPicker value={customer} onChange={setCustomer} /></Field>
            <div className="grid grid-cols-[1fr_110px] gap-3">
              <Field label="Product" required><ProductField productId={form.product_id} onChange={(p) => set({ product_id: p?.id ?? '', uom_id: defaultUomId(p) })} /></Field>
              <Field label="Unit" required><UomSelect productId={form.product_id} value={form.uom_id} onChange={(u) => set({ uom_id: u })} /></Field>
            </div>
            <div className="grid grid-cols-3 gap-3">
              <Field label="Quantity" required><Input inputMode="decimal" className="tabular" value={form.quantity} onChange={(e) => set({ quantity: decimalInput(e.target.value) })} /></Field>
              <Field label="Discount %" hint="As requested at the till."><Input inputMode="decimal" className="tabular" value={form.requested_discount_pct} onChange={(e) => set({ requested_discount_pct: decimalInput(e.target.value) })} /></Field>
              <Field label="Price as at"><Input type="date" value={form.quote_date} onChange={(e) => set({ quote_date: e.target.value })} /></Field>
            </div>
            <p className="text-xs text-slate-500">Discount limits use your own roles' authority, as they would at your till.</p>
            <Button variant="primary" className="w-full" disabled={!form.product_id || !form.uom_id || !storeId || !Number(form.quantity) || run.isPending} onClick={() => run.mutate()}>
              <Play size={12} /> {run.isPending ? 'Pricing…' : 'Run the engine'}
            </Button>
            {run.isError && <InlineError error={run.error} />}
          </div>
        </Card>

        <Card title="Engine result">
          {!line ? (
            <EmptyState title="No result yet" hint="Choose a product and run the engine to see which rule set the price and why." />
          ) : (
            <div className="p-4 space-y-4">
              <div className="flex flex-wrap items-center gap-3">
                <div className="text-2xl font-extrabold tabular font-mono"><MoneyCell value={line.unit_price} symbol /></div>
                <span className="text-xs text-slate-500">per {line.uom_code} · {formatQty(line.quantity)} {line.uom_code} for <MoneyCell value={line.line_total} symbol className="font-semibold text-slate-900" /> incl. tax</span>
                <StatusBadge status={line.price_source} tone="blue" label={titleCase(line.price_source)} />
                {Number(line.bonus_qty) > 0 && <StatusBadge status="BONUS" tone="teal" label={`+${formatQty(line.bonus_qty)} free`} />}
                {line.floor_breached && <StatusBadge status="FAILED" label="Below margin floor" />}
                {line.approval_required && <StatusBadge status="PENDING_APPROVAL" label="Needs approval" />}
              </div>
              <DescriptionList
                items={[
                  { label: 'List price', value: <MoneyCell value={line.list_price} /> },
                  { label: 'After breaks / contract / promo', value: <MoneyCell value={line.break_price} /> },
                  { label: 'Discount applied', value: `${formatPct(line.discount_pct)}${line.discount_capped_by ? ` (capped by ${line.discount_capped_by})` : ''}` },
                  { label: 'Tax', value: <>{line.tax_code ?? '—'} · <MoneyCell value={line.tax_amount} /></> },
                  ...(canCost ? [
                    { label: 'Unit cost (WAC)', value: line.unit_cost ? <MoneyCell value={line.unit_cost} /> : 'No stock in this store' },
                    { label: 'Margin', value: formatPct(line.margin_pct) },
                    { label: 'Margin floor price', value: line.floor_price ? <MoneyCell value={line.floor_price} /> : '—' },
                  ] : []),
                  { label: 'Priced as at', value: formatDate(form.quote_date || todayIso()) },
                ]}
              />
              <div>
                <h3 className="text-xs font-semibold text-slate-900 uppercase tracking-wide mb-1.5">How the engine got there</h3>
                <ol className="space-y-1 text-xs list-decimal pl-5">
                  {line.explain.map((e, i) => <li key={i} className="text-slate-600">{e}</li>)}
                </ol>
              </div>
            </div>
          )}
        </Card>
      </div>
      {canCost && <WhatIfCalculator />}
    </div>
  )
}

/** Part 4.10 — pure what-if arithmetic (POST /api/pricing/simulate): discount against margin, bonus cost and break-even volume. */
function WhatIfCalculator() {
  const [form, setForm] = useState({ cost: '', list_price: '', quantity: '1', discount_pct: '', min_margin_pct: '', bonus_buy_qty: '', bonus_free_qty: '', funded_by: 'US', monthly_volume: '' })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const run = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<WhatIf>('/api/pricing/simulate', Object.fromEntries(Object.entries(form).map(([k, v]) => [k, v === '' ? null : v]))),
  })
  const r = run.data
  const n = (k: string) => (r?.[k] === null || r?.[k] === undefined ? null : String(r[k]))

  return (
    <Card title="What-if calculator" actions={<span className="text-xs text-slate-400">Arithmetic only; no rules are read</span>}>
      <div className="p-4 grid gap-4 lg:grid-cols-[1fr_1fr]">
        <div className="grid grid-cols-3 gap-3 content-start">
          {([
            ['cost', 'Unit cost'], ['list_price', 'List price'], ['quantity', 'Quantity'],
            ['discount_pct', 'Discount %'], ['min_margin_pct', 'Min margin %'], ['monthly_volume', 'Monthly volume'],
            ['bonus_buy_qty', 'Bonus: buy'], ['bonus_free_qty', 'Bonus: free'],
          ] as const).map(([k, label]) => (
            <Field key={k} label={label}><Input inputMode="decimal" className="tabular" value={form[k]} onChange={(e) => set({ [k]: decimalInput(e.target.value) })} /></Field>
          ))}
          <Field label="Bonus funded by"><Select value={form.funded_by} onChange={(e) => set({ funded_by: e.target.value })}><option value="US">Us</option><option value="SUPPLIER">Supplier</option></Select></Field>
          <div className="col-span-3">
            <Button disabled={!form.cost || !form.list_price || run.isPending} onClick={() => run.mutate()}><Play size={12} /> Calculate</Button>
            {run.isError && <InlineError error={run.error} className="mt-2" />}
          </div>
        </div>
        {r ? (
          <DescriptionList
            items={[
              { label: 'Unit price', value: <MoneyCell value={n('unit_price')} symbol /> },
              { label: 'Margin floor price', value: n('floor_price') ? <MoneyCell value={n('floor_price')} /> : '—' },
              { label: 'Floor breached', value: r.floor_breached ? 'Yes: price raised to the floor' : 'No' },
              { label: 'Max discount within floor', value: formatPct(n('max_allowable_discount_pct')) },
              { label: 'Revenue', value: <MoneyCell value={n('revenue')} /> },
              { label: 'Gross profit', value: <MoneyCell value={n('gross_profit')} /> },
              { label: 'Margin / markup', value: `${formatPct(n('margin_pct'))} / ${formatPct(n('markup_pct'))}` },
              { label: 'Bonus units / cost', value: <>{formatQty(n('bonus_units'))} · <MoneyCell value={n('bonus_cost')} /></> },
              { label: 'Effective margin', value: formatPct(n('effective_margin_pct')) },
              { label: 'Volume needed to break even', value: n('break_even_volume_increase_pct') ? `+${formatPct(n('break_even_volume_increase_pct'))}` : '—' },
              { label: 'Monthly profit vs list', value: <><MoneyCell value={n('monthly_profit')} /> (change <MoneyCell value={n('monthly_profit_delta')} />)</> },
            ]}
          />
        ) : (
          <EmptyState title="Enter a cost and list price" hint="See what a discount or bonus does to margin, and how much more volume it would take to earn the same profit." />
        )}
      </div>
    </Card>
  )
}
