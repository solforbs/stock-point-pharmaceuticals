import { FileDown } from 'lucide-react'
import { useState } from 'react'
import { api } from '../lib/api'
import { downloadBlob } from '../lib/csv'
import { toastApiError } from '../lib/toast'
import { Button } from './ui/primitives'

/**
 * Part 16.6 — downloads a document as a PDF rendered by the server from the
 * posted record, so what is handed to a customer always matches the books.
 */
export function PdfDownloadButton({
  url,
  filename,
  label = 'PDF',
  size = 'sm',
}: {
  url: string
  filename: string
  label?: string
  size?: 'sm' | 'md'
}) {
  const [busy, setBusy] = useState(false)

  const download = async () => {
    setBusy(true)
    try {
      const { data } = await api.get<Blob>(url, { responseType: 'blob' })
      downloadBlob(data, `${filename}.pdf`, 'application/pdf')
    } catch (e) {
      toastApiError(e, 'Could not produce the PDF')
    } finally {
      setBusy(false)
    }
  }

  return (
    <Button size={size} disabled={busy} onClick={(e) => { e.stopPropagation(); void download() }}>
      <FileDown size={12} /> {busy ? 'Preparing…' : label}
    </Button>
  )
}
