import { act, fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { api } from '../src/api/client'
import { AppRouter } from '../src/app/router'
import { clearSession, setSession } from '../src/auth/session'

vi.mock('../src/api/client', () => ({
  api: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
  ensureCsrfCookie: vi.fn().mockResolvedValue(undefined),
}))

beforeEach(() => {
  clearSession()
  vi.mocked(api.get).mockReset()
  vi.mocked(api.post).mockReset()
})

afterEach(() => {
  vi.useRealTimers()
})

it('shows an empty branch list', async () => {
  setSession({ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'An', email: 'a@example.com', isSuperAdmin: false, emailVerifiedAt: '2026-10-22T00:00:00+00:00', role: 'owner', permissions: ['branches.manage', 'services.manage', 'staff.manage', 'schedules.manage', 'appointments.manage', 'appointments.handle', 'invitations.manage'] })
  vi.mocked(api.get).mockResolvedValue({ data: [] })

  render(
    <MemoryRouter initialEntries={['/app/branches']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByText('Chưa có chi nhánh')).toBeInTheDocument()
})

it('toasts after creating a branch and clears the line after 4 seconds', async () => {
  vi.useFakeTimers()
  setSession({
    id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    name: 'An',
    email: 'a@example.com',
    isSuperAdmin: false,
    emailVerifiedAt: '2026-10-22T00:00:00+00:00',
    role: 'owner',
    permissions: ['branches.manage', 'services.manage', 'staff.manage', 'schedules.manage', 'appointments.manage', 'appointments.handle', 'invitations.manage'],
  })
  vi.mocked(api.get).mockResolvedValue({ data: [] })
  vi.mocked(api.post).mockResolvedValue({ data: { id: '1' } })

  render(
    <MemoryRouter initialEntries={['/app/branches']}>
      <AppRouter />
    </MemoryRouter>,
  )

  fireEvent.change(screen.getByRole('textbox', { name: 'Tên' }), { target: { value: 'Cơ sở 1' } })
  await act(async () => {
    fireEvent.click(screen.getByRole('button', { name: 'Tạo chi nhánh' }))
    await Promise.resolve()
    await Promise.resolve()
  })

  expect(screen.getByRole('status')).toHaveTextContent('Đã tạo chi nhánh.')
  act(() => {
    vi.advanceTimersByTime(3999)
  })
  expect(screen.getByRole('status')).toHaveTextContent('Đã tạo chi nhánh.')
  act(() => {
    vi.advanceTimersByTime(1)
  })
  expect(screen.queryByRole('status')).not.toBeInTheDocument()
})
