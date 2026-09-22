import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowLeft, BookOpen, CheckCircle2, ChevronDown, ChevronRight, ClipboardCheck, ExternalLink, MessageSquareText, PlayCircle, ShieldCheck, Star } from 'lucide-react'
import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { PdfDownloadButton } from '../../../components/PdfDownloadButton'
import { useTourStore } from '../../../components/tour/useTourStore'
import { Badge } from '../../../components/ui/Badge'
import { Page, PageHeader } from '../../../components/ui/PageHeader'
import { ErrorState, LoadingSkeleton } from '../../../components/ui/States'
import { Button, Field, Textarea } from '../../../components/ui/primitives'
import { useCurrentUser } from '../../../hooks/useCurrentUser'
import { apiGet, apiPost } from '../../../lib/api'
import { formatDate, formatDateTime } from '../../../lib/format'
import { toast, toastApiError } from '../../../lib/toast'
import type { TrainingLesson, TrainingModuleDetail, TrainingQuizResult, TrainingTask, TrainingTaskState } from '../../../lib/types'
import { progressPercent } from './trainingProgress'

type Section = 'lessons' | 'tasks' | 'quiz' | 'feedback'

/** One training module: lessons, practice tasks in the live system, the knowledge check and feedback. */
export default function TrainingModulePage() {
  const { moduleKey = '' } = useParams()
  const { data: me } = useCurrentUser()
  const [section, setSection] = useState<Section>('lessons')
  const detail = useQuery({
    queryKey: ['training', 'module', moduleKey],
    queryFn: () => apiGet<TrainingModuleDetail>(`/api/training/modules/${moduleKey}`),
  })

  if (detail.isLoading) return <Page><LoadingSkeleton rows={8} /></Page>
  if (detail.isError || !detail.data) return <Page><ErrorState error={detail.error} onRetry={() => detail.refetch()} /></Page>

  const { module, progress } = detail.data
  const pct = progressPercent(progress)
  const sections: { key: Section; label: string; icon: typeof BookOpen; count: string }[] = [
    { key: 'lessons', label: 'Lessons', icon: BookOpen, count: `${progress.lessons_viewed}/${progress.lessons_total}` },
    { key: 'tasks', label: 'Practice tasks', icon: ClipboardCheck, count: `${progress.tasks_completed}/${progress.tasks_total}` },
    { key: 'quiz', label: 'Knowledge check', icon: ShieldCheck, count: progress.best_score === null ? `${module.quiz.length} Qs` : `${progress.best_score}%` },
    { key: 'feedback', label: 'Feedback', icon: MessageSquareText, count: progress.feedback_rating ? `${progress.feedback_rating}/5` : '' },
  ]

  return (
    <Page>
      <Link to="/training" className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-blue-700">
        <ArrowLeft size={12} /> Training centre
      </Link>
      <PageHeader
        parent={module.audience}
        title={module.title}
        subtitle={module.summary}
        actions={
          progress.completed && me ? (
            <PdfDownloadButton url={`/api/training/certificates/${me.id}/${module.key}`} filename={`certificate-${module.key}`} label="Download certificate" size="md" />
          ) : null
        }
      />

      <div className="ui-card p-4">
        <div className="flex flex-wrap items-center justify-between gap-2 text-xs">
          <span className="font-semibold text-slate-900">
            {progress.completed ? `Completed ${progress.completed_at ? formatDate(progress.completed_at) : ''}` : `${pct}% done`}
          </span>
          <span className="text-slate-500">
            Read every lesson, do every practice task and score {detail.data.pass_mark}% or more on the knowledge check.
          </span>
        </div>
        <div className="mt-2 h-2 rounded-full bg-slate-100 overflow-hidden">
          <div className={`h-full ${progress.completed ? 'bg-emerald-500' : 'bg-blue-600'}`} style={{ width: `${pct}%` }} />
        </div>
      </div>

      <div className="flex gap-1 border-b border-slate-200 overflow-x-auto">
        {sections.map(({ key, label, icon: Icon, count }) => (
          <button
            key={key}
            type="button"
            onClick={() => setSection(key)}
            className={`px-4 py-2 text-xs font-semibold border-b-2 -mb-px transition-colors inline-flex items-center gap-1.5 whitespace-nowrap ${section === key ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
          >
            <Icon size={13} /> {label} {count && <span className="text-slate-400 font-medium">{count}</span>}
          </button>
        ))}
      </div>

      {section === 'lessons' && <Lessons detail={detail.data} />}
      {section === 'tasks' && <Tasks detail={detail.data} />}
      {section === 'quiz' && <Quiz detail={detail.data} />}
      {section === 'feedback' && <Feedback detail={detail.data} />}
    </Page>
  )
}

function useRefreshTraining() {
  const queryClient = useQueryClient()
  return () => queryClient.invalidateQueries({ queryKey: ['training'] })
}

function Lessons({ detail }: { detail: TrainingModuleDetail }) {
  const firstUnread = detail.module.lessons.find((l) => !detail.lessons_viewed[l.key])?.key ?? null
  const [open, setOpen] = useState<string | null>(firstUnread)

  return (
    <div className="space-y-2">
      {detail.module.lessons.map((lesson, index) => (
        <LessonCard
          key={lesson.key}
          moduleKey={detail.module.key}
          lesson={lesson}
          index={index}
          viewedAt={detail.lessons_viewed[lesson.key] ?? null}
          isOpen={open === lesson.key}
          onToggle={() => setOpen((cur) => (cur === lesson.key ? null : lesson.key))}
          onRead={() => {
            const next = detail.module.lessons[index + 1]
            setOpen(next ? next.key : null)
          }}
        />
      ))}
    </div>
  )
}

function LessonCard({ moduleKey, lesson, index, viewedAt, isOpen, onToggle, onRead }: {
  moduleKey: string
  lesson: TrainingLesson
  index: number
  viewedAt: string | null
  isOpen: boolean
  onToggle: () => void
  onRead: () => void
}) {
  const refresh = useRefreshTraining()
  const startTourById = useTourStore((s) => s.startTourById)
  const markRead = useMutation({
    mutationFn: () => apiPost(`/api/training/modules/${moduleKey}/lessons/${lesson.key}/viewed`),
    onSuccess: () => {
      refresh()
      onRead()
    },
    onError: (e) => toastApiError(e),
  })

  return (
    <div className="ui-card overflow-hidden">
      <button type="button" onClick={onToggle} className="w-full px-4 py-3 flex items-center gap-3 text-left hover:bg-slate-50/70">
        {viewedAt ? <CheckCircle2 size={16} className="text-emerald-600 shrink-0" /> : <span className="w-4 h-4 rounded-full border-2 border-slate-300 shrink-0" />}
        <div className="flex-1 min-w-0">
          <p className="text-sm font-semibold text-slate-900">{index + 1}. {lesson.title}</p>
          <p className="text-xs text-slate-500">{lesson.summary}</p>
        </div>
        {isOpen ? <ChevronDown size={16} className="text-slate-400" /> : <ChevronRight size={16} className="text-slate-400" />}
      </button>
      {isOpen && (
        <div className="px-4 pb-4 pt-1 space-y-3 border-t border-slate-100">
          {lesson.body.map((paragraph, i) => (
            <p key={i} className="text-sm text-slate-700 leading-relaxed">{paragraph}</p>
          ))}
          {lesson.steps.length > 0 && (
            <div>
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Step by step</p>
              <ol className="list-decimal pl-5 space-y-1 text-sm text-slate-700">
                {lesson.steps.map((step, i) => (<li key={i}>{step}</li>))}
              </ol>
            </div>
          )}
          {lesson.tips.map((tip, i) => (
            <p key={i} className="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">{tip}</p>
          ))}
          <div className="flex flex-wrap items-center gap-2 pt-1">
            {lesson.route && (
              <Link to={lesson.route} className="inline-flex items-center gap-1.5 h-8 px-3 text-xs font-semibold rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700">
                <ExternalLink size={12} /> Open this screen{lesson.route_label ? `: ${lesson.route_label}` : ''}
              </Link>
            )}
            {lesson.tour && (
              <Button size="sm" onClick={() => startTourById(lesson.tour!)}>
                <PlayCircle size={12} /> Start guided tour
              </Button>
            )}
            <span className="flex-1" />
            {viewedAt ? (
              <span className="text-xs text-emerald-700 font-medium">Read {formatDateTime(viewedAt)}</span>
            ) : (
              <Button size="sm" variant="primary" disabled={markRead.isPending} onClick={() => markRead.mutate()}>
                <CheckCircle2 size={12} /> Mark as read
              </Button>
            )}
          </div>
        </div>
      )}
    </div>
  )
}

function Tasks({ detail }: { detail: TrainingModuleDetail }) {
  return (
    <div className="space-y-3">
      <p className="text-xs text-slate-500">
        Practice tasks are done in the real system. Agree with your manager which store, customer and products to use, press "Start task", do it on the real screen, then come back and mark it done. Where the system can check your work it shows "Verified by the system".
      </p>
      {detail.module.tasks.map((task) => (
        <TaskCard key={task.key} moduleKey={detail.module.key} task={task} state={detail.tasks[task.key] ?? null} />
      ))}
    </div>
  )
}

function TaskCard({ moduleKey, task, state }: { moduleKey: string; task: TrainingTask; state: TrainingTaskState | null }) {
  const refresh = useRefreshTraining()
  const [note, setNote] = useState(state?.note ?? '')
  const [ticked, setTicked] = useState<Record<number, boolean>>({})

  const start = useMutation({
    mutationFn: () => apiPost<TrainingTaskState>(`/api/training/modules/${moduleKey}/tasks/${task.key}/start`),
    onSuccess: () => refresh(),
    onError: (e) => toastApiError(e),
  })
  const complete = useMutation({
    mutationFn: () => apiPost<TrainingTaskState>(`/api/training/modules/${moduleKey}/tasks/${task.key}/complete`, { note: note.trim() || null }),
    onSuccess: (result) => {
      if (result.is_verified) toast.success('Verified by the system', result.verification_detail ?? undefined)
      else toast.info('Marked as done', result.verification_detail ?? undefined)
      refresh()
    },
    onError: (e) => toastApiError(e),
  })

  const isDone = !!state?.completed_at
  const isStarted = !!state?.started_at && !isDone

  return (
    <div className="ui-card p-4 space-y-3">
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div className="min-w-0">
          <p className="text-sm font-semibold text-slate-900">{task.title}</p>
          <p className="text-xs text-slate-600 mt-0.5">{task.instructions}</p>
        </div>
        <div className="flex items-center gap-1.5">
          {task.auto_verified && <Badge color="info">Checked by the system</Badge>}
          {isDone && (state?.is_verified ? <Badge color="success">Verified by the system</Badge> : <Badge color="neutral">Self-confirmed</Badge>)}
          {isStarted && <Badge color="primary">In progress</Badge>}
        </div>
      </div>

      <div>
        <p className="text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">What success looks like</p>
        <ul className="space-y-1">
          {task.checklist.map((item, i) => (
            <li key={i}>
              <label className="flex items-start gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="checkbox" className="mt-1" checked={isDone || !!ticked[i]} disabled={isDone} onChange={(e) => setTicked((t) => ({ ...t, [i]: e.target.checked }))} />
                <span>{item}</span>
              </label>
            </li>
          ))}
        </ul>
      </div>

      {isDone && state?.verification_detail && <p className="text-xs text-slate-500">{state.verification_detail} · {formatDateTime(state.completed_at)}</p>}
      {isDone && state?.note && <p className="text-xs text-slate-700 bg-slate-50 rounded-lg px-3 py-2 whitespace-pre-line">Your note: {state.note}</p>}

      {isStarted && (
        <Field label="Note (optional)" hint="Anything that was unclear, or what you did differently.">
          <Textarea rows={2} value={note} maxLength={2000} onChange={(e) => setNote(e.target.value)} />
        </Field>
      )}

      <div className="flex flex-wrap items-center gap-2">
        {task.route && (
          <Link to={task.route} className="inline-flex items-center gap-1.5 h-8 px-3 text-xs font-semibold rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700">
            <ExternalLink size={12} /> Open this screen
          </Link>
        )}
        <span className="flex-1" />
        {!state?.started_at || isDone ? (
          <Button size="sm" variant={isDone ? 'secondary' : 'primary'} disabled={start.isPending} onClick={() => start.mutate()}>
            <PlayCircle size={12} /> {isDone ? 'Practise again' : 'Start task'}
          </Button>
        ) : (
          <Button size="sm" variant="success" disabled={complete.isPending} onClick={() => complete.mutate()}>
            <CheckCircle2 size={12} /> {complete.isPending ? 'Checking…' : 'Mark as done'}
          </Button>
        )}
      </div>
      {isStarted && state?.started_at && <p className="text-xs text-slate-400">Started {formatDateTime(state.started_at)}. Only records you create after this time count.</p>}
    </div>
  )
}

function Quiz({ detail }: { detail: TrainingModuleDetail }) {
  const refresh = useRefreshTraining()
  const [answers, setAnswers] = useState<Record<string, number>>({})
  const [result, setResult] = useState<TrainingQuizResult | null>(null)
  const questions = detail.module.quiz
  const lessonTitles = Object.fromEntries(detail.module.lessons.map((l) => [l.key, l.title]))
  const answered = Object.keys(answers).length

  const submit = useMutation({
    mutationFn: () => apiPost<TrainingQuizResult>(`/api/training/modules/${detail.module.key}/quiz`, { answers }),
    onSuccess: (r) => {
      setResult(r)
      refresh()
      window.scrollTo({ top: 0, behavior: 'smooth' })
    },
    onError: (e) => toastApiError(e),
  })

  const reset = () => {
    setAnswers({})
    setResult(null)
  }

  if (result) {
    const toRevisit = new Set(result.review.map((r) => r.question_id))
    return (
      <div className="space-y-3">
        <div className={`ui-card p-5 border ${result.attempt.passed ? 'border-emerald-200 bg-emerald-50/40' : 'border-amber-200 bg-amber-50/40'}`}>
          <p className="text-2xl font-extrabold text-slate-900">{result.attempt.score_pct}%</p>
          <p className="text-sm font-semibold text-slate-800">
            {result.attempt.passed ? 'Passed.' : `Not passed yet: the pass mark is ${result.pass_mark}%.`} {result.attempt.correct} of {result.attempt.total} correct. Your best score is {result.best_score}%.
          </p>
          {result.review.length > 0 && (
            <div className="mt-3">
              <p className="text-xs font-bold uppercase tracking-wide text-slate-500 mb-1">Questions to revisit</p>
              <ul className="space-y-1 text-sm text-slate-700">
                {questions.filter((q) => toRevisit.has(q.id)).map((q) => {
                  const lesson = result.review.find((r) => r.question_id === q.id)?.lesson
                  return (
                    <li key={q.id}>
                      {q.question}
                      {lesson && lessonTitles[lesson] && <span className="text-xs text-slate-500"> (see the lesson "{lessonTitles[lesson]}")</span>}
                    </li>
                  )
                })}
              </ul>
            </div>
          )}
          <Button className="mt-4" variant="primary" onClick={reset}>Take it again</Button>
        </div>
        <AttemptHistory detail={detail} />
      </div>
    )
  }

  return (
    <div className="space-y-3">
      <p className="text-xs text-slate-500">
        {questions.length} questions. Pass mark {detail.pass_mark}%. You can retake it as often as you like; your best score counts. Answers are checked by the server.
      </p>
      {questions.map((q, index) => (
        <fieldset key={q.id} className="ui-card p-4">
          <legend className="sr-only">Question {index + 1}</legend>
          <p className="text-sm font-semibold text-slate-900 mb-2">{index + 1}. {q.question}</p>
          <div className="space-y-1.5">
            {q.options.map((option, i) => (
              <label key={i} className={`flex items-start gap-2 text-sm rounded-lg border px-3 py-2 cursor-pointer ${answers[q.id] === i ? 'border-blue-300 bg-blue-50/60' : 'border-slate-200 hover:bg-slate-50'}`}>
                <input type="radio" className="mt-1" name={q.id} checked={answers[q.id] === i} onChange={() => setAnswers((a) => ({ ...a, [q.id]: i }))} />
                <span className="text-slate-700">{option}</span>
              </label>
            ))}
          </div>
        </fieldset>
      ))}
      <div className="flex items-center gap-3">
        <Button variant="primary" disabled={submit.isPending || answered === 0} onClick={() => submit.mutate()}>
          {submit.isPending ? 'Scoring…' : 'Submit answers'}
        </Button>
        {answered < questions.length && <span className="text-xs text-slate-500">{questions.length - answered} unanswered (they count as wrong)</span>}
      </div>
      <AttemptHistory detail={detail} />
    </div>
  )
}

function AttemptHistory({ detail }: { detail: TrainingModuleDetail }) {
  if (detail.attempts.length === 0) return null
  return (
    <div className="ui-card p-4">
      <p className="text-xs font-bold uppercase tracking-wide text-slate-500 mb-2">Your attempts</p>
      <ul className="space-y-1 text-xs">
        {detail.attempts.map((a) => (
          <li key={a.id} className="flex items-center gap-2">
            <span className={`font-semibold tabular ${a.passed ? 'text-emerald-700' : 'text-slate-700'}`}>{a.score_pct}%</span>
            <span className="text-slate-500">{a.correct}/{a.total}</span>
            <span className="text-slate-400">{formatDateTime(a.created_at)}</span>
            {a.passed && <Badge color="success">Passed</Badge>}
          </li>
        ))}
      </ul>
    </div>
  )
}

function Feedback({ detail }: { detail: TrainingModuleDetail }) {
  const refresh = useRefreshTraining()
  const [rating, setRating] = useState(detail.feedback?.rating ?? 0)
  const [comments, setComments] = useState(detail.feedback?.comments ?? '')

  const send = useMutation({
    mutationFn: () => apiPost(`/api/training/modules/${detail.module.key}/feedback`, { rating, comments: comments.trim() || null }),
    onSuccess: () => {
      toast.success('Thank you', 'Your manager reads this feedback to improve the training.')
      refresh()
    },
    onError: (e) => toastApiError(e),
  })

  return (
    <div className="ui-card p-5 space-y-4 max-w-2xl">
      <div>
        <p className="text-sm font-semibold text-slate-900">How useful was this module?</p>
        <div className="mt-2 flex gap-1" role="radiogroup" aria-label="Rating">
          {[1, 2, 3, 4, 5].map((n) => (
            <button key={n} type="button" role="radio" aria-checked={rating === n} aria-label={`${n} out of 5`} onClick={() => setRating(n)} className="p-1 cursor-pointer">
              <Star size={24} className={n <= rating ? 'fill-amber-400 text-amber-500' : 'text-slate-300'} />
            </button>
          ))}
        </div>
      </div>
      <Field label="What was unclear?" hint="Screens, steps or words that confused you, and anything you would like added.">
        <Textarea rows={4} value={comments} maxLength={3000} onChange={(e) => setComments(e.target.value)} />
      </Field>
      <div className="flex items-center gap-3">
        <Button variant="primary" disabled={rating === 0 || send.isPending} onClick={() => send.mutate()}>
          {detail.feedback ? 'Update feedback' : 'Send feedback'}
        </Button>
        {detail.feedback && <span className="text-xs text-slate-500">Sent {formatDateTime(detail.feedback.updated_at)}</span>}
      </div>
    </div>
  )
}
