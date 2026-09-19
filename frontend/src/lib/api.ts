import axios, { type AxiosRequestConfig } from 'axios'
import { useBranchStore } from './branch'
import { queryClient } from './queryClient'

export type { ApiError } from './apiError'
export { getApiError } from './apiError'

// In dev, Vite proxies /api and /sanctum to the Laravel server so the
// browser sees everything as same-origin — required for Sanctum's SPA
// cookie auth. In production this still works as long as the SPA is
// served from a domain listed in SANCTUM_STATEFUL_DOMAINS.
export const api = axios.create({
  baseURL: '/',
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: { Accept: 'application/json' },
})

// Part 18.2 — branch scoping on every request.
api.interceptors.request.use((config) => {
  const branchId = useBranchStore.getState().activeBranchId
  if (branchId) config.headers.set('X-Branch-Id', branchId)
  return config
})

// A 401 anywhere means the session is gone: drop the cached user so
// ProtectedRoute sends the person back to /login.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (axios.isAxiosError(error) && error.response?.status === 401 && !String(error.config?.url).startsWith('/auth/')) {
      queryClient.setQueryData(['auth', 'user'], null)
    }
    return Promise.reject(error)
  },
)

export async function ensureCsrfCookie() {
  await api.get('/sanctum/csrf-cookie')
}

export async function apiGet<T>(url: string, params?: Record<string, unknown>): Promise<T> {
  const { data } = await api.get<T>(url, { params: cleanParams(params) })
  return data
}

export async function apiPost<T>(url: string, body?: unknown, config?: AxiosRequestConfig): Promise<T> {
  const { data } = await api.post<T>(url, body, config)
  return data
}

export async function apiDelete<T>(url: string): Promise<T> {
  await ensureCsrfCookie()
  const { data } = await api.delete<T>(url)
  return data
}

export async function apiPatch<T>(url: string, body?: unknown): Promise<T> {
  const { data } = await api.patch<T>(url, body)
  return data
}

export async function apiPut<T>(url: string, body?: unknown): Promise<T> {
  const { data } = await api.put<T>(url, body)
  return data
}

/** Drops empty filter values so the API sees only what was actually chosen. */
export function cleanParams(params?: Record<string, unknown>) {
  if (!params) return undefined
  const out: Record<string, unknown> = {}
  for (const [k, v] of Object.entries(params)) {
    if (v === '' || v === null || v === undefined) continue
    out[k] = v
  }
  return out
}

/** Part 21.1 — one key per attempt, reused on retry of the same attempt. */
export function newIdempotencyKey(): string {
  return crypto.randomUUID()
}

export function withIdempotency(key: string): AxiosRequestConfig {
  return { headers: { 'Idempotency-Key': key } }
}
