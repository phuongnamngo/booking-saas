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
  vi.mocked(api.post).mockReset()
})

it('shows revenue and posts a csv export', async () => {
  setSession({ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'An', email: 'a@example.com', isSuperAdmin: false, emailVerifiedAt: '2026-10-22T00:00:00+00:00', role: 'owner', permissions: ['branches.manage', 'services.manage', 'staff.manage', 'schedules.manage', 'appointments.manage', 'appointments.handle', 'invitations.manage'] })
  vi.mocked(api.get).mockImplementation(async (url: string) => {
    if (url === '/api/v1/reports') {
      return { data: { revenue: 100000 } }
    }
    return { data: [] }
  })
  vi.mocked(api.post).mockResolvedValue({ data: { id: '01ARZ3NDEKTSV4RRFFQ69G5FAV' } })

  render(
    <MemoryRouter initialEntries={['/app/reports']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByText('100000')).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: 'Xuất CSV' }))
  await waitFor(() => {
    expect(api.post).toHaveBeenCalledWith(
      '/api/v1/reports/exports',
      expect.objectContaining({ from: expect.any(String), to: expect.any(String) }),
    )
  })
})

it('charts the report series and refetches when the group changes', async () => {
  setSession({ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'An', email: 'a@example.com', isSuperAdmin: false, emailVerifiedAt: '2026-10-22T00:00:00+00:00', role: 'owner', permissions: ['branches.manage', 'services.manage', 'staff.manage', 'schedules.manage', 'appointments.manage', 'appointments.handle', 'invitations.manage'] })
  vi.mocked(api.get).mockImplementation(async (url: string) => {
    if (url === '/api/v1/reports') {
      return {
        data: {
          revenue: 100000,
          cancel_rate: 0.25,
          no_show_rate: 0,
          by_period: [{ period: '2026-10-22', revenue: 100000 }],
          by_service: [{ id: 'svc', name: 'Cắt tóc', revenue: 100000 }],
          by_staff: [{ id: 'stf', name: 'An', revenue: 100000 }],
          peak_hours: [{ hour: '09', count: 2 }],
        },
      }
    }
    return { data: [] }
  })

  render(
    <MemoryRouter initialEntries={['/app/reports']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByText('2026-10-22')).toBeInTheDocument()
  expect(screen.getByText('Cắt tóc')).toBeInTheDocument()
  expect(screen.getByText('An')).toBeInTheDocument()
  expect(screen.getByText('09')).toBeInTheDocument()

  fireEvent.change(screen.getByRole('combobox', { name: 'Nhóm' }), { target: { value: 'week' } })

  await waitFor(() => {
    expect(api.get).toHaveBeenCalledWith(
      '/api/v1/reports',
      expect.objectContaining({ params: expect.objectContaining({ group: 'week' }) }),
    )
  })
})
