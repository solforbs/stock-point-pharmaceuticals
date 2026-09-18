/** RFC 4180-ish CSV: quoted fields, doubled quotes, commas inside quotes ("2,500"). Blank lines are dropped. */
export function parseCsv(text: string): string[][] {
  const rows: string[][] = []
  let row: string[] = []
  let cell = ''
  let quoted = false
  const src = text.replace(/^﻿/, '')
  for (let i = 0; i < src.length; i++) {
    const c = src[i]
    if (quoted) {
      if (c === '"' && src[i + 1] === '"') { cell += '"'; i++ }
      else if (c === '"') quoted = false
      else cell += c
    } else if (c === '"') quoted = true
    else if (c === ',') { row.push(cell); cell = '' }
    else if (c === '\n' || c === '\r') {
      if (c === '\r' && src[i + 1] === '\n') i++
      row.push(cell); cell = ''
      if (row.some((v) => v.trim() !== '')) rows.push(row)
      row = []
    } else cell += c
  }
  row.push(cell)
  if (row.some((v) => v.trim() !== '')) rows.push(row)
  return rows
}

/**
 * Header row → lower-cased keys, every body row → an object of trimmed cells.
 * `missing` lists required columns the header does not have.
 */
export function csvToObjects(text: string, required: readonly string[] = []): { keys: string[]; rows: Record<string, string>[]; missing: string[] } {
  const [header = [], ...body] = parseCsv(text)
  const keys = header.map((h) => h.trim().toLowerCase())
  const missing = required.filter((k) => !keys.includes(k))
  const rows = body.map((cells) => Object.fromEntries(keys.map((k, i) => [k, (cells[i] ?? '').trim()])))
  return { keys, rows, missing }
}

/** Saves text as a file in the browser (CSV templates, exports fetched through the API client). */
export function downloadBlob(content: BlobPart, fileName: string, type = 'text/csv') {
  const url = URL.createObjectURL(new Blob([content], { type }))
  const a = document.createElement('a')
  a.href = url
  a.download = fileName
  a.click()
  URL.revokeObjectURL(url)
}
