import type { TrainingModuleProgress } from '../../../lib/types'

/** Share of a module done: each lesson, each task and passing the knowledge check count as one step. */
export function progressPercent(p: TrainingModuleProgress): number {
  const total = p.lessons_total + p.tasks_total + 1
  const done = p.lessons_viewed + p.tasks_completed + (p.passed ? 1 : 0)
  return Math.round((done / total) * 100)
}

export function progressLabel(p: TrainingModuleProgress): 'Completed' | 'In progress' | 'Not started' {
  if (p.completed) return 'Completed'
  return p.started ? 'In progress' : 'Not started'
}

/** The link managers send to new staff; they sign in first and land here. */
export function trainingLink(): string {
  return `${window.location.origin}/training`
}
