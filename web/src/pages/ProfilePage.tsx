import { useState, type FormEvent } from 'react'
import { api, ensureCsrfCookie } from '../api/client'
import { useToast } from '../app/toast'
import { getSession, setSession } from '../auth/session'
import { readApiError } from './readApiError'

export function ProfilePage() {
  const user = getSession().user
  const [nameMessage, setNameMessage] = useState<string | null>(null)
  const [passwordMessage, setPasswordMessage] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const showToast = useToast()

  function reportError(caught: unknown) {
    const message = readApiError(caught)
    setError(message)
    showToast('error', message)
  }

  async function saveName(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const name = String(new FormData(event.currentTarget).get('name') ?? '').trim()
    setError(null)
    try {
      await ensureCsrfCookie()
      const response = await api.patch<{ name: string }>('/api/v1/auth/profile', { name })
      if (user !== null) {
        setSession({ ...user, name: response.data.name })
      }
      setNameMessage('Đã lưu tên.')
      showToast('success', 'Đã lưu tên.')
    } catch (caught) {
      reportError(caught)
    }
  }

  async function savePassword(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = new FormData(event.currentTarget)
    setError(null)
    try {
      await ensureCsrfCookie()
      await api.put('/api/v1/auth/password', {
        current_password: String(form.get('current_password') ?? ''),
        password: String(form.get('password') ?? ''),
        password_confirmation: String(form.get('password_confirmation') ?? ''),
      })
      setPasswordMessage('Đã đổi mật khẩu.')
      showToast('success', 'Đã đổi mật khẩu.')
    } catch (caught) {
      reportError(caught)
    }
  }

  return (
    <section>
      <div className="page-head">
        <div>
          <h1>Hồ sơ</h1>
          <p className="mute">{user?.email}</p>
        </div>
      </div>
      {error ? <p role="alert">{error}</p> : null}
      <div className="forms">
      <form onSubmit={(event) => void saveName(event)}>
        <h2>Tên</h2>
        {nameMessage ? <p>{nameMessage}</p> : null}
        <label>
          Tên
          <input name="name" defaultValue={user?.name ?? ''} />
        </label>
        <button type="submit">Lưu tên</button>
      </form>
      <form onSubmit={(event) => void savePassword(event)}>
        <h2>Mật khẩu</h2>
        {passwordMessage ? <p>{passwordMessage}</p> : null}
        <label>
          Mật khẩu hiện tại
          <input name="current_password" type="password" />
        </label>
        <label>
          Mật khẩu mới
          <input name="password" type="password" />
        </label>
        <label>
          Nhập lại mật khẩu
          <input name="password_confirmation" type="password" />
        </label>
        <button type="submit">Đổi mật khẩu</button>
      </form>
      </div>
    </section>
  )
}
