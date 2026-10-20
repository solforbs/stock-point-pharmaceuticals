/**
 * Reads the first worksheet of an Excel .xlsx file into rows of text, the
 * same shape parseCsv returns, so an import screen can take either format.
 *
 * An .xlsx is a zip of XML parts; the browser's own DecompressionStream
 * inflates them, so no spreadsheet library is needed. Values come back as
 * Excel stores them (formulas as their last calculated result, numbers
 * without display formatting).
 */
export async function readXlsxRows(file: Blob): Promise<string[][]> {
  const entries = await unzip(await file.arrayBuffer())
  const text = async (path: string) => {
    const bytes = entries.get(path)
    return bytes ? new TextDecoder().decode(await bytes()) : null
  }

  const sheetPath = await firstSheetPath(text)
  const sheetXml = sheetPath ? await text(sheetPath) : null
  if (!sheetXml) throw new Error('This file has no worksheet; save it from Excel as .xlsx and try again.')

  const shared = sharedStrings(await text('xl/sharedStrings.xml'))
  const rows: string[][] = []
  for (const row of Array.from(parseXml(sheetXml).getElementsByTagNameNS('*', 'row'))) {
    const index = Number(row.getAttribute('r') ?? rows.length + 1) - 1
    const cells: string[] = []
    let next = 0
    for (const cell of Array.from(row.getElementsByTagNameNS('*', 'c'))) {
      const ref = cell.getAttribute('r')
      const column = ref ? columnIndex(ref) : next
      cells[column] = cellValue(cell, shared)
      next = column + 1
    }
    rows[index] = Array.from(cells, (v) => v ?? '')
  }

  return Array.from(rows, (r) => r ?? [])
}

/** The workbook's first sheet, by its order in the workbook rather than its file name. */
async function firstSheetPath(text: (path: string) => Promise<string | null>): Promise<string | null> {
  const workbook = await text('xl/workbook.xml')
  const rels = await text('xl/_rels/workbook.xml.rels')
  const sheet = workbook ? parseXml(workbook).getElementsByTagNameNS('*', 'sheet')[0] : undefined
  const relId = sheet?.getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id') ?? sheet?.getAttribute('r:id')
  if (!rels || !relId) return 'xl/worksheets/sheet1.xml'

  const target = Array.from(parseXml(rels).getElementsByTagNameNS('*', 'Relationship')).find((r) => r.getAttribute('Id') === relId)?.getAttribute('Target')
  if (!target) return 'xl/worksheets/sheet1.xml'
  return target.startsWith('/') ? target.slice(1) : `xl/${target}`
}

function sharedStrings(xml: string | null): string[] {
  if (!xml) return []
  return Array.from(parseXml(xml).getElementsByTagNameNS('*', 'si'), (si) => textOf(si))
}

/** Joins a rich-text string's runs, skipping phonetic (furigana) hints. */
function textOf(node: Element): string {
  return Array.from(node.getElementsByTagNameNS('*', 't'))
    .filter((t) => t.parentElement?.localName !== 'rPh')
    .map((t) => t.textContent ?? '')
    .join('')
}

function cellValue(cell: Element, shared: string[]): string {
  const type = cell.getAttribute('t')
  if (type === 'inlineStr') return textOf(cell)
  const raw = cell.getElementsByTagNameNS('*', 'v')[0]?.textContent ?? ''
  if (type === 's') return shared[Number(raw)] ?? ''
  if (type === 'b') return raw === '1' ? 'TRUE' : 'FALSE'
  return raw
}

/** "AB12" → 27 (zero-based column). */
function columnIndex(ref: string): number {
  let n = 0
  for (const ch of ref.toUpperCase()) {
    if (ch < 'A' || ch > 'Z') break
    n = n * 26 + (ch.charCodeAt(0) - 64)
  }
  return n - 1
}

function parseXml(xml: string): Document {
  return new DOMParser().parseFromString(xml, 'application/xml')
}

/** A zip's entries by path, each inflated only when read. */
async function unzip(buffer: ArrayBuffer): Promise<Map<string, () => Promise<Uint8Array>>> {
  const view = new DataView(buffer)
  const bytes = new Uint8Array(buffer)

  // The end-of-central-directory record sits in the last 64 KiB (it may trail a comment).
  let eocd = -1
  for (let i = buffer.byteLength - 22; i >= Math.max(0, buffer.byteLength - 65557); i--) {
    if (view.getUint32(i, true) === 0x06054b50) { eocd = i; break }
  }
  if (eocd < 0) throw new Error('This is not an .xlsx file. Save it from Excel as "Excel Workbook (.xlsx)" or as CSV.')

  const count = view.getUint16(eocd + 10, true)
  let offset = view.getUint32(eocd + 16, true)
  const entries = new Map<string, () => Promise<Uint8Array>>()

  for (let i = 0; i < count; i++) {
    if (view.getUint32(offset, true) !== 0x02014b50) break
    const method = view.getUint16(offset + 10, true)
    const compressedSize = view.getUint32(offset + 20, true)
    const nameLength = view.getUint16(offset + 28, true)
    const extraLength = view.getUint16(offset + 30, true)
    const commentLength = view.getUint16(offset + 32, true)
    const localHeader = view.getUint32(offset + 42, true)
    const name = new TextDecoder().decode(bytes.subarray(offset + 46, offset + 46 + nameLength))

    const dataStart = localHeader + 30 + view.getUint16(localHeader + 26, true) + view.getUint16(localHeader + 28, true)
    const data = bytes.subarray(dataStart, dataStart + compressedSize)
    entries.set(name, () => (method === 0 ? Promise.resolve(data) : inflate(data)))

    offset += 46 + nameLength + extraLength + commentLength
  }

  return entries
}

async function inflate(data: Uint8Array): Promise<Uint8Array> {
  const stream = new Blob([data as BlobPart]).stream().pipeThrough(new DecompressionStream('deflate-raw'))
  return new Uint8Array(await new Response(stream).arrayBuffer())
}
