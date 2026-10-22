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

it('suspends a tenant without the branch navigation', async () => {
  setSession({
    id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    name: 'Root',
    email: 'root@example.com',
    isSuperAdmin: true,
    emailVerifiedAt: '2026-10-22T00:00:00+00:00',
  })
  vi.mocked(api.get).mockResolvedValue({
    data: [{ id: '01TENANT', name: 'Spa A', slug: 'spa-a', plan: 'free', suspended_at: null }],
  })
  vi.mocked(api.post).mockResolvedValue({ data: { suspended_at: '2026-10-22T00:00:00+00:00' } })

  render(
    <MemoryRouter initialEntries={['/admin']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByText('Spa A')).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: 'Khóa' }))

  await waitFor(() => {
    expect(api.post).toHaveBeenCalledWith('/api/v1/admin/tenants/01TENANT/suspend')
  })
  expect(screen.queryByRole('link', { name: 'Chi nhánh' })).not.toBeInTheDocument()
})
