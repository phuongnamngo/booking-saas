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

it('shows an unread notification and marks it read', async () => {
  setSession({ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'An', email: 'a@example.com' })
  vi.mocked(api.get).mockResolvedValue({
    data: [{ id: 'n1', data: { message: 'Lịch mới' }, read_at: null }],
  })
  vi.mocked(api.post).mockResolvedValue({ data: null })

  render(
    <MemoryRouter initialEntries={['/app']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByRole('button', { name: 'Thông báo' })).toBeInTheDocument()
  expect(await screen.findByText('Lịch mới')).toBeInTheDocument()
  fireEvent.click(screen.getByText('Lịch mới'))
  await waitFor(() => {
    expect(api.post).toHaveBeenCalledWith('/api/v1/notifications/n1/read')
  })
})
