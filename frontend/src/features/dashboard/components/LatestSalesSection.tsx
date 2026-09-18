import { ArrowRight, ReceiptText } from 'lucide-react'
import { Link } from 'react-router-dom'
import { formatKes } from '../../../lib/money'
import type { Sale } from '../../../lib/types'

export interface LatestSalesSectionProps {
  sales: Sale[]
  isLoading?: boolean
}

export function LatestSalesSection({ sales, isLoading = false }: LatestSalesSectionProps) {
  const displaySales = sales.slice(0, 7)

  return (
    <section className="ui-card flex flex-col overflow-hidden">
      <header className="px-5 py-3.5 border-b border-[var(--border)] flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="p-1.5 rounded-lg bg-[color-mix(in_srgb,var(--color-navy)_12%,transparent)] text-[var(--color-navy)] dark:text-blue-400">
            <ReceiptText size={16} />
          </div>
          <div>
            <h2 className="text-[13.5px] font-bold text-[var(--text)]">Recent Posted Sales</h2>
            <p className="text-[11px] text-[var(--text-muted)]">Latest invoices across wholesale & POS</p>
          </div>
        </div>
        <Link
          to="/sell/invoices"
          className="inline-flex items-center gap-1 text-[11.5px] font-bold text-[var(--color-navy)] dark:text-blue-400 hover:underline"
        >
          All Invoices <ArrowRight size={12} />
        </Link>
      </header>

      {isLoading ? (
        <div className="p-6 space-y-3">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-8 bg-[var(--surface-2)] rounded animate-pulse" />
          ))}
        </div>
      ) : displaySales.length === 0 ? (
        <div className="p-6 text-center text-[12.5px] text-[var(--text-muted)]">
          No sales recorded yet today.
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="ui-table">
            <thead>
              <tr>
                <th>Invoice #</th>
                <th>Mode</th>
                <th>Customer / Account</th>
                <th className="text-right">Total (KES)</th>
              </tr>
            </thead>
            <tbody>
              {displaySales.map((sale) => (
                <tr key={sale.id} className="group hover:bg-[var(--surface-2)] transition-colors">
                  <td className="tabular font-bold">
                    <Link
                      to={`/sell/invoices?sale=${sale.id}`}
                      className="text-[var(--color-navy)] dark:text-blue-400 hover:underline"
                    >
                      {sale.doc_number}
                    </Link>
                  </td>
                  <td>
                    <span className="text-[11px] px-2 py-0.5 rounded-full font-semibold bg-[var(--surface-2)] text-[var(--text-secondary)] border border-[var(--border)]">
                      {sale.sale_mode}
                    </span>
                  </td>
                  <td className="text-[var(--text)] font-medium">
                    {sale.customer?.name ?? 'Walk-in Cash Customer'}
                  </td>
                  <td className="text-right tabular font-extrabold text-[var(--text)]">
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
