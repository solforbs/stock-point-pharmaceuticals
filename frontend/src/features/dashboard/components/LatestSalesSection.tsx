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
    <section className="ui-card flex flex-col overflow-hidden">
      <header className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="p-2 rounded-xl bg-blue-50 text-blue-600">
            <ReceiptText size={18} />
          </div>
          <div>
            <h2 className="text-[14px] font-bold text-slate-900">
              Recent Posted Sales
            </h2>
            <p className="text-[11px] text-slate-400">
              Audited invoices across wholesale & counter desks
            </p>
          </div>
        </div>
        <Link
          to="/sell/invoices"
          className="inline-flex items-center gap-1.5 text-[12px] font-bold text-blue-600 hover:text-blue-700 transition-colors"
        >
          View all invoices <ArrowRight size={13} />
        </Link>
      </header>

      {isLoading ? (
        <div className="p-6 space-y-3">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-9 bg-slate-100 rounded-xl animate-pulse" />
          ))}
        </div>
      ) : displaySales.length === 0 ? (
        <div className="p-8 text-center text-[13px] text-slate-400">
          No transactions posted yet today.
        </div>
      ) : (
        <div className="overflow-x-auto">
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
                <tr key={sale.id} className="group hover:bg-slate-50/80 transition-colors">
                  <td className="tabular font-bold">
                    <Link
                      to={`/sell/invoices?sale=${sale.id}`}
                      className="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 text-blue-600 hover:bg-blue-50 transition-colors text-[12px]"
                    >
                      {sale.doc_number}
                    </Link>
                  </td>
                  <td>
                    <StatusBadge status={sale.sale_mode} />
                  </td>
                  <td>
                    <div className="flex items-center gap-2 font-medium text-slate-800">
                      <Store size={14} className="text-slate-400 shrink-0" />
                      <span>{sale.customer?.name ?? 'Walk-in Cash Customer'}</span>
                    </div>
                  </td>
                  <td className="text-right tabular font-black text-slate-900 text-[13.5px]">
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
