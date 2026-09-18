import { useId, type KeyboardEvent, type Ref } from 'react'
import { dCmp, dMulInt, isValidDecimal } from '../../lib/decimal'
import { formatQty } from '../../lib/money'

type Props = {
  value: string
  onChange: (value: string) => void
  uomCode: string
  factorToBase: number
  baseUomCode: string
  isDiscrete?: boolean
  max?: string | null
  inputRef?: Ref<HTMLInputElement>
  onEnter?: () => void
  onEscape?: () => void
  className?: string
  disabled?: boolean
  compact?: boolean
}

/**
 * Part 1.5 — QuantityInput: UOM-aware, shows the base-unit conversion live
 * beneath the field ("3 BOX = 600 TAB"). Integer for discrete products;
 * warns (but does not block — the server decides) above free-to-sell.
 */
export function QuantityInput({
  value,
  onChange,
  uomCode,
  factorToBase,
  baseUomCode,
  isDiscrete = true,
  max,
  inputRef,
  onEnter,
  onEscape,
  className = '',
  disabled,
  compact,
}: Props) {
  const id = useId()
  const valid = isValidDecimal(value) && Number(value) > 0
  const integerViolation = isDiscrete && valid && !/^\d+$/.test(value.trim())
  const qtyBase = valid ? dMulInt(value, factorToBase) : null
  const overMax = qtyBase !== null && max !== null && max !== undefined && dCmp(qtyBase, max) > 0
  const showConversion = factorToBase !== 1 && qtyBase !== null

  function onKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'Enter') {
      e.preventDefault()
      onEnter?.()
    } else if (e.key === 'Escape') {
      e.preventDefault()
      onEscape?.()
    }
  }

  return (
    <div className={className}>
      <div className="flex items-center gap-1">
        <input
          id={id}
          ref={inputRef}
          type="text"
          inputMode={isDiscrete ? 'numeric' : 'decimal'}
          value={value}
          disabled={disabled}
          onChange={(e) => onChange(e.target.value)}
          onKeyDown={onKeyDown}
          onFocus={(e) => e.target.select()}
          aria-invalid={!valid || integerViolation || overMax}
          className={`ui-input tabular text-right ${compact ? 'h-7 text-[12px]' : ''} ${!valid || integerViolation ? '!border-[var(--status-red)]' : overMax ? '!border-[var(--status-amber)]' : ''}`}
          style={{ width: compact ? 64 : undefined }}
        />
        <span className="text-[11px] text-[var(--text-muted)] shrink-0">{uomCode}</span>
      </div>
      <div className="text-[10.5px] mt-0.5 leading-tight min-h-[13px]">
        {integerViolation ? (
          <span className="text-[var(--status-red)]">Whole units only</span>
        ) : overMax ? (
          <span className="text-[#b45309]">
            Only {formatQty(max)} {baseUomCode} free to sell
          </span>
        ) : showConversion ? (
          <span className="text-[var(--text-muted)] tabular">
            {formatQty(value)} {uomCode} = {formatQty(qtyBase)} {baseUomCode}
          </span>
        ) : null}
      </div>
    </div>
  )
}
