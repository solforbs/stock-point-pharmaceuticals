import { ArrowRight, ReceiptText, Store } from 'lucide-react'
import { Link } from 'react-router-dom'
import { StatusBadge } from '../../../components/ui/StatusBadge'
import { formatKes } from '../../../lib/money'
import type { Sale } from '../../../lib/types'

export interface LatestSalesSectionProps {
  sales: Sale[]
  isLoading?: boolean
}

export function LatestSalesSection({ sales, isLoading = false }: LatestSalesSectionProps) {
  const displaySales = sales.slice(0, 6)

  return (
    <section className="bg-white rounded-xl p-5 sm:p-6 border border-slate-200 shadow-xs flex flex-col overflow-hidden">
      <header className="flex items-center justify-between pb-3 border-b border-slate-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center">
            <ReceiptText size={16} />
          </div>
          <div>
            <h2 className="text-sm font-semibold text-slate-900">
              Recent Posted Sales
            </h2>
            <p className="text-xs text-slate-500 font-normal">
              Audited invoices across wholesale & counter desks
            </p>
          </div>
        </div>
        <Link
          to="/sell/invoices"
          className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700 transition-colors"
        >
          View all invoices <ArrowRight size={13} />
        </Link>
      </header>

      {isLoading ? (
        <div className="py-4 space-y-2.5">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-9 bg-slate-100 rounded-md animate-pulse" />
          ))}
        </div>
      ) : displaySales.length === 0 ? (
        <div className="py-8 text-center text-xs text-slate-400">
          No transactions posted yet today.
        </div>
      ) : (
        <div className="overflow-x-auto -mx-5 sm:-mx-6 -mb-5 sm:-mb-6">
          <table className="ui-table">
            <thead>
              <tr>
                <th>Invoice #</th>
                <th>Mode</th>
                <th>Customer / Healthcare Account</th>
                <th className="text-right">Total (KES)</th>
              </tr>
            </thead>
            <tbody>
              {displaySales.map((sale) => (
                <tr key={sale.id} className="hover:bg-slate-50/70 transition-colors">
                  <td className="tabular font-medium">
                    <Link
                      to={`/sell/invoices?sale=${sale.id}`}
                      className="font-mono text-xs font-semibold text-slate-900 hover:text-blue-600 underline-offset-2 hover:underline"
                    >
                      {sale.doc_number}
                    </Link>
                  </td>
                  <td>
                    <StatusBadge status={sale.sale_mode} />
                  </td>
                  <td>
                    <div className="flex items-center gap-2 text-sm font-normal text-slate-800">
                      <Store size={14} className="text-slate-400 shrink-0" />
                      <span className="truncate">{sale.customer?.name ?? 'Walk-in Cash Customer'}</span>
                    </div>
                  </td>
                  <td className="text-right tabular font-semibold text-slate-900 text-sm">
                    {formatKes(sale.grand_total)}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
