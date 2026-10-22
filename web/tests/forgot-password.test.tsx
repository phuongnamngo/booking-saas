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
  vi.mocked(api.post).mockResolvedValue({ data: {} })
})

it('sends a forgot-password request for the typed email', async () => {
  render(
    <MemoryRouter initialEntries={['/forgot-password']}>
      <AppRouter />
    </MemoryRouter>,
  )

  fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'a@example.com' } })
  fireEvent.click(screen.getByRole('button', { name: 'Gửi hướng dẫn' }))

  await waitFor(() => {
    expect(api.post).toHaveBeenCalledWith('/api/v1/auth/forgot-password', {
      email: 'a@example.com',
    })
  })
})

it('shows the verification result without calling the signed url', async () => {
  render(
    <MemoryRouter initialEntries={['/verify-email?expires=1&signature=abc']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByText('Email đã được xác thực.')).toBeInTheDocument()
  const calls = vi.mocked(api.get).mock.calls.map((call) => String(call[0]))
  expect(calls.some((url) => url.includes('signature'))).toBe(false)
})

it('links the login form to forgot password', () => {
  render(
    <MemoryRouter initialEntries={['/login']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(screen.getByRole('link', { name: 'Quên mật khẩu' })).toHaveAttribute('href', '/forgot-password')
})
