import { useState, type FormEvent } from 'react'
import { Link } from 'react-router-dom'
import { api, ensureCsrfCookie } from '../api/client'
import { readApiError } from './readApiError'

export function ForgotPasswordPage() {
  const [error, setError] = useState<string | null>(null)
  const [sent, setSent] = useState(false)
  const [pending, setPending] = useState(false)

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const email = String(new FormData(event.currentTarget).get('email') ?? '').trim()
    if (email === '') {
      setError('Nhập email.')
      return
    }

    setPending(true)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.post('/api/v1/auth/forgot-password', { email })
      setSent(true)
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
        <h1>Quên mật khẩu</h1>
        {error ? <p role="alert">{error}</p> : null}
        {sent ? <p>Nếu email tồn tại, hướng dẫn đã được gửi.</p> : null}
        <label>
          Email
          <input name="email" type="email" />
        </label>
        <button type="submit" disabled={pending}>
          {pending ? 'Đang gửi...' : 'Gửi hướng dẫn'}
        </button>
        <p>
          <Link to="/login">Đăng nhập</Link>
        </p>
      </form>
    </main>
  )
}
