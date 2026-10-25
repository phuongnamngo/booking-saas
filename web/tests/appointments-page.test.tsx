import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, expect, it, vi } from 'vitest'
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
})

it('shows a pending appointment', async () => {
  setSession({ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'An', email: 'a@example.com', isSuperAdmin: false, emailVerifiedAt: '2026-10-22T00:00:00+00:00', role: 'owner', permissions: ['branches.manage', 'services.manage', 'staff.manage', 'schedules.manage', 'appointments.manage', 'appointments.handle', 'invitations.manage'] })
  vi.mocked(api.get).mockResolvedValue({
    data: [
      {
        id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        status: 'pending',
        starts_at: '2026-10-26T02:00:00+00:00',
        ends_at: '2026-10-26T02:30:00+00:00',
      },
    ],
  })

  render(
    <MemoryRouter initialEntries={['/app/appointments']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByText('Chờ xác nhận')).toBeInTheDocument()
})

it('requests a week range when Tuần is clicked', async () => {
  setSession({ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'An', email: 'a@example.com', isSuperAdmin: false, emailVerifiedAt: '2026-10-22T00:00:00+00:00', role: 'owner', permissions: ['branches.manage', 'services.manage', 'staff.manage', 'schedules.manage', 'appointments.manage', 'appointments.handle', 'invitations.manage'] })
  vi.mocked(api.get).mockResolvedValue({
    data: [
      {
        id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        name: 'Chi nhánh 1',
        status: 'pending',
        starts_at: '2026-10-26T02:00:00+00:00',
        ends_at: '2026-10-26T02:30:00+00:00',
      },
    ],
  })

  render(
    <MemoryRouter initialEntries={['/app/appointments']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByText('Chờ xác nhận')).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: 'Tuần' }))

  await waitFor(() => {
    const weekCall = vi.mocked(api.get).mock.calls.find((call) => {
      const params = call[1] as { params?: { from?: string } } | undefined
      return params?.params?.from !== undefined
    })
    const params = (weekCall?.[1] as { params: { from: string; to: string; branch_id: string } }).params
    expect(weekCall?.[0]).toBe('/api/v1/appointments')
    expect(params.branch_id).toBe('01ARZ3NDEKTSV4RRFFQ69G5FAV')
    expect(params.to).toBe(addDays(params.from, 6))
    expect(params).not.toHaveProperty('date')
  })
})

function addDays(isoDate: string, days: number): string {
  const [year, month, day] = isoDate.split('-').map(Number)
  const date = new Date(year, month - 1, day)
  date.setDate(date.getDate() + days)
  const nextMonth = String(date.getMonth() + 1).padStart(2, '0')
  const nextDay = String(date.getDate()).padStart(2, '0')
  return `${date.getFullYear()}-${nextMonth}-${nextDay}`
}
