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

it('shows an empty staff list', async () => {
  setSession({ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'An', email: 'a@example.com', isSuperAdmin: false, emailVerifiedAt: '2026-10-22T00:00:00+00:00' })
  vi.mocked(api.get).mockResolvedValue({ data: [] })

  render(
    <MemoryRouter initialEntries={['/app/staff']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByText('Chưa có nhân viên')).toBeInTheDocument()
})
