/** Clean axis ticks (0 / 1,000 / 2,000 …) covering 0..max. */
export function niceTicks(max: number, count = 4): number[] {
  if (!Number.isFinite(max) || max <= 0) return [0, 1]
  const rough = max / count
  const pow = 10 ** Math.floor(Math.log10(rough))
  const step = [1, 2, 2.5, 5, 10].map((m) => m * pow).find((s) => s >= rough) ?? rough
  const ticks: number[] = []
  for (let v = 0; v <= max + step * 0.001; v += step) ticks.push(Number(v.toFixed(6)))
  if (ticks[ticks.length - 1] < max) ticks.push(Number((ticks[ticks.length - 1] + step).toFixed(6)))
  return ticks
}

const compact = new Intl.NumberFormat('en-KE', { notation: 'compact', maximumFractionDigits: 1 })
const full = new Intl.NumberFormat('en-KE', { maximumFractionDigits: 2, minimumFractionDigits: 0 })

/** Axis and label figures: 1,284 / 12.9K / 4.2M. */
export function formatCompact(value: number): string {
  return Math.abs(value) < 10_000 ? full.format(value) : compact.format(value)
}

export function formatFull(value: number): string {
  return full.format(value)
}

/** The categorical slot colour for series index i (fixed order, never cycled past 8). */
export function seriesColor(i: number): string {
  return `var(--viz-${Math.min(i, 7) + 1})`
}

export type ValueFormatter = (value: number) => string
