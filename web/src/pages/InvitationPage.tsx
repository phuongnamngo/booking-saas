import { useState, type FormEvent } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { api, ensureCsrfCookie } from '../api/client'
import { sessionFromMe, type MeResponse, setSession } from '../auth/session'
import { readApiError } from './readApiError'

export function InvitationPage() {
  const navigate = useNavigate()
  const { token = '' } = useParams()
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const name = String(form.get('name') ?? '').trim()
    const password = String(form.get('password') ?? '')
    if (name === '') {
      setError('Nhập tên.')
      return
    }

    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/invitations/accept', {
        token,
        name,
        password,
      })
      const me = await api.get<MeResponse>('/api/v1/auth/me')
      setSession(sessionFromMe(me.data))
      navigate('/app')
    } catch (caught) {
      setError(readApiError(caught))
    } finally {
      setPending(false)
    }
  }

  return (
    <main className="stage">
      <p className="mark">Sổ lịch</p>
      <form onSubmit={onSubmit}>
        <h1>Nhận lời mời</h1>
        {error ? <p role="alert">{error}</p> : null}
        <label>
          Tên
          <input name="name" />
        </label>
        <label>
          Mật khẩu
          <input name="password" type="password" />
        </label>
        <button type="submit" disabled={pending}>
          {pending ? 'Đang tham gia...' : 'Tham gia'}
        </button>
      </form>
    </main>
  )
}
