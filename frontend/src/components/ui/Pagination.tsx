import type { Paginated } from '../../lib/types'
import { Button } from './primitives'

export function Pagination({ page, onPage }: { page: Pick<Paginated<unknown>, 'current_page' | 'last_page' | 'total' | 'from' | 'to'> | undefined; onPage: (page: number) => void }) {
  if (!page || page.last_page <= 1) return null
  return (
    <div className="flex items-center justify-between px-5 py-3 text-xs text-slate-500 border-t border-slate-100">
      <span className="tabular font-medium">
        {page.from ?? 0}–{page.to ?? 0} of {page.total}
      </span>
      <div className="flex items-center gap-1.5">
        <Button size="sm" disabled={page.current_page <= 1} onClick={() => onPage(page.current_page - 1)}>
          Previous
        </Button>
        <span className="tabular px-1">
          {page.current_page} / {page.last_page}
        </span>
        <Button size="sm" disabled={page.current_page >= page.last_page} onClick={() => onPage(page.current_page + 1)}>
          Next
        </Button>
      </div>
    </div>
  )
}
