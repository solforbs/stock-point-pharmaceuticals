import { apiGet } from './api'
import type { XlsxLetterhead } from './xlsxWriter'

/** The institution's letterhead as the PDFs print it (GET /api/documents/letterhead). */
export type Letterhead = {
  organisation: string
  legal_name: string | null
  tagline: string | null
  kra_pin: string | null
  branch: string | null
  branch_code: string | null
  address: string | null
  postal_address: string | null
  phone: string | null
  email: string | null
  website: string | null
  /** A JPEG or PNG data URI, or null when no logo is set. */
  logo: string | null
}

/** The logo is drawn at most this tall and wide in the spreadsheet, keeping its shape. */
const LOGO_MAX_HEIGHT = 72
const LOGO_MAX_WIDTH = 240

/** The letterhead for an Excel download: the same lines and logo as the PDF letterhead. */
export async function xlsxLetterhead(): Promise<XlsxLetterhead> {
  const l = await apiGet<Letterhead>('/api/documents/letterhead')
  const address = [l.address, l.postal_address].filter(Boolean).join(', ')
  const contacts = [l.phone ? `Tel ${l.phone}` : null, l.email, l.website].filter(Boolean).join(' · ')
  const identity = [`KRA PIN ${l.kra_pin ?? 'not set'}`, l.branch ? `Branch ${l.branch}` : null].filter(Boolean).join(' · ')

  return {
    lines: [
      { text: l.legal_name || l.organisation, style: 'heading' },
      ...(l.tagline ? [{ text: l.tagline }] : []),
      ...(address ? [{ text: address }] : []),
      ...(contacts ? [{ text: contacts }] : []),
      { text: identity },
    ],
    logo: l.logo ? await logoFrom(l.logo).catch(() => null) : null,
  }
}

async function logoFrom(dataUri: string): Promise<XlsxLetterhead['logo']> {
  const match = /^data:image\/(jpeg|png);base64,(.+)$/.exec(dataUri)
  if (!match) return null
  const binary = atob(match[2])
  const bytes = new Uint8Array(binary.length)
  for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i)

  const image = new Image()
  image.src = dataUri
  await image.decode()
  const scale = Math.min(LOGO_MAX_HEIGHT / image.naturalHeight, LOGO_MAX_WIDTH / image.naturalWidth, 1)

  return { bytes, type: match[1] as 'jpeg' | 'png', width: Math.round(image.naturalWidth * scale), height: Math.round(image.naturalHeight * scale) }
}
