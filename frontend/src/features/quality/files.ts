import { api } from '../../lib/api'
import { toastApiError } from '../../lib/toast'

/**
 * Downloads a stored file through the API client (not a bare link) so the
 * session and X-Branch-Id travel with the request. The server's
 * Content-Disposition name wins over the fallback.
 */
export async function downloadFile(url: string, fallbackName: string) {
  try {
    const res = await api.get(url, { responseType: 'blob' })
    const disposition = String(res.headers['content-disposition'] ?? '')
    const match = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(disposition)
    const name = match ? decodeURIComponent(match[1]) : fallbackName
    const href = URL.createObjectURL(res.data as Blob)
    const a = document.createElement('a')
    a.href = href
    a.download = name
    a.click()
    URL.revokeObjectURL(href)
  } catch (e) {
    toastApiError(e, 'Download failed')
  }
}

/**
 * Builds multipart form data. null/undefined are left out; an empty string is
 * sent (Laravel turns it into null, which clears the field); booleans go as 1/0.
 */
export function toFormData(values: Record<string, string | number | boolean | File | null | undefined>): FormData {
  const fd = new FormData()
  for (const [key, value] of Object.entries(values)) {
    if (value === null || value === undefined) continue
    if (typeof value === 'boolean') fd.append(key, value ? '1' : '0')
    else if (value instanceof File) fd.append(key, value)
    else fd.append(key, String(value))
  }
  return fd
}

export const MAX_UPLOAD_BYTES = 10 * 1024 * 1024
