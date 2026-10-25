import { useState, type FormEvent } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { api, ensureCsrfCookie } from '../api/client'
import { sessionFromMe, type MeResponse, setSession } from '../auth/session'
import { readApiError } from './readApiError'

export function LoginPage() {
  const navigate = useNavigate()
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    const email = String(form.get('email') ?? '').trim()
    const password = String(form.get('password') ?? '')
    if (email === '' || password === '') {
      setError('Nhập email và mật khẩu.')
      return
    }

    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/auth/login', {
        email,
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
        <h1>Đăng nhập</h1>
        {error ? <p role="alert">{error}</p> : null}
        <label>
          Email
          <input name="email" type="email" />
        </label>
        <label>
          Mật khẩu
          <input name="password" type="password" />
        </label>
        <button type="submit" disabled={pending}>
          {pending ? 'Đang đăng nhập...' : 'Đăng nhập'}
        </button>
        <p className="form-links">
          <Link to="/forgot-password">Quên mật khẩu</Link>
          <Link to="/register">Đăng ký</Link>
        </p>
      </form>
    </main>
  )
}
