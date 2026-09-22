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
    <section className="bg-white rounded-xl p-5 sm:p-6 border border-slate-200 shadow-xs h-full flex flex-col justify-between">
      <div>
        <header className="flex items-center gap-2.5 mb-4">
          <div className="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center">
            <Zap size={15} />
          </div>
          <div>
            <h2 className="text-sm font-semibold text-slate-900">Quick Actions</h2>
            <p className="text-xs text-slate-500 font-normal">Frequent operational shortcuts</p>
          </div>
        </header>

        <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
          {ACTIONS.map((action) => {
            const Icon = action.icon
            return (
              <motion.div key={action.to} whileHover={{ y: -1.5 }} whileTap={{ scale: 0.98 }}>
                <Link
                  to={action.to}
                  className="p-3 rounded-lg bg-slate-50 hover:bg-white hover:border-slate-300 hover:shadow-xs border border-slate-200/60 transition-all flex flex-col justify-between h-full group"
                >
                  <div className="w-8 h-8 rounded-md bg-white border border-slate-200 text-slate-700 flex items-center justify-center mb-2 group-hover:bg-blue-50 group-hover:border-blue-200 group-hover:text-blue-600 transition-colors">
                    <Icon size={15} />
                  </div>
                  <div>
                    <div className="text-xs font-semibold text-slate-900 group-hover:text-blue-600 transition-colors">
                      {action.label}
                    </div>
                    <div className="text-xs text-slate-500 font-normal truncate">
                      {action.desc}
                    </div>
                  </div>
                </Link>
              </motion.div>
            )
          })}
        </div>
      </div>
    </section>
  )
}
