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
    <section className="bg-white rounded-[26px] p-6 shadow-[0_2px_12px_-2px_rgba(15,23,42,0.03),0_10px_28px_-6px_rgba(15,23,42,0.03)] border-0">
      <header className="flex items-center gap-2.5 mb-4">
        <div className="w-8 h-8 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center">
          <Zap size={16} />
        </div>
        <div>
          <h2 className="text-[14px] font-bold text-slate-900">Quick Actions</h2>
          <p className="text-[11.5px] text-slate-400 font-medium">Frequent operational shortcuts</p>
        </div>
      </header>

      <div className="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
        {ACTIONS.map((action) => {
          const Icon = action.icon
          return (
            <motion.div key={action.to} whileHover={{ y: -2 }} whileTap={{ scale: 0.98 }}>
              <Link
                to={action.to}
                className="p-3.5 rounded-2xl bg-slate-50/80 hover:bg-white hover:shadow-[0_4px_20px_-4px_rgba(15,23,42,0.08)] border-0 transition-all duration-200 flex flex-col justify-between h-full group"
              >
                <div className="w-9 h-9 rounded-xl bg-white shadow-2xs text-blue-600 flex items-center justify-center mb-2.5 group-hover:bg-blue-600 group-hover:text-white transition-all">
                  <Icon size={16} />
                </div>
                <div>
                  <div className="text-[13px] font-bold text-slate-900 group-hover:text-blue-600 transition-colors">
                    {action.label}
                  </div>
                  <div className="text-[11px] text-slate-400 font-medium">
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
