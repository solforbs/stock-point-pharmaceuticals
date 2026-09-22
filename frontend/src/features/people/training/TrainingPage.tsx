import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, ClipboardCopy, GraduationCap, Link2, Star } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { PdfDownloadButton } from '../../../components/PdfDownloadButton'
import { Badge } from '../../../components/ui/Badge'
import { Page, PageHeader } from '../../../components/ui/PageHeader'
import { EmptyState, ErrorState, LoadingSkeleton } from '../../../components/ui/States'
import { Button, Select } from '../../../components/ui/primitives'
import { apiGet } from '../../../lib/api'
import { formatDate, formatDateTime } from '../../../lib/format'
import { toast } from '../../../lib/toast'
import type { Paginated, TrainingFeedbackRow, TrainingModuleCard, TrainingOverview, TrainingReport } from '../../../lib/types'
import { progressLabel, progressPercent, trainingLink } from './trainingProgress'

type Tab = 'mine' | 'staff' | 'feedback'

/**
 * Client item 17 — the training centre. Everyone sees their own modules;
 * managers (training.manage) also see everyone's progress, the feedback and
 * the link to send to new staff.
 */
export default function TrainingPage() {
  const [tab, setTab] = useState<Tab>('mine')
  const overview = useQuery({ queryKey: ['training', 'overview'], queryFn: () => apiGet<TrainingOverview>('/api/training') })
  const canManage = !!overview.data?.can_manage

  return (
    <Page>
      <PageHeader
        parent="People"
        title="Training centre"
        subtitle="Learn PharmaPoint one job at a time: read the lessons, practise in the real system, pass the knowledge check and tell us what was unclear."
      />

      {canManage && <TrainingLinkCard />}

      {canManage && (
        <div className="flex gap-1 border-b border-slate-200">
          {([['mine', 'My training'], ['staff', 'Staff progress'], ['feedback', 'Feedback']] as const).map(([key, label]) => (
            <button
              key={key}
              type="button"
              onClick={() => setTab(key)}
              className={`px-4 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors ${tab === key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
            >
              {label}
            </button>
          ))}
        </div>
      )}

      {overview.isLoading && <LoadingSkeleton rows={6} />}
      {overview.isError && <ErrorState error={overview.error} onRetry={() => overview.refetch()} />}
      {overview.data && tab === 'mine' && <MyModules overview={overview.data} />}
      {canManage && tab === 'staff' && <StaffProgress />}
      {canManage && tab === 'feedback' && <FeedbackList modules={overview.data?.modules ?? []} />}
    </Page>
  )
}

function TrainingLinkCard() {
  const link = trainingLink()

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(link)
      toast.success('Training link copied', 'Send it to new staff. They sign in with the account you created and land on their training.')
    } catch {
      toast.info('Copy the link', link)
    }
  }

  return (
    <div className="ui-card p-4 flex flex-col sm:flex-row sm:items-center gap-3">
      <span className="p-2 rounded-xl border border-blue-200 bg-blue-50 text-blue-700 self-start"><Link2 size={16} /></span>
      <div className="flex-1 min-w-0">
        <p className="text-xs font-semibold text-slate-900">Training link for new staff</p>
        <p className="text-xs text-slate-500">Create their account in Admin → Users & Roles first; they sign in and go straight to their modules.</p>
        <code className="mt-1 block text-xs font-mono text-slate-700 truncate">{link}</code>
      </div>
      <Button variant="primary" onClick={() => void copy()}>
        <ClipboardCopy size={14} /> Copy link
      </Button>
    </div>
  )
}

function MyModules({ overview }: { overview: TrainingOverview }) {
  const recommended = overview.modules.filter((m) => m.recommended)
  const others = overview.modules.filter((m) => !m.recommended)
  const done = recommended.filter((m) => m.progress.completed).length

  return (
    <div className="space-y-5">
      <div className="ui-card p-4 flex items-center gap-3">
        <span className="p-2 rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700"><GraduationCap size={18} /></span>
        <div>
          <p className="text-sm font-semibold text-slate-900">{done} of {recommended.length} modules for your role completed</p>
          <p className="text-xs text-slate-500">
            A module is complete when every lesson is read, every practice task is done and the knowledge check is passed ({overview.pass_mark}% or more; retakes allowed, your best score counts).
          </p>
        </div>
      </div>

      <section className="space-y-2">
        <h2 className="text-sm font-semibold text-slate-900">For your role</h2>
        <ModuleGrid modules={recommended} />
      </section>

      {others.length > 0 && (
        <section className="space-y-2">
          <h2 className="text-sm font-semibold text-slate-900">Other modules</h2>
          <p className="text-xs text-slate-500">Open to everyone. Some practice tasks need permissions your role may not have.</p>
          <ModuleGrid modules={others} />
        </section>
      )}
    </div>
  )
}

function ModuleGrid({ modules }: { modules: TrainingModuleCard[] }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
      {modules.map((module) => {
        const pct = progressPercent(module.progress)
        const status = progressLabel(module.progress)
        return (
          <Link key={module.key} to={`/training/${module.key}`} className="ui-card p-4 flex flex-col gap-3 hover:border-blue-300 transition-colors">
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0">
                <p className="text-sm font-semibold text-slate-900">{module.title}</p>
                <p className="text-xs text-slate-500">{module.audience}</p>
              </div>
              <Badge color={status === 'Completed' ? 'success' : status === 'In progress' ? 'primary' : 'neutral'}>{status}</Badge>
            </div>
            <p className="text-xs text-slate-600 leading-relaxed flex-1">{module.summary}</p>
            <div>
              <div className="h-1.5 rounded-full bg-slate-100 overflow-hidden">
                <div className={`h-full ${module.progress.completed ? 'bg-emerald-500' : 'bg-blue-600'}`} style={{ width: `${pct}%` }} />
              </div>
              <div className="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-500">
                <span>{module.progress.lessons_viewed}/{module.lesson_count} lessons</span>
                <span>{module.progress.tasks_completed}/{module.task_count} tasks</span>
                <span>{module.progress.best_score === null ? `${module.question_count} questions` : `Best ${module.progress.best_score}%`}</span>
                {module.progress.completed_at && <span className="text-emerald-700 font-medium">Completed {formatDate(module.progress.completed_at)}</span>}
              </div>
            </div>
          </Link>
        )
      })}
    </div>
  )
}

function StaffProgress() {
  const [includeInactive, setIncludeInactive] = useState(false)
  const report = useQuery({
    queryKey: ['training', 'report', includeInactive],
    queryFn: () => apiGet<TrainingReport>('/api/training/report', { include_inactive: includeInactive ? 1 : undefined }),
  })

  if (report.isLoading) return <LoadingSkeleton rows={8} />
  if (report.isError) return <ErrorState error={report.error} onRetry={() => report.refetch()} />
  if (!report.data) return null
  const { modules, staff, pass_mark } = report.data

  return (
    <div className="ui-card overflow-hidden">
      <div className="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center justify-between gap-2">
        <p className="text-xs text-slate-500">
          Each cell: share of the module done, best knowledge-check score (pass {pass_mark}%) and, once complete, the date and a certificate.
        </p>
        <label className="flex items-center gap-2 text-xs text-slate-600 font-medium cursor-pointer">
          <input type="checkbox" checked={includeInactive} onChange={(e) => setIncludeInactive(e.target.checked)} />
          Include inactive users
        </label>
      </div>
      {staff.length === 0 ? (
        <EmptyState title="No staff yet" hint="Create user accounts in Admin → Users & Roles, then send them the training link." />
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-xs">
            <thead className="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
              <tr>
                <th className="text-left px-4 py-2 font-bold sticky left-0 bg-slate-50">Staff</th>
                {modules.map((m) => (
                  <th key={m.key} className="text-left px-3 py-2 font-bold min-w-[150px]" title={m.audience}>{m.title}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {staff.map((person) => (
                <tr key={person.id} className="border-t border-slate-100 align-top">
                  <td className="px-4 py-2.5 sticky left-0 bg-white">
                    <p className="font-semibold text-slate-900">{person.name}{!person.is_active && <span className="ml-1 text-slate-400 font-normal">(inactive)</span>}</p>
                    <p className="text-slate-500">{person.roles.join(', ') || 'No role'}</p>
                  </td>
                  {modules.map((m) => {
                    const p = person.modules[m.key]
                    return (
                      <td key={m.key} className="px-3 py-2.5">
                        {p.completed ? (
                          <div className="space-y-1">
                            <p className="flex items-center gap-1 font-semibold text-emerald-700"><CheckCircle2 size={12} /> {p.completed_at ? formatDate(p.completed_at) : 'Completed'}</p>
                            <p className="text-slate-500">Best {p.best_score}% · {p.tasks_verified}/{p.tasks_total} verified</p>
                            <PdfDownloadButton url={`/api/training/certificates/${person.id}/${m.key}`} filename={`certificate-${person.username ?? person.id}-${m.key}`} label="Certificate" />
                          </div>
                        ) : p.started ? (
                          <div>
                            <p className="font-semibold text-blue-700">{progressPercent(p)}%</p>
                            <p className="text-slate-500">{p.best_score === null ? 'No quiz yet' : `Best ${p.best_score}%${p.passed ? '' : ' (not passed)'}`}</p>
                          </div>
                        ) : (
                          <span className="text-slate-400">Not started</span>
                        )}
                      </td>
                    )
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

function FeedbackList({ modules }: { modules: TrainingModuleCard[] }) {
  const [moduleKey, setModuleKey] = useState('')
  const feedback = useQuery({
    queryKey: ['training', 'feedback', moduleKey],
    queryFn: () => apiGet<Paginated<TrainingFeedbackRow>>('/api/training/feedback', { module: moduleKey || undefined, per_page: 100 }),
  })
  const rows = feedback.data?.data ?? []

  return (
    <div className="ui-card overflow-hidden">
      <div className="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center gap-2">
        <Select value={moduleKey} onChange={(e) => setModuleKey(e.target.value)} className="w-auto">
          <option value="">All modules</option>
          {modules.map((m) => (<option key={m.key} value={m.key}>{m.title}</option>))}
        </Select>
      </div>
      {feedback.isLoading ? (
        <LoadingSkeleton rows={5} />
      ) : feedback.isError ? (
        <ErrorState error={feedback.error} onRetry={() => feedback.refetch()} compact />
      ) : rows.length === 0 ? (
        <EmptyState title="No feedback yet" hint="Trainees rate each module and say what was unclear when they finish it." />
      ) : (
        <ul className="divide-y divide-slate-100">
          {rows.map((row) => (
            <li key={row.id} className="px-4 py-3">
              <div className="flex flex-wrap items-center gap-2 text-xs">
                <span className="font-semibold text-slate-900">{row.user?.name ?? 'Unknown'}</span>
                <span className="text-slate-400">·</span>
                <span className="text-slate-600">{row.module_title}</span>
                <span className="flex items-center gap-0.5 text-amber-500" aria-label={`${row.rating} out of 5`}>
                  {Array.from({ length: 5 }).map((_, i) => (
                    <Star key={i} size={12} className={i < row.rating ? 'fill-amber-400' : 'text-slate-300'} />
                  ))}
                </span>
                <span className="ml-auto text-slate-400">{formatDateTime(row.updated_at)}</span>
              </div>
              {row.comments && <p className="mt-1 text-xs text-slate-700 whitespace-pre-line">{row.comments}</p>}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
