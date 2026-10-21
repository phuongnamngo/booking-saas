import { render, screen } from '@testing-library/react'
import { expect, it } from 'vitest'
import { MemoryRouter } from 'react-router-dom'
import { AppRouter } from '../src/app/router'

it('sends /app to the login heading when there is no session', async () => {
  render(
    <MemoryRouter initialEntries={['/app']}>
      <AppRouter />
    </MemoryRouter>,
  )

  expect(await screen.findByRole('heading', { name: 'Đăng nhập' })).toBeInTheDocument()
})
