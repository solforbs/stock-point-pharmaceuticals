import { useQuery } from '@tanstack/react-query'
import { useEffect } from 'react'
import { api } from '../lib/api'
import { useCurrentUser } from './useCurrentUser'

/**
 * The signed-in institution's logo as an object URL, or null when none is
 * uploaded. Fetched through the API client (session cookie) once per
 * logo_version, so a new upload shows up and an unchanged one is not
 * downloaded again.
 */
export function useOrganisationLogo(): string | null {
  const user = useCurrentUser()
  const version = user.data?.organisation?.logo_version ?? null

  const logo = useQuery({
    queryKey: ['organisation', 'logo', version],
    queryFn: async () => {
      const { data } = await api.get<Blob>('/api/organisation/logo', { responseType: 'blob' })
      return URL.createObjectURL(data)
    },
    enabled: !!version,
    staleTime: Infinity,
    gcTime: Infinity,
    retry: false,
  })

  return version ? (logo.data ?? null) : null
}

/** Puts the institution's logo in the browser tab while signed in; the platform icon otherwise. */
export function useOrganisationFavicon(): void {
  const logo = useOrganisationLogo()

  useEffect(() => {
    const link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
    if (!link) return
    const original = { href: link.getAttribute('href') ?? '/favicon.svg', type: link.getAttribute('type') }
    if (logo) {
      link.removeAttribute('type')
      link.href = logo
    }
    return () => {
      link.href = original.href
      if (original.type) link.setAttribute('type', original.type)
    }
  }, [logo])
}
