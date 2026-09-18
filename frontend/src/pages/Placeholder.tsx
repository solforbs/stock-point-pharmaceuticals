import { useParams } from 'react-router-dom'
import { NAV_ITEMS } from '../lib/navigation'

function titleCase(slug: string) {
  return slug.replace(/-/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

export default function Placeholder() {
  const { moduleKey = '', sectionKey } = useParams()

  const module = NAV_ITEMS.find((m) => m.key === moduleKey)
  const section = module?.children?.find((c) => c.key === sectionKey)

  const title = section?.label ?? module?.label ?? titleCase(moduleKey)
  const parent = section ? module?.label : undefined

  return (
    <div className="p-7">
      {parent && <div className="text-[11px] text-[var(--text-muted)] mb-1">{parent}</div>}
      <h1 className="text-[19px] font-extrabold text-[var(--text)]">{title}</h1>
      <p className="text-[11px] text-[var(--text-muted)] mt-2 max-w-md">
        This workspace is on the build roadmap but not implemented yet — the sidebar link is
        live so navigation can be reviewed end to end before each module's screens are built.
      </p>
    </div>
  )
}
