import { useEffect, useRef, useState, type FormEvent } from 'react'
import { MessageSquare, X, Send, ShieldCheck, LogOut } from 'lucide-react'
import { api, getApiError } from '../../lib/api'
import { Button, Input } from '../../components/ui/primitives'
import type { AssistantAnswer, AssistantCommand, AssistantVerified } from '../../lib/types'

/**
 * The assistant on the sign-in page: ask for a code by email, type it in,
 * then ask the system questions without signing in.
 *
 * The session token lives in component state only — never in localStorage —
 * so closing the tab ends it, and it is sent on the assistant's own calls.
 * Everything it can answer is read-only and already gated server-side by the
 * asker's own permissions; this component only decides how it looks.
 */

type Message =
  | { kind: 'bot'; text: string }
  | { kind: 'me'; text: string }
  | { kind: 'answer'; answer: AssistantAnswer }

const OPENING = 'Hello. I can read out today’s figures without you signing in. What is the email address on your account?'

export default function AssistantWidget() {
  const [open, setOpen] = useState(false)
  const [step, setStep] = useState<'email' | 'code' | 'chat'>('email')
  const [email, setEmail] = useState('')
  const [draft, setDraft] = useState('')
  const [token, setToken] = useState<string | null>(null)
  const [commands, setCommands] = useState<AssistantCommand[]>([])
  const [messages, setMessages] = useState<Message[]>([{ kind: 'bot', text: OPENING }])
  const [busy, setBusy] = useState(false)
  const endRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    endRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, open])

  const say = (text: string) => setMessages((m) => [...m, { kind: 'bot', text }])
  const said = (text: string) => setMessages((m) => [...m, { kind: 'me', text }])

  async function submit(event: FormEvent) {
    event.preventDefault()
    const typed = draft.trim()
    if (!typed || busy) return
    setBusy(true)
    setDraft('')

    try {
      if (step === 'email') {
        said(typed)
        const { data } = await api.post<{ message: string }>('/api/assistant/request-code', { email: typed })
        setEmail(typed)
        setStep('code')
        say(data.message)
      } else if (step === 'code') {
        said('••••••')
        const { data } = await api.post<AssistantVerified>('/api/assistant/verify', { email, code: typed })
        setToken(data.session_token)
        setStep('chat')
        const { data: list } = await api.get<{ data: AssistantCommand[] }>('/api/assistant/commands', {
          headers: { Authorization: `Bearer ${data.session_token}` },
        })
        setCommands(list.data)
        say(`Thank you, ${data.user.name}. Ask me anything below, or type /help. This session ends in 30 minutes.`)
      } else {
        await ask(typed)
      }
    } catch (error) {
      say(getApiError(error).message)
      if (step === 'chat' && isExpired(error)) signOut(false)
    } finally {
      setBusy(false)
    }
  }

  async function ask(command: string) {
    said(command)
    setBusy(true)
    try {
      const { data } = await api.post<AssistantAnswer>(
        '/api/assistant/ask',
        { command },
        { headers: { Authorization: `Bearer ${token}` } },
      )
      setMessages((m) => [...m, { kind: 'answer', answer: data }])
    } catch (error) {
      say(getApiError(error).message)
      if (isExpired(error)) signOut(false)
    } finally {
      setBusy(false)
    }
  }

  function signOut(tellServer = true) {
    if (tellServer && token) {
      void api.post('/api/assistant/end', {}, { headers: { Authorization: `Bearer ${token}` } }).catch(() => undefined)
    }
    setToken(null)
    setCommands([])
    setStep('email')
    setEmail('')
    setMessages([{ kind: 'bot', text: OPENING }])
  }

  if (!open) {
    return (
      <button
        type="button"
        onClick={() => setOpen(true)}
        className="fixed bottom-5 right-5 z-50 flex items-center gap-2 rounded-full bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/30 hover:bg-blue-700 cursor-pointer"
      >
        <MessageSquare className="h-4 w-4" aria-hidden />
        Ask the assistant
      </button>
    )
  }

  return (
    <div className="fixed bottom-5 right-5 z-50 flex h-[32rem] w-[min(24rem,calc(100vw-2.5rem))] flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
      <header className="flex items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
        <ShieldCheck className="h-4 w-4 text-blue-600" aria-hidden />
        <div className="min-w-0 flex-1">
          <div className="text-sm font-semibold text-slate-900">Assistant</div>
          <div className="truncate text-[11px] text-slate-500">
            {step === 'chat' ? `Signed in as ${email} · read only` : 'Verified by an emailed code'}
          </div>
        </div>
        {step === 'chat' && (
          <button type="button" onClick={() => signOut()} title="End this session" className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-200 cursor-pointer">
            <LogOut className="h-4 w-4" aria-hidden />
          </button>
        )}
        <button type="button" onClick={() => setOpen(false)} title="Close" className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-200 cursor-pointer">
          <X className="h-4 w-4" aria-hidden />
        </button>
      </header>

      <div className="flex-1 space-y-3 overflow-y-auto px-4 py-3">
        {messages.map((message, index) =>
          message.kind === 'answer' ? (
            <AnswerCard key={index} answer={message.answer} />
          ) : (
            <div
              key={index}
              className={
                message.kind === 'me'
                  ? 'ml-auto max-w-[85%] rounded-2xl rounded-br-sm bg-blue-600 px-3 py-2 text-sm text-white'
                  : 'max-w-[90%] rounded-2xl rounded-bl-sm bg-slate-100 px-3 py-2 text-sm text-slate-800'
              }
            >
              {message.text}
            </div>
          ),
        )}
        {busy && <div className="text-xs text-slate-400">Working…</div>}
        <div ref={endRef} />
      </div>

      {step === 'chat' && commands.length > 0 && (
        <div className="flex flex-wrap gap-1.5 border-t border-slate-100 px-3 py-2">
          {commands.map((command) => (
            <button
              key={command.command}
              type="button"
              title={command.hint}
              disabled={busy}
              onClick={() => void ask(command.command)}
              className="rounded-full border border-slate-200 px-2.5 py-1 font-mono text-[11px] text-slate-600 hover:border-blue-300 hover:bg-blue-50 disabled:opacity-50 cursor-pointer"
            >
              {command.command}
            </button>
          ))}
        </div>
      )}

      <form onSubmit={submit} className="flex items-center gap-2 border-t border-slate-200 px-3 py-2.5">
        <Input
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          disabled={busy}
          autoComplete={step === 'email' ? 'email' : 'one-time-code'}
          inputMode={step === 'code' ? 'numeric' : undefined}
          placeholder={step === 'email' ? 'you@pharmacy.co.ke' : step === 'code' ? 'Six-digit code' : 'Type a command, e.g. /recent_sales'}
        />
        <Button type="submit" variant="primary" disabled={busy || !draft.trim()} title="Send">
          <Send className="h-4 w-4" aria-hidden />
        </Button>
      </form>
    </div>
  )
}

function AnswerCard({ answer }: { answer: AssistantAnswer }) {
  return (
    <div className="max-w-full rounded-2xl rounded-bl-sm bg-slate-100 px-3 py-2.5">
      <div className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{answer.title}</div>
      <p className="mt-0.5 text-sm text-slate-800">{answer.summary}</p>
      {answer.rows.length > 0 && answer.columns.length > 0 && (
        <div className="mt-2 overflow-x-auto">
          <table className="w-full text-[11px]">
            <thead>
              <tr className="text-left text-slate-500">
                {answer.columns.map((column) => (
                  <th key={column.key} className="py-1 pr-3 font-medium">{column.label}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              {answer.rows.map((row, index) => (
                <tr key={index} className="border-t border-slate-200/70 text-slate-700">
                  {answer.columns.map((column) => (
                    <td key={column.key} className="py-1 pr-3 whitespace-nowrap">{String(row[column.key] ?? '')}</td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  )
}

function isExpired(error: unknown): boolean {
  return getApiError(error).code === 'ASSISTANT_SESSION_EXPIRED'
}
