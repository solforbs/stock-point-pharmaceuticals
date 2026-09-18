import { create } from 'zustand'
import { getApiError } from './apiError'

export type ToastTone = 'error' | 'success' | 'info' | 'warning'
export type Toast = { id: number; tone: ToastTone; title: string; message?: string }

type ToastState = {
  toasts: Toast[]
  push: (toast: Omit<Toast, 'id'>) => void
  dismiss: (id: number) => void
}

let nextId = 1

export const useToastStore = create<ToastState>((set) => ({
  toasts: [],
  push: (toast) => {
    const id = nextId++
    set((s) => ({ toasts: [...s.toasts, { ...toast, id }] }))
    window.setTimeout(
      () => set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })),
      toast.tone === 'error' ? 8000 : 4000,
    )
  },
  dismiss: (id) => set((s) => ({ toasts: s.toasts.filter((t) => t.id !== id) })),
}))

export const toast = {
  error: (title: string, message?: string) => useToastStore.getState().push({ tone: 'error', title, message }),
  success: (title: string, message?: string) => useToastStore.getState().push({ tone: 'success', title, message }),
  info: (title: string, message?: string) => useToastStore.getState().push({ tone: 'info', title, message }),
  warning: (title: string, message?: string) => useToastStore.getState().push({ tone: 'warning', title, message }),
}

/** Shows error.code + message so the user can quote it back to support. */
export function toastApiError(err: unknown, fallbackTitle = 'Request failed') {
  const e = getApiError(err)
  if (e.code === 'UNAUTHENTICATED') return
  toast.error(e.code === 'UNKNOWN' ? fallbackTitle : e.code, e.message)
}
