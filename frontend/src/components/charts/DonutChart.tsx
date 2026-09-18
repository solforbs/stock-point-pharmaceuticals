import { useState } from 'react'
import './charts.css'
import { ChartTooltip, type TooltipState } from './ChartTooltip'
import { formatCompact, formatFull, seriesColor, type ValueFormatter } from './scale'
import { useChartWidth } from './useChartWidth'

export type DonutSlice = { key: string; label: string; value: number; color?: string }

/**
 * Part-to-whole for a handful of slices (expiry tiers). The list beside
 * the ring names every slice with its value and share, so identity never
 * rests on colour; the centre carries the total.
 */
export function DonutChart({
  data,
  centerLabel = 'Total',
  format = formatCompact,
  tooltipFormat = formatFull,
  ariaLabel,
}: {
  data: DonutSlice[]
  centerLabel?: string
  format?: ValueFormatter
  tooltipFormat?: ValueFormatter
  ariaLabel: string
}) {
  const { ref, width } = useChartWidth<HTMLDivElement>()
  const [tip, setTip] = useState<TooltipState>(null)
  const [active, setActive] = useState<string | null>(null)
  const size = 168
  const r = size / 2 - 4
  const inner = r - 22
  const cx = size / 2
  const cy = size / 2
  const total = data.reduce((s, d) => s + Math.max(0, d.value), 0)
  const colors = data.map((d, i) => d.color ?? seriesColor(i))
  const gapAngle = total > 0 && data.filter((d) => d.value > 0).length > 1 ? 2 / r : 0

  const arcs: { d: DonutSlice; color: string; start: number; end: number; sweep: number }[] = []
  let angle = -Math.PI / 2
  data.forEach((d, i) => {
    const sweep = total > 0 ? (Math.max(0, d.value) / total) * Math.PI * 2 : 0
    arcs.push({ d, color: colors[i], start: angle + gapAngle / 2, end: angle + sweep - gapAngle / 2, sweep })
    angle += sweep
  })

  const point = (radius: number, a: number) => `${cx + radius * Math.cos(a)},${cy + radius * Math.sin(a)}`
  const arcPath = (start: number, end: number) => {
    if (end - start >= Math.PI * 2 - 0.0001) {
      return `M${cx},${cy - r}A${r},${r} 0 1 1 ${cx - 0.01},${cy - r}Z M${cx},${cy - inner}A${inner},${inner} 0 1 0 ${cx + 0.01},${cy - inner}Z`
    }
    const large = end - start > Math.PI ? 1 : 0
    return `M${point(r, start)}A${r},${r} 0 ${large} 1 ${point(r, end)}L${point(inner, end)}A${inner},${inner} 0 ${large} 0 ${point(inner, start)}Z`
  }
  const share = (value: number) => (total > 0 ? `${((value / total) * 100).toFixed(1)}%` : '—')

  return (
    <div ref={ref} className="viz-root relative w-full flex flex-wrap items-center gap-4" onMouseLeave={() => { setTip(null); setActive(null) }}>
      <svg width={size} height={size} role="img" aria-label={ariaLabel} className="shrink-0">
        {total === 0 && <circle cx={cx} cy={cy} r={(r + inner) / 2} fill="none" stroke="var(--viz-grid)" strokeWidth={r - inner} />}
        {arcs.map((a) =>
          a.sweep > 0 ? (
            <path
              key={a.d.key}
              d={arcPath(a.start, Math.max(a.start + 0.001, a.end))}
              fill={a.color}
              fillRule="evenodd"
              opacity={active && active !== a.d.key ? 0.35 : 1}
              onMouseMove={(e) => {
                const box = (e.currentTarget.ownerSVGElement as SVGSVGElement).getBoundingClientRect()
                setActive(a.d.key)
                setTip({ x: e.clientX - box.left, y: e.clientY - box.top, title: a.d.label, rows: [{ label: 'Value', value: tooltipFormat(a.d.value), color: a.color }, { label: 'Share', value: share(a.d.value) }] })
              }}
            />
          ) : null,
        )}
        <text x={cx} y={cy - 2} textAnchor="middle" fontSize={16} fontWeight={700} fill="var(--text)">{format(total)}</text>
        <text x={cx} y={cy + 14} textAnchor="middle" fontSize={10} fill="var(--text-muted)">{centerLabel}</text>
      </svg>
      <ul className="flex-1 min-w-[160px] space-y-1.5 text-[11.5px]">
        {arcs.map((a) => (
          <li key={a.d.key} className="flex items-center gap-2" onMouseEnter={() => setActive(a.d.key)} onMouseLeave={() => setActive(null)}>
            <span className="inline-block w-2.5 h-2.5 rounded-[3px] shrink-0" style={{ background: a.color }} aria-hidden />
            <span className="text-[var(--text-secondary)] flex-1">{a.d.label}</span>
            <span className="font-semibold tabular">{format(a.d.value)}</span>
            <span className="text-[var(--text-muted)] tabular w-12 text-right">{share(a.d.value)}</span>
          </li>
        ))}
      </ul>
      <ChartTooltip tip={tip} width={width} />
    </div>
  )
}
