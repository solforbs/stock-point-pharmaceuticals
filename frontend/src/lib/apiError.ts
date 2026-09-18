import { isAxiosError } from 'axios'

// Part 21.15 — every domain error is { error: { code, message, details } };
// Laravel validation is { message, errors }. This normalises both so the
// screens act on the code rather than parse prose.
export type ApiError = {
  error?: { code: string; message: string; details?: unknown }
  message?: string
  errors?: Record<string, string[]>
}

export type NormalisedApiError = {
  status: number
  code: string
  message: string
  details: Record<string, unknown>
  errors: Record<string, string[]>
}

export function getApiError(err: unknown): NormalisedApiError {
  if (isAxiosError<ApiError>(err)) {
    const status = err.response?.status ?? 0
    const body = err.response?.data
    if (body?.error?.code) {
      return {
        status,
        code: body.error.code,
        message: body.error.message,
        details: (body.error.details as Record<string, unknown> | undefined) ?? {},
        errors: {},
      }
    }
    if (body?.errors) {
      const first = Object.values(body.errors)[0]?.[0]
      return { status, code: 'VALIDATION', message: first ?? body.message ?? 'Validation failed.', details: {}, errors: body.errors }
    }
    if (status === 401) return { status, code: 'UNAUTHENTICATED', message: 'Your session has ended. Sign in again.', details: {}, errors: {} }
    if (status === 403) return { status, code: 'FORBIDDEN', message: body?.message ?? 'You do not have permission for that.', details: {}, errors: {} }
    if (status === 404) return { status, code: 'NOT_FOUND', message: body?.message ?? 'Not found.', details: {}, errors: {} }
    if (!err.response) return { status: 0, code: 'NETWORK', message: 'Cannot reach the server.', details: {}, errors: {} }
    return { status, code: `HTTP_${status}`, message: body?.message ?? err.message, details: {}, errors: {} }
  }
  return { status: 0, code: 'UNKNOWN', message: err instanceof Error ? err.message : String(err), details: {}, errors: {} }
}
