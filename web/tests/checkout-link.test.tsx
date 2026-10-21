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

it('turns a checkout response into a link on the appointments page', async () => {
  setSession({ id: '01ARZ3NDEKTSV4RRFFQ69G5FAV', name: 'An', email: 'a@example.com' })
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
  vi.mocked(api.post).mockResolvedValue({ data: { url: 'https://checkout.test/session' } })

  render(
    <MemoryRouter initialEntries={['/app/appointments']}>
      <AppRouter />
    </MemoryRouter>,
  )

  fireEvent.click(await screen.findByRole('button', { name: 'Thanh toán' }))
  await waitFor(() => {
    expect(api.post).toHaveBeenCalledWith('/api/v1/appointments/01ARZ3NDEKTSV4RRFFQ69G5FAV/checkout')
  })
  expect(await screen.findByRole('link', { name: 'Thanh toán' })).toHaveAttribute(
    'href',
    'https://checkout.test/session',
  )
})
