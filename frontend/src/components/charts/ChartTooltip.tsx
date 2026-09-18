import type { ReactNode } from 'react'

export type TooltipRow = { label: string; value: string; color?: string }
export type TooltipState = { x: number; y: number; title: ReactNode; rows: TooltipRow[] } | null

/** Hover read-out, positioned inside the chart's relative wrapper and kept on-canvas. */
export function ChartTooltip({ tip, width }: { tip: TooltipState; width: number }) {
  if (!tip) return null
  const flip = tip.x > width - 180
  return (
    <div className="viz-tooltip" style={{ top: Math.max(0, tip.y - 12), left: flip ? undefined : tip.x + 14, right: flip ? width - tip.x + 14 : undefined }} role="status">
      <div className="font-semibold mb-0.5">{tip.title}</div>
      {tip.rows.map((row) => (
        <div key={row.label} className="flex items-center gap-2">
          {row.color && <span className="inline-block w-2 h-2 rounded-[2px]" style={{ background: row.color }} aria-hidden />}
          <span className="text-[var(--text-secondary)]">{row.label}</span>
          <span className="ml-auto pl-3 font-semibold tabular">{row.value}</span>
        </div>
      ))}
    </div>
  )
}
