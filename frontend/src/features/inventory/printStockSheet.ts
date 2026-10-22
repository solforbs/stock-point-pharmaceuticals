import type { StockStateRow } from '../../lib/types'

/** The sheet as it stands on screen, edits included: what the PDF is made from. */
export type StockSheetContent = { title: string; subtitle: string; header: string[]; rows: string[][] }

/**
 * The stock list as a printable cross-check sheet in a new tab: one line per
 * batch in stock, with blank Counted and Remarks columns. Every cell can be
 * edited before printing or downloading as PDF (a quantity corrected, a note
 * added); none of it is saved — a real difference is put right through a
 * stock count or adjustment.
 */
export function printStockSheet({ rows, title, filters, onDownloadPdf }: {
  rows: StockStateRow[]
  title: string
  filters: string
  /** Turns the sheet, as edited, into a PDF download. */
  onDownloadPdf: (content: StockSheetContent) => Promise<void>
}): boolean {
  const lines: { code: string; name: string; store: string; batch: string; expiry: string; qty: number }[] = []
  for (const row of rows) {
    const batches = row.batches.filter((b) => Number(b.on_hand) > 0)
    if (batches.length === 0 && Number(row.on_hand) > 0) {
      lines.push({ code: row.product_code, name: row.product_name, store: row.store_code, batch: '', expiry: row.nearest_expiry ?? '', qty: Number(row.on_hand) })
    }
    for (const b of batches) {
      lines.push({ code: row.product_code, name: row.product_name, store: row.store_code, batch: b.batch_number, expiry: b.expiry_date, qty: Number(b.on_hand) })
    }
  }
  lines.sort((a, b) => a.name.localeCompare(b.name) || a.store.localeCompare(b.store) || a.expiry.localeCompare(b.expiry))

  const tab = window.open('', '_blank')
  if (!tab) return false

  const body = lines.map((l, i) => `<tr><td class="n">${i + 1}</td><td>${esc(l.code)}</td><td>${esc(l.name)}</td><td>${esc(l.store)}</td><td>${esc(l.batch)}</td><td>${esc(formatDate(l.expiry))}</td><td class="q">${formatQty(l.qty)}</td><td class="q"></td><td></td></tr>`).join('')
  const printed = new Date().toLocaleString('en-GB', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })

  tab.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${esc(title)}</title><style>
    *{box-sizing:border-box} body{font:12px Arial,sans-serif;color:#111;margin:20px}
    .bar{position:sticky;top:0;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:10px 12px;margin-bottom:14px;display:flex;gap:12px;align-items:center}
    .bar button{font:600 13px Arial;background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 16px;cursor:pointer} .bar button:disabled{opacity:.6;cursor:wait} #status{font-weight:600}
    h1{font-size:17px;margin:0 0 2px} .meta{color:#555;margin-bottom:10px}
    table{width:100%;border-collapse:collapse} th,td{border:1px solid #999;padding:4px 6px;text-align:left;vertical-align:top}
    th{background:#f1f5f9;font-size:11px} .n,.q{text-align:right;white-space:nowrap} td.q:nth-child(8){min-width:70px} td:last-child{min-width:110px}
    [contenteditable]:focus{outline:2px solid #2563eb;background:#fffbeb}
    .sign{margin-top:28px;display:flex;gap:60px} .sign div{border-top:1px solid #111;padding-top:4px;min-width:200px}
    @media print{.bar{display:none} body{margin:0} thead{display:table-header-group} tr{page-break-inside:avoid}}
  </style></head><body>
    <div class="bar"><button onclick="window.print()">Print</button><button id="pdf" onclick="window.downloadStockPdf()">Download PDF</button><span>Click any cell (or the heading) to change it before printing or downloading. Changes are for this sheet only and are not saved.</span><span id="status"></span></div>
    <h1 contenteditable="true">${esc(title)}</h1>
    <div class="meta" contenteditable="true">${esc(filters)} · ${lines.length} batch lines · printed ${esc(printed)}</div>
    <table><thead><tr><th>#</th><th>Code</th><th>Product</th><th>Store</th><th>Batch</th><th>Expiry</th><th class="q">On hand</th><th class="q">Counted</th><th>Remarks</th></tr></thead>
    <tbody contenteditable="true">${body || '<tr><td colspan="9">No stock on hand for this selection.</td></tr>'}</tbody></table>
    <div class="sign"><div>Counted by</div><div>Checked by</div><div>Date</div></div>
  </body></html>`)
  tab.document.close()

  // The button lives in the sheet's tab; the request goes out through the app (session, CSRF) in this one.
  ;(tab as Window & { downloadStockPdf?: () => void }).downloadStockPdf = () => {
    const doc = tab.document
    const button = doc.getElementById('pdf') as HTMLButtonElement | null
    const status = doc.getElementById('status')
    const text = (el: Element | null) => (el?.textContent ?? '').replace(/\s+/g, ' ').trim()
    const content: StockSheetContent = {
      title: text(doc.querySelector('h1')) || title,
      subtitle: text(doc.querySelector('.meta')),
      header: Array.from(doc.querySelectorAll('thead th'), (th) => text(th)),
      rows: Array.from(doc.querySelectorAll('tbody tr'))
        .map((tr) => Array.from(tr.querySelectorAll('td'), (td) => text(td)))
        .filter((cells) => cells.length > 1),
    }
    if (button) button.disabled = true
    if (status) status.textContent = 'Preparing PDF…'
    onDownloadPdf(content)
      .then(() => { if (status) status.textContent = 'PDF downloaded.' })
      .catch(() => { if (status) status.textContent = 'The PDF could not be made. Try again.' })
      .finally(() => { if (button) button.disabled = false })
  }
  return true
}

function esc(text: string): string {
  return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
}

function formatQty(qty: number): string {
  return qty.toLocaleString('en-GB', { maximumFractionDigits: 4 })
}

function formatDate(iso: string): string {
  if (!iso) return ''
  const d = new Date(iso.slice(0, 10) + 'T00:00:00')
  return Number.isNaN(d.getTime()) ? iso : d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}
