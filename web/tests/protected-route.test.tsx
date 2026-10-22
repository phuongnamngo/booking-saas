import { fireEvent, render, screen } from '@testing-library/react'
import { expect, it } from 'vitest'
import { MemoryRouter, useNavigate } from 'react-router-dom'
import { AppRouter } from '../src/app/router'
import { clearSession, setSession } from '../src/auth/session'

function EnterDesk() {
  const navigate = useNavigate()

  return (
    <button
      type="button"
      onClick={() => {
        setSession({
          id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
          name: 'An',
          email: 'demo@example.com',
          isSuperAdmin: false,
          emailVerifiedAt: '2026-10-22T00:00:00+00:00',
        })
        navigate('/app')
      }}
    >
      Vào quầy
    </button>
  )
}

it('sends /app to the login heading when there is no session', async () => {
  clearSession()
  render(
    <MemoryRouter initialEntries={['/app']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByRole('heading', { name: 'Đăng nhập' })).toBeInTheDocument()
})

it('opens the desk after the session is set and the route changes', async () => {
  clearSession()
  render(
    <MemoryRouter initialEntries={['/login']}>
      <EnterDesk />
      <AppRouter />
    </MemoryRouter>,
  )

  fireEvent.click(screen.getByRole('button', { name: 'Vào quầy' }))

  expect(await screen.findByRole('heading', { name: 'Lịch hẹn' })).toBeInTheDocument()
})

it('sends a super admin away from the desk', async () => {
  clearSession()
  setSession({
    id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    name: 'Root',
    email: 'root@example.com',
    isSuperAdmin: true,
    emailVerifiedAt: '2026-10-22T00:00:00+00:00',
  })
  render(
    <MemoryRouter initialEntries={['/app']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByRole('heading', { name: 'Tenant' })).toBeInTheDocument()
  expect(screen.queryByRole('link', { name: 'Chi nhánh' })).not.toBeInTheDocument()
})

it('shows a resend wall until the tenant email is verified', async () => {
  clearSession()
  setSession({
    id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    name: 'An',
    email: 'a@example.com',
    isSuperAdmin: false,
    emailVerifiedAt: null,
  })
  render(
    <MemoryRouter initialEntries={['/app/branches']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByRole('button', { name: 'Gửi lại email xác thực' })).toBeInTheDocument()
  expect(screen.queryByRole('heading', { name: 'Chi nhánh' })).not.toBeInTheDocument()
})
