import { motion } from 'framer-motion'
import {
  CheckSquare,
  CreditCard,
  FileText,
  PackageSearch,
  ShoppingCart,
  Truck,
  Zap,
} from 'lucide-react'
import { Link } from 'react-router-dom'

const ACTIONS = [
  { to: '/sell/pos', label: 'Point of Sale', icon: ShoppingCart, desc: 'Counter checkout' },
  { to: '/buy/goods-receipts', label: 'Receive Goods', icon: Truck, desc: 'Supplier GRN' },
  { to: '/inventory/batches', label: 'Release QC', icon: CheckSquare, desc: 'Batch inspection' },
  { to: '/sell/quotations', label: 'New Quotation', icon: FileText, desc: 'Proforma pricing' },
  { to: '/warehouse/pick-lists', label: 'Pick Orders', icon: PackageSearch, desc: 'Fulfillment' },
  { to: '/finance/receivables', label: 'Receive Payment', icon: CreditCard, desc: 'Customer balance' },
]

export function QuickActionsSection() {
  return (
    <section className="ui-card p-5">
      <header className="flex items-center gap-2 pb-4 border-b border-[var(--border)] mb-4">
        <div className="p-1.5 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400">
          <Zap size={16} />
        </div>
        <div>
          <h2 className="text-[13.5px] font-bold text-[var(--text)]">Quick Actions</h2>
          <p className="text-[11px] text-[var(--text-muted)]">Frequent operational shortcuts</p>
        </div>
      </header>

      <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
        {ACTIONS.map((action) => {
          const Icon = action.icon
          return (
            <motion.div key={action.to} whileHover={{ y: -2 }} whileTap={{ scale: 0.98 }}>
              <Link
                to={action.to}
                className="p-3 rounded-xl border border-[var(--border)] bg-[var(--surface-2)] hover:border-[var(--color-navy-light)] hover:bg-[var(--card)] transition-all flex flex-col justify-between h-full group"
              >
                <div className="p-2 w-fit rounded-lg bg-[var(--card)] group-hover:bg-[color-mix(in_srgb,var(--color-navy)_10%,transparent)] text-[var(--color-navy)] dark:text-blue-400 border border-[var(--border)] mb-2 transition-colors">
                  <Icon size={16} />
                </div>
                <div>
                  <div className="text-[12.5px] font-bold text-[var(--text)] group-hover:text-[var(--color-navy)] dark:group-hover:text-blue-400 transition-colors">
                    {action.label}
                  </div>
                  <div className="text-[10.5px] text-[var(--text-muted)] font-medium">
                    {action.desc}
                  </div>
                </div>
              </Link>
            </motion.div>
          )
        })}
      </div>
    </section>
  )
}
