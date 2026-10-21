import { render, screen } from '@testing-library/react'
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
  setSession({ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'An', email: 'a@example.com' })
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

  expect(await screen.findByText('pending')).toBeInTheDocument()
})
