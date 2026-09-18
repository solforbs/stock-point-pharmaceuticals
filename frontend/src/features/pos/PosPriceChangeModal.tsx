import { Modal } from '../../components/ui/Modal'
import { Button } from '../../components/ui/primitives'
import { formatMoney } from '../../lib/money'
import type { PriceChange } from './cartStore'

export interface PosPriceChangeModalProps {
  priceChange: PriceChange | null
  onClose: () => void
  onAccept: () => void
}

export function PosPriceChangeModal({
  priceChange,
  onClose,
  onAccept,
}: PosPriceChangeModalProps) {
  if (!priceChange) return null

  return (
    <Modal
      open={priceChange !== null}
      onClose={onClose}
      title="Catalog Prices Changed During Quoting"
      footer={
        <>
          <Button onClick={onClose}>Review Cart</Button>
          <Button variant="primary" onClick={onAccept}>
            Accept New Prices
          </Button>
        </>
      }
    >
      <div className="space-y-3">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 tabular">
          <div className="ui-card p-3">
            <span className="ui-label">Previous Total</span>
            <div className="text-[18px] font-bold line-through text-[var(--text-muted)]">
              {formatMoney(priceChange.old_total)}
            </div>
          </div>
          <div className="ui-card p-3">
            <span className="ui-label">Updated Total</span>
            <div className="text-[18px] font-extrabold text-[var(--text)]">
              {formatMoney(priceChange.new_total)}
            </div>
          </div>
        </div>

        {priceChange.changed_lines.length > 0 && (
          <table className="ui-table">
            <thead>
              <tr>
                <th>Line</th>
                <th>Field</th>
                <th className="text-right">Previous</th>
                <th className="text-right">Updated</th>
              </tr>
            </thead>
            <tbody>
              {priceChange.changed_lines.map((c, i) => (
                <tr key={i}>
                  <td>{c.line_ref}</td>
                  <td>{c.field}</td>
                  <td className="text-right tabular">{c.old}</td>
                  <td className="text-right tabular font-bold">{c.new ?? '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
        <p className="text-[12px] text-[var(--text-secondary)]">
          Accepting updates the counter cart with active catalog pricing.
        </p>
      </div>
    </Modal>
  )
}
