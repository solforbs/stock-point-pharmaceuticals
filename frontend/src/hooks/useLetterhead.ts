import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../lib/api'
import type { Letterhead } from '../lib/letterhead'

const STORAGE_KEY = 'stockpoint.letterhead'

function recall(): Letterhead | undefined {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    return raw ? (JSON.parse(raw) as Letterhead) : undefined
  } catch {
    return undefined
  }
}

/**
 * The institution's letterhead from the company profile: name, KRA PIN,
 * address and contacts. The last copy is kept on the device so a till
 * selling offline still prints who it is. The logo is not kept; it is large
 * and the till slip does not print it.
 */
export function useLetterhead() {
  return useQuery({
    queryKey: ['documents', 'letterhead'],
    queryFn: async () => {
      const data = await apiGet<Letterhead>('/api/documents/letterhead')
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...data, logo: null }))
      } catch {
        // Storage full or blocked: the slip still prints from the live copy.
      }
      return data
    },
    placeholderData: recall,
    staleTime: 5 * 60 * 1000,
  })
}
