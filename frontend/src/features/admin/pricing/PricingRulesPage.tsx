import { useSearchParams } from 'react-router-dom'
import { Page, PageHeader } from '../../../components/ui/PageHeader'
import { NoAccess } from '../../../components/ui/States'
import { usePermission } from '../../../lib/permissions'
import CustomerPricesTab from './CustomerPricesTab'
import DiscountAuthorityTab from './DiscountAuthorityTab'
import DiscountPoliciesTab from './DiscountPoliciesTab'
import PriceBreaksTab from './PriceBreaksTab'
import PromotionsTab from './PromotionsTab'
import SimulatorTab from './SimulatorTab'

const TABS = [
  { key: 'promotions', label: 'Promotions' },
  { key: 'breaks', label: 'Price breaks' },
  { key: 'policies', label: 'Discount policies' },
  { key: 'authority', label: 'Discount authority' },
  { key: 'contracts', label: 'Customer prices' },
  { key: 'simulator', label: 'Simulator' },
] as const

type TabKey = (typeof TABS)[number]['key']

/** Part 6 — the rules the pricing engine reads, and a simulator that runs it. */
export default function PricingRulesPage() {
  const canManage = usePermission('price.manage')
  const canSimulate = usePermission('price.simulate')
  const canSaleView = usePermission('sale.view')
  const [params, setParams] = useSearchParams()
  const requested = params.get('tab') as TabKey | null
  const tab: TabKey = TABS.some((t) => t.key === requested) ? (requested as TabKey) : 'promotions'

  if (!canManage && !canSimulate && !canSaleView) return <NoAccess permission="price.simulate" />

  return (
    <Page>
      <PageHeader
        parent="Administration"
        title="Pricing Rules"
        subtitle={canManage ? 'Contract price, promotion, tier list, mode list, branch list, default: the cheapest valid rule wins. Every change is audited.' : 'Read-only: changing rules needs the price.manage permission.'}
      />
      <div className="flex flex-wrap gap-1 mb-4 border-b border-[var(--border)]">
        {TABS.filter((t) => t.key !== 'simulator' || canSimulate).map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => setParams({ tab: t.key })}
            className={`px-3 py-2 text-[12.5px] font-semibold border-b-2 -mb-px ${tab === t.key ? 'border-[var(--color-navy)] text-[var(--text)]' : 'border-transparent text-[var(--text-muted)] hover:text-[var(--text)]'}`}
          >
            {t.label}
          </button>
        ))}
      </div>
      {tab === 'promotions' && <PromotionsTab canManage={canManage} />}
      {tab === 'breaks' && <PriceBreaksTab canManage={canManage} />}
      {tab === 'policies' && <DiscountPoliciesTab canManage={canManage} />}
      {tab === 'authority' && <DiscountAuthorityTab canManage={canManage} />}
      {tab === 'contracts' && <CustomerPricesTab canManage={canManage} />}
      {tab === 'simulator' && <SimulatorTab />}
    </Page>
  )
}
