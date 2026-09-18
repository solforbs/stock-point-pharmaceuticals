import { useState } from 'react'
import './charts.css'
import { ChartTooltip, type TooltipState } from './ChartTooltip'
import { Legend } from './Legend'
import { vBarPath } from './paths'
import { formatCompact, formatFull, niceTicks, seriesColor, type ValueFormatter } from './scale'
import { useChartWidth } from './useChartWidth'

export type StackSeries = { key: string; label: string; color?: string }
export type StackDatum = { key: string; label: string; values: Record<string, number> }

/**
 * Vertical columns, stacked when there is more than one series (sales per
 * day split by mode; AR by ageing bucket as a single series). Segments are
 * separated by a 2px surface gap; only the top of the column is rounded.
 */
export function StackedBarChart({
  data,
  series,
  height = 220,
  format = formatCompact,
  tooltipFormat = formatFull,
  ariaLabel,
}: {
  data: StackDatum[]
  series: StackSeries[]
  height?: number
  format?: ValueFormatter
  tooltipFormat?: ValueFormatter
  ariaLabel: string
}) {
  const { ref, width } = useChartWidth<HTMLDivElement>()
  const [tip, setTip] = useState<TooltipState>(null)
  const [active, setActive] = useState<string | null>(null)
  const colors = series.map((s, i) => s.color ?? seriesColor(i))
  const left = 48
  const right = 8
  const top = 10
  const bottom = 22
  const plotW = Math.max(40, width - left - right)
  const plotH = height - top - bottom
  const totals = data.map((d) => series.reduce((sum, s) => sum + Math.max(0, d.values[s.key] ?? 0), 0))
  const ticks = niceTicks(Math.max(0, ...totals), 4)
  const domain = ticks[ticks.length - 1] || 1
  const band = data.length ? plotW / data.length : plotW
  const barW = Math.max(3, Math.min(24, band * 0.68))
  const sy = (v: number) => (v / domain) * plotH
  const labelEvery = Math.max(1, Math.ceil(data.length / Math.max(1, Math.floor(plotW / 64))))

  return (
    <div ref={ref} className="viz-root relative w-full" onMouseLeave={() => { setTip(null); setActive(null) }}>
      <Legend items={series.map((s, i) => ({ key: s.key, label: s.label, color: colors[i] }))} />
      <svg width={width} height={height} role="img" aria-label={ariaLabel}>
        {ticks.map((t) => {
          const y = Math.round(top + plotH - sy(t)) + 0.5
          return (
            <g key={t}>
              <line x1={left} x2={width - right} y1={y} y2={y} stroke="var(--viz-grid)" strokeWidth={1} />
              <text x={left - 6} y={y + 3.5} textAnchor="end" fontSize={10} fill="var(--text-muted)">{format(t)}</text>
            </g>
          )
        })}
        {data.map((d, i) => {
          const x = left + i * band + (band - barW) / 2
          let base = top + plotH
          const visible = series.map((s, si) => ({ s, si, v: Math.max(0, d.values[s.key] ?? 0) })).filter((p) => p.v > 0)
          const dim = active !== null && active !== d.key
          return (
            <g
              key={d.key}
              opacity={dim ? 0.4 : 1}
              onMouseMove={(e) => {
                const box = (e.currentTarget.ownerSVGElement as SVGSVGElement).getBoundingClientRect()
                setActive(d.key)
                setTip({
                  x: e.clientX - box.left,
                  y: e.clientY - box.top,
                  title: d.label,
                  rows: [
                    ...series.map((s, si) => ({ label: s.label, value: tooltipFormat(d.values[s.key] ?? 0), color: colors[si] })),
                    ...(series.length > 1 ? [{ label: 'Total', value: tooltipFormat(totals[i]) }] : []),
                  ],
                })
              }}
            >
              <rect x={left + i * band} y={top} width={band} height={plotH} fill="transparent" />
              {visible.map((p, vi) => {
                const h = sy(p.v)
                const isTop = vi === visible.length - 1
                const gap = vi > 0 ? 2 : 0
                const y = base - h
                const drawH = Math.max(0, h - gap)
                base = y
                return isTop ? (
                  <path key={p.s.key} d={vBarPath(x, y, barW, drawH)} fill={colors[p.si]} />
                ) : (
                  <rect key={p.s.key} x={x} y={y} width={barW} height={drawH} fill={colors[p.si]} />
                )
              })}
              {i % labelEvery === 0 && (
                <text x={left + i * band + band / 2} y={height - 6} textAnchor="middle" fontSize={10} fill="var(--text-muted)">{d.label}</text>
              )}
            </g>
          )
        })}
      </svg>
      <ChartTooltip tip={tip} width={width} />
    </div>
  )
}
