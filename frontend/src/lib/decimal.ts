// Fixed-point decimal helpers on strings (scale 4, matching the API).
// Exact for add/sub/compare — used for tender sums and change — and good
// enough for the display-only estimates the cart shows before the server
// quote lands. Nothing here ever posts a computed money value.

const SCALE = 10000n

export function toUnits(value: string | number | null | undefined): bigint {
  if (value === null || value === undefined || value === '') return 0n
  let str = typeof value === 'number' ? value.toString() : value.trim().replace(/,/g, '')
  if (/e/i.test(str)) str = Number(str).toFixed(4)
  if (!/^-?\d*(\.\d*)?$/.test(str)) return 0n
  const negative = str.startsWith('-')
  const body = negative ? str.slice(1) : str
  const [intPart, fracPart = ''] = body.split('.')
  const frac = (fracPart + '0000').slice(0, 4)
  const units = BigInt(intPart || '0') * SCALE + BigInt(frac || '0')
  return negative ? -units : units
}

export function fromUnits(units: bigint): string {
  const negative = units < 0n
  const abs = negative ? -units : units
  const int = abs / SCALE
  const frac = (abs % SCALE).toString().padStart(4, '0')
  return `${negative ? '-' : ''}${int}.${frac}`
}

export const dAdd = (a: string, b: string) => fromUnits(toUnits(a) + toUnits(b))
export const dSub = (a: string, b: string) => fromUnits(toUnits(a) - toUnits(b))
export const dMul = (a: string, b: string) => fromUnits((toUnits(a) * toUnits(b)) / SCALE)
export const dNeg = (a: string) => fromUnits(-toUnits(a))
export const dSum = (values: string[]) => fromUnits(values.reduce((acc, v) => acc + toUnits(v), 0n))
export const dCmp = (a: string, b: string): -1 | 0 | 1 => {
  const x = toUnits(a)
  const y = toUnits(b)
  return x < y ? -1 : x > y ? 1 : 0
}
export const dEq = (a: string, b: string) => toUnits(a) === toUnits(b)
export const dIsZero = (a: string) => toUnits(a) === 0n
export const dIsNeg = (a: string) => toUnits(a) < 0n
export const dIsPos = (a: string) => toUnits(a) > 0n
export const dMax = (a: string, b: string) => (dCmp(a, b) >= 0 ? a : b)

/** Multiplies a quantity by an integer factor (UOM to base units). */
export const dMulInt = (a: string, factor: number) => fromUnits(toUnits(a) * BigInt(Math.trunc(factor)))

/** Truncating division, for display-only ratios such as a mark-up percentage. Dividing by zero gives "0.0000". */
export const dDiv = (a: string, b: string) => {
  const divisor = toUnits(b)
  return divisor === 0n ? fromUnits(0n) : fromUnits((toUnits(a) * SCALE) / divisor)
}

/** Rounds half-up to 2dp and returns "1234.57". */
export function dRound2(a: string): string {
  const units = toUnits(a)
  const negative = units < 0n
  const abs = negative ? -units : units
  const rounded = (abs + 50n) / 100n
  const int = rounded / 100n
  const frac = (rounded % 100n).toString().padStart(2, '0')
  return `${negative ? '-' : ''}${int}.${frac}`
}

export function isValidDecimal(input: string): boolean {
  return /^-?\d+(\.\d+)?$/.test(input.trim())
}
