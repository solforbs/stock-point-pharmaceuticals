import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertCircle, CheckCircle2, Plus, RotateCcw, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { ConfirmDialog, Modal } from '../../components/ui/Modal'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { useChartOfAccounts } from '../../lib/hooks'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { JournalEntry, Paginated } from '../../lib/types'

export default function JournalsPage() {
  const queryClient = useQueryClient()
  const canPost = usePermission('journal.post')
  const canReverse = usePermission('journal.reverse')

  const [filters, setFilters] = useState({ q: '', source_doc_type: '', from: '', to: '' })
  const [page, setPage] = useState(1)
  const [sort, setSort] = useState<{ key: string; dir: 'asc' | 'desc' } | null>(null)
  const [isCreating, setIsCreating] = useState(false)
  const [reversingJournal, setReversingJournal] = useState<JournalEntry | null>(null)

  const list = useQuery({
    queryKey: ['finance', 'journals', filters, page, sort?.key, sort?.dir],
    queryFn: () => apiGet<Paginated<JournalEntry>>('/api/finance/journals', { ...filters, page, per_page: 50, sort_by: sort?.key, sort_dir: sort?.dir }),
    placeholderData: (prev) => prev,
  })

  const reverseMutation = useMutation({
    mutationFn: ({ id, reason }: { id: string; reason: string }) =>
      apiPost<JournalEntry>(`/api/finance/journals/${id}/reverse`, { reason }),
    onSuccess: (reversal) => {
      toast.success(`Reversal journal ${reversal.doc_number} posted successfully`)
      queryClient.invalidateQueries({ queryKey: ['finance', 'journals'] })
      queryClient.invalidateQueries({ queryKey: ['finance', 'chart-of-accounts'] })
      setReversingJournal(null)
    },
    onError: (err) => {
      const apiErr = getApiError(err)
      toast.error(apiErr.message || 'Failed to reverse journal')
    },
  })

  const columns: Column<JournalEntry>[] = [
    {
      key: 'doc',
      header: 'Journal',
      sortKey: 'doc_number',
      render: (j) => (
        <span className="font-semibold tabular flex items-center gap-1.5">
          {j.doc_number}
          {j.source_doc_type === 'journal_reversal' && <StatusBadge status="REVERSAL" tone="amber" />}
        </span>
      ),
    },
    { key: 'date', header: 'Entry date', sortKey: 'entry_date', render: (j) => formatDate(j.entry_date) },
    {
      key: 'source',
      header: 'Source',
      sortable: false,
      render: (j) => (
        <span>
          {titleCase(j.source_doc_type ?? 'Manual')}{' '}
          <span className="text-slate-400 font-mono text-xs tabular">({j.source_doc_id?.slice(0, 8)})</span>
        </span>
      ),
    },
    { key: 'narration', header: 'Narration', sortable: false, render: (j) => j.narration ?? '—' },
    { key: 'lines', header: 'Lines', sortable: false, align: 'right', render: (j) => <span className="tabular">{j.lines.length}</span> },
    { key: 'debit', header: 'Debit', sortable: false, align: 'right', render: (j) => <MoneyCell value={j.lines.reduce((s, l) => s + Number(l.debit_amount), 0).toFixed(4)} /> },
    { key: 'posted', header: 'Posted', sortKey: 'posted_at', render: (j) => formatDateTime(j.posted_at) },
  ]

  return (
    <Page>
      <PageHeader
        parent="Finance"
        title="Journals"
        subtitle="Every posted journal, append-only; expand a row for its lines or post manual entries."
        actions={
          canPost ? (
            <Button variant="primary" onClick={() => setIsCreating(true)}>
              <Plus size={14} className="mr-1 inline" /> New Journal Entry
            </Button>
          ) : null
        }
      />
      <FilterBar>
        <Field label="Search">
          <Input
            placeholder="JE-0001 or narration…"
            value={filters.q}
            onChange={(e) => setFilters({ ...filters, q: e.target.value })}
          />
        </Field>
        <Field label="Source type">
          <Input
            placeholder="manual, sale, payment…"
            value={filters.source_doc_type}
            onChange={(e) => setFilters({ ...filters, source_doc_type: e.target.value })}
          />
        </Field>
        <Field label="From">
          <Input type="date" value={filters.from} onChange={(e) => setFilters({ ...filters, from: e.target.value })} />
        </Field>
        <Field label="To">
          <Input type="date" value={filters.to} onChange={(e) => setFilters({ ...filters, to: e.target.value })} />
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data?.data}
          rowKey={(j) => j.id}
          sort={sort}
          onSortChange={setSort}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          emptyTitle="No journals"
          renderExpanded={(j) => (
            <div className="space-y-3">
              <div className="flex items-center justify-between">
                <div className="text-xs text-slate-500">
                  Document Reference: <span className="font-semibold text-slate-900 font-mono">{j.doc_number}</span>
                  {j.reverses_journal_id && <span className="ml-2 text-amber-600 font-medium">(Reverses another journal)</span>}
                </div>
                {canReverse && j.source_doc_type !== 'journal_reversal' && !j.reverses_journal_id && (
                  <Button
                    size="sm"
                    variant="danger"
                    onClick={() => setReversingJournal(j)}
                  >
                    <RotateCcw size={13} className="mr-1 inline" /> Reverse Journal
                  </Button>
                )}
              </div>
              <table className="ui-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Account</th>
                    <th>Narration</th>
                    <th className="text-right">Debit</th>
                    <th className="text-right">Credit</th>
                  </tr>
                </thead>
                <tbody>
                  {j.lines.map((l) => (
                    <tr key={l.id}>
                      <td className="tabular">{l.line_number}</td>
                      <td>
                        <span className="tabular font-semibold font-mono">{l.account?.code}</span> {l.account?.name}
                        {l.account?.system_role && (
                          <span className="ml-1 text-xs text-slate-500">({l.account.system_role})</span>
                        )}
                      </td>
                      <td>{l.narration ?? '—'}</td>
                      <td className="text-right">
                        <MoneyCell value={Number(l.debit_amount) ? l.debit_amount : null} />
                      </td>
                      <td className="text-right">
                        <MoneyCell value={Number(l.credit_amount) ? l.credit_amount : null} />
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      {isCreating && (
        <NewJournalModal open={isCreating} onClose={() => setIsCreating(false)} />
      )}

      {reversingJournal && (
        <ConfirmDialog
          open={!!reversingJournal}
          title={`Reverse Journal ${reversingJournal.doc_number}`}
          message={`Are you sure you want to reverse journal ${reversingJournal.doc_number}? A new balanced reversing journal entry with inverted debits and credits will be permanently posted.`}
          confirmLabel="Reverse Journal"
          danger
          requireReason="Reason for reversal"
          isPending={reverseMutation.isPending}
          onConfirm={(reason) => reverseMutation.mutate({ id: reversingJournal.id, reason })}
          onCancel={() => setReversingJournal(null)}
        />
      )}
    </Page>
  )
}

type JournalLineDraft = {
  id: string
  account_id: string
  narration: string
  debit: string
  credit: string
}

function NewJournalModal({ open, onClose }: { open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const accountsQuery = useChartOfAccounts()
  const postableAccounts = (accountsQuery.data?.data ?? []).filter((a) => a.is_postable && a.is_active)

  const [entryDate, setEntryDate] = useState(() => new Date().toISOString().split('T')[0])
  const [narration, setNarration] = useState('')
  const [lines, setLines] = useState<JournalLineDraft[]>([
    { id: '1', account_id: '', narration: '', debit: '', credit: '' },
    { id: '2', account_id: '', narration: '', debit: '', credit: '' },
  ])

  const addLine = () => {
    setLines((prev) => [
      ...prev,
      { id: String(Date.now() + Math.random()), account_id: '', narration: '', debit: '', credit: '' },
    ])
  }

  const removeLine = (id: string) => {
    if (lines.length <= 2) return
    setLines((prev) => prev.filter((l) => l.id !== id))
  }

  const updateLine = (id: string, patch: Partial<JournalLineDraft>) => {
    setLines((prev) =>
      prev.map((l) => {
        if (l.id !== id) return l
        const updated = { ...l, ...patch }
        // Enforce one-sided lines: entering debit clears credit and vice-versa
        if ('debit' in patch && patch.debit) {
          updated.credit = ''
        } else if ('credit' in patch && patch.credit) {
          updated.debit = ''
        }
        return updated
      }),
    )
  }

  const totalDebit = lines.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0)
  const totalCredit = lines.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0)
  const diff = Math.abs(totalDebit - totalCredit)
  const isBalanced = diff < 0.0001 && totalDebit > 0

  const isValid =
    Boolean(entryDate) &&
    narration.trim().length >= 3 &&
    lines.length >= 2 &&
    isBalanced &&
    lines.every(
      (l) =>
        Boolean(l.account_id) &&
        ((parseFloat(l.debit) > 0 && !parseFloat(l.credit)) || (parseFloat(l.credit) > 0 && !parseFloat(l.debit))),
    )

  const postMutation = useMutation({
    mutationFn: () =>
      apiPost<JournalEntry>('/api/finance/journals', {
        entry_date: entryDate,
        narration: narration.trim(),
        lines: lines.map((l) => ({
          account_id: l.account_id,
          narration: l.narration?.trim() || null,
          debit: parseFloat(l.debit) || 0,
          credit: parseFloat(l.credit) || 0,
        })),
      }),
    onSuccess: (journal) => {
      toast.success(`Manual journal ${journal.doc_number} posted successfully`)
      queryClient.invalidateQueries({ queryKey: ['finance', 'journals'] })
      queryClient.invalidateQueries({ queryKey: ['finance', 'chart-of-accounts'] })
      queryClient.invalidateQueries({ queryKey: ['finance', 'trial-balance'] })
      onClose()
    },
  })

  const err = postMutation.isError ? getApiError(postMutation.error) : null

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="New Manual Journal Entry"
      width={840}
      footer={
        <div className="flex w-full items-center justify-between">
          <div className="flex items-center gap-2">
            {isBalanced ? (
              <span className="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200/60 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                <CheckCircle2 size={13} /> Balanced ({formatMoney(totalDebit)})
              </span>
            ) : (
              <span className="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200/60 px-2.5 py-1 text-xs font-semibold text-amber-700">
                <AlertCircle size={13} /> Out of balance: KES {formatMoney(diff)}
              </span>
            )}
          </div>
          <div className="flex gap-2">
            <Button onClick={onClose} disabled={postMutation.isPending}>
              Cancel
            </Button>
            <Button
              variant="primary"
              disabled={!isValid || postMutation.isPending}
              onClick={() => postMutation.mutate()}
            >
              {postMutation.isPending ? 'Posting…' : 'Post Journal'}
            </Button>
          </div>
        </div>
      }
    >
      <div className="space-y-4">
        <div className="grid grid-cols-3 gap-3">
          <Field label="Entry Date" required>
            <Input type="date" value={entryDate} onChange={(e) => setEntryDate(e.target.value)} />
          </Field>
          <div className="col-span-2">
            <Field label="Narration / Description" required hint="Required memo explaining the journal adjustment">
              <Input
                placeholder="e.g. Month-end inventory write-off, petty cash adjustment…"
                value={narration}
                onChange={(e) => setNarration(e.target.value)}
              />
            </Field>
          </div>
        </div>

        <div>
          <div className="mb-2 flex items-center justify-between">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              Journal Lines (Double-Entry)
            </h3>
            <Button size="sm" onClick={addLine}>
              <Plus size={13} className="mr-1 inline" /> Add Line
            </Button>
          </div>

          <div className="overflow-x-auto rounded-xl border border-slate-200">
            <table className="ui-table w-full">
              <thead>
                <tr>
                  <th style={{ width: '40%' }}>Account</th>
                  <th>Line Memo</th>
                  <th style={{ width: '18%' }} className="text-right">Debit</th>
                  <th style={{ width: '18%' }} className="text-right">Credit</th>
                  <th style={{ width: '36px' }} />
                </tr>
              </thead>
              <tbody>
                {lines.map((line) => (
                  <tr key={line.id}>
                    <td>
                      <Select
                        value={line.account_id}
                        onChange={(e) => updateLine(line.id, { account_id: e.target.value })}
                      >
                        <option value="">Select Account…</option>
                        {postableAccounts.map((a) => (
                          <option key={a.id} value={a.id}>
                            {a.code} — {a.name} ({a.account_type})
                          </option>
                        ))}
                      </Select>
                    </td>
                    <td>
                      <Input
                        placeholder="Optional memo…"
                        value={line.narration}
                        onChange={(e) => updateLine(line.id, { narration: e.target.value })}
                      />
                    </td>
                    <td>
                      <Input
                        inputMode="decimal"
                        placeholder="0.00"
                        className="text-right tabular font-medium"
                        value={line.debit}
                        onChange={(e) => updateLine(line.id, { debit: e.target.value.replace(/[^\d.]/g, '') })}
                      />
                    </td>
                    <td>
                      <Input
                        inputMode="decimal"
                        placeholder="0.00"
                        className="text-right tabular font-medium"
                        value={line.credit}
                        onChange={(e) => updateLine(line.id, { credit: e.target.value.replace(/[^\d.]/g, '') })}
                      />
                    </td>
                    <td className="text-center">
                      <button
                        type="button"
                        className="cursor-pointer text-slate-400 hover:text-rose-600 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                        disabled={lines.length <= 2}
                        title={lines.length <= 2 ? 'Minimum 2 lines required' : 'Remove line'}
                        onClick={() => removeLine(line.id)}
                      >
                        <Trash2 size={14} />
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr className="bg-slate-50 font-semibold text-slate-900">
                  <td colSpan={2} className="text-right text-xs">
                    Totals:
                  </td>
                  <td className="text-right tabular font-mono">{formatMoney(totalDebit)}</td>
                  <td className="text-right tabular font-mono">{formatMoney(totalCredit)}</td>
                  <td />
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        {err && <InlineError error={postMutation.error} />}
      </div>
    </Modal>
  )
}
