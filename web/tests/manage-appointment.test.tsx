import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, expect, it, vi } from 'vitest'
import { api } from '../src/api/client'
import { AppRouter } from '../src/app/router'

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
  vi.mocked(api.get).mockReset()
  vi.mocked(api.post).mockReset()
})

it('cancels a public appointment with the token on the query', async () => {
  vi.mocked(api.get).mockResolvedValue({
    data: {
      id: '01ARZ3NDEKTSV4RRFFQ69G5FAV',
      status: 'pending',
      starts_at: '2026-10-26T02:00:00+00:00',
      staff_id: 'staff-1',
    },
  })
  vi.mocked(api.post).mockResolvedValue({
    data: { id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', status: 'cancelled' },
  })

  render(
    <MemoryRouter initialEntries={['/t/spa-a/appointments/01ARZ3NDEKTSV4RRFFQ69G5FAV?token=raw-token']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByText('Chờ xác nhận')).toBeInTheDocument()
  fireEvent.click(screen.getByRole('button', { name: 'Hủy lịch' }))

  await waitFor(() => {
    expect(api.post).toHaveBeenCalled()
  })
  const [url, , config] = vi.mocked(api.post).mock.calls[0]
  expect(String(url).endsWith('/cancel')).toBe(true)
  expect(config).toMatchObject({ params: { token: 'raw-token' } })
})
