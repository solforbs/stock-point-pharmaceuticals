import { useQuery } from '@tanstack/react-query'
import { Printer } from 'lucide-react'
import { Link } from 'react-router-dom'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { Modal } from '../../components/ui/Modal'
import { Button, Kbd } from '../../components/ui/primitives'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { apiGet } from '../../lib/api'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { dCmp, dIsPos, dSub, dSum, isValidDecimal } from '../../lib/decimal'
import type { Sale } from '../../lib/types'
import { ReceiptSlip } from '../sales/ReceiptSlip'
import { useCartStore } from './cartStore'

/**
 * The till slip shown after posting. The document for the customer's file is
 * the server-rendered cash sale invoice PDF (Part 16.6), downloadable here
 * once the sale has reached the server.
 */
export function ReceiptView({ onNewSale }: { onNewSale: () => void }) {
  const sale = useCartStore((s) => s.postedSale)
  const quote = useCartStore((s) => s.quote)
  const lines = useCartStore((s) => s.lines)
  const payments = useCartStore((s) => s.postedPayments)
  const cashTendered = useCartStore((s) => s.cashTendered)
  const customer = useCartStore((s) => s.customer)
  const terminalId = useCartStore((s) => s.terminalId)
  const offline = useCartStore((s) => s.postedOffline)
  const user = useCurrentUser().data

  // KRA signs the invoice just after posting: follow the sale until it has
  // the eTIMS invoice number and signature that belong on the receipt.
  const etims = useQuery({
    queryKey: ['sales', sale?.id, 'etims'],
    queryFn: () => apiGet<Sale>(`/api/sales/${sale?.id}`),
    enabled: !!sale && !offline,
    initialData: sale ?? undefined,
    refetchInterval: (q) => (q.state.data?.etims_status === 'PENDING' && q.state.dataUpdateCount < 10 ? 2000 : false),
  }).data

  if (!sale) return null

  const paid = dSum(payments.map((p) => p.amount))
  const cashLine = payments.find((p) => p.method === 'CASH')
  const change = cashLine && isValidDecimal(cashTendered) && dIsPos(dSub(cashTendered, sale.grand_total)) ? dSub(cashTendered, sale.grand_total) : null
  const nameByProduct = new Map(lines.map((l) => [l.productId, l]))

  return (
    <Modal open onClose={onNewSale} title={<span className="flex items-center gap-2">Receipt {sale.doc_number} {offline ? <StatusBadge status="PENDING_SYNC" tone="amber" label="Waiting to sync" /> : <StatusBadge status={sale.status} />}</span>} width={520}>
      <ReceiptSlip
        sale={sale}
        payments={payments}
        servedBy={user?.name}
        terminalId={terminalId}
        customerName={customer?.name}
        describeLine={(line) => {
          const cart = nameByProduct.get(line.product_id)
          const quoted = quote?.lines.find((q) => q.product_id === line.product_id && q.uom_id === line.uom_id)
          return { name: cart?.productName ?? quoted?.product_name, uom: cart?.uomCode ?? quoted?.uom_code }
        }}
        cashTendered={change ? cashTendered : null}
        change={change}
        etims={etims}
        offline={offline}
      />
      <div className="flex items-center gap-2 mt-4">
        <Button variant="primary" size="lg" className="flex-1" onClick={onNewSale} autoFocus>
          New sale <Kbd>Enter</Kbd>
        </Button>
        <Button size="lg" onClick={() => window.print()}>
          <Printer size={14} /> Print
        </Button>
        {!offline && <PdfDownloadButton size="md" url={`/api/sales/${sale.id}/pdf`} filename={sale.doc_number} label={payments.length > 0 && dCmp(paid, sale.grand_total) >= 0 ? 'Cash sale invoice' : 'Invoice PDF'} />}
        {!offline && (
          <Link to="/sell/invoices" className="text-xs text-blue-700 underline ml-2">
            Open in Invoices
          </Link>
        )}
      </div>
    </Modal>
  )
}
