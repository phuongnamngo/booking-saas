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
  setSession({ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'An', email: 'a@example.com', isSuperAdmin: false, emailVerifiedAt: '2026-10-22T00:00:00+00:00' })
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
