import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { MessageSquare, Send } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { apiGet, apiPost, getApiError } from '../lib/api'
import { formatDateTime } from '../lib/format'
import { connectRealtime } from '../lib/realtime'
import { playMessageReceived, playMessageSent } from '../lib/sounds'
import { toast } from '../lib/toast'
import { Modal } from './ui/Modal'
import { Button, Field, Input, Select, Textarea } from './ui/primitives'

export type UserMessage = {
  id: string
  subject: string
  body: string
  priority: 'NORMAL' | 'HIGH' | 'URGENT'
  link: string | null
  read_at: string | null
  created_at: string
  recipient_id: number | null
  sender?: { id: number; name: string; username: string | null }
}

type Recipient = { id: number; name: string; username: string | null }

const priorityStyle = {
  URGENT: 'text-rose-700 bg-rose-50 border-rose-200',
  HIGH: 'text-amber-800 bg-amber-50 border-amber-200',
  NORMAL: 'text-sky-700 bg-sky-50 border-sky-200',
} as const

/**
 * Part 17 — messages between people, live. The bell counts what is unread;
 * a message sent from another till arrives over the websocket without a
 * refresh, and the same message is still here after a reload because it is
 * stored as well as broadcast.
 */
export function MessagesBell() {
  const queryClient = useQueryClient()
  const { data: me } = useCurrentUser()
  const [open, setOpen] = useState(false)
  const [composing, setComposing] = useState(false)
  const panelRef = useRef<HTMLDivElement>(null)

  const unread = useQuery({
    queryKey: ['messages', 'unread'],
    queryFn: () => apiGet<{ unread: number }>('/api/messages/unread-count'),
    refetchInterval: 5 * 60_000, // A fallback; the socket is what normally updates this.
    retry: false,
  })

  const list = useQuery({
    queryKey: ['messages', 'list'],
    queryFn: () => apiGet<{ data: UserMessage[] }>('/api/messages?per_page=25'),
    enabled: open,
    retry: false,
  })

  // Live delivery. The channel names match routes/channels.php exactly.
  useEffect(() => {
    if (!me) return
    const echo = connectRealtime()
    if (!echo) return

    const refresh = (payload: { subject: string; sender?: { name?: string } }) => {
      playMessageReceived()
      toast.info(`${payload.sender?.name ?? 'Someone'}: ${payload.subject}`)
      queryClient.invalidateQueries({ queryKey: ['messages'] })
    }

    const mine = echo.private(`users.${me.id}`).listen('.message.sent', refresh)
    const branch = me.active_branch?.id ? echo.private(`branches.${me.active_branch.id}`).listen('.message.sent', refresh) : null

    return () => {
      mine.stopListening('.message.sent')
      branch?.stopListening('.message.sent')
    }
  }, [me, queryClient])

  useEffect(() => {
    if (!open) return
    const close = (e: MouseEvent) => {
      if (panelRef.current && !panelRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [open])

  const markRead = useMutation({
    mutationFn: (id: string) => apiPost(`/api/messages/${id}/read`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['messages'] }),
  })
  const markAll = useMutation({
    mutationFn: () => apiPost('/api/messages/read-all'),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['messages'] }),
  })

  const count = unread.data?.unread ?? 0
  if (unread.isError) return null

  return (
    <div className="relative" ref={panelRef}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        title={count > 0 ? `${count} unread message(s)` : 'Messages'}
        aria-label="Messages"
        className="relative p-1.5 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 border border-transparent hover:border-slate-200 transition-colors cursor-pointer"
      >
        <MessageSquare size={17} />
        {count > 0 && (
          <span className="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full text-xs font-bold text-white bg-blue-600 flex items-center justify-center">
            {count > 99 ? '99+' : count}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 mt-2 w-[420px] max-w-[calc(100vw-2rem)] bg-white rounded-2xl border border-slate-200 shadow-xl z-50 overflow-hidden">
          <header className="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 className="text-sm font-bold text-slate-900">Messages</h3>
              <p className="text-xs text-slate-500">Delivered the moment they are sent</p>
            </div>
            <div className="flex items-center gap-2">
              {count > 0 && (
                <button type="button" onClick={() => markAll.mutate()} className="text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline cursor-pointer">
                  Mark all read
                </button>
              )}
              <Button size="sm" variant="primary" onClick={() => { setComposing(true); setOpen(false) }}>
                <Send size={12} /> New
              </Button>
            </div>
          </header>

          <div className="max-h-[420px] overflow-y-auto">
            {list.isLoading && <p className="px-4 py-6 text-sm text-slate-500">Loading…</p>}
            {!list.isLoading && (list.data?.data.length ?? 0) === 0 && (
              <p className="px-4 py-6 text-sm text-slate-500">No messages yet.</p>
            )}
            {list.data?.data.map((m) => (
              <div key={m.id} className={`px-4 py-3 border-b border-slate-50 last:border-0 ${m.read_at ? '' : 'bg-blue-50/40'}`}>
                <div className="flex items-start justify-between gap-2">
                  <div className="min-w-0">
                    <p className="text-sm font-bold text-slate-900">{m.subject}</p>
                    <p className="text-xs text-slate-600 mt-0.5 whitespace-pre-wrap">{m.body}</p>
                    <p className="text-xs text-slate-500 mt-1">
                      {m.sender?.name ?? 'System'} · {formatDateTime(m.created_at)}
                      {m.recipient_id === null && <span className="ml-1 font-semibold">· to everyone in the branch</span>}
                    </p>
                    {m.link && (
                      <Link to={m.link} onClick={() => setOpen(false)} className="text-xs font-semibold text-blue-600 hover:underline">
                        Open
                      </Link>
                    )}
                  </div>
                  <span className={`shrink-0 px-1.5 py-0.5 rounded-lg border text-xs font-semibold ${priorityStyle[m.priority]}`}>{m.priority}</span>
                </div>
                {!m.read_at && (
                  <button type="button" onClick={() => markRead.mutate(m.id)} className="text-xs font-semibold text-slate-500 hover:text-slate-800 hover:underline mt-1 cursor-pointer">
                    Mark read
                  </button>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      <ComposeMessage open={composing} onClose={() => setComposing(false)} />
    </div>
  )
}

function ComposeMessage({ open, onClose }: { open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState({ recipient_id: '', subject: '', body: '', priority: 'NORMAL' })
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })

  const recipients = useQuery({
    queryKey: ['messages', 'recipients'],
    queryFn: () => apiGet<Recipient[]>('/api/messages/recipients'),
    enabled: open,
    retry: false,
  })

  const send = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost('/api/messages', {
        ...form,
        recipient_id: form.recipient_id === '' ? null : Number(form.recipient_id),
      }),
    onSuccess: () => {
      playMessageSent()
      toast.success('Message sent')
      queryClient.invalidateQueries({ queryKey: ['messages'] })
      setForm({ recipient_id: '', subject: '', body: '', priority: 'NORMAL' })
      onClose()
    },
    onError: (e) => toast.error(getApiError(e).message),
  })

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="New message"
      footer={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="primary" disabled={!form.subject.trim() || !form.body.trim() || send.isPending} onClick={() => send.mutate()}>
            {send.isPending ? 'Sending…' : 'Send'}
          </Button>
        </>
      }
    >
      <div className="space-y-3">
        <Field label="To" hint="Leave as everyone to reach all of this branch.">
          <Select value={form.recipient_id} onChange={(e) => set({ recipient_id: e.target.value })}>
            <option value="">Everyone in this branch</option>
            {(recipients.data ?? []).map((r) => (
              <option key={r.id} value={r.id}>{r.name}{r.username ? ` · ${r.username}` : ''}</option>
            ))}
          </Select>
        </Field>
        <Field label="Subject" required>
          <Input value={form.subject} maxLength={150} onChange={(e) => set({ subject: e.target.value })} placeholder="Price override needed at till 2" />
        </Field>
        <Field label="Message" required>
          <Textarea rows={4} value={form.body} maxLength={5000} onChange={(e) => set({ body: e.target.value })} />
        </Field>
        <Field label="Priority">
          <Select value={form.priority} onChange={(e) => set({ priority: e.target.value })}>
            <option value="NORMAL">Normal</option>
            <option value="HIGH">High</option>
            <option value="URGENT">Urgent</option>
          </Select>
        </Field>
      </div>
    </Modal>
  )
}
